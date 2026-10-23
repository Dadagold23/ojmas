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

import { Extension, Mark, Node, mergeAttributes } from '@tiptap/core'

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
 * An embedded manuscript image (Corrective Module 3).
 *
 * Images are NOT content-loadable from arbitrary URLs: the server renders the
 * authorization-gated media route into the document JSON before the editor
 * ever sees it, so an <img> here can only display a figure the current user
 * was already allowed to see inside their copy of the manuscript.
 *
 * The node is a leaf block (atom) with no editable content of its own — the
 * author's figure stays as it was imported; deleting the paragraph it came
 * with must remain an explicit author decision. Alt text recorded on the Word
 * docPr is carried in the `alt` attribute and shown in a <figcaption>, so the
 * figure is describable even when it cannot render.
 */
const SubmissionImage = Node.create({
  name: 'submissionImage',

  group: 'block',
  atom: true,
  selectable: true,
  draggable: false,

  addAttributes () {
    return {
      src: { default: null },
      mediaId: { default: null },
      mediaPart: { default: null, parseHTML: () => null, renderHTML: () => ({}) },
      alt: { default: null },
      title: { default: null },
      width: { default: null },
      height: { default: null },
      preserved: {
        default: null,
        parseHTML: element => element.getAttribute('data-preserved') !== null,
        renderHTML: attributes => attributes.preserved ? { 'data-preserved': '' } : {},
      },
      diagnostic: {
        default: null,
        parseHTML: () => null,
        renderHTML: () => ({}),
      },
    }
  },

  parseHTML () {
    return [{ tag: 'img[data-manuscript-media]' }]
  },

  renderHTML ({ node, HTMLAttributes }) {
    const attrs = mergeAttributes(HTMLAttributes, {
      'data-manuscript-media': '',
      src: node.attrs.src ?? '',
      alt: node.attrs.alt ?? '',
      title: node.attrs.title ?? '',
    })

    if (node.attrs.width) attrs.width = node.attrs.width
    if (node.attrs.height) attrs.height = node.attrs.height

    return ['img', attrs]
  },

  // A small figure wrapper: the actual image plus (when known) the caption
  // text and an honest diagnostic placeholder when the media cannot load.
  addNodeView () {
    return ({ node }) => {
      const dom = document.createElement('figure')
      dom.className = 'oj-manuscript-figure'

      const img = document.createElement('img')
      img.setAttribute('data-manuscript-media', '')
      img.alt = node.attrs.alt ?? ''
      img.title = node.attrs.title ?? ''
      if (node.attrs.width) img.setAttribute('width', String(node.attrs.width))
      if (node.attrs.height) img.setAttribute('height', String(node.attrs.height))

      const setStatus = text => {
        let caption = dom.querySelector('figcaption')
        if (!caption) {
          caption = document.createElement('figcaption')
          dom.appendChild(caption)
        }
        caption.textContent = text
      }

      img.src = node.attrs.src ?? ''

      img.addEventListener('load', () => {
        dom.classList.remove('oj-figure-missing')
        if (node.attrs.alt) setStatus(node.attrs.alt)
      })

      img.addEventListener('error', () => {
        dom.classList.add('oj-figure-missing')
        setStatus(node.attrs.diagnostic || 'The image could not be displayed.')
      })

      dom.appendChild(img)

      return { dom, contentDOM: null }
    }
  },
})

export { SubmissionImage }

/**
 * Paragraph-level formatting (the office-suite essentials).
 *
 * Line spacing defaults to APA 7 double spacing — the editor CSS and the DOCX
 * export fall back to it when no value is set. Space before/after defaults to
 * 0 pt exactly as Word's "Before: 0 pt, After: 0 pt" settings: none of the
 * gaps Word's defaults sneak in, and the editor can deliberately add them.
 * First-line/left/hanging indents carry the original upload's paragraph
 * indentation when present.
 *
 * Every value lives on the node, so choices survive save/load round-trips,
 * snapshots and restores, and the DOCX exporter writes them faithfully.
 */
const ParagraphFormatting = Extension.create({
  name: 'paragraphFormatting',

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
        spaceBefore: {
          default: null,
          parseHTML: element => element.getAttribute('data-space-before'),
          renderHTML: attributes => attributes.spaceBefore !== null && attributes.spaceBefore !== undefined && attributes.spaceBefore !== ''
            ? {
                'data-space-before': String(attributes.spaceBefore),
                style: `margin-top: ${attributes.spaceBefore}pt`,
              }
            : {},
        },
        spaceAfter: {
          default: null,
          parseHTML: element => element.getAttribute('data-space-after'),
          renderHTML: attributes => attributes.spaceAfter !== null && attributes.spaceAfter !== undefined && attributes.spaceAfter !== ''
            ? {
                'data-space-after': String(attributes.spaceAfter),
                style: `margin-bottom: ${attributes.spaceAfter}pt`,
              }
            : {},
        },
        firstLineIndent: {
          default: null,
          parseHTML: element => element.getAttribute('data-first-line-indent'),
          renderHTML: attributes => attributes.firstLineIndent !== null && attributes.firstLineIndent !== undefined && attributes.firstLineIndent !== ''
            ? {
                'data-first-line-indent': String(attributes.firstLineIndent),
                style: `text-indent: ${attributes.firstLineIndent / 20}pt`,
              }
            : {},
        },
        leftIndent: {
          default: null,
          parseHTML: element => element.getAttribute('data-left-indent'),
          renderHTML: attributes => attributes.leftIndent !== null && attributes.leftIndent !== undefined && attributes.leftIndent !== ''
            ? {
                'data-left-indent': String(attributes.leftIndent),
                style: `margin-left: ${attributes.leftIndent / 20}pt`,
              }
            : {},
        },
        hangingIndent: {
          default: null,
          parseHTML: element => element.getAttribute('data-hanging-indent'),
          renderHTML: attributes => attributes.hangingIndent !== null && attributes.hangingIndent !== undefined && attributes.hangingIndent !== ''
            ? {
                'data-hanging-indent': String(attributes.hangingIndent),
                style: `text-indent: -${attributes.hangingIndent / 20}pt`,
              }
            : {},
        },
        // Structural tags written by the importer (submissionBlockType marks
        // table rows, equations and other preserved blocks; `preserved` keeps
        // the flag). Without declaring them, any browser round-trip would
        // silently drop the attrs and the export could no longer reassemble
        // tables. Declared with no visual side effects.
        submissionBlockType: {
          default: null,
          parseHTML: element => element.getAttribute('data-submission-block-type'),
          renderHTML: attributes => attributes.submissionBlockType
            ? { 'data-submission-block-type': String(attributes.submissionBlockType) }
            : {},
        },
        preserved: {
          default: null,
          parseHTML: element => element.getAttribute('data-preserved'),
          renderHTML: attributes => attributes.preserved
            ? { 'data-preserved': '' }
            : {},
        },
      },
    }]
  },

  addCommands () {
    const paragraphTypes = new Set(['paragraph', 'heading', 'blockquote'])

    /** Applies a value to the paragraphs in the selection, or the paragraph holding the caret. */
    const applyToSelection = (state, tr, attrName, value) => {
      const { from, to, empty } = state.selection
      const start = empty ? Math.max(from - 1, 0) : from
      const end = empty ? from + 1 : to

      state.doc.nodesBetween(start, end, (node, pos) => {
        if (paragraphTypes.has(node.type.name) && node.attrs[attrName] !== value) {
          tr.setNodeMarkup(pos, null, { ...node.attrs, [attrName]: value })
        }
      })
    }

    const commandFor = attrName => ({
      set: value => ({ state, tr, dispatch }) => {
        if (!dispatch) return true

        applyToSelection(state, tr, attrName, value)

        return true
      },
      unset: () => ({ state, tr, dispatch }) => {
        if (!dispatch) return true

        applyToSelection(state, tr, attrName, null)

        return true
      },
    })

    return {
      setLineSpacing: value => commandFor('lineSpacing').set(value),
      unsetLineSpacing: () => commandFor('lineSpacing').unset(),
      setSpacingBefore: value => commandFor('spaceBefore').set(value),
      unsetSpacingBefore: () => commandFor('spaceBefore').unset(),
      setSpacingAfter: value => commandFor('spaceAfter').set(value),
      unsetSpacingAfter: () => commandFor('spaceAfter').unset(),
      setFirstLineIndent: value => commandFor('firstLineIndent').set(value),
      unsetFirstLineIndent: () => commandFor('firstLineIndent').unset(),
      setLeftIndent: value => commandFor('leftIndent').set(value),
      unsetLeftIndent: () => commandFor('leftIndent').unset(),
      setHangingIndent: value => commandFor('hangingIndent').set(value),
      unsetHangingIndent: () => commandFor('hangingIndent').unset(),
    }
  },
})

export { ParagraphFormatting }
