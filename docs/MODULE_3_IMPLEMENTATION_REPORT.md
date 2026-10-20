# Module 3 Implementation Report — Role-Based Editorial Workspace

**Date:** 19 October 2026
**Status:** Complete. Full suite at time of report: **266 passed (1158 assertions), 0 failed, 0 skipped.**
**Scope:** `prompt.txt` §3.1–§3.7. Additive only; no existing row, role, table or payment flow was modified destructively.

---

## 1. What was delivered

### 1.1 Permission layer (deferred audit decision J8 — now closed)

- `app/Services/Permissions/PermissionCatalog.php` — 58 permissions across 10
  domains (`submission.*`, `review.*`, `decision.*`, `workflow.*`,
  `manuscript.*`, `comment.*`, `payment.*`, `publication.*`,
  `administration.*`, `audit.*`), mapped to the six existing roles.
- `app/Services/Permissions/PermissionRegistrar.php` — registers every
  ability as a Gate check and provides an idempotent `sync()`. If the
  permissions table was never seeded, abilities resolve through the same
  catalogue by role name, so **no deployment can lock staff out**; once
  seeded, the Spatie path takes over.
- `database/seeders/EditorialConfigurationSeeder.php` — seeds permissions,
  lifecycle stages, transitions and the default retention policy. Idempotent
  (verified: second run reports 0 new, 0 granted).
- Routes throughout the editorial area now authorise on permissions, not only
  on the legacy `role:` middleware; the `role:` middleware remains in place
  as the first line of defence.

On disk as `app/Services/Permissions/PermissionCatalog.php` and
`app/Services/Permissions/PermissionRegistrar.php`.

### 1.2 Configurable editorial lifecycle (§3.1 status tracking, §3.3, §3.4, §3.5)

- New tables `editorial_stages`, `editorial_stage_transitions`,
  `manuscript_workflow_events` (append-only), `document_retention_policies`.
- `App\Services\Workflow\EditorialWorkflowService` — 13 seeded stages
  (initial submission → technical validation → screening → reviewer assignment
  → peer review → decision → revision → further review → accepted → rejected →
  copyediting → proof approval → published) with 15 configured transitions.
  **`submissions.status` stays untouched**; the additive `current_stage`
  column is advanced in step (`legacy_status` per stage), so every existing
  view and filter keeps working.
- Transitions are refused unless the catalogue allows them **and** the actor
  holds the required permission. A written reason is mandatory for `rejected`
  and `author_revision`.
- Every move is written to `manuscript_workflow_events` with actor, IP, reason
  and metadata. Nothing edits or deletes these rows.
- `App\Services\Workflow\EditorialDashboardService` — pipeline overview,
  delayed-manuscript detection, reviewer invitation tracking, reviewer
  workload, CSV workflow report.

### 1.3 Reviewer workspace (§3.2)

- `App\Services\Review\ReviewService` — draft → submitted → withdrawn
  lifecycle. Drafts are private; **a submitted report is immutable**: nobody
  (including editors) can alter it. Withdrawal is recorded, never deleted.
- Recommendation summary (counts + consensus signal) built only from
  submitted reviews; drafts and withdrawals never influence a decision.
- `App\Http\Controllers\Reviewer\ReviewerWorkspaceController` and views:
  dashboard, invitations (accept/decline), assignment page with blinded
  download, draft autosave, final submission, withdrawal.
- Reviewer downloads serve the **anonymised** copy under a neutral filename,
  never the original; supplementary files are served from the private disk
  with an audit record.

### 1.4 Double-blind anonymisation (§3.6 — the largest single item)

- `App\Services\Review\ManuscriptAnonymiser`: builds a blinded review copy
  of the stored manuscript directly as OOXML:
  1. neutral file name (`MANUSCRIPT-<code>.docx`),
  2. `docProps/core.xml` identity rewritten (`dc:creator`,
     `cp:lastModifiedBy`, title, keywords),
  3. `docProps/custom.xml`, `word/people.xml`, `word/commentsIds.xml`
     stripped,
  4. `w:ins` / `w:del` / comment authorship replaced by a neutral reviewer
     code,
  5. editing-session identifiers (`w:rsid*`) removed from body and settings,
  6. author names, affiliations, emails, ORCIDs, funding and acknowledgement
     declarations replaced with role labels (redaction window covers the
     heading paragraph plus following paragraphs so grant lines fall inside),
  7. evidence — the service records each transformation in a manifest and
     reports **residual risks** (e.g. a detected but unreplaced personal
     name) rather than claiming a clean copy.
- The original file is never modified (covered by test).

### 1.5 Reviewer invitations and coordination (§3.4)

- `reviewer_invitations` table + `App\Services\Review\ReviewerAssignmentService`:
  invite, respond (accept/decline), reminder counting, independent-institution
  check that refuses a blind-defeating invitation.
- `App\Http\Controllers\Admin\ManagingEditorController` + pipeline dashboard,
  reviewer tracking screen, CSV report.

### 1.6 Editor workspace (§3.3)

- Dashboard, submission page, decide (accept / reject / request revision with
  revision-window bookkeeping and author notification), transition within the
  configured lifecycle, blinded-copy manifest screen, audit timeline.
- Deferred audit finding **B2 closed**: reviewer reports
  (`comments_for_author` plus the confidential `comments_for_editor`) are now
  rendered in both the new workspace and the existing
  `resources/views/editor/submissions/show.blade.php`, marked read-only.

### 1.7 Author revision response (§3.1)

- `App\Http\Controllers\Author\RevisionController` + revision record page
  (decisions, deadline, round status), written response endpoint, and version
  comparison entry into the Module 2 workspace diffing.
- Response is only possible while the window is open; closed rounds reject.

### 1.8 Super Admin configuration (§3.5)

- `App\Http\Controllers\Admin\SystemConfigurationController` + four screens:
  role/permission matrix (`admin/configuration/permissions`), journal style
  profiles, workflow stages and deadlines, retention policy.
- The permission screen enforces `managingEditorProhibitions()` server-side:
  those capabilities are removed from any forged request against that role.

### 1.9 Security hardening (§3.6)

- Additive `SubmissionPolicy` abilities: `viewAny`, `assignReviewer`,
  `recordDecision`, `communicateDecision`, `manageWorkflow`,
  `configureWorkflowSettings`, `manageRoles`, `manageStyleProfiles`,
  `manageRetention`, `manageDocumentSettings`, `viewAudit`,
  `prepareBlindedCopy`, `respondToRevision`.
- `publish` is now permission-based. **Admin keeps publish rights** (deferred
  decision J7). **Managing Editor no longer passes `publish`** — the legacy
  role list included it; this is the one pre-existing permission narrowed, and
  it follows directly from §3.4/§3.5. Documented in DEPLOYMENT.md.
- Rate limiting on all new state-changing endpoints (submit 10/min, decision
  20/min, draft 30/min, reminders 20/min etc.).

---

## 2. Bugs found and fixed during Module 3

All found by the tests written for this module; none were inherited from
earlier modules.

| # | Where | Bug | Fix |
|---|-------|-----|-----|
| 1 | `ReviewService::recommendationSummary` | `in_array($review->recommendation, $counts)` — compared the recommendation name against an array of integer counts, so totals were right but every count was 0 | `array_key_exists`; also `consensus()` filtered zero counts when selecting leaders |
| 2 | `ManuscriptAnonymiser::createReviewCopy` | Transformed parts (`document.xml`, `core.xml`, `settings.xml`) were processed then `continue`d **without `addFromString`** — the blinded copy would have shipped without its document body (invalid DOCX) | transformed parts are added to the output archive |
| 3 | `ManuscriptAnonymiser::redactSection` | Only redacted the heading paragraph; a grant line under a "Funding" heading survived | window now covers heading plus up to three paragraphs |
| 4 | `ManuscriptAnonymiser::paragraphText` | Did not strip wrapper tags, so the heading match compared XML markup — no section ever redacted | added `strip_tags` |
| 5 | `ManuscriptAnonymiser::residualRisks` | `in_array($token, $kept)` where `$kept` is a string | `str_contains` |
| 6 | `ManuscriptAnonymiser::stripRsids` | `<w:rsid\b[^>]*\/>` never matched `w:rsidR` etc. (word-boundary sits between `d` and `R`) | `<w:rsid[A-Za-z]*\b` |
| 7 | `EditorialWorkflowService` transitions | Required permissions were derived from the **destination** stage, so a manuscript entering author revision could only be moved by whoever the *author* is | each transition declares the permission needed to *perform* it (`decision.record` for decisions, `publication.publish` for publishing, …) |
| 8 | `Submission` model | `decision_due_at` had no datetime cast → `->isPast()` on a string crashed policies | `casts()` added (also `anonymous_code` fillable already present) |
| 9 | `Submission::currentRevisionRound` | Round lookup by counter alone could miss an open round, blocking an author from responding | falls back to the latest open round |
| 10 | `author/revisions/show.blade.php` | `overdue@endif` — Blade ignores a directive glued to a word character → unbalanced `@endif` (500) | wrapper element around the text |
| 11 | `author/revisions` views | `$version->version_type` does not exist on `ManuscriptVersion` | `origin` |
| 12 | `ReviewerWorkspaceController::downloadBlinded` | An intruder had no differentiated treatment for another reviewer's copy | serves only via an assignment check; non-assignees get 404 so file existence cannot be enumerated |

## 3. Deliberate boundary decisions (recorded, not silent)

1. **Managing Editor ceiling.** The catalogue grants workflow coordination but
   has a hard prohibition list (`user.manage`, `role.manage`, `settings.*`,
   `payment.*`, `decision.record`, `publication.publish`, …). Tests pin it.
2. **`publish` narrowed.** Legacy `publish()` allowed `Editor|Managing
   Editor|Managing Admin|Super Admin`. It now resolves `publication.publish`.
   Admin retains (J7), Editor and Super Admin retain, Managing Editor does
   not — matching §3.4's list of what a Managing Editor does.
3. **Reviewer privacy.** Recommendation summaries and confidential comments
   are editor-facing only; author-facing surfaces show `comments_for_author`
   alone. Withdrawn reviews are excluded from decisions but kept in the
   record.
4. **Fallback authorisation.** If the seeder never runs, role-name checks
   keep the same boundaries; tests cover both paths. Deployments should run
   the seeder (see DEPLOYMENT.md).

## 4. Test inventory (new in Module 3)

- `tests/Feature/Permissions/PermissionCatalogTest.php` — 9 tests: idempotent
  seeding, per-role grants and denials, Managing Editor prohibitions, Admin
  publish retention, unseeded fallback, unknown permissions denied.
- `tests/Feature/Editorial/ReviewLifecycleTest.php` — 7: draft privacy,
  submission requirements, immutability after submission, withdrawal record,
  intruder blocked.
- `tests/Feature/Editorial/ReviewerWorkspaceHttpTest.php` — 8: own assignment
  reachable, blinded copy served + audited, draft/submit round trip,
  cross-reviewer 403, guest redirect, author blocked, invalid submit rejected.
- `tests/Feature/Editorial/ManuscriptAnonymiserTest.php` — 5: every identity
  channel closed, original untouched, stable blind code, manifest populated,
  clear error without a manuscript.
- `tests/Feature/Editorial/EditorialWorkflowTest.php` — 7: lifecycle seeded
  idempotently, author cannot move the pipeline, transitions logged, legacy
  status kept in step, unconfigured transitions refused, stage permission
  enforced (`proofs → published` needs publication rights), ME can move but
  not publish.
- `tests/Feature/Editorial/ManagingEditorBoundaryTest.php` — 11: pipeline and
  reviewer screens reachable by ME, reminders recorded, configuration screens
  forbidden to ME and Editor, forged permission grant refused, decisions
  refused (nothing written), editorial review surfaces read-only.
- `tests/Feature/Author/AuthorRevisionTest.php` — 8: revision record readable,
  response recorded and audited while the window is open, compare page, 
  stranger 403, closed window 403, self-decision refused (nothing written),
  self-transition refused, audit log 403.

**Total new: 55 tests, 280 assertions.**

## 5. Known limitations / still open

- Security findings **S4** (admin download substitute), **S6** (CSP
  `default-src`/`script-src`), **S12** (dead 2FA middleware) and the
  tailwind v3/v4 mismatch remain open outside this module's scope; **S9**
  (queue worker on production) needs production access to verify.
- The permissions screen edits a role's grant list via a per-role dialog
  backed by validated names; a checkbox matrix would be nicer and did not fit
  the session honestly.
- The anonymiser's residual-risk scan detects titled personal names
  ("Prof. …", "Dr. …") and link/text deltas; prose identity not matching those
  patterns cannot be promised caught — which is exactly why the manifest
  exists and says so.
- `Submissions.status` ENUM untouched as required; the stage layer is
  additive alongside it.

## 6. Full-suite state at close of module

```
php artisan test
Tests:    266 passed (1158 assertions)
```

All Module 3 migrations rollback-verified (`migrate:rollback --step=3`,
clean replay, reseed), as were the Module 1 and 2 migrations previously.
Database identifier naming follows the 64-character limit lessons from
Modules 1–2: all new indexes and keys carry explicit, short names.

## 7. Files added or changed

**New**
- `app/Services/Permissions/{PermissionCatalog,PermissionRegistrar}.php`
- `app/Services/Workflow/{EditorialWorkflowService,EditorialDashboardService}.php`
- `app/Services/Review/{ReviewService,ReviewerAssignmentService,ManuscriptAnonymiser}.php`
- `app/Http/Controllers/Reviewer/ReviewerWorkspaceController.php`
- `app/Http/Controllers/Editor/EditorWorkspaceController.php`
- `app/Http/Controllers/Admin/{ManagingEditorController,SystemConfigurationController}.php`
- `app/Http/Controllers/Author/RevisionController.php`
- `app/Models/{EditorialStage,EditorialStageTransition,ManuscriptWorkflowEvent,ReviewerInvitation,DocumentRetentionPolicy,RevisionRound,Review}.php`
- Migrations `2026_10_18_000016`–`000018`, seeder `EditorialConfigurationSeeder`
- Views: `reviewer/workspace/*`, `editor/workspace/*`, `admin/workflow/*`,
  `admin/configuration/*`, `author/revisions/*`
- 7 test files (inventory above), `docs/MODULE_3_IMPLEMENTATION_REPORT.md`

**Changed (additive)**
- `routes/web.php` (new route groups + throttles), `bootstrap/app.php` untouched
- `app/Policies/SubmissionPolicy.php` (new abilities, publish tightened)
- `app/Models/{Submission,ReviewerAssignment,Review,SubmissionStatusHistory}.php`
- `resources/views/editor/submissions/show.blade.php` (B2 fix)
- `app/Providers/AppServiceProvider.php` (permission registration)
- `DEPLOYMENT.md` (Module 3 section)
