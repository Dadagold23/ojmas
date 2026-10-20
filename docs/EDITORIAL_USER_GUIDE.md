# OJMAS Editorial Operations Guide

Instructions for the administrator and editorial staff for the capabilities
introduced by the manuscript workspace, editorial workflow, and publication
readiness work (Modules 1–4). Public-facing formatting guidance for authors
lives in the APA 7 style documents linked from the submission area.

## Roles and who can do what

The authoritative source is the permission catalogue — see
**Admin → Configuration → Permissions** in the app. Summary:

| Capability | Author | Reviewer | Managing Editor | Editor | Super Admin |
|---|---|---|---|---|---|
| Submit / edit own manuscript workspace | ✔ | | | | |
| Complete vetting payment | ✔ | | | | |
| View editorial workspace of any manuscript | | | ✔ | ✔ | ✔ |
| Technical validation / editorial screening | | | ✔ | ✔ | ✔ |
| Assign reviewers / invite / remind | | | ✔ | ✔ | ✔ |
| Record decisions (accept / revise / reject) | | | | ✔ | ✔ |
| Open & close revision rounds | | | ✔ | ✔ | ✔ |
| Manage style profiles / workflow stages / retention | | | ✖ | ✖ | ✔ |
| Publish to an issue | | | ✖ | ✔ | ✔ |
| Approve proofs (own paper) | ✔ (at proof stage) | | | | |

Notes:

- **Managing Editor deliberately cannot publish.** It coordinates the
  workflow; publication is an Editor/Super Admin act. Do not "fix" this
  without a conscious policy decision.
- All of these boundaries are enforced server-side. Invisible buttons are
  cosmetic only; do not rely on hidden UI as an access control.

## The manuscript lifecycle

Submissions move through thirteen stages: submission received → technical
validation → editorial screening → reviewer assignment → peer review →
editorial decision → author revision → copyediting → proof approval →
published (or withdrawn / rejected / legacy paths). Every move is a
**workflow transition** by a staff member from the editorial workspace; there
are transition permission checks and each move records who, what, when, and
any note.

- **Reviewer assignment stage:** assign reviewers with due dates; send
  reminders from the assignment list. Reviewers see only a **blinded copy** —
  no author names — and submit confidential comments to the editor plus
  comments for the author.
- **Author revision stage:** opening the transition to revision creates a
  **revision round** with a deadline. Closing it ends the round. The author
  uploads revisions during the window.
- **Copyediting stage:** editors edit the manuscript in the browser
  workspace. The workspace is *sequential editing with change detection* —
  if two people have the page open, the second save is rejected with a
  conflict message rather than silently overwritten. **Do not open the same
  manuscript for simultaneous editing.**
- **Proof approval stage:** the corresponding author receives the final
  manuscript and either approves it or requests corrections. Only approval
  unlocks publication.
- **Publication:** press *Publish* in the editorial workspace and choose the
  issue.

## Publication readiness — what publish refuses

Before any publish, the system checks all six requirements and refuses with a
named list if any is missing:

1. Vetting fee paid
2. Editorial acceptance on record
3. At least one **submitted** reviewer report (drafts don't count)
4. No open revision round
5. Copyediting completed
6. Author proof approval recorded

A refusal is normal operation, not a bug — it also logs a `publication_refused`
workflow event so you can see why a manuscript didn't go out. Fix the named
items and publish again.

## Configuration screens (Super Admin)

- **Style profiles:** APA 7 formatting presets. Changing the active profile
  affects *new* formatting runs, never the accepted original files.
- **Workflow stages:** stage order, names, and legacy status mapping. Use
  with care — transitions between stages are what your staff rely on.
- **Retention policy:** how long workspace/format artefacts are kept. The
  *original* uploaded files are never deleted automatically.
- **Permissions:** view the catalogue. Editing the role matrix is a policy
  change; treat it like one.

The configuration seeder is idempotent; re-running
`php artisan db:seed --class=EditorialConfigurationSeeder --force` on upgrade
updates stages/permissions without touching manuscript data.

## Notifications

Invitations, reminders, and proof-approval notices are **queued** (email
mail). This means:

- A queue worker must be running on the server, otherwise mail does not go
  out (jobs pile up in the `jobs` table instead — nothing is lost).
- Timing is "soon", not instant. Deliverability depends on SMTP settings;
  set `MAIL_EHLO_DOMAIN`, and check `php artisan queue:failed` if mail seems
  missing.

## Data handling ground rules

- Original uploaded manuscripts are stored byte-for-byte and are never
  modified by formatting, editing, or publication. Formatting produces
  **derived** files; the SHA-256 of the original can always be checked.
- Reviewers must not be able to identify authors from the blinded copy. **Do
  not paste identifying information into reviewer-visible fields**, and do
  not send reviewers the original file — the anonymiser exists for a reason.
- Author identifiers (submission numbers, emails) remain stable across the
  whole lifecycle; never "renumber" a paper that changed stage.
- Unrecognised content in ingested documents is **flagged, not dropped** — if
  the formatting report shows preservation warnings, treat them as needing
  human review before acceptance.

## When something looks wrong

| Symptom | Likely cause | What to do |
|---|---|---|
| 409 conflict on workspace save | Someone else saved while you had the page open | Reload the workspace, review their revision, re-apply your change |
| "Cannot publish" with a checklist | Readiness gate (normal) | Complete the named items |
| No invitation/reminder email | Queue worker not running, or SMTP problem | Check the `jobs` table and `queue:failed`; verify mail settings |
| Reviewer sees 404 on a manuscript link | Wrong reviewer or revoked assignment | Verify the assignment; re-invite if needed |
| Formatting report lists warnings | Legal-but-unmapped OOXML constructs | Human review before accepting; content is never silently dropped |
