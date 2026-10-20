# MODULE 1 — MANUSCRIPT INGESTION AND APA 7 FORMATTING ENGINE

**Status:** Implementation complete. All tests pass. Awaiting approval.
**Date:** 2026-10-18
**Scope:** Module 1 only. No Module 2 work has begun.

---

## 1. What was built

A complete, deterministic manuscript processing pipeline:

```
Upload → Security validation → Immutable storage → Parse → Rule set → Render → Report → Author approval
```

Nothing in the chain can damage the author's file. Every destructive-looking
step is either reversible or writes to a new artefact.

### 1.1 Component inventory

| Layer | File | Responsibility |
|---|---|---|
| Exceptions | `app/Services/Documents/Exceptions/*.php` | Five typed failure modes with stable machine-readable codes |
| Security | `Ingestion/DocxSecurityValidator.php` | Magic bytes, zip-bomb ceilings, path traversal, macro rejection, encryption, required parts |
| Security | `Ingestion/SecurityValidationReport.php` | Immutable result of a validation pass |
| Ingestion | `ManuscriptProcessingService.php` | Orchestrates the pipeline, persists every artefact |
| AST | `Ast/NodeType.php`, `Ast/ManuscriptNode.php`, `Ast/ParsedManuscript.php` | Neutral manuscript tree, fully serialisable |
| Parsing | `Parsing/OoxmlPackage.php` | In-memory ZIP reader (never extracts to disk) |
| Parsing | `Parsing/DocxStructureParser.php` | OOXML → manuscript tree, with lossless preservation |
| Rules | `Formatting/FormattingEngine.php` | Two-phase evaluate-then-apply |
| Rules | `Formatting/Rules/*.php` (7 rules) | Page geometry, typography, indentation, headings, references, title/abstract, preservation |
| Profiles | `Formatting/StyleProfile.php`, `StyleProfileRepository.php` | Versioned, editable APA 7 and OJMAS profiles |
| Validation | `Validation/CitationExtractor.php`, `ReferenceParser.php`, `CitationValidationRule.php` | Deterministic citation/reference cross-checks |
| Rendering | `Formatting/DocxRenderer.php` | AST → real DOCX, carrying media, equations, tracked changes |
| Access | `ManuscriptAccessLogger.php` | Closes the "no audit trail" gap from audit finding B7 |
| HTTP | `Http/Controllers/Author/ManuscriptController.php` | Report, preview, download, approval |
| Policy | `Policies/ManuscriptPolicy.php`, `Policies/SubmissionPolicy.php` | Server-side authorisation |

### 1.2 The seven rules

| Rule id | What it decides |
|---|---|
| `apa7.page_geometry` | Paper size and margins — **auto-corrected** (unambiguous) |
| `apa7.typography` | Approved font, approved size, double spacing — spacing **auto-corrected**; wrong typeface/size **reported as an author-action error** |
| `apa7.paragraph_indentation` | 0.5″ first-line indent on body paragraphs, flush left — **auto-corrected**; the abstract and keyword line are explicitly excluded |
| `apa7.heading_structure` | Heading levels 1–5, no skipped levels — **reported only** (level choice is editorial) |
| `apa7.reference_presentation` | Hanging indent — **auto-corrected**; alphabetical order and manual numbering — **reported only** |
| `ojmas.title_and_abstract` | 18-word title, 150-word abstract, separate cover page, separate abstract sheet, no author names in the abstract |
| `ojmas.content_preservation` | Media, footnotes, equations, tracked changes and unmapped elements are each counted and reported |
| `apa7.citation_reference_consistency` | Unmatched citations, uncited references, incomplete entries, duplicates, malformed DOIs and URLs |

### 1.3 The strict division between "corrected" and "reported"

This is the design decision that matters most.

**Auto-corrected** (severity `info`, `was_auto_fixed = true`) — only where there is exactly one correct answer and no editorial judgement:
paper size, margins, line spacing, paragraph indents, text alignment, hanging indents.

**Reported, never auto-corrected** — anything involving meaning:
- A wrong typeface is *reported*, not silently substituted.
- A 20-word title is *reported*, not truncated.
- An over-length abstract is *reported*, not cut — cutting can change what the paper claims.
- References are never reordered automatically — reordering affects any numbered citation.
- Headings are never re-levelled — level choice changes how a section reads.

---

## 2. Verified test results

**Command:** `php artisan test`
**Result:** `Tests: 161 passed (547 assertions)` — zero failures, zero skipped.

Baseline before Module 1 was `24 passed, 3 failed`. All three pre-existing failures were fixed and the suite now passes in full.

### 2.1 Per-suite breakdown

| Suite | Tests | Covers |
|---|---|---|
| `Auth/*`, `ProfileTest`, `ExampleTest` | 27 | Pre-existing Breeze suite (3 previously failing — now green) |
| `Documents/Ingestion/DocxSecurityValidatorTest` | 13 | Macro, zip bomb, traversal, corrupt, empty, oversized, over-entry, non-OOXML, immutability |
| `Documents/Parsing/DocxStructureParserTest` | 17 | Headings, levels, geometry, formatting attributes, tables, media, content controls, OMML, core properties, abstract promotion, serialisation, immutability |
| `Documents/Formatting/FormattingEngineTest` | 30 | Profile overrides, A4 conversion, margins, fonts, spacing, indents, references, title/abstract rules, preservation, determinism |
| `Documents/Formatting/DocxRendererTest` | 16 | Openability for 6 fixtures, re-parsing, media byte-parity, equations, tracked changes, unmapped elements, wording preservation, metadata confidentiality, determinism |
| `Documents/Validation/CitationValidationTest` | 17 | Extraction, narrative/parenthetical, non-citations, unmatched, uncited, incomplete, duplicates, malformed DOI, severity discipline, 60-reference scale test |
| `Documents/ManuscriptProcessingServiceTest` | 16 | Ingestion, versioning, parse failure recording, full pipeline, status neutrality, approval gate, seeded profiles, profile override |
| `Documents/ManuscriptWorkspaceTest` | 20 | Author access, IDOR prevention, reviewer scoping, editor boundaries, download, approval, auditing, existing pages still work |
| `Editorial/ReviewerSubmissionTest` | 5 | Regression cover for audit finding B1 |

### 2.2 Acceptance criteria (prompt §1.7) — verified

| # | Criterion | Evidence | Result |
|---|---|---|---|
| 1 | Original document unchanged | `DocxSecurityValidatorTest::validation_never_modifies_the_source_file`, `DocxStructureParserTest::it_never_writes_to_the_source_document`, `DocxRendererTest::it_never_modifies_the_source_document`, `ManuscriptProcessingServiceTest::the_original_is_never_overwritten_by_processing` | **PASS** |
| 2 | Formatted DOCX opens in compatible processors | `DocxRendererTest::it_renders_an_openable_docx_for_every_fixture` — 6 fixtures, all unzipped, all required parts present, all `word/document.xml` parsed as well-formed XML with a `w:body` | **PASS** |
| 3 | Tables, images, equations, references, headings preserved or explicitly flagged | `it_copies_embedded_media_byte_for_byte` (md5 equality), `it_preserves_equations` (`oMath` present in output XML), `it_preserves_tracked_changes_with_their_authorship`, `it_preserves_unmapped_elements`, `it_preserves_table_structure`, `it_preserves_every_reference_entry` | **PASS** |
| 4 | Rules tested against representative samples | 6 synthetic fixtures plus 60-reference scale test | **PASS** |
| 5 | Report distinguishes errors from warnings | `every_finding_is_a_warning_not_an_error`, `warnings_alone_never_block_progression`, `ManuscriptValidation::blocksProgression()` | **PASS** |
| 6 | Unsupported content not silently deleted | `unsupported_content_is_reported_and_never_dropped`, `the_formatted_manuscript_still_contains_every_original_block`, `it_does_not_alter_the_authors_wording` | **PASS** |
| 7 | No auto-accept/reject on formatting alone | `ManuscriptProcessingServiceTest::processing_never_changes_the_submission_status`, `ReviewerSubmissionTest::the_submission_is_not_accepted_or_rejected_by_a_review` | **PASS** |

Additional criteria verified:

| Criterion | Result |
|---|---|
| Zip-bomb / macro / traversal rejection | **PASS** — 13 security tests |
| Determinism (same input → same output, byte-identical DOCX) | **PASS** |
| Output metadata does not leak the author's name | **PASS** |
| Original preserved even when processing fails | **PASS** |

---

## 3. Demonstration

A realistic, deliberately non-compliant manuscript (US Letter, 1.5″ margins, single-spaced, justified, 4 references, 1 table, 387 words) was processed end-to-end. Actual output:

```
── STEP 1: INGESTION ──
  Security     : 4 package parts, macros: no
  Version      : #1 (original)
  Checksum     : 124c9f026f103aa2161d99d682eba786710d2c9838ca9b112387c7f9840ef290

── STEP 2: STRUCTURE RECOGNITION ──
  Title        : Environmental Practices And Household Income In Selected Rural Communi...
  Words        : 387    Paragraphs : 9    Headings : 6
  References   : 4      Tables     : 1     Citations : 14

── STEP 3: APA 7 / OJMAS RULE SET ──
  Profile      : OJMAS Submission Profile (v1.0.0)
  Corrected    : 25     Errors : 2     Warnings : 1

  Errors the author must resolve:
    ! The title is 20 words. The submission profile allows at most 18 words. The title has
      not been shortened, because shortening research titles involves judgement about meaning.
    ! The submission profile requires the abstract to appear on a separate sheet. No page
      break was found separating it from the title page.

  Warnings for the author to consider:
    ? Reference 3 begins with "Ibrahim", which sorts before the preceding entry ("Okafor").

── STEP 4: OUTPUT VERIFICATION ──
  Formatted    : manuscripts/formatted/3-IfE6InXZmpRf6Ze2mJIsZNrB4970rJda.docx  (4.2 KB)
  Re-parsed OK : 23 blocks, 4 references, A4=11906x16838, margin=1440

── STEP 5: ORIGINAL PRESERVED ──
  Original SHA256 : 124c9f026f103aa2161d99d682eba786710d2c9838ca9b112387c7f9840ef290
  Matches upload  : YES
  Status unchanged: submitted

── STEP 6: APPROVAL GATE ──
  Blocks workflow : YES - awaiting author approval
  Approver field  : null (only the submitting author may approve)
```

The 25 corrections were: 1 paper size (Letter → A4), 4 margins (1.5″ → 1.0″), 9 line spacings (single → double), 4 alignments (justified → flush left), 3 first-line indents (0 → 0.5″), 4 hanging indents (none → 0.5″).

---

## 4. Files created or modified

### Created — application code (26)
```
app/Models/ManuscriptVersion.php
app/Models/ManuscriptDocument.php
app/Models/ManuscriptValidation.php
app/Models/ManuscriptValidationIssue.php
app/Models/ManuscriptCitation.php
app/Models/ManuscriptReference.php
app/Models/JournalStyleProfile.php
app/Models/ManuscriptAccessLog.php
app/Policies/ManuscriptPolicy.php
app/Http/Controllers/Author/ManuscriptController.php
app/Services/Documents/ManuscriptProcessingService.php
app/Services/Documents/ManuscriptProcessingResult.php
app/Services/Documents/ManuscriptAccessLogger.php
app/Services/Documents/Exceptions/DocumentProcessingException.php
app/Services/Documents/Exceptions/DocumentSecurityException.php
app/Services/Documents/Exceptions/CorruptDocumentException.php
app/Services/Documents/Exceptions/UnsupportedDocumentException.php
app/Services/Documents/Exceptions/DocumentTooLargeException.php
app/Services/Documents/Ingestion/DocxSecurityValidator.php
app/Services/Documents/Ingestion/SecurityValidationReport.php
app/Services/Documents/Parsing/OoxmlPackage.php
app/Services/Documents/Parsing/DocxStructureParser.php
app/Services/Documents/Ast/NodeType.php
app/Services/Documents/Ast/ManuscriptNode.php
app/Services/Documents/Ast/ParsedManuscript.php
app/Services/Documents/Formatting/FormattingEngine.php
app/Services/Documents/Formatting/FormattingResult.php
app/Services/Documents/Formatting/FormattingRule.php
app/Services/Documents/Formatting/RuleFinding.php
app/Services/Documents/Formatting/StyleProfile.php
app/Services/Documents/Formatting/StyleProfileRepository.php
app/Services/Documents/Formatting/DocxRenderer.php
app/Services/Documents/Formatting/Rules/PageGeometryRule.php
app/Services/Documents/Formatting/Rules/TypographyRule.php
app/Services/Documents/Formatting/Rules/ParagraphIndentationRule.php
app/Services/Documents/Formatting/Rules/HeadingStructureRule.php
app/Services/Documents/Formatting/Rules/ReferencePresentationRule.php
app/Services/Documents/Formatting/Rules/TitleAndAbstractRule.php
app/Services/Documents/Formatting/Rules/ContentPreservationRule.php
app/Services/Documents/Validation/CitationExtractor.php
app/Services/Documents/Validation/ReferenceParser.php
app/Services/Documents/Validation/CitationRecord.php
app/Services/Documents/Validation/ReferenceRecord.php
app/Services/Documents/Validation/CitationValidationRule.php
config/manuscripts.php
resources/views/author/manuscripts/show.blade.php
resources/views/author/manuscripts/report.blade.php
database/seeders/ManuscriptStyleProfileSeeder.php
database/factories/SubmissionFactory.php
database/factories/ManuscriptVersionFactory.php
tests/Fixtures/DocxFixtureFactory.php
```

### Created — migrations (11)
```
2026_10_18_000001_add_provenance_columns_to_article_files_table
2026_10_18_000002_create_manuscript_versions_table
2026_10_18_000003_create_manuscript_documents_table
2026_10_18_000004_create_manuscript_validations_table
2026_10_18_000005_create_manuscript_validation_issues_table
2026_10_18_000006_create_manuscript_citations_and_references_tables
2026_10_18_000007_create_journal_style_profiles_table
2026_10_18_000008_create_revision_rounds_table
2026_10_18_000009_add_workflow_pointer_columns_to_submissions_table
2026_10_18_000010_add_anonymity_columns_to_reviewer_assignments_table
2026_10_18_000011_create_manuscript_access_logs_table
```

### Created — tests (9 files, 134 tests)
```
tests/Feature/Documents/Ingestion/DocxSecurityValidatorTest.php
tests/Feature/Documents/Parsing/DocxStructureParserTest.php
tests/Feature/Documents/Formatting/FormattingEngineTest.php
tests/Feature/Documents/Formatting/DocxRendererTest.php
tests/Feature/Documents/Validation/CitationValidationTest.php
tests/Feature/Documents/ManuscriptProcessingServiceTest.php
tests/Feature/Documents/ManuscriptWorkspaceTest.php
tests/Feature/Editorial/ReviewerSubmissionTest.php
tests/Fixtures/DocxFixtureFactory.php
```

### Modified — existing files (11)
```
composer.json                     + phpoffice/phpword ^1.3
composer.lock                     regenerated
phpunit.xml                       + APP_KEY, EMAIL_VERIFICATION_ENABLED for tests
tests/Feature/ExampleTest.php     + RefreshDatabase (fixes pre-existing failure)
routes/web.php                    + 5 author formatting routes
app/Models/Submission.php         + 4 relations, + hasReviewerAssignmentBy(), isLinkedToUserEmail made public,
                                   canBeViewedBy() reviewer scoping corrected (finding B6)
app/Models/ArticleFile.php        + 4 fillable columns, + manuscriptVersion(), uploader(), isAnonymised()
app/Policies/SubmissionPolicy.php rewritten — reviewer access now assignment-scoped (finding B5),
                                   manuscript abilities delegated
app/Providers/AppServiceProvider.php  (net neutral after revert)
app/Http/Controllers/Reviewer/AssignmentController.php  storeReview fixed (finding B1)
resources/views/reviewer/assignments/show.blade.php  review form now matches the real reviews schema
database/seeders/DatabaseSeeder.php  + ManuscriptStyleProfileSeeder
```

**No existing migration was modified. No existing table was altered destructively. No existing record was touched.**

---

## 5. Migrations and rollback

All 11 migrations are **additive only**. No table is dropped, no existing column is altered, renamed or removed.

`submissions.status` (the 9-value MySQL `ENUM`) was **not** touched. A nullable `current_stage` column was added instead and is kept in sync by the workflow service, so every existing query, view and filter continues to work.

**Verified rollback:**
```
php artisan migrate            → 11 DONE
php artisan migrate:rollback --step=11 → 11 DONE (no errors, no residual)
php artisan migrate            → 11 DONE (clean replay)
php artisan migrate:status     → all 42 migrations Ran
```

---

## 6. New dependencies and licensing

| Package | Version | Licence | Purpose |
|---|---|---|---|
| `phpoffice/phpword` | ^1.3 | **MIT** | OOXML read/write (installed for the renderer; the pipeline itself writes OOXML directly) |

**No licence obligation is added.** All existing production packages are MIT-compatible. No paid dependency. No AGPL. No external service. No new infrastructure.

---

## 7. Configuration requirements

| Key | Default | Purpose |
|---|---|---|
| `MANUSCRIPT_DISK` | `private` | Storage disk for manuscripts (must stay private) |
| `MANUSCRIPT_STYLE_PROFILE` | `ojmas` | Which profile to apply by default |
| `MANUSCRIPT_EXTERNAL_LOOKUP` | `false` | External metadata verification — **off by default** |
| `MANUSCRIPT_LOOKUP_MAILTO` | null | Contact address for metadata lookups, if ever enabled |

Security-relevant limits are **hard-coded in `config/manuscripts.php`**, deliberately not environment-driven, so a misconfigured `.env` cannot weaken upload validation.

Style profiles are editable at runtime through `journal_style_profiles` (seeded with both built-ins) — no deployment required to change the 18-word title limit or the 150-word abstract ceiling.

---

## 8. Known limitations — stated plainly

1. **DOCX→PDF conversion does not exist.** No `gd`, no `imagick`, no Ghostscript, no LibreOffice on this stack. DomPDF is HTML-only and cannot open a DOCX. Publication PDFs remain the existing editor-uploaded path. This is a documented decision, not an oversight (audit §H.2).

2. **PhpWord is installed but not on the hot path.** The parser and renderer write OOXML directly over `ext-zip`/`ext-dom`. PhpWord is retained as a supported dependency and for module-phase generation. If you would prefer a single dependency surface, it can be removed with no functional change — worth deciding before Module 2.

3. **Legacy `.doc` binary and scanned PDFs cannot be processed.** They are rejected with a clear `document_unsupported_format` warning, not an error — the journal still accepts them, they just cannot be auto-formatted. Scanned image-only DOCX files are detected and reported via `content.no_body_paragraphs`.

4. **Equations round-trip only when OMML is present.** Equation text stored in legacy `w:instrText` or embedded objects is preserved as text, not as live mathematics.

5. **Citation detection is syntactic, not semantic.** It matches author/year strings. It cannot and does not judge whether a citation actually supports a claim — stated in the rule's own documentation and in the author-facing report.

6. **Track changes survive as tracked changes only when present as `w:ins`/`w:del`.** Accept/reject UI is Module 2 scope.

7. **The rule set covers 8 rule groups.** Not implemented yet: table/figure numbering and placement rules, appendix presentation, footnote formatting, and the full APA 7 heading-format specification (per-level bold/italic/centering). These were scoped out of this pass and should be added in a follow-up before the journal relies on the engine for production decisions.

8. **A single `ParsingError` path is coarse.** A malformed `word/document.xml` fails the whole parse rather than degrading to text-only. This is deliberate — silently producing a partial parse would be worse — but it means a single bad paragraph blocks formatting for the whole file.

9. **The `.htaccess` CSP has no `default-src`/`script-src`** (audit finding S6). Unchanged in this module, but the Module 2 workspace will need a deliberate, tested policy that permits `blob:` for images.

---

## 9. Security considerations

**Fixed in this module:**
- **B5 — Reviewer access was role-wide.** `SubmissionPolicy::view()` granted *any* reviewer access to *any* submission. Now assignment-scoped, with regression test `an_unassigned_reviewer_cannot_open_a_manuscript`.
- **B6 — `canBeViewedBy()`** had the same flaw. Now assignment-scoped.
- **B7 — No audit trail existed.** `manuscript_access_logs` now records every view, download and export of a manuscript, with user, IP and user agent. Tested.
- **S3 (partial) — Output metadata no longer carries the author's name.** The formatted derivative is written with journal metadata rather than the original `dc:creator`, so a reviewer opening it does not receive the author's name through file properties.
- **B1 — The reviewer review endpoint crashed on every submission.** It wrote three columns that do not exist. Now writes the real schema, with five regression tests.
- **S7/S8 — Upload hardening.** Magic-byte validation, entry-count ceiling, per-entry and total uncompressed size ceilings, compression-ratio ceiling, path-traversal rejection, macro rejection, encryption detection, required-part verification — none of which existed before.

**New endpoints are server-side authorised.** `ManuscriptPolicy` grants: report viewing to the author, co-authors, assigned reviewers and editorial staff; **approval to the submitting author only** — an editor cannot approve on an author's behalf, because the gate exists precisely so the author inspects the reformatted file. Cross-submission access returns 404, not 403, to avoid confirming that another submission exists.

**Still outstanding (audit findings, not addressed here):** S4 (admin download substituting a different file), S6 (CSP), S9 (queue worker unverified), S12 (2FA middleware dead code), B2 (reviewer report text not displayed — Module 3 UI work).

---

## 10. Deployment instructions

```powershell
# 1. Back up first
php artisan down
# database backup via your usual mysqldump routine

# 2. Dependencies (PhpWord only)
composer install --no-dev --optimize-autoloader

# 3. Migrate (additive only; verified reversible)
php artisan migrate --force

# 4. Seed the style profiles (idempotent)
php artisan db:seed --class=ManuscriptStyleProfileSeeder --force

# 5. Confirm storage is writable by the web user
# storage/app/private/manuscripts/formatted  will be created on first run

# 6. Cache
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Smoke test, then bring up
php artisan up
```

**Verify after deployment:**
1. Open an author submission → "APA 7 Automated Formatting" → upload a `.docx` → the report renders.
2. Download the formatted DOCX and open it in Word or LibreOffice.
3. Confirm `storage/app/private/manuscripts/formatted/` contains the artefact.
4. Confirm the original in `storage/app/private/submissions/manuscript/` is byte-identical (compare SHA-256).
5. Submit a macro-enabled `.docm` renamed to `.docx` → must be rejected.
6. Confirm `manuscript_access_logs` gains a row on every view and download.

**Rollback:**
```powershell
php artisan down
php artisan migrate:rollback --step=11 --force   # removes only what Module 1 added
php artisan optimize:clear
php artisan up
```
The `manuscripts/formatted/` directory can be deleted safely at any time; it holds only derived artefacts.

---

## 11. Test results — every test actually executed

```
Tests:    161 passed (547 assertions)
Duration: ~4 minutes
Failures: 0
Skipped:  0
```

No test is marked skipped, incomplete or risky. The pre-existing suite went from **24 passed / 3 failed** to **27 passed / 0 failed**, and Module 1 added **134 further passing tests**.

---

## 12. Answering the Module 0 decisions

| Decision | Outcome |
|---|---|
| **J1/J2 — Tiptap + PhpWord?** | PhpWord installed (MIT). Tiptap deliberately **not** installed — it is Module 2 scope, and installing it now would be an unverified change. See limitation 2 on whether PhpWord is still needed. |
| **J3 — Double-blind or single-blind?** | Implemented as **double-blind by default**, per the journal's published guidelines. The provenance columns (`is_anonymised`, `anonymous_code`, `blind_code`) and an `anonymised_view` audit action are in place. **The anonymisation service itself is deliberately not built yet** — it belongs with the reviewer workspace in Module 3, where it can be tested against real access patterns. |
| **J4 — Fix B1, B2, B5, B6?** | B1, B5, B6 fixed now with regression tests. **B2 (reviewer report text never displayed) deliberately deferred** — it is UI work in the reviewer/editor workspace, which is Module 3 scope. |
| **J5 — Fix the test harness?** | Done. Three pre-existing failures resolved; suite fully green before any Module 1 code was written. |
| **J6 — DOCX→PDF?** | Confirmed impossible on this stack. Existing editor-uploaded-PDF publication path retained and documented. |
| **J7 — Should Admin lose publish rights?** | **Not changed.** That is a live workflow change and belongs to Module 3 with your explicit sign-off. |
| **J8 — Additive Spatie permissions?** | **Not implemented.** Permissions remain empty; the existing role-string model is untouched. Deferred to Module 3. |
| **J9 — `current_stage` vs altering the ENUM?** | Additive `current_stage`. The ENUM is untouched and all existing queries keep working. |
| **J10 — Synthetic fixtures?** | Used exclusively. **No author manuscript in `storage/` was opened, copied or tested against.** All fixtures are synthesised in `tests/Fixtures/DocxFixtureFactory.php`. |
| **J11 — Queue worker?** | Unverified — requires production access. Module 1 queues nothing, so this does not block. |
| **J12 — Log rotation?** | Not done. Out of Module 1 scope. |

---

## 13. What I would fix before the engine is trusted with production decisions

1. **Add the missing rule groups** — table/figure numbering and placement, appendix presentation, and the full per-level APA 7 heading format specification. The engine currently covers layout, typography, structure, citations and preservation, but not the whole of §1.4.
2. **Decide on PhpWord** — remove it or keep it. Nothing on the hot path depends on it.
3. **Add a real-world corpus.** Six synthetic fixtures prove the rules work; they do not prove the parser handles the quirks of real manuscripts produced by Google Docs, Pages, LaTeX-to-Word and older Word versions. If you can supply three or four **de-identified** real manuscripts, that would materially raise confidence.
4. **Add CI.** There is still no GitHub Actions workflow. The suite runs in about four minutes and is entirely deterministic, so it would fit a push/PR gate unchanged.

---

# Module 1 implementation is complete. Test results, known limitations and a demonstration are above.

**Do you approve Module 1 and authorize implementation of Module 2: Integrated Browser-Based Manuscript Workspace?**