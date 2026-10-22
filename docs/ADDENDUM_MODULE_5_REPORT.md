# Addendum Module 5 — Automated Issue and Announcement Scheduling

**Status: complete (this report is the module's record; nothing else was shipped alongside it).**

Module 5 adds planning tools for upcoming volumes and issues: the journal records the
volume and issue numbers, the planned publication date, the call-for-papers dates, the
announcement date, a related landing page, an optional banner, and an optional push
notification. The system prepares and publishes **announcements only** — a scheduled
announcement never creates a published issue and never publishes unpublished articles.

## 1. What was built (and what it deliberately never does)

### 1.1 The schedule record (`issue_schedules` table)

One new table; nothing existing is altered. Every schedule anchors on a volume and an
issue, carries the dates the journal intends, and links the announcement surfaces:

| Column | Purpose |
| --- | --- |
| `volume_id` / `issue_id` | The planned volume/issue. One schedule per issue (`unique(issue_id)`) — no duplicates. |
| `title`, `message` | The public announcement text (message capped at 300 chars — the push payload limit). |
| `announcement_type` | `issue_announcement` or `call_for_papers`. |
| `status` | `draft \`| scheduled \`| paused \`| cancelled`. Cancelled plans are kept, never deleted. |
| `planned_publication_date`, `cfp_opens_at`, `submission_deadline` | The editorial dates, recorded exactly as entered. |
| `announce_at`, `announcement_ends_at` | The announcement's window. |
| `landing_url`, `banner_path` | The related landing page and optional promotional media. |
| `notify_push` | Whether a push notification should be queued at the announcement time. |
| `linked_campaign_id`, `linked_notification_id` | The actual announcement surfaces (one of each — no orphans, no double-sends). |

### 1.2 Existing records are reused; duplicates are impossible

* An existing volume is selected from the list, or a new volume number/year is recorded —
  an entered year+number combination that already exists is **matched, not duplicated**.
* An `issue_no` is entered per volume; an existing issue with that exact number is
  **reused**, otherwise a placeholder issue is created in `draft` with a clear
  “(planned)” title and a note that editors will complete it. Editors own the record.
* A schedule is refused (HTTP 422) when the target issue is already `published`, or its
  `published_on` date is already recorded, or the issue already has a schedule.

### 1.3 What scheduling actually prepares — and what it never touches

* **Campaign window:** scheduling creates one ad campaign of the matching type
  (`issue_announcement` on the sitewide `announcement_strip` placement; `call_for_papers`
  on the `calls_for_papers` placement), status `scheduled`, window
  `announce_at → announcement_ends_at`. Module 2's approved-status-plus-window logic
  serves and takes it down at exactly the recorded times — no cron dependency.
* **Push notification (optional):** when “queue a push” is set, one Module 3
  notification is created, category-mapped (`issue_announcement → upcoming_issue`,
  `call_for_papers → call_for_papers`), audience `all`, dispatch time `announce_at`.
  The existing `push:dispatch` command sends it exactly once when it comes due.
* **Never published by scheduling:** the placeholder/planned issue stays in `draft` with
  no publication date; no article is attached, moved, or published. Public issue pages
  render only what the editorial workflow published, as before.
* **Ineligible push category is reported, not failed silently:** if the category is not
  marked eligible on the push screens, no notification is queued, the announcement still
  runs, and the schedule screen states exactly why.

### 1.4 Delay handling: edit, pause, resume, cancel

* **Edit/reschedule:** dates propagate to surfaces that can still change — the campaign
  window and the queued notification's dispatch time move with the plan while they are
  `draft`/`scheduled`. An announcement already served (`published`, `paused`, `expired`,
  `archived`) keeps its recorded window, and a notification already `sent`/`sending`/
  `failed`/`cancelled` is never rewritten — the edit screen states these limits up front
  and the audit trail records which consequences applied.
* **Pause:** the campaign is paused (stops serving even inside its window) and the queued
  notification is cancelled — exactly the brief's “pause the announcement, or cancel
  queued notifications”. A notification that already left cannot be recalled; the screen
  says so in plain words.
* **Resume:** the window revives (Module 2 transition rules: future start → `scheduled`).
  A cancelled queue entry stays cancelled — verifiable history — and a **fresh**
  notification is scheduled in its place (a never-sent cancelled one cannot duplicate).
* **Cancel:** the campaign is archived and the queue entry cancelled. Records are
  archived/cancelled, never deleted; the schedule itself is kept and flagged.

### 1.5 Authorisation and audit

* New permission domain `scheduling` with `scheduling.view` (read the calendar and plans)
  and `scheduling.manage` (create/edit/pause/resume/cancel). Both granted additively to
  `Admin` and `Super Admin`; no other role holds them; no role changes.
* Scheduling routes are additionally gated to `role:Super Admin|Admin`.
* Every action is audited (`audit_logs`): schedule created/published/paused/resumed/
  cancelled, placeholder volume/issue creation, campaign creation, the ineligible-category
  skip, and queue cancellations with reasons. The schedule's own screen renders the trail.

### 1.6 Calendar view

The scheduler index renders a Monday-start month grid of **recorded dates only**, with
month navigation: planned CFP open/close, planned publication, announcement times,
queued push dispatches, and ad-campaign window starts/ends. Cancelled schedules leave
the grid; paused ones stay (their surfaces are paused but the dates remain on record).
Every chip is a date stored in the database — nothing is interpolated or inferred.

## 2. Files

* `database/migrations/2026_10_21_000005_create_issue_schedules_table.php` (one table)
* `app/Models/IssueSchedule.php` (statuses, announcement types, push-category mapping)
* `app/Services/Planning/IssueScheduleService.php` (plan/update/pause/resume/cancel/publish)
* `app/Services/Planning/ScheduleCalendarService.php` (month grid of recorded dates)
* `app/Http/Controllers/Admin/IssueScheduleController.php`
* Views: `resources/views/auth/admin/schedules/{index,create,edit,show,fields}.blade.php`
* `app/Http/...`: dashboard link (`admin/dashboard.blade.php`)
* `routes/web.php`: `admin.schedules.*`
* `app/Services/Permissions/PermissionCatalog.php`: `scheduling.view`, `scheduling.manage`
* `tests/Feature/Addendum/Module5IssueScheduleTest.php`
* This report

## 3. Production deployment notes

* **Migration:** `php artisan migrate` (additive; one new table).
* **Permissions:** re-run the idempotent `EditorialConfigurationSeeder` (additive, as with
  every new permission) so `scheduling.view` and `scheduling.manage` are seeded and synced.
* **No new cron, queue, or environment entries:** announcements run on Module 2 window
  serving; queued push notifications on the Module 3 `push:dispatch` cron and worker
  (see DEPLOYMENT.md as it stands).

## 4. Honest-features checklist (per the brief)

| Brief requirement | Delivered |
| --- | --- |
| Prepare upcoming issue announcements in advance | Schedule record with all §5 fields, saved as draft or scheduled |
| Volume number, issue number, planned publication date, CFP dates, announcement date, landing page, banner, notification campaign | All present; banner reused as campaign media and push icon; notification campaign is a real Module 3 notification bound 1:1 to the schedule |
| System may automatically prepare or publish approved announcements at configured times | Campaign window and scheduled push are prepared on scheduling and execute at their recorded times through existing serving/dispatch logic |
| A scheduled announcement must NOT auto-publish an issue or unpublished articles | Tests assert the issue stays `draft` with no publication date after announcement and dispatch; no code path sets issue/article status |
| Delay → change schedule, pause, cancel queued notifications | Edit/reschedule, pause (campaign paused + queue cancelled), resume (fresh queue entry), cancel (archived/cancelled) — with the already-sent caveat stated |
| Use existing issue records; no duplicate issue entries | Match-or-reuse on volume+issue; unique one-schedule-per-issue; duplicate plans refused with an explanation |
| Calendar or schedule view | Month grid of recorded dates plus the schedules table |

## 5. Test record

* Module 5 suite: `tests/Feature/Addendum/Module5IssueScheduleTest.php` — **24 tests, 130 assertions**.
* Full suite after the module: **397 passed, 1 skipped (GD image fixture), 1,840 assertions**.
* The skipped test is the long-standing GD-dependent fixture skip, unchanged.

## 6. Deliberate limitations

* One announcement surface set per schedule (one campaign window + at most one queued
  push). Multiple placements for the same announcement are a future need, not a guess.
* The push payload uses the schedule's message; the message length is capped at the
  push limit (300) so one text serves both surfaces honestly.
* The placeholder issue created for planning still needs editors to complete (title,
  description, articles). Scheduling never fills it, and says so on the record.
