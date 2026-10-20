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

The existing CSP (see the root `.htaccess` and `SecurityHeadersMiddleware`) has
no `default-src` or `script-src` directive. Module 2 adds a ~404 kB JavaScript
bundle but does not change the policy. **A deliberate, tested CSP should be
defined before the workspace is enabled in production.**

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
- **CSP finding S6 open:** the CSP still has no `default-src`/`script-src`.
  The Tiptap bundle is self-hosted, but a deliberate, tested policy should be
  added before or shortly after go-live.
- **Admin download substitute (S4), dead 2FA middleware (S12), Tailwind
  v3/v4 config mismatch**: tracked in `docs/MODULE_0_AUDIT.md`; none block
  launch, none were silently "fixed".

## Rollback Notes

- Keep a database backup before migration (see §4 for the exact commands)
- Keep the previous release available for fast rollback
- If deployment fails after cache changes, run:

```powershell
php artisan optimize:clear
```
