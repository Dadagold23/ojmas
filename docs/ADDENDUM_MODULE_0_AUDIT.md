# OJMAS ENHANCEMENT — MODULE 0 AUDIT AND DESIGN

Scope: addendum prompt (`add_prompt.txt`) — enhanced archive & issue ToC, author/citation
attribution, advertisement & announcement manager, browser web-push notifications,
analytics, scheduling, integration QA. **Read-only audit; no source code or data
modified** (this document is the only file added).

---

## 1. Verified environment

- Local: Windows/XAMPP, Laravel 12.56, PHP 8.2.12, MySQL `ojmas_journal` @127.0.0.1.
- Production: https://ojmaspublish.online — **unverifiable from this machine**; every
  hosting claim below is flagged for on-host verification before its feature is trusted.
- Dev database content state verified read-only: **0 articles, 0 issues, 0 volumes,
  0 article_authors, 0 article_publications** — the enhancement must therefore be
  schema-capability-first; real content lives only on production.

## 2. Findings (the ten audit questions)

### 2.1 How articles are stored and published
- `articles` table: unique `submission_id`, `author_id` (account), `issue_id`, `title`,
  `slug` (unique, public URL key), `doi` (nullable, unique), `abstract` (NOT NULL),
  `keywords`, `subject_area`, `full_text`, `pages` (string), `published_on` (date),
  `status` ENUM('draft','published').
- `article_publications`: `published_by`, `published_on`, `published_pdf_path` (public
  disk), `note`. One per article.
- Publishing flows through `PublicationService::publish()` behind the Module 4
  readiness gate; administratively restored legacy forms were repointed to the
  pipeline (Oct 2026 amendment, MODULE_5 report §G). No path publishes an unpublished
  manuscript any more.

### 2.2 Volumes, issues, archive and article PDFs
- `volumes(title, slug, volume_no, year, description, status draft|published, is_active)`.
- `issues(volume_id, title, slug, issue_no, published_on, description, pdf_path,
  status draft|published)` — full-issue PDF exists (`issue.pdf` route) where uploaded.
- Public pages already exist: `/archives` (articles search + subject + year filters,
  12/page), `/issues` (search + volume + year filters, 12/page), `/issue/{id}` (ToC),
  `/article/{slug}` (+ `/pdf`, `/download`, `/preview`).
- **Gap: no archival cover images** — issues/volumes carry no `cover_image_path`.
- **Gap: no established article ordering** — `Issue::articles()` is a plain `hasMany`
  with no ordinal column; "journal's approved article ordering" cannot currently be
  represented or preserved. Module 1 needs an additive `articles.issue_order`.

### 2.3 How authors and co-authors are associated with articles
- The submitting account is `articles.author_id` via `submissions.author_id`.
- A normalized multi-author table **already exists**: `article_authors(submission_id,
  full_name, email, institution, country, is_corresponding, author_order, timestamps)`
  with `Submission::articleAuthors()` / `coAuthors()` relations.

### 2.4 Author order, affiliations, ORCID, DOI, page range, publication date
- Author order: `author_order` ✓ (schema).
- Affiliations: only a single `institution` string per author — no distinct
  affiliation/link association. Adequate for display; extend additively if richer
  attribution is needed later.
- ORCID: **missing entirely** (additive column needed); "multiple authors" is
  modelled, but — verified by grep and empty-table check — **nothing in the
  application ever writes to `article_authors`**: there is no capture UI and no
  publication-time snapshot. Author attribution is today effectively
  "the submitting account only".
- DOI ✓ (`articles.doi`, nullable); page range ✓ (`articles.pages` string);
  publication date ✓ (`articles.published_on` / `article_publications.published_on`).

### 2.5 How public archive and issue pages are generated
- Server-rendered Blade (`archives.blade.php`, `issues.blade.php`, `issue.blade.php`,
  `article.blade.php`) from `HomeController`, strictly status-gated to
  published-only material (both article status and issue status are checked, and
  unpublished slugs 404). Responsive Bootstrap-based markup already in place.
- **Gap: `issue.blade.php` ToC line 114 shows only
  `article->author->name`** — the submitting account — not the ordered full
  author list; the article page splits the submitting author and "Co-Authors" into
  separate boxes rather than one ordered byline.

### 2.6 Existing roles and permissions
- Six roles: Super Admin, Admin, Managing Editor, Editor, Reviewer, Author — Spatie
  `role:` middleware on route groups, plus a 58-permission catalog (Module 3 of the
  main project) with an idempotent seeder. New advertisement/notification surfaces
  should extend this catalog rather than invent a parallel mechanism.

### 2.7 File storage and PDF delivery
- Disks: `local`/`private` = `storage/app/private` (manuscripts, receipts — access
  controlled), `public` = `storage/app/public` via `/storage` (published article
  PDFs, issue PDF — **public by nature**, which is correct for published material),
  S3 configured but unused. Citations/PDF serving go through controllers, not bare
  file URLs, giving an audit seam.

### 2.8 Notifications, queues, scheduled tasks, email
- Fourteen Laravel notifications (mail + database channels), all mail-class based.
- `QUEUE_CONNECTION=database` — a `database` queue driver exists (jobs/batches
  tables present); production worker operation is **still unverified (S9 open)**.
- **No scheduler entries** (`routes/console.php` has only artisan commands) — Module 5
  scheduled announcements and Module 2 auto-expiry need the scheduler + worker
  verified on the host before anything can be promised.
- Email configuration exists (receipts etc.); delivery reliability is the operator's
  verified-on-host concern.

### 2.9 Hosting compatibility for web push, scheduling, media
- Web Push (Module 3) requirements: HTTPS run-time (verified only by looking at the
  production URL scheme), a service worker served from the site origin, **VAPID key
  pair** (generate at deploy; secrets in .env only — never in frontend code), and a
  maintained Laravel Web Push package (e.g. `laravel-notification-channels/webpush`)
  + PHP `mbstring`/OpenSSL (present in XAMPP; to be verified on host).
- Scheduled publishing (Modules 2/3/5): requires host cron/pulse or a supervisor
  queue worker — **same open item as S9; must be verified on-host first.**
- Media uploads (video/audio Module 2): bounded by PHP post/upload limits and disk
  capacity on host; validation will be type/size/extension based, and duration probing
  is unsafe to promise cheaply — size/type limits are the honest guarantee.

### 2.10 Safest database changes — proposal (all additive, non-destructive)
| Change | Type | Module |
| --- | --- | --- |
| `articles.issue_order` (nullable unsigned int) | add column | M1 |
| `articles.licence`, `articles.copyright_note` (nullable strings) | add columns | M1 |
| `article_authors.orcid` (nullable string 19-ish) | add column | M1 |
| `articles.cover`/`issues.cover_image_path`, `volumes.cover_image_path` | add columns | M1 |
| `ad_placements` (configurable placement registry) | new table | M2 |
| `ad_campaigns` (type, media_path, external_media_url, destination_url + label, placement, audience, status, priority, start/end, pricing/invoice fields, notes, `push_campaign_id` FK) | new table | M2 |
| `ad_events` (campaign_id, event impression|click, fingerprint hash, occurred_at) | new table | M2 |
| `push_subscriptions` (endpoint, p256dh, auth, user_id nullable, categories/consent flags, status active|failing|disabled, failure_count, device hint) | new table | M3 |
| `push_notifications` (title, body, category, icon/url, audience scope, send_at, expires_at, status draft|scheduled|sent|cancelled, dedupe batch id, linked campaign) | new table | M3 |
| `push_deliveries` (notification_id, subscription_id, attempt, result, recorded_at) | new table | M3 |
| `announcement_plans` (M5: volume/issue ref, dates, cfp/deadline, status, linked entities) | new table | M5 |
- Nothing existing is renamed, re-typed, or dropped; existing ENUM values untouched
  (same rule as the main project's `submissions.status`).

## 3. Implementation plan (module by module)

- **M1** Archive/issue ToC revamp + cover images + `issue_order` management UI + ordered byline
  everywhere + ORCID capture (author submission form + editor) + citation service
  (APA 7 deterministic, honest-degradation; BibTeX/RIS; copyable text) + meta/canonical/
  Scholar meta/Schema.org/sitemap. Reuses existing routes/slugs/URLs — established URLs preserved.
- **M2** `admin/advertisements` dashboard behind `role:Super Admin|Admin` + permission
  entries; placements registry; media upload with strict mime/size validation; date-window
  serving; labelled rendering partials on the (permitted) placements; calendar + performance basics.
- **M3** Web Push: VAPID config, service worker, opt-in subscribe/unsubscribe UI,
  subscriptions storage, composer + queued delivery with dedupe + failure cleanup;
  in-site fallback for unsupported browsers.
- **M4** Impression/click counting definitions + reports + optional invoice tracking.
- **M5** Issue/announcement scheduling: plans, calendar, publish-time automation that
  **never** auto-publishes issues/articles — announcements only.
- **M6** Security & QA sweep + integration tests + deployment guide.

## 4. Security assessment (standing rules carried forward)

- Server-side authorization everywhere; public pages remain strict published-only.
- Ad media uploads: MIME/extension allow-list, size cap, hashed names under
  `storage/app/public/ad-media/`; **never** render ad-supplied HTML/JS — controlled
  templates only.
- Push endpoints: rate-limited subscribe/unsubscribe/click-beacon endpoints; the
  subscription endpoint is stored but never exposed verbatim in public responses;
  IDOR-safe by design (anonymous tokens).
- Finance (M4): no auto-charging, no invented payment records; Paystack untouched.
- Audit trail on all campaign/notification mutations; delivery statuses only for
  events genuinely observed.

## 5. Acceptance tests to be pinned (M1 acceptance derived from brief)

1. Issue ToC shows exactly its published articles, in the editorial (reorderable) sequence.
2. Byline: full ordered authors with affiliations on archive, ToC, article page,
   generated citations — consistent across all four.
3. `article/{slug}` URLs unchanged; PDFs serve/preview/download (no new exception rules).
4. Citations: APA 7 correct from real metadata; missing DOI/pages ⇒ stated, never invented;
   BibTeX/RIS downloads valid.
5. Unpublished/draft articles and issues remain publicly inaccessible.
6. Archive: year/volume/issue filters + pagination + responsive layout.
7. Whole existing regression suite (303 tests) stays green.

## 6. Open items this module cannot verify locally

1. Production HTTPS/service-worker/VAPID feasibility (Module 3 deploy prerequisite).
2. Production queue worker + cron for scheduling (the standing S9 item).
3. PHP upload/post limits for video/audio on production host.
4. Whether real co-author metadata exists anywhere on production to backfill
   `article_authors`, or attribution must be seeded forward-only (no fabricated data).
