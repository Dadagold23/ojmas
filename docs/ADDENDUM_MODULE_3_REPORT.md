# OJMAS ENHANCEMENT — MODULE 3 IMPLEMENTATION REPORT

Module: Web Push Notification System (Addendum brief §3). Everything is
additive: new tables, new services, new screens; nothing existing was renamed,
re-typed or removed. Subscription records and campaign records are disabled or
archived — never deleted.

---

## 1. Library evaluation (§3.1)

Adopted **`minishlink/web-push` v11.0.0** (installed via Composer, MIT-licensed,
~4 small dependencies including Guzzle's PSR-7 stack). Reasons:

- Standards-based, server-side only: it implements VAPID (JWT ECDSA via
  `openssl` — installed; `gmp` optional and not present on this host),
  `aes128gcm` payload encryption, and plain HTTP dispatch to push services.
- **No third-party service, no externally-hosted component**: notifications go
  directly from this server to the browser vendors' endpoints (FCM / APNs /
  Mozilla). There is no per-message fee, no data sharing beyond the encrypted
  payload and the endpoint URL every Web Push implementation must use. Data
  handling stays entirely on-host.
- Laravel-agnostic; used through one service class (`PushDispatchService`),
  so a future library swap touches one file.
- One dependency is cryptographic (JWT signing) and one is HTTP dispatch —
  both below this system's existing HTTP surface, installed only after the
  inventory check in the Module 0 audit.

## 2. Data model (single migration `2026_10_21_000003_create_push_notification_tables`)

| Table | Purpose |
| --- | --- |
| `push_subscriptions` | One row per browser endpoint. `endpoint_hash` (sha256) unique index sidesteps every driver's URL-length limits. Consent flags: `wants_editorial` (the default a subscriber gets by opting in) and `wants_promotions` (**never default-on** — §3.6). `user_id` links authenticated subscribers only. Failures counted; `is_active=false` disables; never a delete. |
| `push_notifications` | Composed notification: title (≤120), message (≤300), one of the ten §3.2 categories, audience (all / editorial / promotional / authors / reviewers / editors), icon, http(s)-only destination, optional scheduled/expiry window, status machine, `ad_campaign_id` linkage for Module 4. |
| `push_deliveries` | Exactly one row per (notification, subscription) — `unique` pair is the duplicate-send guarantee. Records what is observable: accepted / failed / pending, attempts, timestamps, endpoint error text. |
| `push_category_settings` | The ten categories seeded with labels (§3.2); administrators toggle eligibility. An ineligible category can be composed and previewed but can never reach subscribers. |

## 3. Subscribing (§3.4 — consent first)

- A persistent, non-intrusive "Subscribe to Journal Updates" control in the
  page footer, present on public pages. Pressing it first explains the purpose
  and (for promotions) shows a separate tick box; **only then** is the
  browser's own permission dialog requested, once, from the explicit
  interaction. Denials are respected — the site never re-prompts.
- `public/service-worker.js`: standards-only worker. It shows what was pushed
  (title/body/destination from the encrypted payload), opens only the recorded
  http(s) destination on click, caches no HTML, executes nothing.
- `public/js/push-subscribe.js`: consent-first subscribe/unsubscribe flow with
  **honest degradation** — when Web Push, the service worker, HTTPS, or a
  configured VAPID key is unavailable, the control says so and points readers
  to the on-site announcements (Calls for Papers page) instead of pretending.
- Server endpoints: `/push/subscribe` (create-or-refresh, promotions never
  default-on, scheme-validated, throttled), `/push/unsubscribe` (disables the
  record, never deletes). VAPID **public** key only reaches the browser; the
  private key stays in the environment and is never rendered anywhere.
- Subscription state is read back from the browser, so returning visitors see
  their true state.

## 4. Composing and sending (§3.3, §3.6)

`/admin/push` (Admin + Super Admin; per-method gate checks, consistent with
Module 2's role boundaries):

- **Composer** (`auth.admin.push.create`): title/message/category/audience/
  destination/icon/optional linked advertising campaign/scheduled/expiry, with
  an inline "how it arrives" preview (the exact recorded content).
  Three explicit actions: *Save as draft*, *Schedule*, *Send now*.
- **Preview screen** renders the recorded content like a standard notification.
- **Notification page**: record, dispatch controls (immediate, scheduled,
  cancel, retry), delivery report, and the audit trail.
- **Category eligibility screen** — Super Admin only (`push.configure`);
  Admin holds view/manage. Every change is audited (`audit_logs`).
- Audiences: `all` = every active subscription; `editorial`/`promotions` =
  consent-flag holds; `authors`/`reviewers`/`editors` = authenticated
  subscribers with that role (`editors` includes Admin/Super Admin).
  The legacy `users.role` column is kept consistent with the Spatie role when
  targeting (factory was observed defaulting it to 'author' — fixture now
  syncs both, matching production usage).

## 5. Delivery honesty (§3.5)

- **Duplicate sends are impossible**: staging creates one delivery row per
  (notification, subscription) guarded by a database unique pair; re-staging
  and retries reuse it; already-accepted rows are never re-sent.
- **Retry limits**: each delivery retries at most `push.max_attempts` (3)
  across dispatch runs; the terminal status reflects recorded deliveries
  (accepted → sent; nothing accepted and something failed → failed; pending
  work → sending).
- **Invalid subscriptions are disabled, never deleted**: 404/410 or the
  library's expiry signal disables immediately; 5 consecutive generic failures
  disable (configurable). Re-subscribing the same endpoint re-enables it.
- **Nothing is claimed beyond observability**: Web Push exposes per-endpoint
  HTTP acceptance only. There are no "read" or "click" counts anywhere in the
  system, and the report screens say so explicitly.
- Delivery exceptions are logged with their real error text; the composer's
  immediate flow reports sent/failed/disabled counts verbatim.

## 6. Scheduling (§3.3 "scheduled publish/dispatch")

- Status machine: draft → scheduled → sending → sent / failed / cancelled.
- `push:dispatch` (artisan command) stages and dispatches due notifications;
  registered on Laravel's scheduler every minute (`schedule:run`) and
  simultaneously callable directly in cron (`php artisan push:dispatch`).
  Optional `expires_at` stops the scheduler after expiry. The same command
  resumes interrupted sends after a restart without duplicates.
- **Honest queue dependency**: the dispatch job is queued when a queue driver
  is configured; with the `sync` driver it runs inline. Either way the *next
  cron minute* is the fallback that resumes unfinished work — this is the
  documented answer to the audit's standing S9 item ("verify the production
  queue worker/cron on-host"), which remains open for Module 3's deployment.
- `push:keys` generates a VAPID pair and prints `.env` lines (optionally
  appends if absent). Keys are never hardcoded, never logged as data, and the
  composer honestly refuses to claim delivery when they are missing.

## 7. Verification

- New suite `tests/Feature/Addendum/Module3WebPushTest.php` — 17 tests,
  62 assertions: subscription consent semantics (promotions never default-on,
  resubscribe refreshes not duplicates), malformed-endpoint refusal,
  unsubscribe disables without deleting, audience targeting (role audiences
  hit only matching authenticated subscribers; anonymous subscribers never
  receive role pushes), delivery receipt honesty (201/410 split, disable on
  gone endpoints), retry limits with no duplicate sends, duplicate staging
  impossibility, scheduled-not-due isolation, queue handoff exactly once,
  role/permission boundaries (Author/Editor/Reviewer/Managing Editor → 403),
  composer validation (length limits, scheme refusal), ineligible categories
  never reaching subscribers, Super-Admin-only eligibility configuration,
  audit rows recorded.
- Full application suite re-run after all changes: see the verification line
  in the commit message (recorded honestly).
- Test-harness notes:
  - Dispatch tests drive a stubbed `WebPush` whose per-endpoint responses are
    controlled, so nothing here pretends a real FCM/Mozilla endpoint was
    reachable; real-network behaviour is exercised in production deployment.
  - The library's v11 `Subscription` constructor takes `(endpoint,
    publicKey, auth)` — the initial draft's array form was caught by tests
    (`TypeError`) and fixed.

## 8. Open items (flagged, not changed)

1. **Production prerequisites** (Module 0 audit's open item) — the
   deployment material is now prepared and documented in
   [DEPLOYMENT.md § "Addendum Module 3"](../DEPLOYMENT.md): the
   `PUSH_VAPID_*` block added to `.env.production.example`, VAPID key
   generation on the host (`php artisan push:keys`, required before any
   delivery — the composer honestly refuses delivery without them), the
   required seeder re-run for the new `push.*` permissions
   (`EditorialConfigurationSeeder`, idempotent), the cron entry
   (`deploy/cron/ojmas.crontab.example`; scheduler or direct, never both),
   and a CSP compatibility check (same-origin worker already permitted by
   `default-src 'self'`; no policy change). Still open until someone with
   server access runs them: the HTTPS origin confirmation, the key pair on
   the production `.env`, the cron installation, and the queue worker
   (standing item S9). Local development now has its own VAPID pair
   generated into `.env`, so the subscribe flow is testable on localhost.
2. Deliverability depends on the browser service — endpoints may reject at
   send time; that is exactly what the delivery report records.
3. `notify_push` on advertising campaigns (Module 2) is not yet auto-fed into
   the composer — the composer currently links a campaign manually. Module 4
   analytics will consume the `ad_campaign_id` linkage recorded here.

## 9. Completion pass (2026-10-27)

Gap-closing after the implementation-status review. No schema changes and no
new dependencies.

### Fixed

1. **Retry/resume now actually dispatches.** The scheduler skipped any
   notification whose delivery rows already existed, so the admin "Queue a
   retry" action and recovery after an interrupted send never sent anything.
   `push:dispatch` now distinguishes *staging* (new rows) from *retryable work*
   (pending rows and failed rows under `push.max_attempts`) and resumes the
   latter. This makes §6's resume claim true rather than aspirational.
2. **Truthful terminal status.** `PushDispatchService::settle()` derives
   `sent` / `failed` / `sending` from the stored deliveries instead of the
   current run's counters, so an exhausted retry can no longer relabel an
   all-failed notification as `sent`. `sent_at` is written only when the push
   service accepted at least one delivery.
3. **Admin retry restores the attempt budget.** Retrying a failed notification
   resets the attempts of its failed deliveries (accepted rows untouched; the
   audit row records how many were restored), so the button is not a no-op
   once the limit is reached.
4. **No stuck queue entries, no double-pick.** A notification with no eligible
   subscribers returns to `draft`; a notification whose attempts are spent is
   settled to its real terminal state. `last_dispatch_at` is stamped when a
   run starts or a job is handed to the queue, and only a `sending` row older
   than ten minutes is resumed — a concurrent cron pass cannot pick the same
   notification again, and a dead worker is recovered after that window.
5. **Subscription preferences are wired (§3.4/§3.6).** The previously dead
   `preferences()` action now has owner-only routes (`push.preferences`,
   `push.preferences.state`). The endpoint is the ownership proof; an anonymous
   row is claimed by the first signed-in account that manages it, and any other
   account receives 404 — never a hint that someone else's row exists. Signed-in
   subscribers get a Preferences panel in the existing push control; promotions
   remain opt-in and are never defaulted on.
6. **In-site announcement centre (§3.4 fallback).** `/announcements` lists sent,
   non-expired notifications for public audiences only (`all`, `editorial`,
   `promotions`); role-targeted internal notices never appear. It is linked from
   the footer and is what the unsupported-browser message now points to.

### Verification

- `tests/Feature/Addendum/Module3WebPushTest.php` — 23 tests, 98 assertions
  (6 new: cron resume, exhausted-send settling, no false "sent", admin retry
  budget, owner-only preferences with anonymous claim, public-only
  announcements).
- Full application suite: **OK (462 tests, 2401 assertions)**.
- Smoke check on the local app: `/announcements` returns 200 with the heading,
  footer link and push-control preferences URL rendered.

### Remaining limits

- The ten-minute stale window is the recovery bound if a queue worker dies
  mid-send; the next cron pass after the window resumes the delivery.
- The announcement centre mirrors pushed content. There is still no separate
  announcement authoring surface outside the push composer.
