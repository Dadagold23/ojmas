# Addendum Module 6 — Integration, Security and QA Consolidation (final deliverables)

**Status: complete.** Everything in this report reflects the state committed with this
module. One distinction is maintained throughout, as the brief requires:
**all results below are locally verified test results (Windows/XAMPP, PHP 8.2.12,
Laravel 12, SQLite test database)**. No step of this addendum has been executed on a
production server; production items (VAPID keys, cron, queue worker, permissions
seeding, HTTPS confirmation) remain documented prerequisites in `DEPLOYMENT.md` and are
re-listed in §10.

---

## Deliverable 1 — Architecture audit (post-implementation)

The architecture stands exactly as approved in `docs/MODULE_0_AUDIT.md` (A.1–A.9), now
fully implemented across the six addendum modules. Layer map:

| Layer | Implementation |
| --- | --- |
| Presentation | Blade templates; public ad render through one controlled partial; CSP enforced (`.htaccess` + test) |
| HTTP | Controllers with per-action permission gates over route role middleware; throttles on public POST endpoints; server-side |
| Domain services | `EditorialWorkflowService`, `PublicationService` (+ readiness gate), `AdCampaignService`, `AdRevenueReportService`, `WebPushService`/`PushDispatchService`, `IssueScheduleService`, `ScheduleCalendarService`, `ArticleBylineService`, `CitationService`, `ManuscriptFormattingService` |
| Persistence | Eloquent; all addendum changes additive (`2026_10_21_*`); status machines enforced in services, never inferred in views |
| Audit | `audit_logs` (push composer, campaign changes — new in M6, schedules, category eligibility, permission events), `manuscript_access_logs`, `manuscript_workflow_events` |
| Records policy | Journal records and campaigns are disabled/archived, never deleted; recorded money is never reinterpreted |

## Deliverable 2 — Implementation summary (all modules)

- **Module 0** — read-only architecture audit (committed `900cf34`).
- **Module 1** (`e892c10`) — public archive search/filters, issue table of contents with
  byline attribution, ordered multi-author/co-author attribution, deterministic
  APA/MLA/Chicago/BibTeX/RIS citations with limitation statements, article meta tags,
  PDF access rules.
- **Module 2** (`da8c9ad`) — advertising management: seven placements, ten campaign
  types, window-based scheduling/expiry, priority, audiences, honest impression/click
  counting, public render partial, upload validation.
- **Module 3** (`60dfde5`, deploy prep `de732c3`) — web push: consent-gated
  subscriptions, category eligibility, staged duplicate-immune delivery, scheduled
  dispatch via existing cron, VAPID tooling, audit trail, delivery reporting of
  observable results only.
- **Module 4** (`32e8763`) — revenue fields as their own authorization surface;
  recorded-state payment statuses; reports screen excluding drafts/archived from agreed
  revenue and counting only `paid` as collected.
- **Module 5** (`e92f0e6`) — issue and announcement scheduling: schedule records
  (volume/issue/dates/landing-page/banner/push), announcement-only automation
  (campaign window + optional scheduled push), pause/resume/cancel with honest
  already-sent semantics, one-schedule-per-issue, month calendar of recorded dates.
- **Module 6** (this module) — security consolidation and QA: campaign audit trail,
  unpublished-issue PDF access fix, contact rate limit, subscription-secret encryption
  at rest, consolidated security/test documentation (this report + the two guides).

## Deliverable 3 — Files created and modified (by module)

Authoritative per-module lists are in each module report
(`docs/ADDENDUM_MODULE_{1..5}_REPORT.md`). Module 6 changed:

*Modified:* `app/Http/Controllers/Admin/AdCampaignController.php` (audit trail),
`app/Http/Controllers/HomeController.php` (published-only issue PDF),
`app/Models/PushSubscription.php` (encrypted-at-rest secrets, hidden),
`routes/web.php` (contact throttle),
`DEPLOYMENT.md` (Module 6 section).

*Created:* `tests/Feature/Addendum/Module6SecurityIntegrationTest.php`,
`app/Console/Commands/PushReencryptSecrets.php` (one-time migration of
pre-encryption subscription secrets),
`docs/ADDENDUM_MODULE_6_REPORT.md` (this document),
`docs/ADVERTISING_MANAGEMENT_GUIDE.md`, `docs/PUSH_NOTIFICATION_SETUP_GUIDE.md`.

## Deliverable 4 — Database migrations

Addendum migrations, in order (all additive; none alters existing columns):

1. `2026_10_21_000001_add_addendum_module1_columns.php` — Article/columns for archive,
   ordering, attribution.
2. `2026_10_21_000002_create_advertising_tables.php` — `ad_placements`,
   `ad_campaigns`, `ad_events` (seeded placements).
3. `2026_10_21_000003_create_push_notification_tables.php` — `push_notifications`,
   `push_subscriptions`, `push_deliveries`, `push_category_settings` (seeded).
4. `2026_10_21_000004_add_revenue_columns_to_ad_campaigns.php` — six nullable revenue
   columns.
5. `2026_10_21_000005_create_issue_schedules_table.php` — `issue_schedules`.

**After migrating on production, re-run the idempotent
`EditorialConfigurationSeeder`** (permissions are additive: every addendum including
Module 6's `scheduling.*` grants; Module 6 itself added *no* new permissions).
Permissions are stored in tables, not migrations — this is why the seeder is required.

**Rollback:** all five migrations implement `down()` (drop the new table / remove the
added columns); `php artisan migrate:rollback --step=N` reverses them newest-first.
Module 6's hardening is code-only — rolling back the commit returns the previous
behaviour; note that subscriptions stored by the code with encryption-at-rest can only
be read by code that also decrypts them (see known limitations).

## Deliverable 5 — Configuration instructions

- Pre-deploy environment: `DEPLOYMENT.md` §Environment + §Security.
- PHP extensions/platform: verified with `composer check-platform-reqs` (run during
  Module 3 prep; re-verify on the target host).
- Advertising: `docs/ADVERTISING_MANAGEMENT_GUIDE.md` (permissions, lifecycle, windows,
  revenue rules, counting semantics).
- Push: `docs/PUSH_NOTIFICATION_SETUP_GUIDE.md` (VAPID keys, categories/consent,
  compose/send, scheduler/worker) + `DEPLOYMENT.md` Addendum-Module-3 section (cron,
  HTTPS, queue).
- Permissions seeding after every deploy that includes migrations:
  `php artisan db:seed --class=EditorialConfigurationSeeder` (idempotent).
- No new `.env` keys were introduced by Module 6; Module 3's `PUSH_VAPID_*` remain the
  only new addendum keys.

## Deliverable 6 — Advertisement-management user guide

`docs/ADVERTISING_MANAGEMENT_GUIDE.md` (created with this module).

## Deliverable 7 — Push-notification setup guide

`docs/PUSH_NOTIFICATION_SETUP_GUIDE.md` (created with this module).

## Deliverable 8 — Citation and archive test results

Local results, module suites as committed:

| Suite (file) | Result |
| --- | --- |
| `tests/Feature/Addendum/Module1ArchiveCitationsTest.php` | 15 tests — archive filters/search, issue ToC ordering, multi-author/co-author bylines, deterministic APA/BibTeX/RIS with limitation statements, export downloads, meta tags, PDF rules (1 skipped: GD image fixture — document-image-dependent check, see limitations) |
| `tests/Feature/Addendum/Module2AdvertisingTest.php` | 20 tests — placements, media upload/preview, windows/expiry, audiences/labels, dedupe counting, click route |
| `tests/Feature/Addendum/Module3WebPushTest.php` | 17 tests — subscribe/deny/refresh/unsubscribe, consent audiences, duplicate-send prevention, delivery states, composer validation |
| `tests/Feature/Addendum/Module4RevenueAnalyticsTest.php` | 11 tests — impression/click accuracy, revenue definitions (unpaid never collected), permission boundary |
| `tests/Feature/Addendum/Module5IssueScheduleTest.php` | 24 tests — plan/reserving records, window serving over time (time travel), scheduled push dispatch via cron, pause/resume/cancel, calendar |
| `tests/Feature/Addendum/Module6SecurityIntegrationTest.php` | 11 tests — see Deliverable 9 |

Cumulative honest ledger (test-suite totals recorded at each module commit): 318 → 345
→ 362 → 373 → 397 → **408 passed, 1 skipped, 1,932 assertions** today.

## Deliverable 9 — Security test results

New consolidated suite `tests/Feature/Addendum/Module6SecurityIntegrationTest.php`
— **11 passed, 92 assertions**:

| §6.1 requirement | Implementation | Test |
| --- | --- | --- |
| Server-side authorization | Gates (`Gate::authorize`) on every addendum action over route role middleware | `only_authorized_personnel_may_publish_advertisements_or_send_notifications` (+ permission matrices in Module 4/5 suites) |
| CSRF protection | Laravel CSRF middleware on all non-API routes; webhook is signature-verified instead | framework-level; asserted round trip via state-changing POSTs |
| Input validation & output escaping | `$request->validate` everywhere (limits, enums, URLs http(s)-only); Blade escaping with strip-tags on ad descriptions; CSP | output rules exercised through every compose/store route across suites |
| Secure media upload validation | Per-type mimes+mimetypes+size caps (image 8 MB, video 50 MB, audio 20 MB) — Module 2 | Module 2 upload tests; limits documented in the advertising guide |
| Private access to restricted files | Manuscript workspace access logging + authorization (Modules 1–3 of the platform), **new in M6**: public issue PDF now published-only (404 for drafts), article pages/PDF/citations refuse unpublished records | `an_unpublished_issue_pdf_is_not_publicly_downloadable`, `unpublished_articles_do_not_leak_through_public_pages` |
| Rate limiting | Subscribe/unsubscribe 30/min, ad click 60/min, contact **10/min (new)**, submissions 5/min | `the_public_contact_form_is_rate_limited` (429 recorded) |
| IDOR protection | Route-model gate checks + role middleware on all record routes; workspace tests pin stranger access | workspace suites ("a stranger cannot open the workspace") |
| Subscription endpoint key handling | **Encrypted at rest** (Laravel attribute encryption) and excluded from serialization | `subscription_endpoints_and_keys_are_encrypted_and_never_serialised` |
| Audit trails for campaign changes and notification sends | Campaign create/update/status transitions and placement changes now audited (gap found and closed in M6); push send/schedule/cancel already audited | `campaign_changes_and_status_changes_are_audited`, `placement_changes_are_audited`, `notification_cancellations_are_audited` (+ Module 3 tests) |
| Privacy and retention controls | Daily-rotating, irreversible visitor fingerprints; consent flags stored per subscriber; minimal payload encryption; document retention policy screens for submissions. **Deliberate stance:** counted ad events and delivery rows are *not* auto-pruned — they are the evidence behind recorded revenue and delivery rates, and deleting them would rewrite history; no pruning without explicit authorization | consent and fingerprint semantics pinned in Module 2/3 suites |

Also verified against regressions: `public_issues_still_render_the_announcement_strip_and_ad_click_redirects`
and `the_full_public_notification_round_trip_still_works_after_hardening` prove the
integration surfaces still function end-to-end with the hardening applied.

## Deliverable 10 — Hosting and deployment instructions

The complete, verifiable checklist remains `DEPLOYMENT.md` (per-module sections plus
backup procedure and rollback notes). Executed on the target host, in order:

1. **Backup** database + uploaded files + code state (exact commands in DEPLOYMENT.md
   “Backup procedure”).
2. Put code in place; `composer install --no-dev`; `npm ci && npm run build` for the
   workspace assets.
3. `php artisan migrate --force` (runs the five addendum migrations; confirm each,
   none are destructive).
4. `php artisan db:seed --class=EditorialConfigurationSeeder` (permissions).
5. `php artisan push:keys` once, if not already in `.env` (required for push delivery).
6. Verify HTTPS on the real domain (service worker and notification permission require
   a secure origin), storage permissions (`storage/`, `bootstrap/cache`),
   PHP extensions (`composer check-platform-reqs`).
7. Install/verify the scheduler cron (`schedule:run` every minute) and a supervised
   queue worker (`queue:work`); verify both actually run on this host before launch
   (standing item S9 from the audit — **not yet verifiable from this environment**).
8. Smoke checks: DEPLOYMENT.md “Extended final smoke checks”.
9. **No real public notifications during development**: test with the maintainer's own
   consenting subscription(s) only.

Migrations/rollback: see Deliverable 4. DNS/HTTPS/VAPID/service-worker/third-party
(push services) documentation: DEPLOYMENT.md Addendum-Module-3 section and the push
setup guide.

## Deliverable 11 — Known limitations

Carried from earlier modules and updated:

1. **Unverified production operations** — cron, queue worker, VAPID generation and
   HTTPS on the actual host remain to be executed and confirmed (audit item S9). All
   claims in these documents are local-test results.
2. **GD image fixture skip** — one test (document-image dependent check) still skips
   where the GD extension's behaviour differs; long-standing, unchanged.
3. **Advertising audiences recorded but not enforced at serve time** — public pages
   render to all visitors; audience targeting is a record/reporting concept
   (Module 2 report §5).
4. **Subscription encryption at rest requires app-key stability** — re-keying the
   application invalidates stored subscription secrets and stops delivery on existing
   subscriptions (their records persist; the browser simply decides to
   re-subscribe). A documented trade-off, but operationally real. Deployments that
   stored subscriptions before this module get a one-time migration command
   (`php artisan push:reencrypt-secrets`) documented in DEPLOYMENT.md.
5. **Module 6 campaign audit adds rows, not reports** — campaign audit entries are in
   `audit_logs` and each record's show screen; no consolidated advertising-audit report
   screen was built (scope discipline; data exists).
6. **No automatic pruning of counted records** — deliberate (see Deliverable 9
   privacy/retention row); growth of `ad_events`/`push_deliveries` is bounded only by
   traffic.
7. **DOI/registration integrations, per-audience serving, and issue PDF bulk
   generation** remain out of scope, as stated in their module reports.
8. **One schedule per issue and one announcement surface set per schedule** — multiple
   placements for the same announcement are a possible future extension.

## Deliverable 12 — Demonstration of the complete workflow

An end-to-end pass of the journal's lifecycle, mapped to where it is demonstrated. The
suite's 407 tests exercise each link; the walkthrough below is what an operator does.

1. **Author submits** (public form + payment status) — author/payment suites.
2. **Editorial workflow** runs assign → review → decide → revise → ready
   (`EditorialWorkflowService` seed; workflow suites).
3. **Manuscript workspace & APA engine** ingest/verify the DOCX (`ManuscriptWorkspace*`
   suites; accession of formatting findings).
4. **Publication readiness gate** blocks missing pieces, then editor/publisher release
   via the production pipeline (Module 4/5 platform suites).
5. **Archive & issue ToC** show the released work with ordered attribution; citations
   render deterministically on article pages (Module 1 suites).
6. **Advertising** — admin records a campaign with a real window; it serves on its
   placements when the window opens and stops at `ends_at`; impressions/clicks dedupe
   per fingerprint-day; revenue states track agreed → invoiced → paid (Module 2/4
   suites + `campaign_changes_and_status_changes_are_audited` from M6).
7. **Push** — a visitor consents (rate-limited endpoint), category eligibility decides
   reachability, a notification is composed/scheduled, dispatch is exactly once and
   reports only observable deliveries (Module 3 suites + the M6 round-trip test).
8. **Issue scheduling** — the next volume/issue is planned with dates; its announcement
   window/push go live at their recorded times; a delayed issue is rescheduled, its
   queued notification cancelled/re-queued on pause/resume, and nothing auto-publishes
   the issue (Module 5 suites).
9. **Security audit trail closes the loop** — every campaign, scheduling, notification
   and category action above is recorded with who/when/what (`audit_logs`; M6 tests).

---

## Commit record

- Module 6 commit: this module's commit (security consolidation + QA suite + the four
  documents + DEPLOYMENT.md update).
- New module test results: **11 tests / 92 assertions**; full suite at commit time:
  **408 passed, 1 skipped (GD), 1,932 assertions** — recorded honestly in the commit
  message.

With these deliverables the addendum's developmental work is concluded. Remaining
operational steps are the production-execution items listed in Deliverable 10 — they
require server access, not further code.
