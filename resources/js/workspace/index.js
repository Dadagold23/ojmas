/**
 * OJMAS Manuscript Workspace — application entry point.
 *
 * Bootstraps the Tiptap editor, wires autosave, the comment panel, the tracked
 * changes panel and the version panel.
 */

import { Editor } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import Placeholder from '@tiptap/extension-placeholder'
import CharacterCount from '@tiptap/extension-character-count'

import {
  TrackedInsertion,
  TrackedDeletion,
  CommentAnchor,
} from './extensions'
import { AutosaveController } from './autosave'

const el = id => document.getElementById(id)

const state = {
  revision: null,
  comments: [],
  changes: [],
  capabilities: {},
  visibilities: [],
  conflicted: false,
  pendingAnchor: null,
}

const csrf = document.querySelector('meta[name="csrf-token"]')?.content || ''

function setStatus (payload) {
  const badge = el('save-status')
  if (!badge) return

  badge.dataset.state = payload.state
  badge.textContent = payload.message || ''

  if (payload.revision !== undefined && payload.revision !== null) {
    el('revision-label').textContent = `Revision ${payload.revision}`
  }
}

function toast (message, kind = 'info') {
  const host = el('workspace-toasts')
  if (!host) return

  const node = document.createElement('div')
  node.className = `alert alert-${kind} py-2 px-3 small mb-2 rounded-3`
  node.textContent = message
  host.appendChild(node)

  window.setTimeout(() => node.remove(), 8000)
}

async function api (url, options = {}) {
  const response = await fetch(url, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': csrf,
      Accept: 'application/json',
      ...(options.headers || {}),
    },
  })

  let body = {}
  try { body = await response.json() } catch (e) { body = {} }

  return { status: response.status, body }
}

/* ────────────────────────── editor ────────────────────────── */

const editor = new Editor({
  element: el('editor-surface'),
  extensions: [
    StarterKit.configure({
      heading: { levels: [1, 2, 3, 4, 5, 6] },
    }),
    Placeholder.configure({ placeholder: 'The manuscript text appears here once the document has been imported.' }),
    CharacterCount,
    TrackedInsertion,
    TrackedDeletion,
    CommentAnchor,
  ],
  content: { type: 'doc', content: [] },
  editable: false,
  onUpdate: () => {
    updateWordCount()
    if (state.capabilities.canEdit) autosave.markDirty()
  },
  onSelectionUpdate: () => updateCommentButton(),
})

function updateWordCount () {
  const words = editor.storage.characterCount?.words?.() ?? 0
  const chars = editor.storage.characterCount?.characters?.() ?? 0

  if (el('word-count')) el('word-count').textContent = `${words} words`
  if (el('char-count')) el('char-count').textContent = `${chars} characters`
}

function updateCommentButton () {
  const { from, to } = editor.state.selection
  const hasSelection = from !== to

  if (el('btn-comment')) {
    el('btn-comment').disabled = !hasSelection || !state.capabilities.canComment
  }

  if (el('selection-preview')) {
    el('selection-preview').textContent = hasSelection
      ? editor.state.doc.textBetween(from, to, ' ').slice(0, 80)
      : ''
  }
}

/* ────────────────────────── autosave ────────────────────────── */

const autosave = new AutosaveController({
  endpoint: el('app').dataset.saveUrl,
  csrf,
  getContent: () => editor.getJSON(),
  onStatus: setStatus,
  onConflict: payload => {
    state.conflicted = true
    showConflict(payload)
  },
  onError: payload => {
    if (payload.fatal) {
      toast(payload.body?.message || 'The workspace could not save your changes.', 'danger')
      setStatus({ state: 'error', message: 'Save failed' })
    }
  },
})

/* ────────────────────────── conflict UI ────────────────────────── */

function showConflict (payload) {
  const modal = el('conflict-modal')
  if (!modal) return

  el('conflict-message').textContent = payload.message
  el('conflict-detail').innerHTML =
    `You edited revision <strong>${payload.submittedRevision}</strong>, but the manuscript is now at ` +
    `<strong>revision ${payload.serverRevision}</strong>. Nothing has been discarded.`

  modal.dataset.serverContent = JSON.stringify(payload.serverContent)
  modal.dataset.localContent = JSON.stringify(payload.localContent)

  modal.classList.remove('d-none')
  modal.setAttribute('aria-hidden', 'false')

  document.querySelectorAll('[data-workspace-editable]').forEach(node => {
    node.setAttribute('contenteditable', 'false')
  })
}

function hideConflict () {
  const modal = el('conflict-modal')
  if (!modal) return

  modal.classList.add('d-none')
  modal.setAttribute('aria-hidden', 'true')
  document.querySelectorAll('[data-workspace-editable]').forEach(node => {
    node.setAttribute('contenteditable', 'true')
  })
}

function resolveConflict (strategy) {
  const modal = el('conflict-modal')
  const serverContent = JSON.parse(modal.dataset.serverContent || '{"type":"doc","content":[]}')
  const localContent = JSON.parse(modal.dataset.localContent || '{"type":"doc","content":[]}')

  if (strategy === 'theirs') {
    editor.commands.setContent(serverContent)
    autosave.clearConflict()
    state.conflicted = false
    hideConflict()
    setStatus({ state: 'saved', message: 'Loaded the other editor\'s version' })
    toast('You are now looking at the other editor\'s version. Your text was discarded by your choice.', 'warning')
    return
  }

  if (strategy === 'mine') {
    editor.commands.setContent(localContent)
    hideConflict()
    autosave.forceSave().then(result => {
      autosave.clearConflict()
      state.conflicted = false
      if (result) toast('Your version was kept.', 'success')
    })
    return
  }

  // Keep both: the server version is placed below the user's own text in a
  // clearly marked block so nothing is lost and nothing is silently merged.
  const merged = {
    type: 'doc',
    content: [
      ...(localContent.content || []),
      { type: 'heading', attrs: { level: 3 }, content: [{ type: 'text', text: 'Other editor\'s version (kept for comparison)' }] },
      ...(serverContent.content || []),
    ],
  }

  editor.commands.setContent(merged)
  hideConflict()
  autosave.clearConflict()
  state.conflicted = false
  autosave.markDirty()
  toast('Both versions have been kept in the document for you to reconcile.', 'warning')
}

/* ────────────────────────── comments ────────────────────────── */

function renderComments () {
  const host = el('comment-list')
  if (!host) return

  host.innerHTML = ''

  if (state.comments.length === 0) {
    host.innerHTML = '<p class="text-muted small mb-0">No comments yet. Select text in the manuscript to start a discussion.</p>'
    return
  }

  state.comments.forEach(thread => {
    const wrapper = document.createElement('div')
    wrapper.className = `border rounded-3 p-3 mb-2 ${thread.status === 'resolved' ? 'opacity-75' : ''}`
    wrapper.dataset.commentId = thread.id

    const quoted = thread.quoted_text
      ? `<div class="border-start border-3 ps-2 text-muted small mb-2">“${escapeHtml(thread.quoted_text)}”</div>`
      : ''

    const anchorNote = thread.anchor_valid
      ? ''
      : '<div class="text-danger small mb-2">The text this referred to has since changed.</div>'

    const replies = (thread.replies || []).map(reply => `
      <div class="border-start ps-2 mt-2 ms-3">
        <div class="small fw-semibold">${escapeHtml(reply.author || '')}</div>
        <div class="small">${escapeHtml(reply.body)}</div>
      </div>`).join('')

    const statusToggle = state.capabilities.canComment
      ? `<button class="btn btn-outline-secondary btn-sm mt-2" data-action="${thread.status === 'resolved' ? 'reopen' : 'resolve'}" data-id="${thread.id}">
           ${thread.status === 'resolved' ? 'Reopen' : 'Resolve'}
         </button>`
      : ''

    wrapper.innerHTML = `
      <div class="d-flex justify-content-between align-items-start mb-1">
        <div class="fw-semibold small">${escapeHtml(thread.author || '')}</div>
        <span class="badge text-bg-${thread.visibility === 'author_visible' ? 'info' : thread.visibility === 'reviewer_confidential' ? 'danger' : 'secondary'}">
          ${escapeHtml(thread.visibility.replace('_', ' '))}
        </span>
      </div>
      ${anchorNote}${quoted}
      <div class="small">${escapeHtml(thread.body)}</div>
      ${replies}${statusToggle}
    `

    host.appendChild(wrapper)
  })
}

function escapeHtml (value) {
  const node = document.createElement('span')
  node.textContent = value == null ? '' : String(value)
  return node.innerHTML
}

async function addComment () {
  const { from, to } = editor.state.selection

  if (from === to) {
    toast('Select some text in the manuscript first.', 'warning')
    return
  }

  const body = el('comment-body').value.trim()
  const visibility = el('comment-visibility').value
  const quoted = editor.state.doc.textBetween(from, to, ' ')

  if (!body) {
    toast('Write a comment before posting.', 'warning')
    return
  }

  const result = await api(el('app').dataset.commentUrl, {
    method: 'POST',
    body: JSON.stringify({ body, anchorFrom: from, anchorTo: to, quotedText: quoted, visibility }),
  })

  if (result.status !== 201) {
    toast(result.body?.error || 'The comment could not be posted.', 'danger')
    return
  }

  editor.chain().focus().setMark('commentAnchor', { commentId: result.body.comment.id }).run()
  autosave.markDirty()

  el('comment-body').value = ''
  await loadComments()
  toast('Comment added.', 'success')
}

async function loadComments () {
  const result = await api(el('app').dataset.documentUrl)

  if (result.status !== 200) return

  state.comments = result.body.comments || []
  state.changes = result.body.changes || []
  renderComments()
  renderChanges()
  renderVersions(result.body.snapshots || [])
}

async function updateCommentStatus (button) {
  const id = button.dataset.id
  const status = button.dataset.action === 'resolve' ? 'resolved' : 'reopened'

  const result = await api(el('app').dataset.commentStatusUrl.replace('__ID__', id), {
    method: 'PATCH',
    body: JSON.stringify({ status }),
  })

  if (result.status !== 200) {
    toast(result.body?.error || 'The comment status could not be changed.', 'danger')
    return
  }

  await loadComments()
}

/* ────────────────────────── tracked changes ────────────────────────── */

function renderChanges () {
  const host = el('changes-list')
  if (!host) return

  el('pending-count').textContent = state.changes.length

  if (state.changes.length === 0) {
    host.innerHTML = '<p class="text-muted small mb-0">No pending tracked changes.</p>'
    el('bulk-actions').classList.add('d-none')
    return
  }

  host.innerHTML = state.changes.map(change => `
    <div class="border rounded-3 p-2 mb-2" data-uid="${escapeHtml(change.uid)}">
      <div class="d-flex justify-content-between align-items-start">
        <div class="small">
          <span class="badge text-bg-${change.type === 'insertion' ? 'success' : 'danger'} me-1">${change.type === 'insertion' ? 'Added' : 'Removed'}</span>
          <span class="fw-semibold">${escapeHtml(change.author || '')}</span>
        </div>
      </div>
      <div class="small text-muted text-truncate">${escapeHtml(change.text || '')}</div>
      ${state.capabilities.canReviewChanges ? `
        <div class="btn-group btn-group-sm mt-2">
          <button class="btn btn-outline-success" data-change-action="accepted" data-uid="${escapeHtml(change.uid)}">Accept</button>
          <button class="btn btn-outline-danger" data-change-action="rejected" data-uid="${escapeHtml(change.uid)}">Reject</button>
        </div>` : ''}
    </div>`).join('')

  el('bulk-actions').classList.toggle('d-none', !state.capabilities.canReviewChanges)
}

async function resolveChange (uid, decision) {
  const result = await api(el('app').dataset.changeResolveUrl, {
    method: 'POST',
    body: JSON.stringify({ changeUid: uid, decision }),
  })

  if (result.status !== 200) {
    toast(result.body?.error || 'That change could not be resolved.', 'danger')
    return
  }

  // Remove the mark locally, and delete the text when the decision removes it.
  const markName = result.body.change.type === 'insertion' ? 'trackedInsertion' : 'trackedDeletion'
  const shouldRemove = result.body.removesText

  editor.chain().focus().unsetMark(markName, { changeUid: uid }).run()

  if (shouldRemove) {
    const { from, to } = editor.state.selection
    if (from !== to) editor.chain().focus().deleteSelection().run()
  }

  autosave.markDirty()
  await loadComments()
  toast('Change resolved.', 'success')
}

async function bulkResolve (decision) {
  const uids = state.changes.map(change => change.uid)
  if (uids.length === 0) return

  for (const uid of uids) {
    await resolveChange(uid, decision)
  }
}

/* ────────────────────────── versions ────────────────────────── */

function renderVersions (snapshots) {
  const host = el('versions-list')
  if (!host) return

  el('version-count').textContent = snapshots.length

  if (snapshots.length === 0) {
    host.innerHTML = '<p class="text-muted small mb-0">No saved versions yet.</p>'
    return
  }

  host.innerHTML = snapshots.map(snapshot => `
    <div class="border rounded-3 p-2 mb-2 d-flex justify-content-between align-items-start">
      <div>
        <div class="small fw-semibold">r${snapshot.revision} ${snapshot.label ? '· ' + escapeHtml(snapshot.label) : ''}</div>
        <div class="text-muted" style="font-size:.75rem">
          ${escapeHtml(snapshot.author || 'system')} ·
          ${snapshot.created_at ? new Date(snapshot.created_at).toLocaleString() : ''}
        </div>
        <div class="text-muted" style="font-size:.75rem">${snapshot.word_count} words</div>
      </div>
      <div class="btn-group btn-group-sm">
        <button class="btn btn-outline-secondary" data-compare-a="${snapshot.id}">A</button>
        <button class="btn btn-outline-secondary" data-compare-b="${snapshot.id}">B</button>
        ${state.capabilities.canRestore ? `<button class="btn btn-outline-primary" data-restore="${snapshot.id}">Restore</button>` : ''}
      </div>
    </div>`).join('')
}

let compareA = null
let compareB = null

async function compareSelected () {
  if (!compareA || !compareB) {
    toast('Choose two versions: click A on one and B on another.', 'warning')
    return
  }

  const url = el('app').dataset.compareUrl
    .replace('__LEFT__', compareA)
    .replace('__RIGHT__', compareB)

  const result = await api(url)

  if (result.status !== 200) {
    toast(result.body?.error || 'The versions could not be compared.', 'danger')
    return
  }

  renderComparison(result.body)
}

function renderComparison (comparison) {
  const host = el('diff-view')

  if (comparison.identical) {
    host.innerHTML = '<p class="text-muted small">These two versions are identical.</p>'
    return
  }

  host.innerHTML = comparison.operations.map(operation => {
    const text = escapeHtml(operation.text)
    if (operation.type === 'insert') return `<ins class="text-success fw-semibold">${text}</ins> `
    if (operation.type === 'delete') return `<del class="text-danger">${text}</del> `
    if (operation.type === 'skip') return `<span class="text-muted fst-italic">${text} </span>`
    return `<span>${text}</span> `
  }).join('')

  el('diff-summary').textContent =
    `+${comparison.summary.added} / -${comparison.summary.removed} words`
}

async function saveVersion () {
  const label = el('version-label').value.trim()

  if (!label) {
    toast('Give this version a short label so you can find it later.', 'warning')
    return
  }

  await autosave.flushNow()

  const result = await api(el('app').dataset.versionSaveUrl, {
    method: 'POST',
    body: JSON.stringify({ label }),
  })

  if (result.status !== 201) {
    toast(result.body?.message?.join?.(' ') || 'The version could not be saved.', 'danger')
    return
  }

  el('version-label').value = ''
  await loadComments()
  toast('Version saved.', 'success')
}

async function restoreVersion (snapshotId) {
  if (!window.confirm('Restore this version? It becomes a new revision — nothing is deleted, and you can restore again afterwards.')) {
    return
  }

  const result = await api(el('app').dataset.restoreUrl.replace('__ID__', snapshotId), {
    method: 'POST',
    body: JSON.stringify({}),
  })

  if (result.status !== 200) {
    toast(result.body?.error || 'The version could not be restored.', 'danger')
    return
  }

  editor.commands.setContent(result.body.content)
  autosave.setRevision(result.body.revision)
  await loadComments()
  toast('Version restored as a new revision.', 'success')
}

/* ────────────────────────── boot ────────────────────────── */

async function boot () {
  state.capabilities = {
    canEdit: el('app').dataset.canEdit === '1',
    canComment: el('app').dataset.canComment === '1',
    canReviewChanges: el('app').dataset.canReviewChanges === '1',
    canRestore: el('app').dataset.canRestore === '1',
    canCompare: el('app').dataset.canCompare === '1',
    canExport: el('app').dataset.canExport === '1',
    canForceSave: el('app').dataset.canForceSave === '1',
  }

  editor.setEditable(state.capabilities.canEdit)

  document.querySelectorAll('[data-workspace-editable]').forEach(node => {
    node.style.opacity = state.capabilities.canEdit ? '1' : '.75'
    node.style.pointerEvents = state.capabilities.canEdit ? 'auto' : 'none'
  })

  if (el('editor-notice')) {
    el('editor-notice').classList.toggle('d-none', state.capabilities.canEdit)
  }

  const result = await api(el('app').dataset.documentUrl)

  if (result.status !== 200) {
    setStatus({ state: 'error', message: 'Document unavailable' })
    toast(result.body?.error || 'The workspace document could not be loaded.', 'danger')
    renderComments()
    renderChanges()
    return
  }

  editor.commands.setContent(result.body.content)
  autosave.setRevision(result.body.revision)
  state.comments = result.body.comments || []
  state.changes = result.body.changes || []

  setStatus({
    state: 'saved',
    message: 'All changes saved',
    revision: result.body.revision,
  })

  updateWordCount()
  renderComments()
  renderChanges()
  renderVersions(result.body.snapshots || [])
}

/* ────────────────────────── wiring ────────────────────────── */

document.addEventListener('DOMContentLoaded', () => {
  if (!el('editor-surface')) return

  boot()

  el('btn-comment')?.addEventListener('click', addComment)
  el('btn-comment-post')?.addEventListener('click', addComment)
  el('btn-save-now')?.addEventListener('click', () => autosave.flushNow())
  el('btn-save-version')?.addEventListener('click', saveVersion)
  el('btn-compare')?.addEventListener('click', compareSelected)
  el('bulk-accept')?.addEventListener('click', () => bulkResolve('accepted'))
  el('bulk-reject')?.addEventListener('click', () => bulkResolve('rejected'))

  el('btn-bold')?.addEventListener('click', () => editor.chain().focus().toggleBold().run())
  el('btn-italic')?.addEventListener('click', () => editor.chain().focus().toggleItalic().run())
  el('btn-undo')?.addEventListener('click', () => editor.chain().focus().undo().run())
  el('btn-redo')?.addEventListener('click', () => editor.chain().focus().redo().run())

  for (let level = 1; level <= 3; level++) {
    el(`btn-h${level}`)?.addEventListener('click', () => {
      editor.chain().focus().toggleHeading({ level }).run()
    })
  }

  document.addEventListener('click', event => {
    const statusButton = event.target.closest('[data-action][data-id]')
    if (statusButton) {
      updateCommentStatus(statusButton)
      return
    }

    const changeButton = event.target.closest('[data-change-action]')
    if (changeButton) {
      resolveChange(changeButton.dataset.uid, changeButton.dataset.changeAction)
      return
    }

    const compareAButton = event.target.closest('[data-compare-a]')
    if (compareAButton) {
      compareA = compareAButton.dataset.compareA
      toast(`Version A set (${compareA}). Now choose version B.`)
      return
    }

    const compareBButton = event.target.closest('[data-compare-b]')
    if (compareBButton) {
      compareB = compareBButton.dataset.compareB
      toast(`Version B set (${compareB}). Press Compare.`)
      return
    }

    const restoreButton = event.target.closest('[data-restore]')
    if (restoreButton) {
      restoreVersion(restoreButton.dataset.restore)
    }
  })

  document.querySelectorAll('[data-conflict-strategy]').forEach(button => {
    button.addEventListener('click', () => resolveConflict(button.dataset.conflictStrategy))
  })

  el('btn-export')?.addEventListener('click', () => {
    autosave.flushNow().finally(() => {
      window.location.href = el('app').dataset.exportUrl
    })
  })

  // Guard against navigating away with unsaved work.
  window.addEventListener('beforeunload', event => {
    if (autosave.dirty || autosave.conflicted) {
      event.preventDefault()
      event.returnValue = ''
    }
  })

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') autosave.flush()
  })
})