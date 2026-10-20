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

import { Mark, mergeAttributes } from '@tiptap/core'

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