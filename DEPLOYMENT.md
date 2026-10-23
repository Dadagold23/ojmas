# OJMAS Deployment Checklist

## Environment

- Set `APP_ENV=production`
- Set `APP_DEBUG=false`
- Set `APP_URL=https://ojmaspublish.online`
- Set a real `APP_KEY`
- Set valid `MAIL_*` settings
- Set valid `PAYSTACK_SECRET_KEY` and related payment settings
- Use `MAIL_PORT=587`, `MAIL_ENCRYPTION=tls`, and `MAIL_SCHEME=smtp`
- Set `MAIL_EHLO_DOMAIN=ojmaspublish.online`
- Use [.env.production.example](C:/xampp/htdocs/ojmas-journal/.env.production.example) as the live server starting point

## Security

- Serve the site behind HTTPS only
- Enable secure cookies in `.env`:
  - `SESSION_SECURE_COOKIE=true`
  - `SESSION_HTTP_ONLY=true`
  - `SESSION_SAME_SITE=lax`
- Use a non-local session/cache/queue backend in production when available:
  - `SESSION_DRIVER=database` or `redis`
  - `CACHE_STORE=database` or `redis`
  - `QUEUE_CONNECTION=database` or `redis`
- Make sure the `notifications`, `jobs`, and `cache` tables are migrated if using database-backed drivers
- Restrict write permissions to:
  - `storage/`
  - `bootstrap/cache/`
- Keep `.env` outside public exposure

## Laravel Commands

Run these during deployment:

```powershell
php artisan down
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan storage:link
php artisan up
```

For this app, make sure the web server points to `public/` and that `https://ojmaspublish.online/webhooks/paystack` is registered as the Paystack webhook URL.

## Apache / .htaccess

- Preferred setup:
  - point Apache `DocumentRoot` directly to the app's `public/` directory
  - keep `AllowOverride All` enabled for the site so Laravel rewrites work
- This repository now includes:
  - root [`.htaccess`](C:/xampp/htdocs/ojmas-journal/.htaccess) for hosts that still point Apache at the project root
  - [public/.htaccess](C:/xampp/htdocs/ojmas-journal/public/.htaccess) for the preferred `DocumentRoot=public/` setup
- Both rewrite files now:
  - force `https://ojmaspublish.online`
  - redirect `www.ojmaspublish.online` to the bare domain
  - preserve normal Laravel route handling

Example Apache vhost:

```apache
<VirtualHost *:80>
    ServerName ojmaspublish.online
    ServerAlias www.ojmaspublish.online
    DocumentRoot /path/to/ojmas-journal/public

    <Directory /path/to/ojmas-journal/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Full example file:
- [deploy/apache/ojmaspublish.online.conf.example](C:/xampp/htdocs/ojmas-journal/deploy/apache/ojmaspublish.online.conf.example)

## Background Workers

- Start a queue worker if notifications or future background jobs are expected:

```powershell
php artisan queue:work --tries=3 --timeout=120
```

- Add a scheduler if scheduled tasks are introduced later:

```powershell
php artisan schedule:run
```

## Final Smoke Checks

- Register a new user and confirm email verification mail is sent
- Confirm verification links and password reset links point to `https://ojmaspublish.online`
- Verify login/logout
- Submit a manuscript
- Complete a payment and confirm:
  - payment record is marked paid
  - receipt is generated
  - admin dashboard shows the payment
- Confirm private file downloads require auth
- Confirm Paystack callback and webhook URLs use `https://ojmaspublish.online`

## Manuscript Formatting Engine (Module 1)

The APA 7 formatting engine adds one dependency, eleven additive migrations and
one new storage directory.

### Dependency

`phpoffice/phpword ^1.3` — **LGPL-3.0-only** licensed (see
[docs/DEPENDENCIES.md](docs/DEPENDENCIES.md)). It is used unmodified as a
Composer dependency, which LGPL permits without affecting OJMAS application
code. Run `composer install --no-dev --optimize-autoloader` as part of the
normal deployment.

### Configuration

| Key | Default | Notes |
|---|---|---|
| `MANUSCRIPT_DISK` | `private` | **Must remain `private`.** Manuscript files must never be on a web-accessible disk. |
| `MANUSCRIPT_STYLE_PROFILE` | `ojmas` | `ojmas` or `apa7_professional` |
| `MANUSCRIPT_EXTERNAL_LOOKUP` | `false` | External metadata verification. Leave `false` unless you have explicitly authorised it. |
| `MANUSCRIPT_LOOKUP_MAILTO` | unset | Contact address used only when external lookup is enabled. |

Upload validation limits (file size, archive entry count, decompressed size,
compression ratio, forbidden parts) are intentionally hard-coded in
`config/manuscripts.php` rather than environment-driven, so a misconfigured
`.env` cannot weaken them.

### Migrations

Eleven additive migrations dated `2026_10_18_*`. They add new tables and new
nullable columns; no existing table is dropped and no existing column is
altered. The `submissions.status` ENUM is deliberately untouched.

```
php artisan migrate --force
php artisan db:seed --class=ManuscriptStyleProfileSeeder --force   # idempotent
```

Rollback removes only what Module 1 created:

```
php artisan migrate:rollback --step=11 --force
```

### Storage

- Originals: `storage/app/private/submissions/manuscript/` (unchanged path).
- Derived formatted documents: `storage/app/private/manuscripts/formatted/`
  (created automatically on first run).

The formatted directory holds **derived artefacts only** and can be deleted at
any time without data loss. Confirm the web server user can write to
`storage/app/private/`.

### Verification after deployment

1. Open an author submission and run the "APA 7 Automated Formatting" check.
2. Download the formatted DOCX and open it in Word or LibreOffice.
3. Confirm the original file's SHA-256 is unchanged after formatting.
4. Upload a macro-enabled file renamed to `.docx` and confirm it is rejected.
5. Confirm `manuscript_access_logs` records each view and download.

### Queue

Module 1 dispatches no queued jobs. The database queue driver remains required
for the existing notification system.

## Browser Manuscript Workspace (Module 2)

The workspace adds four additive migrations, five front-end packages, and one
previously-missing build dependency.

### Front-end dependencies — REQUIRED

The project's `vite.config.js` imported `laravel-vite-plugin`, which was not
installed. **The asset build could not run at all before Module 2.** It is now
present at `^2` (the major compatible with the project's Vite 7).

```
npm ci
npm run build
```

If you skip this step the workspace page will fail with a Vite manifest error.
If the build fails with a peer-dependency conflict on `laravel-vite-plugin`,
install `laravel-vite-plugin@^2` — version 3 requires Vite 8.

### Dependencies added

| Package | Licence |
|---|---|
| `@tiptap/core`, `@tiptap/starter-kit`, `@tiptap/pm` | MIT |
| `@tiptap/extension-placeholder` | MIT |
| `@tiptap/extension-character-count` | MIT |
| `laravel-vite-plugin@^2` | MIT |

No licence obligation is added. No external service or infrastructure is
required.

### Migrations

Four additive migrations dated `2026_10_18_000012`–`000015`:
`manuscript_workspace_documents`, `manuscript_snapshots`, `manuscript_comments`,
`manuscript_tracked_changes`.

```
php artisan migrate --force
php artisan migrate:rollback --step=4 --force   # removes only these four
```

### Concurrency model

The workspace is **sequential editing with optimistic-concurrency detection**,
not real-time collaboration. Every save carries the revision it was made
against; a stale revision is rejected with HTTP 409 and both versions are
returned. Nothing is ever overwritten automatically, and only editorial staff
may force a save.

Do not describe this feature as real-time collaborative editing in any
user-facing documentation.

### Content Security Policy

**Resolved in the post-Module-5 amendments:** the CSP now defines
`default-src 'self'`, `script-src`, `style-src`, `font-src`, `img-src`,
`connect-src`, and `frame-src` (pinned by
`tests/Feature/Integration/CspHeaderTest.php`). It permits exactly the hosts
the Blade templates actually reference (`cdn.jsdelivr.net`, `unpkg.com`,
`fonts.bunny.net`) and `'unsafe-inline'` where the legacy templates rely on
it (10 inline `<script>` blocks, 15 inline handlers, 84 inline style
attributes — documented in the middleware; removing those is the next
tightening step, not silently blocking pages). Under `APP_ENV=local` the
policy is relaxed for the Vite dev server; production gets the strict
policy. The development environment is relaxed for `npm run dev`.

Visual verification of the CSP on production remains a go-live step (the
header is tested; browser rendering of all pages is a human check).

## Editorial Workflow and Role Permissions (Module 3)

### Permissions — RUN THE SEEDER FIRST

Module 3 introduces a permission catalogue attached to the six existing roles.
The authorisation layer falls back to role checks if the catalogue has never
been seeded, so no account is locked out either way, but the granular
permissions are only active once it is seeded:

```bash
php artisan db:seed --class=EditorialConfigurationSeeder --force
```

Idempotent; safe to run repeatedly. It also seeds the editorial lifecycle
stages and the default retention policy.

### Migrations

```
2026_10_18_000016_create_editorial_stages_tables
2026_10_18_000017_create_reviewer_invitations_table
2026_10_18_000018_create_workflow_events_and_retention_tables
```

All three are additive (create-only) and rollback to `--step=3`.

### Role boundary changes to be aware of

- The `publish` policy is now permission-based (`publication.publish`).
  **Admin keeps publish rights** (deferred decision J7). **Managing Editor no
  longer passes `publish`**; it coordinates the workflow instead.
- A Managing Editor **can** now assign reviewers, invite them, send reminders,
  manage revision rounds and move manuscripts through configured stages.
- Configuration screens (roles and permissions, style profiles, workflow
  stages, retention) reject everyone without the corresponding permission; the
  permission catalogue denies a Managing Editor those capabilities even if a
  forged POST asks for them (`PermissionCatalog::managingEditorProhibitions()`).
- Reviewers receive only blinded copies (`is_anonymised = true`) and never the
  original manuscript file.

### Rollback

Rolling the three migrations back removes the tables but does not affect any
existing behaviour in modules 0–2. The permission layer itself lives in the
Spatie tables and remains harmless if left in place.

## Editorial Workflow Completion (Module 4)

### Migrations

```
2026_10_19_000001_create_manuscript_proof_approvals_table
```

Additive (create-only); rollback with `migrate:rollback --step=1`.

### Publication readiness gate — behaviour change to be aware of

`PublicationService::publish()` now refuses to publish unless all of the
following are on record: vetting fee paid, an editorial acceptance (decision
row or accepted status), at least one submitted reviewer report, no open
revision round, copyediting completed, and **author proof approval**
(`manuscript_proof_approvals`). A refusal names each unmet requirement on
screen and writes a `publication_refused` workflow event. Admin publish
rights and the `publication.publish` permission are unchanged (Module 3).

### New author surface

`author/proofs/submissions/{submission}` — the corresponding author approves
proofs or requests corrections, only while the manuscript sits at the
`proof_approval` stage. The editor is notified through a new queued
`ProofApprovalNotification`; reminders use a new queued
`ReviewerReminderNotification`. **The queue worker must be running for mail
delivery** (finding S9 stays open until verified on production).

### Rollback

Rolling the migration back removes only the proof-approval table. The
readiness gate will then refuse to publish (no approval can exist), which is
the safe direction of failure.

## Module 5 — Release Readiness (Integration, QA, Deployment)

### 1. Pre-deployment environment verification

Run on the production server **before** deploying code:

```bash
php -v                       # must be PHP 8.2+
php -m | sort                # compare against docs/DEPENDENCIES.md ("PHP extension requirements")
composer check-platform-reqs # run in the project directory with composer.lock present
mysql --version
node --version && npm ci && npm run build   # asset build; see Module 2 section
```

Required PHP extensions (from the actual code paths in Modules 1–4):
`pdo_mysql`, `mbstring`, `openssl`, `curl`, `tokenizer`, `fileinfo`, `zip`,
`xml`, `xmlwriter`, `xmlreader`, `dom`, `simplexml`, `gd` (uploaded-image
handling), plus the standard `ctype`/`iconv`/`filter`/`hash` set.

⚠️ **This has been verified locally (Windows/XAMPP) only. Until it is run on
the production server, this remains an open item (audit finding S9-class:
"verified locally, not on production").**

### 2. Correct migration + seeding order

The full canonical order (Modules 1 and 3 summaries below are already correct;
this is the single consolidated sequence):

```bash
php artisan down
# database backup first — see "Backup procedure" below
php artisan migrate --force                              # all 16 additive migrations (11 + 4 + 1)
php artisan db:seed --class=ManuscriptStyleProfileSeeder --force    # idempotent
php artisan db:seed --class=EditorialConfigurationSeeder --force    # idempotent
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan storage:link
npm run build                                            # before `up` so the manifest exists
php artisan up
```

All migrations are **additive**: new tables or nullable columns only. No
existing table is dropped, no column is altered, and the `submissions.status`
ENUM is deliberately untouched. Existing records, uploads, accounts, and
payments are never modified by migration.

### 3. Queue worker — REQUIRED for mail

Module 3 and Module 4 queue three notification types
(`ReviewerInvitationNotification`, `ReviewerReminderNotification`,
`ProofApprovalNotification`); 28 notification classes implement `ShouldQueue`
overall. `QUEUE_CONNECTION=database` (the default) uses the `jobs` table,
which the framework migration already creates.

The worker must run as a supervised service, not a shell session, or
reviewer invitations and proof-approval mail will silently pile up:

```bash
# systemd unit (recommended)
[Service]
User=www-data
WorkingDirectory=/path/to/ojmas-journal
ExecStart=/usr/bin/php artisan queue:work --tries=3 --timeout=120 --max-time=3600
Restart=always
```

With no worker running, queued jobs accumulate in `jobs` and mail is **not
sent** — this is the safe direction of failure for data, but it breaks the
notification promise made to editors and reviewers. Verify after deploy:

```bash
php artisan queue:failed        # should show nothing new after test mail
php artisan tinker --execute="dispatch(new App\Jobs\HealthCheck?)"
```

(S9 remains open until the worker is confirmed running on the production
server.)

### 4. Backup procedure (run before every deploy)

```bash
# database — replace credentials accordingly; do NOT pipe through a web dir
mysqldump -u USER -p --single-transaction --routines ojmas_journal \
    > /root/backups/ojmas_$(date +%F_%H%M).sql

# uploaded manuscripts and derived artefacts
tar -czf /root/backups/ojmas_storage_$(date +%F_%H%M).tar.gz storage/app/private

# current code state (if deploying by git)
git stash list; git rev-parse HEAD > /root/backups/deployed_commit.txt
```

Keep backups outside the web root. The `--single-transaction` flag avoids
locking tables on InnoDB. Restore = re-import the dump, restore the tarball,
`php artisan optimize:clear`.

### 5. Rollback specifics per module

| Scope | Command | Effect |
|---|---|---|
| Module 4 only | `php artisan migrate:rollback --step=1 --force` | Drops `manuscript_proof_approvals`; the readiness gate then correctly refuses to publish (safe direction). |
| Module 3 only | `php artisan migrate:rollback --step=3 --force` | Drops stage/review-invitation/workflow-event/retention tables. Permission data in Spatie tables is untouched. |
| Module 2 only | `php artisan migrate:rollback --step=4 --force` | Drops workspace-document tables. |
| Module 1 only | `php artisan migrate:rollback --step=11 --force` | Drops manuscript tables. |

⚠️ Rollbacks destroy data **added** by those modules (workflow history, review
invitations, workspace snapshots). Always take the §4 backup first. Rollbacks
never touch pre-existing OJMAS data.

Application-code rollback: keep the previous release directory/repo state and
point the vhost back at it after `php artisan optimize:clear`.

### 6. Extended final smoke checks (editorial surface)

In addition to the pre-existing checks above, verify after deployment:

1. Log in as an author → open manuscript workspace → save an edit → confirm
   the revision counter increments and a snapshot appears.
2. Log in as Editor → open the same manuscript from the editorial workspace →
   confirm the author's edit is visible.
3. Confirm a fresh submission appears at the first configured lifecycle stage
   (or legacy status if the seeder has not run for that record's era).
4. As Managing Editor, invite a reviewer → confirm the invitation email
   arrives (requires the queue worker) and that no reviewer can access another
   reviewer's manuscript URL (expect 404).
5. As reviewer, download the blinded copy → confirm no author name appears
   anywhere in the document body.
6. Drive one test manuscript from submission to `proof_approval`, have the
   author approve proofs, then publish as Editor/Admin → confirm the
   readiness panel shows all six requirements green and the article appears
   in the issue.
7. Attempt to publish a *second* manuscript that lacks any of the six items →
   confirm the refusal names the missing items and a `publication_refused`
   workflow event exists.
8. Confirm `php artisan route:list` still parses all routes with no errors
   (156 routes at last local count).

### 7. Known limitations to carry into production (honest disclosure)

- **No automated DOCX→PDF.** Publishing does not produce a PDF; PDFs must be
  produced manually (or a toolchain approved later — earlier decision).
- **Sequential editing, not real-time collaboration.** Do not market the
  workspace as collaborative.
- CSP `default-src`/`script-src` **added after Module 5** (S6 resolved): the
  policy permits the CDNs the templates reference and `'unsafe-inline'` where
  legacy templates need it; tighten inline scripts later. Visual CSP check
  on production is part of go-live.
- Admin download substitute (S4) **fixed after Module 5** — mismatched
  file/submission pairs now 404 (test-pinned). Dead 2FA middleware
  (audit IDs **B4/S8**) **removed after Module 5**.
- Tailwind v3/v4 config mismatch: tracked in `docs/MODULE_0_AUDIT.md`; none
  block launch.

## Addendum Module 3 — Web Push Notifications

### Dependency

`minishlink/web-push` v11 (MIT, [docs/DEPENDENCIES.md](docs/DEPENDENCIES.md))
is an additive Composer dependency; `composer install --no-dev` covers it. It
needs `ext-curl`, `ext-json`, `ext-mbstring` and `ext-openssl` (PHP ≥ 8.2) —
all in the already-documented extension set; `gmp` is not required.

### Migrations

```
2026_10_21_000003_create_push_notification_tables
```

Additive: creates `push_subscriptions`, `push_notifications`,
`push_deliveries`, `push_category_settings` and seeds the ten notification
categories (§3.2 of the brief). Nothing existing is altered. Rollback:
`migrate:rollback --step=1 --force` — destroys only push records (subscription
consent is lost; nothing else is touched).

### Permissions — RE-RUN THE SEEDER (required, not optional)

On any deployment where the Spatie permission tables already exist, the Gate
resolves abilities through Spatie, so the new `push.view`, `push.manage` and
`push.configure` permissions must be created and granted explicitly. The
seeder is idempotent and additive:

```bash
php artisan db:seed --class=EditorialConfigurationSeeder --force
```

Created: the three permissions; Admin gains `push.view`/`push.manage`
(delivery-observing roles: Admin and Super Admin run the composer;
Super Admin alone configures category eligibility); Super Admin holds all
three. Until the seeder runs, the route-level `role:Super Admin|Admin`
middleware still enforces the same boundary, so nothing is left open or
locked out either way.

### VAPID keys — REQUIRED for delivery

Generate a pair **on the production server** (keys are deployment-local by
design; never copy keys between environments or store them in the repo):

```bash
php artisan push:keys          # prints the three .env lines
php artisan push:keys --write  # or appends them to .env directly (skips if a public key is already configured)
php artisan config:cache       # after any .env change
```

Without keys nothing breaks: the composer works (drafts, preview,
category eligibility) but honestly reports that nothing can be delivered;
`push-subscribe.js` degrades and points readers at the Calls for Papers page.
The private key stays in `.env` on the host and is rendered nowhere; only the
public key reaches the browser.

### HTTPS and the service worker

- Production subscriptions are accepted only over secure origins
  (`PUSH_REQUIRE_HTTPS=true`, the default; local testing is exempt).
- The service worker is served at `/service-worker.js` with root scope from
  `public/` — the DocumentRoot=public/ setup deploys it with no extra step.
- CSP compatibility (checked 2026-10-22): `default-src 'self'` permits the
  same-origin worker registration and the same-origin `/push/*` fetches; no
  policy change was needed.

### Cron — REQUIRED for scheduled/due notifications

`push:dispatch` stages and sends due notifications (draft→scheduled→sending→
sent/failed; expired notifications stop automatically). Register it through
the scheduler or as a direct cron entry — behaviour is identical, never
install both:

```bash
# via the Laravel scheduler (also covers future scheduled tasks)
* * * * * cd /path/to/ojmas-journal && php artisan schedule:run >> storage/logs/scheduler.log 2>&1
# or directly
* * * * * cd /path/to/ojmas-journal && php artisan push:dispatch >> storage/logs/push-dispatch.log 2>&1
```

Full sample (with a systemd-less queue-worker fallback):
[deploy/cron/ojmas.crontab.example](deploy/cron/ojmas.crontab.example).
Without cron, only manual "Send now" works; scheduled notifications simply
wait — the safe direction of failure. Duplicate staging is database-guaranteed
(one delivery row per notification+subscription, unique pair), so retries and
restarts cannot double-send.

### Queue

`DispatchPushNotification` runs through the queue when a real driver is
configured (see §3 Module 5 — the worker is already required for mail); with
the `sync` driver it runs inline. Either way the *next cron minute* resumes
any interrupted send. No new failure mode is introduced.

### Verification after deployment

1. Fresh browser → footer "Subscribe to Journal Updates" → one permission
   prompt on explicit click → subscription recorded.
2. Compose a notification as Admin → "Send now" → the delivery report shows
   HTTP acceptance/failure counts verbatim, per endpoint.
3. The notification arrives in the browser; clicking opens only the recorded
   http(s) destination.
4. "Unsubscribe" in the footer disables the record (never deletes).
5. Negative check: with keys intentionally absent in a staging copy, confirm
   the composer refuses to claim delivery.

Delivery honesty: the report screens state that only HTTP acceptance is
observable — there are no read/click counts in this system.

## Corrective Module 3 — Manuscript Media (images)

**Migration**: `2026_10_23_000001_create_manuscript_media_table` — one new
table, strictly additive, nothing altered or dropped elsewhere. Run after the
§4 backup; reruns are harmless.

**Before deploying**:

1. Take the §4 database backup (mysqldump) + storage tarball.
2. **GD must be enabled on the production PHP** (`extension=gd` in php.ini /
   `php-gd` package). Extraction itself works without GD, but probed pixel
   dimensions fall back to the DOCX-declared extents; enable GD for exact
   sizing of extracted figures.
3. Disk space: each manuscript's embedded media is COPIED once into the
   private disk under `manuscripts/media/<submission>/<version>/`. The
   ORIGINAL uploaded package is never modified and never re-written.

**Rollback** (corrective-module-3 only):

```bash
php artisan migrate:rollback --step=1 --force   # drops ONLY manuscript_media
```

Extracted media files remain on disk under `manuscripts/media/` until an
operator deletes them deliberately; rolling the table back makes editor image
display and figure export unavailable for workspaces whose content was
imported after this module (older content never contained image nodes). No
existing records are touched in either direction.

**Where media rows come from**: extraction runs at workspace import and
lazily on the first open of pre-existing workspaces; it is idempotent — one
row per (version, media part name) — so repeated saves or re-opens never
duplicate extraction.

**Image serving**: `/workspace/submissions/{id}/media/{media}` (route
`workspace.media.show`) is authenticated and authorised through the SAME
`openWorkspace` policy check as the workspace itself (own-submission author,
assigned reviewers, editors/managing editors/admins), returns 404 on
cross-submission id guessing, and serves `Content-Disposition: inline` —
never from a publicly guessable path.

## Corrective Module 5 — APA 7 formatting safety (footnotes + captions)

**No migration, no new dependency, no queue/cron change.** Code-only, additive:

1. **Footnotes/endnotes now survive APA formatting.** The formatted derivative
   carries `word/footnotes.xml` / `word/endnotes.xml` byte-for-byte from the
   original and re-emits every in-text anchor as a real Word reference marker.
   Previously the APA output silently lost note bodies and anchors while the
   validation report still claimed preservation. Old formatted documents are NOT
   rewritten silently — the fix applies to formatting runs executed after deploy.
2. **Table/figure captions and note lines are no longer auto-indented.** Paragraphs
   shaped like "Table N.", "Figure N." or "Note. …" are left exactly as authored and
   flagged in the validation report (`layout.caption_left_for_review`, severity
   info) instead of silently restyled. Body sentences starting "Table 1 reports…"
   are deliberately NOT treated as captions.

**Verification after deployment**: format one footnote-bearing manuscript and open
the formatted DOCX in Word — the note anchors must show superscript numbers and the
notes pane must list every note; check the report page shows the caption flags.

**Rollback**: revert the code commit; nothing persists from this module (no tables,
no storage layout changes; a re-run after rollback simply returns to the previous
without-notes behaviour).

---

## Corrective Modules 6–7 — lifecycle + permanent regression suites

**No migration, no new dependency, no queue/cron change.** Regression suite +
two targeted code fixes:

1. **Run-boundary spaces survive saves.** The framework's global string
   trimming ran over the Tiptap payload the browser sends to
   `workspace/save` and erased spaces at run boundaries (e.g. `x` + `2` +
   `" and "` collapsed into `x2and`) — every save of a formatted run
   corrected its whitespace. The endpoints carrying manuscript content
   (`workspace/submissions/*/save`, `workspace/submissions/*/changes`) are
   now exempt from trimming; everything else keeps standard behaviour.
2. **Unsupported embedded image formats are diagnosed.** A figure whose media
   part extension cannot be displayed (e.g. `.heic`) is preserved as before,
   but its editor node now additionally carries `unsupported` plus a
   `diagnostic` text so the problem is visible in the data, not only as a
   broken image frame.
3. **New permanent regression suite** `CorrectiveLifecycleRegressionTest`
   (Module 7 tests A–F and the Module 6 lifecycle round trip); five new
   representative fixtures. The suite fails loudly if the regression ever
   recurs.

**Verification after deployment**: no manual step beyond the standard suite;
optionally run the lifecycle test on the deployment environment
(`php artisan test tests/Feature/Workspace/CorrectiveLifecycleRegressionTest.php`).

**Rollback**: revert the code commit. The TrimStrings exemption disappears
with it (saves would once again trim run-boundary spaces) but no data,
schema or storage layout change is involved.

## Addendum Module 6 — Security Consolidation (no migration, no .env, no cron)

Code-only hardening and documentation; deploy it together with the addendum code:

- **Migrations**: none in this module (the `2026_10_21_*` series is unchanged; §
  "Rollback Notes" below covers the whole addendum).
- **Permissions**: unchanged; the seeder re-run rule still applies after migrating.
- **Behaviour changes** to be aware of:
  1. `route('issue.pdf')` now serves **published** issues only (404 for drafts) —
     the same boundary the public issue page always had.
  2. The public contact form is rate limited to 10 requests per minute per IP.
  3. Push subscription endpoint/keys are stored encrypted at rest and are never
     serialised in responses. If push ran on an earlier release that stored secrets
     in the clear, run the one-time migration once, right after deploying:
     `php artisan push:reencrypt-secrets --dry-run` (reports what would change),
     then again without `--dry-run` to encrypt. Values stored by newer code are
     always encrypted automatically. Verify after deployment with one real
     subscribe + test send on the target origin.

## Rollback Notes

- Keep a database backup before migration (see §4 for the exact commands)
- Keep the previous release available for fast rollback
- If deployment fails after cache changes, run:

```powershell
php artisan optimize:clear
```
