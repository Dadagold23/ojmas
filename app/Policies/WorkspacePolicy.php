<?php

namespace App\Policies;

use App\Models\ManuscriptSnapshot;
use App\Models\Submission;
use App\Models\User;

/**
 * Authorisation for the browser manuscript workspace.
 *
 * Capability split
 * ----------------
 *  - **Read**: the author, co-authors, assigned reviewers and editorial staff.
 *  - **Edit**: editorial staff, and the author while a revision window is open.
 *  - **Review changes**: editorial staff only. An author cannot accept or
 *    reject their own tracked changes.
 *  - **Restore a version**: editorial staff only.
 *  - **Administrative configuration**: Super Admin and Managing Editor only.
 */
class WorkspacePolicy
{
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

        return $submission->hasReviewerAssignmentBy($user);
    }

    public function edit(User $user, Submission $submission): bool
    {
        if ($user->hasAnyRole(['Super Admin', 'Admin', 'Managing Editor', 'Managing Admin', 'Editor'])) {
            return true;
        }

        // An author may edit while a revision is being requested, and never
        // while the manuscript is under or past peer review.
        return $submission->author_id === $user->id
            && in_array($submission->status, ['submitted', 'revision_requested'], true);
    }

    public function comment(User $user, Submission $submission): bool
    {
        return $this->view($user, $submission);
    }

    /** Only editorial staff may accept or reject tracked changes. */
    public function reviewChanges(User $user, Submission $submission): bool
    {
        return $user->hasAnyRole(['Super Admin', 'Admin', 'Managing Editor', 'Managing Admin', 'Editor']);
    }

    /** Only editorial staff may restore an earlier version. */
    public function restore(User $user, Submission $submission): bool
    {
        return $this->reviewChanges($user, $submission);
    }

    public function compareVersions(User $user, Submission $submission): bool
    {
        return $this->view($user, $submission);
    }

    public function export(User $user, Submission $submission): bool
    {
        return $this->view($user, $submission);
    }

    /** Overriding a concurrent save must be an explicit, privileged act. */
    public function forceSave(User $user, Submission $submission): bool
    {
        return $user->hasAnyRole(['Super Admin', 'Admin', 'Managing Editor', 'Managing Admin', 'Editor']);
    }

    public function manageConfiguration(User $user): bool
    {
        return $user->hasAnyRole(['Super Admin', 'Managing Editor', 'Managing Admin']);
    }
}