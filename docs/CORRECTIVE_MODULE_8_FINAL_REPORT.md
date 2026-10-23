# Corrective Programme — Module 8 Final Report

Modules 0–8 of `corrective.txt` — consolidated deliverable. Per-module
inspection records: `docs/CORRECTIVE_MODULE_5_APA_AUDIT.md`,
`docs/CORRECTIVE_MODULE_6_7_LIFECYCLE_REGRESSION.md` (and the committed
Module 0 audit record). Module 8 itself is an audit of the security and
compatibility surfaces plus the consolidated deployment plan.

---

## 1. Root-cause analysis (paragraph, table, image loss)

Committed in the Module 0 audit; summarised:

| Defect | Root cause | Fixed by |
| --- | --- | --- |
| Paragraph loss/merging | Workspace converter treated run/paragraph structure as one text stream; APA engine auto-re-indented every paragraph | `FormattingEngine` two-phase engine has exactly two fix operations (`set_page_geometry`, `set_block_property`); parser emits real paragraph nodes |
| Table loss | Tables flattened to text with borders (rows pipe-joined, legacy markers) | Parser unchanged; converter passes tables as real `table/tableRow/tableCell` nodes; exporter writes genuine `w:tbl` with `gridSpan`/`vMerge` |
| Image loss | Media rows were never extracted; placeholder `\u{FFFC}` marker leaked into exports | `ManuscriptMediaService` idempotent, read-only extraction; exporter embeds real media parts + drawings; marker never leaks (pinned) |

## 2. Exact files and functions corrected

- `app/Services/Documents/Parsing/DocxStructureParser.php` — run-level
  fidelity (soft breaks, no-italics loss), footnote/endnote markers, caption
  detection inputs.
- `app/Services/Documents/Formatting/FormattingEngine.php`,
  `.../Formatting/Rules/ParagraphIndentationRule.php`,
  `.../Rules/ContentPreservationRule.php` — two-phase engine, caption
  exemption, honest report wording.
- `app/Services/Documents/Formatting/DocxRenderer.php` — byte-for-byte notes
  carriage, real reference markers.
- `app/Services/Workspace/WorkspaceDocumentService.php` — structure-faithful
  Tiptap conversion, image diagnostics (`unhandled/`, `invalid/`).
- `app/Services/Workspace/WorkspaceDocxExporter.php`, `MediaEscape` paths —
  real drawings, merged cells, escaped XML.
- `app/Services/Documents/Media/ManuscriptMediaService.php` — image content
  validation (Module 8).
- `bootstrap/app.php` — TrimStrings exemption for the two manuscript-content
  endpoints (Module 6).

## 3. How the implementation preserves document structure

The formatter never invents or reorders structure: it evaluates the ORIGINAL
document tree (Phase 1) and may only apply `set_page_geometry` /
`set_block_property` (Phase 2). Children, text, captions, notes and媒体
references are passed verbatim (`withAttributes()` never touches text). The
workspace converts the parsed AST one node at a time into an equivalent
Tiptap structure; the exporter serialises that structure back into genuine
OOXML (tables, drawings, merges, escapes).

## 4. Regression-test results (executed, not asserted visually)

Baseline before corrective programme: full suite red in 9 suites.
Progression of full-suite runs (all actually executed):

| After module | Full suite result |
| --- | --- |
| Module 3 | 435 passed / 2,102 assertions |
| Module 5 | 446 passed / 2,183 assertions |
| Modules 6–7 | 456 passed / 2,361 assertions |
| Module 8 | 456 passed / 2,365 assertions / 0 failures / 0 skipped (+4 for the image claim-mismatch pins) |

Lifecycle/element suites committed with this programme:
`ApaFormattingSafetyTest` (11/81),
`CorrectiveLifecycleRegressionTest` (10 tests / 182 assertions after the
Module 8 additions), plus the pre-existing table/media/workspace suites.

## 5. Unsupported document features and their safe handling

- Media parts whose extension cannot be displayed (e.g. `.wmf`, `.svg`):
  preserved verbatim, mime `unhandled/<ext>`, editor node flagged
  `unsupported` with a diagnostic.
- Image parts whose bytes contradict a displayable raster claim: mime
  `invalid/<ext>`, node flagged with an explicit "do not decode" diagnostic
  (new in Module 8).
- Drawings with undeclared relationship ids: preserved as explicit `\u{FFFC}`
  markers, nothing invented.
- Note formatting (restyling footnotes), per-level heading NUMBER colours,
  appendix presentation: report-only; out of scope (audit doc F4).
- Workspace PDF export: does not exist — pinned and recorded, not faked.

## 6. Save/reopen/export integrity (executed confirmation)

`CorrectiveLifecycleRegressionTest::test_the_complete_lifecycle_survives_for_a_mixed_manuscript`
executes upload → display → edit/rearrange → autosave → named save →
reopen → version list → editor restore → DOCX export → reimport, and
asserts the original upload is byte-identical at the end. Element-level
round trips (paragraphs, tables incl. merges, figures, whole manuscripts)
are pinned in tests A–E; failure paths in test F.

## 7. Security and compatibility findings (Module 8 audit)

Verified by read-only inspection of the committed tree:

- **Upload, files, sizes, containers**: `DocxSecurityValidator` gates the
  extension, size (`document_too_large`/`document_empty`), zip container,
  required OOXML parts, per-entry safe names (no path traversal), encrypted
  entries (denied), and zip bombs (per-entry, total uncompressed and
  compression-ratio ceilings) — all pinned in `DocxSecurityValidatorTest`.
- **XML processing**: every `loadXml` call uses `LIBXML_NONET` (no network
  entity resolution) and bounded sizes; no DTD fabrication.
- **Image content**: extraction probes bytes with GD
  (`getimagesizefromstring`); Module 8 adds the honest `invalid/` recording
  for claim mismatches. GD must be enabled in production (already in
  DEPLOYMENT.md).
- **Confidentiality**: manuscripts and extracted media live on the private
  disk; the media endpoint is gated by the same `openWorkspace` policy as
  the workspace itself, cross-submission ids abort 404, and all access is
  logged (`ManuscriptAccessLog`). Policy pins: strangers/reviewers cannot
  open; authors cannot edit outside their window.
- **No sanitization loosening**: no raw HTML anywhere in the workspace —
  Tiptap is schema-bound and the doc payload is JSON, never HTML. All
  `{!! !!}` usage site-safe (`e()` + `nl2br`/`strip_tags`). Export-side XML
  is escaped with `ENT_XML1 | ENT_QUOTES` on every attribute/text.
- **Middleware exemption scope**: only `workspace/submissions/*/save` and
  `*/changes` are exempt from global TrimStrings; the exemption is required
  for correctness (run-boundary spaces) and loosens nothing else.
- **No destructive changes**: zero migrations across the programme; no
  editor/library/storage replacement; roles, permissions, payments, and
  editorial workflows untouched (pinned policy tests unchanged).
- **Compatibility**: PHP 8.2 / Laravel 12 / GD / zip — no new composer or
  npm dependencies were added by the programme.

## 8. Backup, deployment and rollback

Consolidated in `DEPLOYMENT.md` (per-module sections + checklist):
backup the private-disk manuscripts directory and DB dump; `php artisan
down` → take exact code tag → `composer install --no-dev` →
`php artisan migrate --force` (no corrective migrations exist today) →
restart queue workers → health checks (upload a manuscript, format it,
open the workspace, export DOCX, check the access log) → `php artisan up`.
Rollback per module is a single-commit revert; data NEEDS no roll-forward/
back procedures because no schema changed.

## 9. Remaining limitations and recommended next steps

1. **Production execution is still outstanding** (the standing item): run
   the DEPLOYMENT.md procedure on the live host; the programme's claims are
   local-verification claims until then.
2. Workspace PDF export does not exist; recommended follow-up if needed.
3. APA note *formatting* (restyling footnote paragraphs) and per-level
   heading/numbering specs remain report-only by design.
4. A reimported export may carry one extra blank paragraph beside a drawing
   (layout artifact, no content effect).
5. Version restore is editorial-staff-only by standing design; authors see
   restored content but cannot trigger it.
6. Caption uncertainty is flagged (`layout.caption_left_for_review`, info)
   for editorial review rather than auto-fixed.
