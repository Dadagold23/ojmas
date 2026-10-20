# Module 4 Implementation Report — Editorial Workflow and Version Management

**Date:** 20 October 2026
**Status:** Complete. Full suite at time of report: **284 passed (1227 assertions), 0 failed, 0 skipped.**
**Scope:** `prompt.txt` Module 4. Built on the Module 3 permission/lifecycle layer; additive only.

---

## 1. What Module 4 required and where it stands

The 12-stage configurable lifecycle itself was delivered in Module 3
(`EditorialWorkflowService`, 13 stages / 15 transitions as seeded data). Module 4
completed the remaining workflow management items and closed the publication
integrity requirement that the brief states explicitly:

> "Do not allow a manuscript to be published simply because an editor has
> finished editing it. Publication must follow the required editorial approvals."

### 1.1 Publication readiness gate — the new hard control

`App\Services\Publishing\PublicationReadinessService` assesses a submission
against six named requirements and reports each one's state constructively:

1. **Vetting fee settled** (the journal's own payment record).
2. **Editorial decision recorded** — acceptance exists as a decision row, or
   the submission has reached accepted status through the lifecycle.
3. **Peer review concluded** — at least one *submitted* reviewer report
   (drafts and withdrawals never count, consistent with the decision workflow).
4. **Revision rounds closed** — an author cannot be published while still
   owing an answer.
5. **Copyediting completed** — read from the stage record, not from what a
   screen happened to claim.
6. **Author proof approval** — `manuscript_proof_approvals` row with
   `status = approved`.

The gate is wired **inside `PublicationService::publish()`**, so *both* publish
paths (the editor tool and the admin publication screen) inherit it. A refused
publish attempt records a `publication_refused` workflow event naming every
unmet requirement and throws a validation error that lands on the operator's
screen as specific sentences, not a bare failure. Tested: refusal with a
workflow log entry and zero articles written; success publishing once every
approval is real (article created, submission `published`, identifier stable).

### 1.2 Author proof approval (§4.11)

- New table `manuscript_proof_approvals` (migration
  `2026_10_19_000001_create_manuscript_proof_approvals_table.php`, additive,
  rollback-verified) with submission, round, author, status
  (`approved` / `corrections_requested`), notes, timestamp and IP.
- `App\Http\Controllers\Author\ProofApprovalController` + view
  `resources/views/author/proofs/show.blade.php`: the corresponding author
  reads the proofs, then either approves or requests corrections.
- Policy door `SubmissionPolicy::approveProofs`: owner only, **and only at the
  `proof_approval` stage**. Editors cannot sign on the author's behalf; other
  authors get 403/404 (never 200 with content).
- Approvals and correction requests both land in the workflow log; the
  assigned editor is told through a new queued `ProofApprovalNotification`
  (mail + database, existing architecture).
- A correction request is visibly **not** an approval — the gate keeps the
  requirement unmet (tested).

### 1.3 Decision history made honest (bug fixed)

`EditorialWorkflowService::transition()` wrote
`old_status == new_status` in every `SubmissionStatusHistory` row because the
"old" value was read *after* the mutation. Fixed: the pre-change status is now
captured first. Two regression tests pin it: a stage move that changes the
legacy ENUM records both values truthfully, and a stage move that leaves the
ENUM unchanged records that too.

### 1.4 Revision-round tracking driven by the stage flags (§4.27)

Transitions whose configuration says they open or close the revision window now
act on it:

- **`opens_revision_window`** (→ author revision): increments
  `submissions.revision_round`, records the deadline on the submission, and
  creates the open `RevisionRound` — identical bookkeeping to the decision
  endpoint, so both paths stay consistent.
- **`closes_revision_window`** (→ copyediting and friends): closes any round
  still awaiting the author, with `closed_at`.
- Round metadata (number, open/close) goes into the workflow event.

### 1.5 Author notifications on stage transitions (§4.32)

`EditorialWorkflowService` now honours the `notifies_author` flag: entering a
stage whose legacy status is `revision_requested` / `accepted` / `rejected`
queues `RevisionRequestedNotification` / `SubmissionAcceptedNotification` /
`SubmissionRejectedNotification` to the author — the same classes the decision
endpoint already uses, sent **after commit** so mail trouble cannot roll the
stage change back. Transitions that say nothing author-facing send nothing
(verified with `Notification::assertNothingSentTo`).

### 1.6 Reviewer deadlines and reminders (§4.30)

Deadlines were already tracked per assignment and per revision round. The
reminder now also **sends**: `remind` records the counting trail (unchanged)
and queues a new `ReviewerReminderNotification` (mail + database, queued) to
the reviewer, in step with the existing notification architecture.

### 1.7 Role dashboards, status transitions, activity logs, version tools

Already standing from Modules 2–3 and unchanged by this module: the author,
reviewer, editor and Managing-Editor dashboards read the same stage/round data
this module feeds; version comparison and previous-version recovery remain the
Module 2 workspace's job and are exercised from the author revision screens
(no second implementation invented here).

---

## 2. Bugs found and fixed during Module 4

| # | Where | Bug | Fix |
|---|-------|-----|-----|
| 1 | `EditorialWorkflowService::transition()` | `SubmissionStatusHistory` rows recorded `old_status == new_status` (read after mutation) — the decision history was systematically blind | capture the pre-change status first; regression-tested both ways |
| 2 | `author/proofs/show.blade.php` | same Blade glue trap as Module 3 (`copy@endif` ignored as a directive → 500) | wrapper element around the text |

## 3. Deliberate decisions (recorded, not silent)

1. **The gate is in the service, not the controllers.** Two publish entry
   points existed; guarding both screens would be telling, not enforcing.
2. **Readiness items are named, not boolean.** An operator told "fix it"
   guesses; an operator told exactly which approvals are missing acts.
3. **No second workflow state.** The brief's stage names map onto the Module 3
   catalogue; nothing new was invented beyond the author-approval record.
4. **Refusals are logged.** A `publication_refused` event means an attempted
   shortcut leaves an auditable trace.

## 4. Test inventory (new in Module 4)

- `tests/Feature/Publication/ReadinessGateTest.php` — 5: fresh submission not
  ready; refusal lists unmet approvals and writes the workflow event without
  publishing; publication succeeds exactly when every approval is real; an
  open revision round blocks even a stage-conformant paper; copyediting
  completion detected from the stage history.
- `tests/Feature/Publication/ProofApprovalTest.php` — 5: proof screen at the
  right stage; approval recorded + editor notified + log entry; correction
  request recorded and keeps the gate unmet; signing forbidden at any other
  stage; strangers and editors cannot sign (no content, nothing written).
- `tests/Feature/Workflow/TransitionBookkeepingTest.php` — 6: honest
  `old_status`/`new_status` (both directions); entering author revision opens
  round + deadline; entering copyediting closes the round; author notified
  only where the transition says so (verified by asserting nothing sent);
  `published-by-stage-move` still requires publish rights.
- `tests/Feature/Publication/LifecycleJourneyTest.php` — 2: the full journey
  through the real services (submission → fee → validation → screening →
  assignment → review draft + submit → decision → revision → copyediting →
  proofs → premature-publish refused → approval → publish → article created,
  identifier stable end to end); skip transitions refused.

**Total new: 18 tests, 54 assertions.**

## 5. Full-suite state at close of module

```
php artisan test
Tests:    284 passed (1227 assertions)
```

The new migration `$table->migrate:rollback --step=1` verified clean (drop +
replay).

## 6. Deployment notes

`DEPLOYMENT.md` gains Module 4 bullet points (one additive migration; the
readiness gate activates immediately; ReviewerReminder/ProofApproval
notifications are queued so a worker is needed for mail delivery — S9
remains the known open production item).

## 7. Files added or changed

**New**
- `app/Services/Publishing/PublicationReadinessService.php`
- `app/Models/ManuscriptProofApproval.php`
- `app/Http/Controllers/Author/ProofApprovalController.php`
- `app/Notifications/{ReviewerReminderNotification,ProofApprovalNotification}.php`
- Migration `2026_10_19_000001_create_manuscript_proof_approvals_table.php`
- View `resources/views/author/proofs/show.blade.php`
- 4 test files (inventory above), this report

**Changed (additive)**
- `app/Services/Publishing/PublicationService.php` (readiness gate + refusal log)
- `app/Services/Workflow/EditorialWorkflowService.php` (status-history fix,
  round opening/closing, author notifications)
- `app/Http/Controllers/Admin/ManagingEditorController.php` (reminder send)
- `app/Http/Controllers/Editor/EditorWorkspaceController.php` +
  `resources/views/editor/workspace/show.blade.php` (readiness panel)
- `app/Policies/SubmissionPolicy.php` (`approveProofs`)
- `app/Models/Submission.php` (nothing new this module — verification only)
- `routes/web.php` (author proofs routes)
- `resources/views/author/revisions/show.blade.php` (proof link)
- `DEPLOYMENT.md`
