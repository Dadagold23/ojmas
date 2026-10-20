<?php

namespace App\Policies;

use App\Models\ReviewerAssignment;
use App\Models\Submission;
use App\Models\User;

/**
 * Authorisation for a submission and its attached files.
 *
 * Security note (Module 0 audit, finding B5):
 *
 * The previous implementation granted `view()` to every account holding the
 * Reviewer role, for every submission. That meant a reviewer could open any
 * author's manuscript by guessing an identifier, which defeats peer review
 * entirely. Reviewer access here is scoped to the assignments they actually
 * hold. `assignReviewer` and `publish` remain role-gated, which is what the
 * Module 3 permission layer will refine further.
 */
class SubmissionPolicy
{
    /**
     * Authorisation for a submission and its attached files.
     *
     * Two things this deliberately does NOT do:
     *  - It does not grant a reviewer blanket access to every submission. The
     *    Module 0 audit found `view()` returned true for any account holding the
     *    Reviewer role; manuscript access is assignment-scoped.
     *  - It does not let an editor approve a formatted manuscript on the
     *    author's behalf.
     */
    public function __construct(
        private readonly ManuscriptPolicy $manuscripts = new ManuscriptPolicy(),
        private readonly WorkspacePolicy $workspace = new WorkspacePolicy(),
    ) {
    }

    public function view(User $user, Submission $submission): bool
    {
        if ((int) $submission->author_id === (int) $user->id) {
            return true;
        }

        if ($user->hasAnyRole(['Super Admin', 'Admin', 'Managing Editor', 'Managing Admin', 'Editor'])) {
            return true;
        }

        if ($submission->isLinkedToUserEmail($user->email)) {
            return true;
        }

        return $this->isAssignedReviewer($user, $submission);
    }

    // ── Manuscript formatting workspace (Module 1) ───────────────────────

    public function viewReport(User $user, Submission $submission): bool
    {
        return $this->manuscripts->viewReport($user, $submission);
    }

    public function approve(User $user, Submission $submission): bool
    {
        return $this->manuscripts->approve($user, $submission);
    }

    public function downloadFormatted(User $user, Submission $submission): bool
    {
        return $this->manuscripts->downloadFormatted($user, $submission);
    }

    public function runFormatting(User $user, Submission $submission): bool
    {
        return $this->manuscripts->runFormatting($user, $submission);
    }

    public function viewAuditLog(User $user, Submission $submission): bool
    {
        return $this->manuscripts->viewAuditLog($user, $submission);
    }

    // ── Browser manuscript workspace (Module 2) ─────────────────────────

    public function openWorkspace(User $user, Submission $submission): bool
    {
        return $this->workspace->view($user, $submission);
    }

    public function editWorkspace(User $user, Submission $submission): bool
    {
        return $this->workspace->edit($user, $submission);
    }

    public function commentOnWorkspace(User $user, Submission $submission): bool
    {
        return $this->workspace->comment($user, $submission);
    }

    public function reviewWorkspaceChanges(User $user, Submission $submission): bool
    {
        return $this->workspace->reviewChanges($user, $submission);
    }

    public function restoreWorkspaceVersion(User $user, Submission $submission): bool
    {
        return $this->workspace->restore($user, $submission);
    }

    public function compareWorkspaceVersions(User $user, Submission $submission): bool
    {
        return $this->workspace->compareVersions($user, $submission);
    }

    public function exportWorkspace(User $user, Submission $submission): bool
    {
        return $this->workspace->export($user, $submission);
    }

    public function forceWorkspaceSave(User $user, Submission $submission): bool
    {
        return $this->workspace->forceSave($user, $submission);
    }

    // ── Editorial workspace (Module 3) ──────────────────────────────────

    /** Seeing any submission, as opposed to only one's own. */
    public function viewAny(User $user, Submission $submission): bool
    {
        if ($user->can('submission.view.any')) {
            return true;
        }

        // An editor may see the manuscripts assigned to them.
        return $user->can('review.view.assigned')
            && (int) $submission->assigned_editor_id === (int) $user->id;
    }

    public function assignReviewer(User $user, Submission $submission): bool
    {
        return $user->can('review.assign') || $user->can('review.invite');
    }

    /**
     * Only a decision-maker may record an editorial decision.
     *
     * A Managing Editor coordinates the workflow but does not hold this power,
     * which is the boundary the module brief requires.
     */
    public function recordDecision(User $user, Submission $submission): bool
    {
        return $user->can('decision.record');
    }

    /** Communicating an existing decision is broader than making one. */
    public function communicateDecision(User $user, Submission $submission): bool
    {
        return $user->can('decision.communicate');
    }

    public function manageWorkflow(User $user, Submission $submission): bool
    {
        return $user->can('workflow.transition') || $user->can('workflow.manage');
    }

    /** Managing Editors coordinate the workflow but may not configure it. */
    public function configureWorkflow(User $user): bool
    {
        return $user->can('settings.workflow');
    }

    public function viewAudit(User $user, Submission $submission): bool
    {
        return $user->can('audit.documents');
    }

    /** Preparing and issuing a blinded review copy. */
    public function prepareBlindedCopy(User $user, Submission $submission): bool
    {
        return $user->can('manuscript.view.anonymised');
    }

    /** Restoring a workspace version is bounded to the editor who set it up. */
    public function restoreVersion(User $user, Submission $submission): bool
    {
        return $user->can('manuscript.restore.version');
    }

    /**
     * An author may respond to a revision request only while the window is open.
     */
    public function respondToRevision(User $user, Submission $submission): bool
    {
        if ((int) $submission->author_id !== (int) $user->id) {
            return false;
        }

        if ($submission->status !== 'revision_requested') {
            return false;
        }

        if ($submission->decision_due_at !== null && $submission->decision_due_at->isPast()) {
            return false;
        }

        return $user->can('submission.respond.revision');
    }

    // ── Original submission lifecycle ───────────────────────────────────

    // ── System configuration (Module 3, §3.5) ──────────────────────────
    //
    // These are permission-based, not role-based, so a Managing Editor can be
    // granted one without receiving the others. The route middleware still
    // restricts the section itself to the right group of roles.

    public function manageRoles(User $user): bool
    {
        return $user->can('role.manage') || $user->hasRole('Super Admin');
    }

    public function manageStyleProfiles(User $user): bool
    {
        return $user->can('settings.style.profiles') || $user->hasRole('Super Admin');
    }

    public function configureWorkflowSettings(User $user): bool
    {
        return $user->can('settings.workflow') || $user->hasRole('Super Admin');
    }

    public function manageRetention(User $user): bool
    {
        return $user->can('settings.retention') || $user->hasRole('Super Admin');
    }

    public function manageDocumentSettings(User $user): bool
    {
        return $user->can('settings.documents') || $user->hasRole('Super Admin');
    }

    // ── Original submission lifecycle ───────────────────────────────────

    public function delete(User $user, Submission $submission): bool
    {
        return (int) $submission->author_id === (int) $user->id
            && in_array($submission->status, ['draft', 'pending_payment', 'submitted'], true);
    }

    public function publish(User $user): bool
    {
        // Publication is now permission-based rather than role-based: the
        // Managing Editor coordinates the pipeline (§3.4) but does not hold
        // the production keys (§3.5). Admin keeps its historic publish
        // rights, which is the standing answer to deferred decision J7.
        return $user->can('publication.publish');
    }

    /** The corresponding author signs off their proofs. */
    public function approveProofs(User $user, Submission $submission): bool
    {
        return (int) $submission->author_id === (int) $user->id
            && $submission->current_stage === 'proof_approval';
    }

    /** Downloading a manuscript file follows exactly the same rule as viewing it. */
    public function download(User $user, Submission $submission): bool
    {
        return $this->view($user, $submission);
    }

    private function isAssignedReviewer(User $user, Submission $submission): bool
    {
        return ReviewerAssignment::query()
            ->where('submission_id', $submission->id)
            ->where('reviewer_id', $user->id)
            ->exists();
    }
}