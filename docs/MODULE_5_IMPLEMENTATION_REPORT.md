# Module 5 Implementation Report — Integration, QA, and Deployment

Date: 20 Oct 2026 · Branch: `main` (uncommitted since `c6e6abc` before this
module; see §9 for the commit made at close)

Module 5 per the master prompt (§5.1–5.4). Every claim in §6 was actually
executed on 20 Oct 2026. **No production deployment was performed or is
claimed** — all work is local (Windows/XAMPP) verification plus
deployment-readiness documentation.

---

## 1. Implementation summary (§5.1 integration)

The capabilities from Modules 1–4 were verified as **one system**, not four
parallel features. The centerpiece is a new executable integration test
(`tests/Feature/Integration/CrossModuleIntegrationTest.php`) that walks a
single synthetic, media-rich manuscript through ingestion → APA 7
formatting → blinded review copy → peer review → the full editorial lifecycle
→ proof approval → the publication readiness gate → publication, asserting at
every step that:

- the submission id never forks (`Submission::count() === 1` at the end);
- every artefact (versions, blinded copy, review, rounds, proof approval,
  article) hangs off that one submission id;
- the original manuscript bytes survive byte-for-byte (SHA-256 compared
  before ingestion and after the whole pipeline);
- the pre-existing payment record stays exactly as it was.

No working code needed changing to pass this test — the modules were
integrated as delivered by reusing the existing `Submission`, `Article`,
`SubmissionPayment`, and version models throughout. No payment-processing
code was modified in Module 5 (and none was).

Also verified: no scheduled tasks are registered (none needed; documented),
queue connection is `database` with the framework `jobs` table present, and
28 notification classes queue jobs — hence the queue-worker requirement now
written into `DEPLOYMENT.md`.

## 2. Files created or modified in Module 5

**New**
- `tests/Feature/Integration/CrossModuleIntegrationTest.php` — §5.1/§5.4
  lifecycle demonstration (executable)
- `docs/DEPENDENCIES.md` — dependency + licence inventory, PHP extension
  requirements, external-service list
- `docs/EDITORIAL_USER_GUIDE.md` — administrator/editorial instructions
- `docs/MANUAL_ACCEPTANCE.md` — §5.2 coverage table with an honest
  automated/human split and the production go-live human checklist
- `docs/MODULE_5_IMPLEMENTATION_REPORT.md` — this report

**Modified**
- `DEPLOYMENT.md` — added the Module 5 release-readiness section
  (environment verification, canonical migration+seed order, queue-worker
  supervision unit, backup procedure, per-module rollback table, extended
  smoke checks, known limitations); corrected the PHPWord licence claim
  (LGPL-3.0-only, not "MIT") with explanation.

**No application code was changed.** That is deliberate: the verification
suite passes against Modules 1–4 as delivered.

## 3. Database migrations and rollback (§5.4.3)

No new migrations in Module 5. The migration set is 16 additive migrations
(11 Module 1 + 4 Module 2 + 3 Module 3 + 1 Module 4), all previously
rollback-verified. Consolidated order, and a per-module rollback table
destructive-data warnings, are now in `DEPLOYMENT.md` §2 and §5. Summary:

| Rollback scope | Command |
|---|---|
| Module 4 only | `php artisan migrate:rollback --step=1 --force` |
| Module 3 only | `php artisan migrate:rollback --step=3 --force` |
| Module 2 only | `php artisan migrate:rollback --step=4 --force` |
| Module 1 only | `php artisan migrate:rollback --step=11 --force` |

Rollbacks destroy those modules' added data; always take the documented
backup first. They never touch pre-existing OJMAS data.

## 4. New dependencies and licensing (§5.4.4)

Full inventory in `docs/DEPENDENCIES.md`. Headlines:

- **phpoffice/phpword 1.3.0 — LGPL-3.0-only** (not MIT, as an earlier
  deployment note wrongly said; corrected this module). Used unmodified via
  Composer, which LGPL permits; no obligation transfers to OJMAS code.
- `barryvdh/laravel-dompdf` (MIT wrapper; underlying dompdf LGPL-2.1) —
  pre-existing payment-receipt usage.
- `spatie/laravel-permission 6.25.0` (MIT), `laravel/framework 12` (MIT).
- Front-end: Tiptap 3.32 packages + `laravel-vite-plugin@^2`, all MIT.
- No new external service, DNS, or credential requirement.

## 5. Configuration requirements (§5.4.5)

Everything required already documented in `DEPLOYMENT.md`: `APP_*`, `MAIL_*`,
`PAYSTACK_*`, `MANUSCRIPT_*` keys, queue/cache/session backend choices,
storage writability, and the HTTPS/vhost setup. Module 5 added no new config.

## 6. Automated test results (§5.4.6) — executed 20 Oct 2026

```
php artisan test
  Tests:    285 passed (1243 assertions)
  Duration: 287.30s
```

- **285 passed, 0 failed, 0 errored, 0 skipped.**
- The full suite includes the new integration test
  (1 test, 16 assertions, PASS 14.45 s).
- `php artisan route:list` parses cleanly: **158 lines** of route table
  (156 routes at Module 3 close; +2 Module 4 proof routes).
- Seeder idempotency re-verified on the dev database: two consecutive
  `EditorialConfigurationSeeder` runs left **6 roles / 58 permissions /
  13 stages** unchanged, with "configured, no changes" behaviour.

⚠️ **Honest count discrepancy:** the Module 4 report recorded "286 passed" at
its close; with the new integration test the suite should have been 287, and
I cannot reconcile that number — a physical count of `public function test_`
methods today finds exactly **285**, matching this run exactly. The most
likely explanation is a mistaken tally in the Module 4 report, not a lost
test: no test file was deleted (verified via git status), and this run has
zero failures including every Module 4 test file (ReadinessGateTest,
ProofApprovalTest, TransitionBookkeepingTest, LifecycleJourneyTest). Treat
**285** as the authoritative count.

## 7. Manual acceptance-test results (§5.4.7)

Documented in `docs/MANUAL_ACCEPTANCE.md`. Every §5.2 requirement is mapped
to executed automated coverage where it exists, and to a named human
verification step where it doesn't:

**Executed (automated, real HTTP/routing/database):** DOCX upload/security
validation, APA 7 formatting, citation validation, document preservation,
DOCX export, autosave persistence, conflict 409s, comments/tracked changes,
version comparison, role-based access, reviewer anonymity manifest,
workflow transitions, queued-notification dispatch, existing auth/submission
tests, payment assertions in the lifecycle tests, plus the full-lifecycle
integration test.

**Not executed; requires a human on production (§5.2/§F standard):**
1. Visual APA-7 + PDF fidelity on a real manuscript in Word/LibreOffice
   (no automated DOCX→PDF exists — approved decision).
2. Real browser click-through (autosave feel, two-browser conflict demo,
   comments UI).
3. Real SMTP delivery of an invitation + reminder (queue worker running).
4. Human search of one blinded copy for author identifiers.
5. One test-mode Paystack payment end-to-end.

These 5 items are the remaining gap between "suite green" and the master
prompt's Final Acceptance Standard; they are listed as a go-live checklist in
`DEPLOYMENT.md` §7 (Module 5).

## 8. Known limitations (§5.4.8)

Carried forward from earlier module reports, plus Module 5 findings:

1. No automated DOCX→PDF; PDF production is a manual step or a future
   approved toolchain.
2. Sequential editing with conflict detection — **not** real-time
   collaboration; must not be marketed as such.
3. S6 — CSP has no `default-src`/`script-src` (Tiptap bundle is self-hosted,
   which is why the app works, but the policy should be tightened and tested
   at go-live).
4. S4 — the admin file-download substitute bug remains as-is (documented;
   fix would change production behaviour and needs approval).
5. S12 — dead `EnsureTwoFactorEnabled` middleware: removed? no — **it
   remains dead code**; removal is safe but was out of module scope.
6. Tailwind v3/v4 config mismatch unresolved (cosmetic; no runtime failure).
7. The anonymiser's residual risks (prose identity outside titled-name
   patterns) — mitigated by the transformation manifest, not eliminated.
8. Audit finding S9-class: everything here is **locally verified only**;
   PHP extensions, queue worker, and storage permissions must be re-verified
   on the production host before go-live.

## 9. Security considerations (§5.4.9)

**Commit made at close of this module:** `f65029c` (Modules 1–5 work,
previously uncommitted since `c6e6abc` — the git commit is the pre-deploy
backup required by §5.3). Excluded from the commit, pending your
confirmation: the working-tree deletions noted below.

- Role/permission boundaries are enforced **server-side** (policies +
  permission middleware + catalogue prohibitions); the UI hides links only.
- Reviewer material is only ever served as the anonymised, blinded copy to
  assigned reviewers; non-assignees get 404 (cannot enumerate manuscripts).
- Manuscript originals are stored byte-preserved on the private disk, never
  web-accessible; access is logged.
- Uploads pass archive/zip-bomb/macro validation before ingestion.
- queued notifications only carry blinding-safe content.
- Deployment readiness additions: backup procedure before *every* deploy,
  per-module rollback, supervised queue worker unit, environment
  verification gate — all in `DEPLOYMENT.md`.
- **Data-flag for the user (pre-existing, not caused by Module 5):** the
  working tree shows uncommitted deletions of `public/images/journal/*.jpg`
  (staff photographs) and `public/.user.ini`. I did **not** commit those
  deletions and did not restore the files, pending your confirmation whether
  the deletion is intentional. The files remain recoverable from git either
  way.

## 10. Deployment instructions (§5.4.10)

`DEPLOYMENT.md` is the single checklist. Module 5 additions:
pre-deploy `php -m` / `composer check-platform-reqs` gate; the canonical
`down → backup → migrate → seed (2 idempotent seeders) → caches → build →
up` sequence; a systemd queue-worker unit; the backup command set (mysqldump
`--single-transaction` + private-storage tarball, kept outside web root);
per-module rollback table; and eight extended smoke checks including the
readiness-refusal path.

## 11. Administrator and editorial-user instructions (§5.4.11)

`docs/EDITORIAL_USER_GUIDE.md`: role capability matrix (including the
deliberate Managing-Editor publish prohibition), the 13-stage lifecycle and
who does what at each stage, how the readiness gate behaves, configuration
screen guidance, notification/queue expectations, data-handling ground
rules, and a symptom → cause → action troubleshooting table.

## 12. Demonstration of the complete manuscript lifecycle (§5.4.12)

Executed as `Tests\Feature\Integration\CrossModuleIntegrationTest` (PASS,
14.45 s, 16 assertions) and in `LifecycleJourneyTest`. One submission:
media-rich DOCX uploaded and ingested → parsed and APA-formatted → blinded
copy produced for review → reviewer assigned, drafts a report (drafts proven
NOT to satisfy readiness), submits acceptance → four lifecycle transitions
to proof stage with workflow bookkeeping and revision rounds → author proof
approval recorded → readiness assesses `ready` → publication produces
exactly one article bound to the original submission id → submission count
still 1, identifier stable, payment record untouched.

The same story, for a human operator, is `docs/EDITORIAL_USER_GUIDE.md` +
the extended smoke checklist in `DEPLOYMENT.md`. **The on-production human
checklist remains open** (§7 above).

---

## Verdict

All Module 5 work that can be honestly done without production access is
done and verified. The suite is green (285/285), the integration
demonstration executes, deployment documentation is finalised and honest
about what remains. **Production deployment has NOT been attempted and is
not claimed.** Remaining before the Final Acceptance Standard can be
declared: the five human go-live checks in §7, on the production host.

Awaiting your approval of Module 5, plus a decision on the two items
flagged in §9 (journal-photo deletions intentional?) and §8 (S4 fix, S12
removal — both need your explicit authorisation since they change or remove
production behaviour).

---

# Post-approval amendments (2026-10-20, user authorised)

You approved Module 5 and authorised all three follow-up actions. Results:

## A. Working-tree deletions — restored

The four `public/images/journal/` photographs and `public/.user.ini` were
restored from git (`git restore`). `public/.user.ini` matters for deployment:
it carries the PHP upload limits (`upload_max_filesize` etc.) documented in
the Module 0 audit (H.1) for hosts pointing Apache at the project root.

## B. S4 — admin download substitution fixed

`EditorialController::downloadFile()` (used by both the admin and editor
file routes): any file id that does not belong to the submission in the URL
now returns **404**. The old fallback that silently served a *different*
manuscript file is deleted. Pinned by
`tests/Feature/Editorial/AdminDownloadGuardTest.php` (3 tests: matched
download serves the exact bytes; cross-submission swap 404s in both
directions; missing private path 404s).

## C. B4/S8 — dead 2FA middleware removed

`app/Http/Middleware/EnsureTwoFactorEnabled.php` deleted. It was referenced
nowhere else (verified by grep across `app/`, `resources/`, `routes/`,
`config/`, `bootstrap/`) and was never registered, so removal changes no
(runtime) behaviour — it only removes misleading code that referenced a
column that does not exist. *Correction to earlier labelling: this finding
is B4/S8 in the Module 0 audit; the Module 3 report used "S12", which is
actually the download-rate-limiting finding (now correctly listed as still
open in the audit appendix).*

## D. S6 — CSP defined and test-pinned

`SecurityHeadersMiddleware` now sets an explicit policy:
`default-src 'self'`, plus `script-src`, `style-src`, `font-src`, `img-src`,
`connect-src`, and `frame-src 'self'` (the article page embeds its own PDF
in a same-origin iframe — caught and allowed). The policy permits exactly
the hosts the Blade templates actually reference
(`cdn.jsdelivr.net`, `unpkg.com`, `fonts.bunny.net`) and must keep
`'unsafe-inline'` until the 10 inline scripts / 15 inline handlers / 84
inline styles in legacy templates are migrated — this is documented in the
middleware rather than hidden. `APP_ENV=local` gets a relaxed variant for
the Vite dev server; production gets the strict policy. Pinned by
`tests/Feature/Integration/CspHeaderTest.php`. **Browser-rendering
verification on production remains a go-live step.**

## E. Suite and bookkeeping after the amendments

```
php artisan test
  Tests:    289 passed (1260 assertions)
```

(285 + 4 new tests: 3 S4 guard tests, 1 CSP test.) All documentation aligned
(`DEPLOYMENT.md`, `docs/MODULE_0_AUDIT.md` appendix resolution log).
Changes committed after the Module 5 implementation commit: see the git log
entry following `a192a3c`.

# Reported-bug fix (2026-10-21): the workspace could not be opened for editing

**Report:** "Admin, managing editor, reviewer, etc. could not open the
document for editing — only downloading works."

**Root cause (verified in code, not guessed):** the browser workspace
document is chained `submission → manuscript_version → parsed manuscript
document → workspace document`. That chain only exists for manuscripts that
had been run through the Module 1 ingest+parse pipeline. Every real
submission that predated that pipeline (legacy rows, plain author uploads,
out-of-band publications) had **no version record**, so
`ManuscriptWorkspaceController::resolveWorkspace()` returned `null` and the
workspace rendered an empty editor shell — reads and downloads worked, the
editor did not. The feature tests never caught it because every fixture
ingested its manuscript first.

**Fix (no destructive change, originals untouched):**
- `WorkspaceDocumentService::bootstrapForSubmission()` — on first open of a
  submission that has no version chain, it registers the **existing stored
  DOCX bytes** as a manuscript version (`source_file_id` points at the
  existing `article_files` row; `storage_path` is the current file path, so
  **no file is copied and no byte is modified**), subjects it to the same
  security validation as an upload (magic bytes, zip ceilings, macro
  rejection), parses it with the standard parser, and imports it into an
  editable workspace document with a first snapshot.
- Version recovery: a version that exists but was never parsed is parsed on
  demand instead of permanently hiding the manuscript.
- Honest failure states: when there is genuinely nothing to edit (original
  is a PDF/corrupt, no file attached), the workspace now shows an explicit
  warning listing the possible reasons, and nothing is created — the APA 7
  formatting tools and downloads remain available.
- UI entry points: the editorial/admin submission detail page
  (`editor.submissions.show`, served for both roles) previously offered
  download-only; it now carries an "Open workspace" button (contents remain
  authorisation-gated by `WorkspacePolicy` server-side; the button is a
  convenience).

**Tests:** `tests/Feature/Workspace/WorkspaceBootstrapTest.php` (4 tests,
29 assertions): staff opens and edits an un-ingested manuscript (original
bytes provably untouched, no fabricated second upload); reopening creates no
duplicate version; assigned reviewer can read but not edit; non-DOCX
original yields the warning banner and zero created records. Full suite
after the change: **293 passed, 1289 assertions, 0 failed.**

**Not possible to verify here:** the live browser experience (Tiptap mount
against real data). The HTTP layer it relies on is now exercised by tests
for exactly the reported case.

## F. Updated open-items list (the honest remainder)

1. **S12** — rate limiting on manuscript download endpoints (still open;
   would need your authorisation like S4 did).
2. **S9** — queue worker on production must be confirmed running.
3. **S10** — `SESSION_ENCRYPT=true` verified on production.
4. The five human go-live checks in `docs/MANUAL_ACCEPTANCE.md`.
5. Production database credentials (twice wrong historically, per the audit
   H.1) must be correct on deploy.
6. `audit_logs` remains an unused legacy table (B7 remainder).
7. Tailwind v3/v4 config mismatch (cosmetic, documented).
