# Manual Acceptance Test Results (Module 5, §5.2)

Honest split of every testing requirement in the master prompt §5.2 between
**automated tests actually executed** and what still requires a **human
acceptance pass** on real hardware.

Many "manual" requirements are covered by feature tests that issue real HTTP
requests through the framework (real routing, middleware, sessions, database).
Those are listed under automated coverage — they are executed code, not
descriptions. What is *not* claimed: browser rendering fidelity, PDF visual
fidelity, real SMTP delivery, or Paystack live behaviour. Those need a human
and are itemised below.

No confidential author manuscripts were used for testing; every sample was
synthesised by `tests/Fixtures/DocxFixtureFactory.php`.

## Coverage table

| §5.2 requirement | Automated coverage (executed) | Human pass required |
|---|---|---|
| DOCX upload and processing | `DocxSecurityValidatorTest`, `ManuscriptProcessingServiceTest`, `DocxStructureParserTest` — real DOCX ingestion incl. macro-renamed rejection | ✔ open in Word/LibreOffice after format on the production server |
| APA 7 formatting rules | `FormattingEngineTest`, `DocxRendererTest`, `ManuscriptStyleProfileSeeder` profile assertions | ✔ visual APA check of a formatted DOCX on a real sample |
| Citation and reference validation | `CitationValidationTest` — in-text/reference matching, deterministic detection | ✔ eyeball one real paper's report |
| Document preservation | `ManuscriptProcessingServiceTest` (SHA-256 of original unchanged), `CrossModuleIntegrationTest` (byte-identical original after full pipeline) | — |
| DOCX export | `DocxRendererTest`, workspace save/export tests | — (DOCX opens fine in test tooling; open once in real Word) |
| **PDF production** | **None** — no automated DOCX→PDF exists (approved design decision) | ✔ produce one PDF manually via Word/LibreOffice; this is the known limitation |
| Autosave and recovery | `ManuscriptWorkspaceTest` (revision persistence), snapshots created on save | ✔ confirm draft autosave in a real browser once |
| Concurrent editing conflicts | `ManuscriptWorkspaceTest` stale-revision → HTTP 409 with both versions | ✔ two real browsers one time |
| Comments and tracked changes | `WorkspaceTest`, `manuscript_comments`/`manuscript_tracked_changes` model tests | ✔ click-through in a real browser |
| Version comparison and restoration | `VersionComparisonServiceTest` | ✔ inspect one diff in the UI |
| Role-based access | `PermissionCatalogTest`, `ManagingEditorBoundaryTest`, `ReviewerWorkspaceHttpTest` (401/403/404 matrix), `ProfileTest` | — |
| Reviewer anonymity | `ManuscriptAnonymiserTest` + manifest assertion (no author leakage), non-assignee 404 | ✔ open one blinded copy in Word and search for author names |
| Editorial decisions and workflow transitions | `EditorialWorkflowTest`, `TransitionBookkeepingTest`, `ReadinessGateTest`, `LifecycleJourneyTest`, `AuthorRevisionTest` | — |
| Notifications | Queue dispatch asserted in workflow/assignment tests (Notification::fake) | ✔ real SMTP delivery of one invitation + one reminder on production |
| Existing authentication and submission functionality | `Auth\*` (registration, login, passwords, email verification), profile tests — all passing unchanged | — (register one real account on production) |
| Existing payment and publication functionality | Payment/vetting assertions inside lifecycle tests; Paystack flow structure untouched | ✔ one real Paystack test-mode payment end-to-end |

Realistic media-rich fixtures (tables, images embedded via OOXML, equations as
raw OOXML, many references, abstract-position violations) exist in
`DocxFixtureFactory` and are exercised by the parsing, formatting, and
integration suites.

## The human acceptance checklist (production go-live)

1. Format one real manuscript → open formatted DOCX in Word → accept APA 7 layout.
2. Render the same manuscript to PDF manually → confirm acceptable fidelity (§ F standard).
3. Two editors open one workspace → confirm the 409 conflict message reads sensibly.
4. Invite a reviewer by email → confirm real mail arrives (queue worker running).
5. Reviewer opens blinded copy → searches for author names → finds none.
6. One test-mode Paystack payment → receipt + admin dashboard correct.
7. Publish a prepped test paper → article appears in the issue page.

**Status of this checklist: not executed on production.** Items 1–7 are the
remaining human work before declaring the Final Acceptance Standard met;
everything automatable up to those touch-points is green (see the automated
test results in the Module 5 report).
