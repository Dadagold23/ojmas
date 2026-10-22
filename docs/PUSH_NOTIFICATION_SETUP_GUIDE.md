# OJMAS Push Notification Setup Guide

*Audience: the person who first sets web push up on a deployment, then the admins who
compose and send. Deployment-level detail (cron, worker, HTTPS) lives in
`DEPLOYMENT.md` under “Addendum Module 3 - Web Push Notifications”; this guide covers
the application-side setup and day-to-day use.*

## 1. One-time setup per deployment

1. **Generate VAPID keys** (required before anything can be delivered):
   ```bash
   php artisan push:keys
   ```
   Copy the printed lines into `.env` as `PUSH_VAPID_PUBLIC_KEY`,
   `PUSH_VAPID_PRIVATE_KEY`, `PUSH_VAPID_SUBJECT`. Without them, subscriptions are
   still accepted but dispatch reports the missing configuration instead of failing
   silently.
2. **HTTPS**: a browser will only grant a subscription on a secure origin (or
   `localhost`). The service worker `sw.js` is served same-origin from the public
   disk; the CSP already permits it.
3. **Scheduler and worker**: install the cron entry from
   `deploy/cron/ojmas.crontab.example` (`php artisan schedule:run` every minute) so
   `push:dispatch` runs every minute, and run a supervised `queue:work`
   (dispatch hand-off is queued).
4. **Verify** in production with a real browser: subscribe with consent toggles,
   check the subscriber record appears (keys stored encrypted at rest), then send a
   *test notification to a consenting test subscription only* — never a real public
   send during development (§6.3 of the addendum).

## 2. Categories and consent (the honest rules, pre-send)

- Ten categories exist (new volume/issue/article, upcoming issue, call for papers,
  deadline reminder, event, sponsored, advertisement campaign, journal notice). Each
  has an **eligibility** flag on *Categories* (Super Admin only, audited). Ineligible
  categories are refused for public dispatch — they cannot reach browsers.
- Subscribers choose **editorial updates** and **promotions** separately in the
  browser prompt; promotions are never default-on. Audience choice at compose time
  (`promotions`, `editorial`, `all`, or authenticated roles) is honour-matched to
  consent on the server; there is no “everyone” bypass.
- Where a Module 5 issue schedule asks for a push on an ineligible category, no
  queue entry is created at all — the schedule screen says why instead of failing
  silently.

## 3. Composing and sending

Composer (*Admin → Push Notifications → New notification*):

- Title ≤120 chars, message ≤300 chars (what browsers actually get), category,
  audience, optional destination URL (http(s) only — other schemes are refused),
  optional icon image. Validation is server-side on every attempt.
- **Send now** stages exactly one delivery row per consenting subscription and hands
  the notifications to the queue; **Schedule** sets a future dispatch time — the
  scheduler dispatches it once, when due. Duplicate staging is blocked at the
  database level (unique notification+subscription pair), so double-sends cannot
  happen even with two dispatchers.
- The delivery report shows **only what Web Push can actually observe**: per-endpoint
  HTTP acceptance (`sent`) or the endpoint's failure code (`failed`), with 410/404
  subscribers disabled for future sends. It never claims “opened” or “read”.

Pausing, cancelling (with a recorded reason), and retrying bounded-limited failed
dispatches are available from the notification page. Notifications already sent
cannot be recalled — the screens say so.

## 4. Module 5 integration

Issue schedules can queue one notification at the planned announcement time. The
notification is a normal Module 3 record: it appears in the composer, honours the
same consent rules, and pausing/resuming the schedule manages its queue entry
cancel/fresh-queue semantics (`docs/ADDENDUM_MODULE_5_REPORT.md`).

## 5. Data and privacy on record

- Stored per subscriber: endpoint URL, endpoint hash (lookup key), P-256dh/AUTH
  keys (**encrypted at rest** and excluded from serialisations — hardened in Module
  6), consent flags, failure counters, user agent string. No name, no email, no IP.
- Payload interchange is `aes128gcm`-encrypted end to end with browser push services;
  only the notification's own content and destination travel in the payload.
- Delivery records keep HTTP-observable results only.
