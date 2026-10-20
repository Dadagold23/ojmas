/**
 * OJMAS Manuscript Workspace — autosave with conflict detection.
 *
 * The contract with the server:
 *  - Every save carries the revision the edit was made against.
 *  - The server answers 200 "saved" (with the new revision) or 409 "conflict"
 *    (with the server's current content and the rejected edit, both intact).
 *  - A conflict is NEVER auto-resolved. The user is shown both versions and
 *    must choose. Overwriting is an explicit, separately-authorised action.
 */

const DEBOUNCE_MS = 2500
const MAX_RETRY_DELAY_MS = 30000

export class AutosaveController {
  /**
   * @param {object} options
   * @param {() => object} options.getContent        Serialise the editor document.
   * @param {(state: object) => void} options.onStatus
   * @param {(payload: object) => void} options.onConflict
   * @param {(payload: object) => void} options.onError
   */
  constructor (options) {
    this.getContent = options.getContent
    this.onStatus = options.onStatus || (() => {})
    this.onConflict = options.onConflict || (() => {})
    this.onError = options.onError || (() => {})
    this.endpoint = options.endpoint
    this.csrf = options.csrf

    this.revision = null
    this.dirty = false
    this.inFlight = false
    this.pendingPayload = null
    this.timer = null
    this.retryDelay = 1000
    this.conflicted = false
    this.stopped = false
  }

  setRevision (revision) {
    this.revision = revision
  }

  markDirty () {
    if (this.conflicted || this.stopped) return

    this.dirty = true
    this.onStatus({ state: 'unsaved', message: 'Unsaved changes' })
    this.schedule()
  }

  schedule () {
    window.clearTimeout(this.timer)
    this.timer = window.setTimeout(() => this.flush(), DEBOUNCE_MS)
  }

  /** Flush immediately, e.g. when the user clicks Save. */
  async flushNow () {
    window.clearTimeout(this.timer)
    return this.flush()
  }

  async flush () {
    if (this.stopped || this.conflicted || this.inFlight) {
      if (this.inFlight) this.pendingPayload = true
      return null
    }

    if (!this.dirty) return null

    const content = this.getContent()

    this.inFlight = true
    this.dirty = false
    this.onStatus({ state: 'saving', message: 'Saving…' })

    try {
      const response = await this.request({
        content,
        baseRevision: this.revision,
        force: false,
      })

      this.retryDelay = 1000
      this.inFlight = false

      if (response.status === 200) {
        this.revision = response.body.revision
        this.onStatus({
          state: 'saved',
          message: 'Saved',
          revision: this.revision,
          savedAt: response.body.saved_at,
        })

        if (this.pendingPayload) {
          this.pendingPayload = null
          this.markDirty()
        }

        return response.body
      }

      if (response.status === 409) {
        this.inFlight = false
        this.dirty = true
        this.conflicted = true
        this.onStatus({ state: 'conflict', message: 'Edit conflict — your work is safe' })
        this.onConflict({
          serverRevision: response.body.revision,
          submittedRevision: response.body.submitted_revision,
          serverContent: response.body.server_content,
          localContent: content,
          message: response.body.message,
        })

        return null
      }

      // 4xx that is not a conflict is a real error we should not retry.
      this.inFlight = false
      this.dirty = true
      this.onError({ status: response.status, body: response.body, fatal: true })

      return null
    } catch (error) {
      this.inFlight = false
      this.dirty = true
      this.onStatus({ state: 'offline', message: 'Not saved — retrying' })

      this.retryDelay = Math.min(this.retryDelay * 2, MAX_RETRY_DELAY_MS)
      window.setTimeout(() => this.flush(), this.retryDelay)

      this.onError({ error, fatal: false })

      return null
    }
  }

  /** Explicitly take over after the user has reviewed a conflict. */
  async forceSave () {
    if (this.conflicted) return null

    const content = this.getContent()

    this.inFlight = true
    this.onStatus({ state: 'saving', message: 'Saving over the other edit…' })

    try {
      const response = await this.request({
        content,
        baseRevision: this.revision,
        force: true,
      })

      this.inFlight = false

      if (response.status === 200) {
        this.revision = response.body.revision
        this.dirty = false
        this.conflicted = false
        this.onStatus({ state: 'saved', message: 'Saved (your version kept)', revision: this.revision })

        return response.body
      }

      this.onError({ status: response.status, body: response.body, fatal: true })

      return null
    } catch (error) {
      this.inFlight = false
      this.onError({ error, fatal: false })

      return null
    }
  }

  /** Called once the user has resolved a conflict in the UI. */
  clearConflict () {
    this.conflicted = false
    this.dirty = false
  }

  async request (payload) {
    const response = await fetch(this.endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': this.csrf,
        Accept: 'application/json',
      },
      body: JSON.stringify(payload),
    })

    let body = null

    try {
      body = await response.json()
    } catch (error) {
      body = { message: 'The server returned an unreadable response.' }
    }

    return { status: response.status, body: body || {} }
  }

  stop () {
    this.stopped = true
    window.clearTimeout(this.timer)
  }
}