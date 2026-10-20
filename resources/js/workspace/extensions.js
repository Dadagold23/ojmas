/**
 * OJMAS Manuscript Workspace — editor extensions.
 *
 * Two marks are added to Tiptap because Tiptap ships without them:
 *
 *  - `trackedInsertion` / `trackedDeletion` implement track changes.
 *  - `commentAnchor` marks the exact range a comment is attached to.
 *
 * Both are deliberately thin. The server holds the record of truth
 * (`manuscript_tracked_changes`, `manuscript_comments`); these marks only
 * render that state and let the editor address it.
 */

import { Extension, Mark, mergeAttributes } from '@tiptap/core'

/** A pending insertion: underlined and coloured, click to select. */
const TrackedInsertion = Mark.create({
  name: 'trackedInsertion',

  addAttributes () {
    return {
      changeUid: { default: null },
      author: { default: null },
      date: { default: null },
    }
  },

  parseHTML () {
    return [{
      tag: 'ins[data-change-uid]',
      getAttrs: element => ({
        changeUid: element.getAttribute('data-change-uid'),
        author: element.dataset.author,
        date: element.dataset.date,
      }),
    }]
  },

  renderHTML ({ HTMLAttributes }) {
    return ['ins', mergeAttributes(HTMLAttributes, {
      'data-change-uid': HTMLAttributes.changeUid,
      'data-author': HTMLAttributes.author,
      'data-date': HTMLAttributes.date,
      'class': 'oj-track-insert',
      title: `Inserted by ${HTMLAttributes.author ?? 'unknown'}`,
    }), 0]
  },

  addCommands () {
    return {
      markTrackedInsertion: attributes => ({ commands }) =>
        commands.setMark(this.name, attributes),
    }
  },
})

/** A pending deletion: struck through. */
const TrackedDeletion = Mark.create({
  name: 'trackedDeletion',

  addAttributes () {
    return {
      changeUid: { default: null },
      author: { default: null },
      date: { default: null },
    }
  },

  parseHTML () {
    return [{
      tag: 'del[data-change-uid]',
      getAttrs: element => ({
        changeUid: element.getAttribute('data-change-uid'),
        author: element.dataset.author,
        date: element.dataset.date,
      }),
    }]
  },

  renderHTML ({ HTMLAttributes }) {
    return ['del', mergeAttributes(HTMLAttributes, {
      'data-change-uid': HTMLAttributes.changeUid,
      'data-author': HTMLAttributes.author,
      'data-date': HTMLAttributes.date,
      'class': 'oj-track-delete',
      title: `Deleted by ${HTMLAttributes.author ?? 'unknown'}`,
    }), 0]
  },

  addCommands () {
    return {
      markTrackedDeletion: attributes => ({ commands }) =>
        commands.setMark(this.name, attributes),
    }
  },
})

/** The range a comment is attached to. */
const CommentAnchor = Mark.create({
  name: 'commentAnchor',
  inclusive: false,

  addAttributes () {
    return {
      commentId: { default: null },
    }
  },

  parseHTML () {
    return [{
      tag: 'span[data-comment-id]',
      getAttrs: element => ({ commentId: element.getAttribute('data-comment-id') }),
    }]
  },

  renderHTML ({ HTMLAttributes }) {
    return ['span', mergeAttributes(HTMLAttributes, {
      'data-comment-id': HTMLAttributes.commentId,
      'class': 'oj-comment-anchor',
    }), 0]
  },

  addCommands () {
    return {
      setCommentAnchor: attributes => ({ commands }) =>
        commands.setMark(this.name, attributes),
      unsetCommentAnchor: () => ({ commands }) => commands.unsetMark(this.name),
    }
  },
})

/** A placeholder shown while the document is loading or an error occurs. */
const WorkspacePlaceholder = Mark.create({
  name: 'workspacePlaceholder',
  inclusive: false,
  addOptions () {
    return { HTMLAttributes: {} }
  },
  parseHTML () { return [{ tag: 'span[data-workspace-placeholder]' }] },
  renderHTML ({ HTMLAttributes }) {
    return ['span', mergeAttributes(this.options.HTMLAttributes, HTMLAttributes, {
      'data-workspace-placeholder': '',
      'class': 'oj-placeholder',
    }), 0]
  },
})

export { TrackedInsertion, TrackedDeletion, CommentAnchor, WorkspacePlaceholder }

/**
 * Paragraph-level line spacing (an office-suite essential).
 *
 * APA 7 defaults are double-spacing everywhere, which is what the editor CSS
 * and the DOCX export fall back to; an explicit `lineSpacing` value on a
 * paragraph is an intentional override that the DOCX exporter honours
 * (value × 240 twips per line, line-rule auto). The value is stored on the
 * node, so it survives save/load round-trips, snapshots and restores.
 */
const LineSpacing = Extension.create({
  name: 'lineSpacing',

  addGlobalAttributes () {
    return [{
      types: ['paragraph', 'heading', 'blockquote'],
      attributes: {
        lineSpacing: {
          default: null,
          parseHTML: element => element.getAttribute('data-line-spacing'),
          renderHTML: attributes => attributes.lineSpacing
            ? {
                'data-line-spacing': String(attributes.lineSpacing),
                style: `line-height: ${attributes.lineSpacing}`,
              }
            : {},
        },
      },
    }]
  },

  addCommands () {
    const paragraphTypes = new Set(['paragraph', 'heading', 'blockquote'])

    /** Applies to the paragraphs in the selection, or the paragraph holding the caret. */
    const applyToSelection = (state, tr, value) => {
      const { from, to, empty } = state.selection
      const start = empty ? Math.max(from - 1, 0) : from
      const end = empty ? from + 1 : to

      state.doc.nodesBetween(start, end, (node, pos) => {
        if (paragraphTypes.has(node.type.name) && node.attrs.lineSpacing !== value) {
          tr.setNodeMarkup(pos, null, { ...node.attrs, lineSpacing: value })
        }
      })
    }

    return {
      setLineSpacing: value => ({ state, tr, dispatch }) => {
        if (!dispatch) return true

        applyToSelection(state, tr, value)

        return true
      },
      unsetLineSpacing: () => ({ state, tr, dispatch }) => {
        if (!dispatch) return true

        applyToSelection(state, tr, null)

        return true
      },
    }
  },
})

export { LineSpacing }
