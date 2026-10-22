# Corrective Implementation — Module 0: Read-Only Audit

Brief: `corrective.txt` (corrective implementation prompt for paragraphs, tables
and images). Stage 1–3 deliverable. Production corrections other than the
already-ordered table work await approval.

Scope note: the workspace **table** work was implemented and committed
(`8bc7dc1`) under the directly preceding user instruction, before this
corrective brief arrived. It covers Module 2 of the brief. This audit covers
the remaining modules and only *reads* code plus ran one temporary
reproduction test (recorded below, then deleted — it was never submitted as
production code and asserted nothing a stranger could misuse).

Execution environment note: all findings below are for **local XAMPP
(PHP 8.2.12)** and are verified by executed tests / direct code reading.
Production behaviour is not verifiable from this environment.

---

## 1. Defect-by-defect reproduction (executed today)

Trace fixture: `DocxFixtureFactory::richMedia()` — title, headings, paragraphs,
a real 3-column table, two embedded PNG images (`word/media/image1.png`,
`image2.png`), figure paragraphs, reference list. Pipeline stages run locally
via existing services: ingest → parse → workspace conversion → save/GET →
workspace DOCX export; APA-7 formatted DOCX (Module 1 pipeline).

| # | Stage | Evidence (executed) |
|---|-------|---------------------|
| A1 | AST | No `image` node type exists at all. The parser emits only a zero-width `\u{FFFC}` marker inside the surrounding run's text. |
| A3 | AST | Figure alt text (`docPr@descr`) is computed and then discarded by `DocxStructureParser::parseDrawing()` (dead code: `$description !== null ? '' : ''`). |
| A4 | Workspace JSON | **0** image nodes — nothing for the editor to visualise. |
| A8 | Workspace JSON | Real `table/tableRow/tableCell/tableHeader` nodes — fixed in `8bc7dc1` (import was previously flattened to pipe text). |
| A9 | Workspace export | Exported DOCX contains **no** `word/media/*` parts. |
| A10 | Workspace export | Exported DOCX contains **0** `<w:drawing>` elements — images gone. |
| A11 | Workspace export | The `\u{FFFC}` marker chars leak into `word/document.xml` as invisible garbage inside `<w:t>` (2 found). |
| A13 | APA-7 formatted DOCX | Media bytes ARE copied into the package (2 parts) — but… |
| A14 | APA-7 formatted DOCX | …**0** `<w:drawing>` elements: images are carried but never displayed. |
| A15 | APA-7 formatted DOCX | The `\u{FFFC}` markers similarly leak into the formatted text (2 found). |

## 2. Root causes (exact files and functions)

### 2.1 Table flattening (brief module 2) — ALREADY CORRECTED
- Was: `WorkspaceDocumentService::convertUnknownBlock()` flattened each Word
  table into pipe-delimited `table_row` paragraphs, and
  `WorkspaceDocxExporter` reassembled the grid from pipes; the browser editor
  had no table capability at all.
- Now (commit `8bc7dc1`): parser ALREADY parses `w:tbl` correctly
  (`DocxStructureParser::parseTable()` — rows, cells, `gridSpan`, header row);
  the converter now emits real TipTap `table/tableRow/tableCell/tableHeader`
  nodes with colspan; `@tiptap/extension-table` v3.32 was added; a table
  toolbar (insert 3×3, row/column add/delete, header toggle, merge/split,
  delete) was wired; the exporter writes `w:tbl` with `w:gridSpan` and true
  `w:vMerge` restart/continuation pairs. Regression suite
  `tests/Feature/Workspace/WorkspaceTableTest.php` (6 tests / 49 assertions)
  pins import, editing round-trip, merged-cell export and legacy-row export.

### 2.2 Image loss (brief module 3) — CONFIRMED DEFECT, not yet corrected
Four independent breaks, each of which alone loses images:

1. **Nothing extracts images.** `DocxStructureParser::parseDrawing()`
   (app/Services/Documents/Parsing/DocxStructureParser.php:617) reduces every
   `w:drawing` to a zero-width string. Relationship IDs (`r:embed`), media part
   names, dimensions and alt text never leave the parser. `media` metadata in
   `ParsedManuscript` records only part names and byte counts.
2. **No editor schema for images.** Neither `resources/js/workspace/index.js`
   nor `extensions.js` registers an image node; the `\u{FFFC}` marker arrives
   as a tipap *text node*, rendered as nothing visible by a browser.
3. **No secure display path.** There is no controller/route to serve extracted
   manuscript images with the existing per-submission authorization
   (`SubmissionPolicy`). Even if a node existed, its `src` would have nothing
   to point at.
4. **Both DOCX writers drop images.**
   - Workspace: `WorkspaceDocxExporter` has no media or relationship handling.
   - APA pipeline: `DocxRenderer` copies media parts byte-for-byte
     (`carryMedia()`, line 520) but **never emits any `<w:drawing>`**; the
     intended placeholder-skip check is a *malformed escape*
     (`if ($part === "\x{FFFC")` at line 413 — the string literal is corrupt,
     so the comparison can never be true), leaking the marker into text;
     `$this->drawingCounter` (line 43) is initialised and never used.

Additional subtlety found: no database table models manuscript media
(`database/migrations` contains no manuscript image/media table), so even the
"recovery copy via the media part of the original version" route relies solely
on the immutable original DOCX zip.

### 2.3 Paragraph structure (brief module 1) — mostly intact, three real gaps
Executed evidence and code reading:

- **Paragraphs DO stay separate** in the workspace: every `w:p` becomes its own
  TipTap paragraph/headings/blockquote node (the existing suites
  `WorkspaceTest`, `WorkspaceBootstrapTest`, `ManuscriptWorkspaceTest` pin
  multi-paragraph imports; no code path concatenates blocks).
- Gap 1 — **soft line breaks collapse**: `DocxStructureParser::parseRun()`
  (line 573-577) turns `w:br` into a raw `\n` *inside a text node*. A TipTap
  text node has no break semantics, so a Word-soft-wrapped paragraph displays
  as one joined line. Page breaks embedded in a text paragraph degrade the
  same way (`has_page_break` is only honoured when the paragraph is empty),
  silently losing the break.
- Gap 2 — **emphasis dropped**: `WorkspaceDocumentService::inlineContent()`
  (line 568) maps only bold/italic/underline. Superscript, subscript and
  strikethrough are parsed (`runProperties()` reports them) and then silently
  discarded on conversion.
- Gap 3 — **lists degrade**: `is_list_item` is parsed but never converted;
  numbered/bulleted items import as plain paragraphs (no bullet/number,
  indentation is lost except the inherited indents).
- Hyperlinks and source tracked changes are also dropped by
  `inlineContent()` (it keeps only children typed `run`) — worth reporting
  under module 1/4.

## 3. Proposed minimal corrections (for approval, no code written yet)

Module-mapped, additive, no destructive migrations:

1. **Module 1 — inline fidelity (small):** in `WorkspaceDocumentService::inlineContent()`
   map superscript/subscript/strike to TipTap marks; represent `w:br` as
   TipTap `hardBreak` nodes (StarterKit already ships hardBreak); keep page
   breaks as a preserved, visually labelled block rather than a dying `\n`.
2. **Module 3 — images (the substantive one):**
   - Migration: `manuscript_media` (submission_id, manuscript_version_id,
     part name, storage_path, within-document ordinal, sha256, size, mime,
     alt text) — additive, nullable-friendly.
   - On ingest/first workspace open: copy embedded media from the original
     package into the private disk under `manuscripts/media/<version>/…`
     (secure path, like existing file conventions), record rows honestly.
   - Parser: emit a genuine `image` AST node with part/salt/ordinal/alt.
   - Converter: TipTap `Image` extension node with `src` pointing to a NEW
     authorization-gated controller endpoint
     (`workspace.media.{submission}.{media}` guarded by the same
     `openWorkspace`/`view` policy; not publicly guessable).
   - Editor: image-node CSS (responsive, correct aspect ratio), placeholder
     with diagnostics when a file is missing — never a silent drop.
   - Export: `WorkspaceDocxExporter` writes `<w:drawing>`/`<w:pic>` + package
     rels + `[Content_Types]`; `DocxRenderer` fixes the corrupted `"\x{FFFC"`
     comparison and emits the drawing at the marker position with correct
     relationship ids (it already carries the bytes).
3. **Module 5 — APA safety:** with images restored in `DocxRenderer`, verify
   formatting rules operate on/around preserved tables (now real), figures
   and captions; no rule rewrites numerical content (they operate on
   attributes, not values — will re-verify with tests).
4. **Module 6/7 — lifecycle & regression:** permanent suites for paragraphs
   (Test A), simple/complex tables (Tests B/C — B and C largely covered),
   images (Test D), mixed manuscript (Test E), failure recovery (Test F).
5. **Module 8:** no schema-incompatible migration; no editor replacement;
   one npm dependency (`@tiptap/extension-table`, already installed); no new
   PHP packages required (GD already present locally and now in
   `php.ini` needed for image probing on production — **a hosting check**
   listed under deployment).

## 4. Save / reopen / integration status (honest)

- Tables: round-trip through save → GET → export is now pinned by executed
  tests (merged cells, legacy rows, word counts).
- Paragraphs: structure already round-trips (pinned by the existing suite
  evidence);
  soft-break/inline-emphasis gaps above mean *some inside-paragraph detail*
  does not survive — pre-correction.
- Images: do not survive ANY stage beyond the original file (A4, A9–A10,
  A14) — pre-correction.
- Original files are never overwritten; every read path opens the immutable
  stored upload (verified reading of `bootstrapForSubmission` /
  `ManuscriptProcessingService`).

## 5. Security

- Extracted media must be served through the existing authorization surface
  (`role`-gated + policy-scoped), private-disk streaming; no
  guessable public URLs; strict mime/extension validation already applies at
  ingest and will extend to extracted parts.
- No HTMLPurifier loosening is required for tables; the TipTap schema
  (server-validated through the existing save pipeline, which stores editor
  JSON) is the model of record.

## 6. Rollback / backup

Everything proposed is additive: new migration (reversible by `down()`),
new endpoint, new editor node, new export branches keyed on stored data.
Rollback = `php artisan migrate:rollback --step=1`, redeploy previous
release tag, no user data destroyed; originals untouched throughout.

## 7. Limitations to state up front

- Merged *row* spans can be created in the editor and exported correctly,
  but DOCX **imports** keep each Word row's own cells (rowspan from source
  docs is not modelled) — stated in code and tests today.
- PDF export capability is not part of the current workspace export path;
  no PDF claims will be made.
- Media formats handled = whatever OOXML already embedded with known types
  (png/jpeg/gif); exotic graphics will get the honest placeholder +
  diagnostic treatment per module 3.B.10 rather than a silent drop.

---

**Stopping here per the brief: Stage 3 complete, awaiting approval before
Modules 1, 3, 5, 6–7 implementation.**
