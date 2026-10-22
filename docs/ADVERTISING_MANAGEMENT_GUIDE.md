# OJMAS Advertisement & Announcement Management Guide

*Audience: Admins and Super Admins running the advertising manager (Addendum Module 2,
extended by Modules 3–6). Every rule stated here is enforced server-side, not just in
the interface.*

## 1. Who may do what

| Capability | Permission | Who holds it |
| --- | --- | --- |
| See the screens and campaign records | `advertising.view` | Admin, Super Admin |
| Create/edit campaigns and change status | `advertising.manage` | Admin, Super Admin |
| See and edit revenue/payment fields, open reports | `advertising.reports` | Admin, Super Admin |
| Rename/activate/deactivate placements | `advertising.configure` | Super Admin only |

Non-administration roles hold none of these and are refused on the server for every
request. Campaign revenue fields are a separate boundary from campaign management:
an `advertising.manage` user without `advertising.reports` can edit everything except
recorded money, and no non-financial edit ever wipes recorded money.

## 2. The campaign lifecycle

Statuses: `draft → pending → scheduled → published → paused/archived`, with `expired`
when a recorded window ends. Transitions are enforced by `AdCampaignService` — an
illegal move (e.g. `archived → published`) is refused by the server.

- **Creating**: Ad manages → *New campaign*. Fill title, type, placement, audience,
  label, window (starts/ends), destination URL, optional media, optional pricing.
  Saving keeps it in your chosen state; *Publish* sets `scheduled` when the window has
  not opened yet — the campaign becomes live the moment its window opens, with no
  worker dependency — and `published` if the window is open now.
- **Windows decide visibility.** A campaign is shown only when
  (status is `published` or `scheduled`) **and** its window is open. Expiry is checked
  at serve time, so a campaign closes itself on its recorded `ends_at` — nothing needs
  cron for expiry.
- **Pause / resume / archive** from the campaign page via the status buttons. Records
  are archived, **never deleted**.
- **Priority** (0–1000 ordering) and **audience** are recorded; the public render is
  placement-wide — see known limitations of the addendum reports.

## 3. Public placements

Seven slots exist (rename or deactivate under *Placement configuration*):
homepage banner, archive banner, issue-page promo, article banner, announcement strip,
Calls for Papers page, sponsored section. Deactivating a placement hides its slot
everywhere; it is audited to `audit_logs`.

Each campaign is rendered **only through `ads/render.blade.php`**: uploaded media or
the approved external URL are the only things embedded, every item carries its public
label, descriptions are stripped of tags before display, and clicks go through
`/ads/{campaign}/click` (throttled, nofollow-sponsored) — never directly to the target.

## 4. Counting (what the numbers mean)

- **An impression** is a rendered campaign slot for a visitor, deduplicated to one per
  visitor fingerprint per day (`ad_events`). The fingerprint is a salted SHA-256 of
  IP+user-agent+date that stops matching after the day ends — unique daily visitor
  counts without personal tracking.
- **A click** is exactly one click event per visitor per day through the click route.
- Nothing is interpolated: the reports only add events actually recorded in the
  database.

## 5. Revenue (Module 4)

On the campaign form, the revenue block records: `agreed_price`, currency,
agreement reference, invoice reference, payment status (`agreed` → `invoiced` → `paid`)
and `paid_at`. The report screen (`advertising.reports` gate) summarises campaigns by
status and records:

- **Agreed revenue** counts campaigns in `scheduled|published|paused|expired` — drafts
  and archived records are openly excluded.
- **Collected revenue** is **only** records whose payment status is `paid`, by
  `paid_at` date. An invoice awaiting payment is shown as *invoiced*, never as money
  received.
- Window summaries and payment-status breakdowns are recorded-state only.

## 6. Announcements (Module 5 integration)

Issue announcements and Calls for Papers can be prepared on the
*Issue & announcement scheduling* screen instead of as plain campaigns. Scheduling
there creates exactly one campaign window (on the announcement strip or the Calls for
Papers placement) and optionally one queued push notification, and it never publishes
the underlying issue — see `docs/ADDENDUM_MODULE_5_REPORT.md`.

## 7. Quick do / don't

- Do: set an honest `ends_at`; use the *Revenue* fields only under `advertising.reports`;
  pause before editing an announcement that is already live.
- Don't: delete records (you can't — archive instead); email media files manually
  (upload through the form — video/audio/image per type are validated server-side);
  promise revenue the report does not corroborate.
