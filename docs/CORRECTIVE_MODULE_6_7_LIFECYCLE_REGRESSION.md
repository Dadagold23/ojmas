# Corrective Modules 6–7 — Lifecycle and Permanent Regression Suites

Status: implemented, additive, no migration, no dependency change.
Branch: `main`, commit "Corrective modules 6 and 7: full lifecycle and permanent regression suites" (counts in the commit message are the full-suite numbers of this build).

---

## Brief requirements

- **Module 6** — test the complete lifecycle, not just the first display after
  upload: upload → initial conversion → first display → editing and
  rearranging → autosave → manual save → close/reopen → version history and
  restoration → export to DOCX → reimport of the exported DOCX into a test
  environment. Verify paragraphs stay separate, tables stay tables, images
  stay visible, captions stay associated, order is preserved. Do not
  overwrite the original source before the corrected version is validated.
- **Module 7** — permanent regression suites over representative manuscripts:
  Test A paragraphs (≥ 10, headings/subheadings, lists, inline formatting,
  intentional line breaks), Test B simple table (3 × 4, text + numbers),
  Test C complex table (merged cells, long text, captions, notes, wide),
  Test D images (different sizes, caption text, between paragraphs, in a
  cell), Test E mixed academic manuscript, Test F failure recovery (corrupt
  document, unsupported image format, missing image reference, failed
  conversion — reported clearly, never silently saved over the original).

## What was built

1. **`tests/Feature/Workspace/CorrectiveLifecycleRegressionTest.php`** — one
   permanent suite covering Module 6 and Module 7. The upload step drives
   `ManuscriptProcessingService::ingest` (the same validation, storage and
   versioning the real upload flow runs) and every workspace stage — display,
   autosave, manual save, named versions, restore, export — goes through the
   real HTTP endpoints a browser calls. Re-import runs the exported DOCX back
   through the normal pipeline into a fresh submission (the brief's "test
   environment"). PDF export does not exist in the workspace — that boundary
   is pinned and recorded by a dedicated test rather than silently skipped.

2. **Five new representative fixtures in `tests/Fixtures/DocxFixtureFactory.php`**
   (additive, existing fixtures untouched):
   - `regressionParagraphs()` — Test A: 12 numbered paragraphs + 3 list
     paragraphs with w:numPr, headings on three levels, inline italic / bold /
     superscript / underline runs, one soft line break, one centred line.
   - `regressionTableSimple()` — Test B: exactly 3 columns × 4 rows, text and
     decimal numbers, header row marked `w:tblHeader`.
   - `regressionTableComplex()` — Test C: caption above, a merged header cell
     (`w:gridSpan w:val="2"`), long two-paragraph cell text, `Note.` line
     below, fixed 9360-twip wide layout.
   - `regressionMixed()` — Test E: title, headings on two levels, paragraphs
     with citations, a table, two figures each preceded by its `Figure N.`
     caption, a References section with hanging-indent entries.
   - `regressionImageProblems()` — Test F: one healthy PNG figure, one figure
     whose media part is a `.heic` (relationship exists, display support does
     not), and one drawing whose relationship id (`rId999`) is declared by
     nobody.

3. **One production-code fix, mandated by the lifecycle findings**

   - **`bootstrap/app.php` — TrimStrings exemption for the two manuscript-
     content endpoints** (`workspace/submissions/*/save`,
     `workspace/submissions/*/changes`). The framework's global string
     trimming walks every string in the request body, including the text
     nodes nested inside the editor payload. Every save therefore erased
     run-boundary spaces: the sequence of runs `x` + `2`(superscript) +
     `' and '` + `an underlined phrase` survived the visual import but
     collapsed to `x2andan underlined phrase` the moment a save went through
     the HTTP layer — real browser saves are subject to the same middleware,
     so editing content through the workspace was silently corrupting
     run-boundary whitespace. The exemption covers only those two payload
     carriers; every other endpoint keeps standard trimming.

   - **`app/Services/Workspace/WorkspaceDocumentService.php` — diagnostic for
     unsupported media.** A media row whose extension nothing can display is
     already recorded honestly (`unhandled/<ext>`), but the figure node now
     also carries `unsupported => true` plus an explicit `diagnostic` text so
     the problem is reported at the data level instead of only failing in the
     `<img>` tag.

## Verified lifecycle paths (Module 6)

Pinned end to end in `test_the_complete_lifecycle_survives_for_a_mixed_manuscript`
(mixed academic fixture, real HTTP throughout):

1. upload + initial conversion → version row with sha256, parsed structure;
2. first display (`GET workspace.document`) → full Tiptap payload;
3. editing/rearranging → last paragraph moved to the front, `POST workspace.save`
   with concurrency token, revision advances;
4. autosave → second save through the same contract;
5. manual save → named version (`POST workspace.versions.save`, HTTP 201);
6. close/reopen → fresh `GET workspace.document` returns an identical
   structural fingerprint;
7. version history → import snapshot and manual snapshot both listed;
   restore is **editorial-staff-only by standing design** (`WorkspacePolicy::restore`
   — unchanged); an editor restoring the import snapshot creates a NEW
   revision carrying the restored content, history never rewritten;
8. export to DOCX (`GET workspace.export`) → openable package with real
   `<w:drawing>` output;
9. reimport of the exported DOCX into a test environment → every real element
   back in place (blank paragraphs next to drawings are excluded from the
   comparison as layout artifacts; one extra blank paragraph can appear beside
   a reimported drawing);
10. the original upload file is byte-identical at the end of the entire run —
    no stage writes into the original.

## Permanent acceptance criteria (Module 7)

| Test | Pinned behaviour |
| --- | --- |
| A | 12 + 3 paragraphs as separate blocks; 9 heading rows (title + 8 on three levels); italic/bold/superscript/underline marks, soft line break as one `hardBreak` inside its paragraph; timeouts: save → reopen fingerprint-identical; export re-parsed still carries every PARA/ITEM marker and 12 paragraphs. |
| B | 4 rows × 3 columns, values in their cells; in-place cell edit persists across reopen and through export + reimport; `w:tbl`/`w:tr` census on the exported XML; 12 table cells on reimport. |
| C | caption → table → `Note.` order; header cell with `colspan 2` carried through workspace model, exported as genuine `w:gridSpan`, reimported as `grid_span` 2; two paragraphs inside one cell survive everything. |
| D | 3 figures at their declared sizes/order with alt caption; full-size persisted copies; delete–reopen–restore round trip (restore by editor); 3 real drawings + media parts in the export; sizes and alt text back after reimport. |
| E | exact document order: title, two-level headings, citation paragraphs, table caption before table, `Figure 1.`/`Figure 2.` captions directly before their figures, body text after the figures, reference entries; save/reopen identity; export + reimport census (all markers, 1 table, 2 figures, ≥ 2 reference entries). |
| F | corrupt upload rejected with a clear message, **no version row, no workspace row**; `.heic` figure preserved with `unhandled/heic` mime + `unsupported` + diagnostic; drawing with an undeclared relationship id kept as the explicit `\u{FFFC}` marker, nothing invented; a conversion that fails (file missing) recorded `failed` with its message, no workspace document built, browser gets a clear 404 `workspace_not_initialised`; original upload sha-identical. |

## Known boundaries (recorded honestly)

- Workspace PDF export does **not** exist; the only workspace export is a
  DOCX. The brief's "if supported" clause applies; adding a PDF pipeline
  would be a new subsystem and is not part of this module.
- Reference database rows (`manuscript_references`) are persisted by the APA
  formatting stage, not by the workspace lifecycle; the suites pin the
  reference *entries* through the parse/record layers and leave row
  persistence to the formatting suites.
- Restore stays editorial-staff-only; the lifecycle tests exercise restore
  through an editor account, consistent with `WorkspacePolicy` (never
  modified by the corrective programme).
- A reimported export may carry one extra *blank* paragraph next to a
  drawing; the comparison normalises blank paragraphs (layout artifacts) and
  pins every real element instead.

## Rollback

Single-commit revert restores the pre-module behaviour completely (suite-only
plus the two targeted fixes above; no migration, no schema, no data change).
