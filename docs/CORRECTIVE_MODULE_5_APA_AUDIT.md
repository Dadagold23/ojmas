# Corrective Module 5 — APA 7 Formatting Without Content Destruction

**Status:** complete (local verification). **Date:** 2026-10-23.
**Controlling brief:** `corrective.txt`, "MODULE 5 — APA 7 FORMATTING WITHOUT CONTENT DESTRUCTION".

The brief's governing instruction for this module is *inspection before redesign*: the
existing APA 7 process had to be inspected and its impact on document structure
established before anything was changed. This report records that inspection first,
then the two gaps it surfaced and the additive fixes.

---

## 1. Inspection — what the APA 7 process is today

### 1.1 The pipeline is a two-phase, attribute-only engine

`FormattingEngine::format` (`app/Services/Documents/Formatting/FormattingEngine.php`)
deliberately splits the process in two:

1. **Evaluate** — every rule (`PageGeometryRule`, `TypographyRule`,
   `ParagraphIndentationRule`, `HeadingStructureRule`, `ReferencePresentationRule`,
   `TitleAndAbstractRule`, `ContentPreservationRule`) reads the *original* parsed tree.
2. **Apply** — the engine applies only two fix operations, both already restricted to
   formatting:

   - `set_page_geometry` — mutates `sectionProperties` only (paper size, margins).
   - `set_block_property` — `ManuscriptNode::withAttributes()` on one top-level block.

`withAttributes()` (`app/Services/Documents/Ast/ManuscriptNode.php:29`) merges the new
attributes over the existing ones and passes `children` and `text` through verbatim.
**Conclusion: the engine is structurally incapable of deleting a paragraph, table,
row, column, figure, caption, reference entry or manuscript section.** Every rule can
report content problems; none may rewrite them (`RuleFinding::autoFixed` is used only
for page geometry, indentation, alignment, line spacing, font metrics report, hanging
indents — never for text).

### 1.2 Which blocks the rules may touch

| Rule | Touches | Explicitly excluded |
|---|---|---|
| `PageGeometryRule` | section properties only | everything else |
| `TypographyRule` | `paragraph`, `abstract`, `keywords`, `reference_entry` (attributes) | tables, figures, cover page, title, headings |
| `ParagraphIndentationRule` | `paragraph` (attributes), blank/list/caption/front-matter lines excluded | tables, figures, titles, headings, references, blockquotes, unsupported nodes |
| `HeadingStructureRule` | nothing (reports only) | — |
| `ReferencePresentationRule` | `reference_entry` indent attributes; order/numbering **reported only** | never reorders or renumbers |
| `TitleAndAbstractRule` | nothing (reports only) | never shortens a title or abstract |
| `ContentPreservationRule` | nothing (reports only) | — |

Rules iterate `$manuscript->blocks` (top level). Table cell content is child-nested
and never traversed by fixing rules, so cell paragraphs and inner figures cannot be
rewritten by the engine.

### 1.3 Uncertain content is flagged, not changed — by design

The severity taxonomy in `RuleFinding` separates **error** (measurable violation, reported
for author action), **warning** (needs a human), and:
- unparsable author names, out-of-alphabetical references and manually numbered
  references are warnings, never reordered;
- unapproved typefaces and font sizes are errors, never substituted;
- unsupported/unmapped elements become `preserved()` warnings ("confirm it reads
  correctly") — brief requirement 7 was already honoured at this level.

### 1.4 Original file and recovery route (brief requirement 8) — already correct

`ManuscriptProcessingService` copies the upload to the private disk with its sha256 at
ingest and never re-opens it for writing. `renderFormattedDocument()` always writes a
**new** package under a fresh random name. The validation report is persisted only
*after* the render succeeds; a failure before that leaves no report, no partial state,
and the original version untouched (`STATUS_FAILED` recorded on the parse record).
This was already pinned by `test_the_original_is_never_overwritten_by_processing`.

### 1.5 File-level metadata is scrubbed deliberately, not lost (brief requirement 5)

`DocxRenderer::corePropertiesXml()` writes a journal-neutral `dc:creator` instead of
the author's original creator string, **on purpose**: the formatted derivative may be
read by reviewers, and carrying the author's name in file properties would break
blinded review (the same policy as `abstract_must_exclude_author_names`). The
*original* file keeps all author metadata untouched, the author/co-author block is
preserved in the manuscript body (cover page / title block), and citations,
references and the report persist as database rows. Documented as an intentional
decision — not changed in this module.

---

## 2. Findings the inspection surfaced (and fixes applied)

### F1 — Footnote bodies and anchors were dropped from the APA derivative (fixed)

The parser read `word/footnotes.xml` / `endnotes.xml` and reported every note as
"preserved with its text intact" (`content.note_preserved`), but two links were
silently missing downstream:

- a `w:footnoteReference` inside a run produced **no** inline node — the anchor
  vanished from the paragraph (the collected marker id was recorded but never used);
- `DocxRenderer` never wrote a notes part into the formatted package at all.

Net effect: the APA-formatted DOCX lost every footnote body *and* every in-text
anchor, while the validation report claimed preservation. The claim was also
over-stated at tree level, because "preserved" without a renderable anchor is not
preservation a reader can see.

**Fix (additive; no existing record or workflow changed):**

- `DocxStructureParser::parseRun()` now emits a real `footnote_reference` /
  `endnote_reference` inline node at each anchor, carrying `note_id`, `kind` and a
  document-order `reference_ordinal`. The nodes contribute no text, so paragraph
  wording, word counts and every pre-existing tree assertion are unchanged.
- Notes are parsed once *before* the body loop (`parseNoteIndex()`) so anchors can
  resolve while paragraphs are built; the previous per-part parse is reused for the
  tree-level note list (no double extraction).
- `DocxRenderer::carryNotes()` copies `word/footnotes.xml` / `word/endnotes.xml`
  **byte-for-byte** from the source package (same convention as media parts), and
  adds the matching `[Content_Types].xml` overrides and document relationships.
- `DocxRenderer::inlineChildren()` re-emits each anchor as the genuine OOXML
  `<w:footnoteReference w:id="…"/>` / `<w:endnoteReference w:id="…"/>` bound to the
  carried part. If a part cannot be carried, the anchor degrades to a *visible*
  bracketed ordinal — never a silent disappearance (same honesty rule as images).
- `ContentPreservationRule`'s message now states the note is "carried into the
  formatted document with its in-text anchor intact" and includes the note text so
  the author can verify it.

### F2 — Caption paragraphs were silently given a body first-line indent (fixed)

APA 7 presents table titles, figure numbers/captions and table notes **flush left**.
`ParagraphIndentationRule` treated any paragraph as a body paragraph, so a
"Table 1. Demographic summary" or "Note. Values are group means…" line was
auto-indented to 0.5 in — a silent style change on exactly the elements brief
requirement 2 protects, and an editorial call the engine cannot verify.

**Fix:** the rule now recognises caption-shaped paragraphs — text starting
`Table N.` / `Table N:` / `Table N` alone, the same for `Figure`, or the literal
`Note.` label — and **leaves indentation and alignment exactly as authored**, adding
an `info` finding (`layout.caption_left_for_review`) inviting the author to confirm
the presentation. The detector is conservative: a number must be followed by a
colon, period or end-of-line, so a body sentence such as "Table 1 reports the
results." keeps the ordinary body corrections (pinned by test). Everything else
(descriptive statistics in Table 1. / Note lines) is preserved verbatim.

### F3 — Already-correct behaviours now pinned by tests (no change)

- Table rows, columns and every cell value survive the **full** APA pipeline
  (ingest → parse → format → render → reparse) unchanged — brief requirements 1–4.
- Every top-level block survives in order with identical text through the pipeline,
  figures survive as real inline images bound to real media parts, reference
  entries survive — brief requirements 3 and 5.
- A failed run leaves status-failed, no `manuscript_validations` row, and the
  original recoverable — brief requirement 8.
- The doc-props scrub (1.5) is pinned by the pre-existing
  `the output metadata does not leak the author's name` test.

### F4 — Documented, out of this module's scope

APA per-level *heading format specification* (bold/italic/centering per level),
table/figure *numbering and placement* rules, and appendix presentation were listed
as follow-ups by the earlier implementation report (`docs/MODULE_1_IMPLEMENTATION_REPORT.md`)
and remain reported-not-applied behaviours in the rule set. Footnote *formatting*
(appearance styling in the notes part) is untouched: bodies and anchors are now
preserved byte-for-byte, which is preservation; restyling the notes part would be a
content-rewriting step the brief forbids without editorial review.

---

## 3. Test evidence

New suite `tests/Feature/Documents/Formatting/ApaFormattingSafetyTest.php` —
**11 tests / 81 assertions**, all passing locally:

| Test | Pins |
|---|---|
| the full APA pipeline preserves every table row column and cell | req 1, 3, 4 |
| the full APA pipeline preserves paragraph wording figures and references | req 1, 3, 5 |
| the original version file and records survive the full pipeline | req 8 |
| a failed formatting run leaves a recoverable state | req 8 |
| caption paragraphs are left unindented and flagged for review | req 2, 7 |
| a body sentence that mentions table 1 is not treated as a caption | req 7 (no false positives) |
| the parser records footnote anchors that reach the engine tree | F1 |
| the APA formatted DOCX carries the note parts byte for byte | F1 |
| the APA formatted DOCX re-emits every footnote anchor | F1 |
| anchor markers supersede the original runs in the engine tree without changing text | F1 |
| the validation report says the notes reach the formatted document | F1 (report honesty) |

Fixture: `DocxFixtureFactory::footnoted()` — genuine Word-shaped
`w:footnoteReference` / `w:endnoteReference` anchors and notes parts, plus a table
caption and note line; no real user data.

Full-suite run result: see commit message / conversation summary (suite itself is
re-run before commit; local XAMPP timing figures vary by run).

## 4. Honest-claims boundary

- Everything above was **executed and verified locally** (PHP 8.2.12 / Laravel 12 /
  sqlite test database), as for modules 0–3.
- Production is still not exercised (standing item): Word's own display of the
  re-emitted anchors (number rendering across Word versions) should be eyeballed on
  one real manuscript during production rollout, as with all rendered derivatives.
