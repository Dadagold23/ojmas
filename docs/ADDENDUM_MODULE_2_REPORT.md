# OJMAS ENHANCEMENT — MODULE 2 IMPLEMENTATION REPORT

Module: Advertisement and Public Announcement Management (Addendum brief §2).
Everything is additive; nothing existing was renamed, re-typed or removed, and
campaign records are never deleted — only archived.

A separate, user-requested editorial addition delivered in the same batch is
described in §4 (the author proof-approval gate).

---

## 1. Data model (single migration `2026_10_21_000002_create_advertising_tables`)

| Table | Purpose |
| --- | --- |
| `ad_placements` | The named slots public pages can render (7 seeded: homepage banner, archive banner, issue promo, article banner, announcement strip, Calls for Papers, sponsored section). Deactivation hides a slot everywhere; never deletion. |
| `ad_campaigns` | One campaign record: type (image / video / audio / banner / call_for_papers / issue announcement / publication-date announcement / event / sponsored / news), advertiser, media file or external media URL, destination URL, start and end window, status machine, priority, audience, public label, optional pricing/sponsorship note, internal notes, push-eligibility flag (Module 3 handoff), `push_campaign_id` reserved. |
| `ad_events` | Impressions and clicks, deduplicated per visitor fingerprint per day (`unique(campaign_id, event, fingerprint, occurred_on)`). The fingerprint is a salted SHA-256 of IP + user agent + date — it counts unique daily visitors but cannot be reversed and stops matching after the day ends. |

## 2. Serving rules (enforced by `AdCampaignService`, not by the UI)

- **Window-based scheduling and expiry.** A campaign is public when its status
  is approved (`published`/`scheduled`) **and** the recorded window is open.
  Expired campaigns therefore stop appearing automatically with no manual
  deletion step and no dependence on an unverified cron worker; `scheduled`
  campaigns appear exactly when their start time passes.
- **Priority ordering** inside a placement (`priority` desc, then id).
- **Honest counting.** Impressions are recorded server-side when a placement
  renders (one row per fingerprint per day); clicks go through
  `/ads/{campaign}/click` (throttled) which re-validates the destination
  scheme and only then redirects. No JavaScript beacons.
- Placements can be deactivated by Super Admin — the slot goes silent on every
  page immediately (pinned in tests).

## 3. Management dashboard (`/admin/advertisements`, Admin + Super Admin only)

- Campaign listing with search and status/type/placement filters, a live
  status-count card row, status badges ("live" indicator when actually
  serving), media previews and one-click open.
- **Scheduled-campaign calendar** strip of every recorded window.
- Create / edit forms: all brief §2.3 fields — media upload (typed by
  campaign: images ≤8 MB jpg/png/webp/gif, video ≤50 MB mp4/webm/ogv, audio
  ≤20 MB mp3/wav/ogg/m4a; `mimes` + `mimetypes` content checks; hashed storage
  under `ad-media/`), external media URL, destination URL, window, priority,
  audience, public label, pricing note, internal notes, push flag.
  *"Save as draft"* vs *"Save & approve"* are two distinct submit buttons
  (approved saves resolve to `scheduled`/`published`/`expired` by window).
- Campaign page: public preview rendered by the very partial readers see,
  record details, **server-validated status transitions** (draft, pending,
  scheduled, published, paused, expired, archived; illegal moves → 422),
  and **basic performance reporting**: impressions, clicks, CTR and a
  per-day last-30 table.
- **Roles follow the brief, not the disclaimer**: Super Admin holds all four
  advertising permissions; Admin gets view/manage/reports; **placement
  configuration (`advertising.configure`) is Super Admin only**. Managing
  Editor, Editor, Author, Reviewer get none. Every controller method calls
  the gate directly, so the checks hold even if the interface were
  circumvented (pinned: an Admin's POST to the placement endpoint is
  rejected 403 and nothing changes).

## 4. Public surfaces (all mobile-responsive, never obscuring article content)

- Slots on the homepage (banner + announcement strip inside the hero), archive
  page banner, issue page promo column, article page banner — placed in
  content columns, never as overlays or popups.
- Every paid/sponsored item carries its configurable label publicly
  ("Advertisement", "Sponsored", "Announcement", …) plus advertiser name.
- Media renders only through controlled templates (`resources/views/ads/render.blade.php`):
  `<img>` / `<video>` / `<audio>` from uploaded files or http(s)-validated
  external URLs — no untrusted markup, no script tags; destination links are
  counted redirects with rel="nofollow sponsored noopener".
- A **dedicated Calls for Papers page** (`/calls-for-papers`, linked in the
  navigation) lists current calls, upcoming issue/date announcements and
  sponsored content, all from recorded campaigns only.
- Advertising respects `audience` fields (public / authors / reviewers /
  editors) at the data level; Module 3's push composer will read
  `notify_push`.

## 5. Deliverable: author proof-approval gate (user request, same batch)

The user's mid-module request — "create Author Proof Approval: verify (1)
peer review concluded = True/False, (2) copyediting completed = True/False;
allow workspace for the corresponding author" — was implemented as a change
to the existing §4.11 flow:

- `EditorialWorkflowService::proofGateChecks()` computes both checks from the
  submission's **real stage position** (never from submitted data): review
  concluded = past the editorial-decision point while not cycling through any
  review stage; copyediting completed = at proofing or beyond.
- The author proofs page (`/author/proofs/...`) shows both checks as literal
  True/False badges. While either is False the decision form renders inside a
  disabled fieldset (visibly locked) and the POST endpoint refuses with an
  explicit message naming which check is False — **no approval row can ever
  be written while the gate is not satisfied** (pinned with a manipulated
  stage table).
- Workspace access stays corresponding-author-only, enforced by the existing
  `approveProofs` policy which additionally requires the `proof_approval`
  stage (strangers → 403, guests → login redirect — pinned).

## 6. Verification

- New suites:
  - `tests/Feature/Addendum/Module2AdvertisingTest.php` — 20 tests: authorisation
    boundaries (guest/author/editor/managing-editor denied), placement
    deactivation, campaign creation with real image media (public disk,
    asserted), URL-scheme refusal (`javascript:`/`data:`), PDF-as-image
    refusal (simulated like a real browser), draft/expired/scheduled window
    behaviour, impression and click dedupe, redirect honesty, transition
    machine, archive-never-delete, Calls for Papers page, issue/article slot
    rendering, performance screen.
  - `tests/Feature/Addendum/ProofApprovalGateTest.php` — 7 tests around the
    proof gate (see §5).
- Full application suite re-run after all changes: **see the closing line of
  this report's verification section in the commit message** (recorded
  honestly; nothing was counted as passing that did not run).
- Engineering note discovered and respected: Blade directives must not be
  written adjacent with no whitespace (`@endif@if…`) on this environment —
  all new templates keep directives on their own lines (this bit Module 1
  and one module-2 template during development; both fixed).
- Test-harness note: Laravel's fake uploads take their MIME from the
  filename, so the PDF-refusal test simulates a genuine browser upload
  (real `UploadedFile` declaring `application/pdf` with real PDF bytes)
  rather than pretending `fake()` proved content sniffing. In production,
  `mimetypes:` checks the real temp file's sniffed MIME.

## 7. Open items (flagged, not changed)

1. The audience field is stored and surfaced but public pages currently
   render to all visitors; audience-targeted serving (e.g. hide public
   campaigns from into workspaces) is deferred for module-signoff discussion
   unless wanted now.
2. Campaign records are additive but `push_campaign_id` is reserved only —
   Module 3 will link it.
3. The web-push scheduling calendar is a window strip inside the manager; a
   month-grid calendar view can be added on request.
4. `JOURNAL_*` / VAPID / queue-worker notes from the Module 0 audit remain
   relevant to Module 3.
