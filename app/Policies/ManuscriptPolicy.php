<?php

namespace App\Policies;

use App\Models\Submission;
use App\Models\User;

/**
 * Authorisation for the manuscript document workspace.
 *
 * Two things this deliberately does NOT do:
 *  - It does not grant a reviewer blanket access to every submission. The
 *    Module 0 audit found `SubmissionPolicy::view()` returned true for any
 *    user holding the Reviewer role; manuscript access is assignment-scoped.
 *  - It does not distinguish "can read the report" from "can edit". Formatting
 *    reports are read-only to everyone; only the author of the submission may
 *    approve a formatted document.
 */
class ManuscriptPolicy
{
    public function view(User $user, Submission $submission): bool
    {
        return $submission->author_id === $user->id
            || $submission->isLinkedToUserEmail($user->email)
            || $user->hasAnyRole(['Super Admin', 'Admin', 'Managing Editor', 'Managing Admin', 'Editor'])
            || $this->isAssignedReviewer($user, $submission);
    }

    public function viewReport(User $user, Submission $submission): bool
    {
        return $this->view($user, $submission);
    }

    /**
     * Only the submitting author may approve a formatted manuscript.
     *
     * An editor approving on the author's behalf would defeat the purpose of the
     * approval gate, which exists so the author inspects the reformatted file
     * before it enters the editorial workflow.
     */
    public function approve(User $user, Submission $submission): bool
    {
        return (int) $submission->author_id === (int) $user->id;
    }

    public function downloadFormatted(User $user, Submission $submission): bool
    {
        return $this->view($user, $submission);
    }

    public function runFormatting(User $user, Submission $submission): bool
    {
        return $submission->author_id === $user->id
            || $user->hasAnyRole(['Super Admin', 'Admin', 'Managing Editor', 'Managing Admin', 'Editor']);
    }

    public function viewAuditLog(User $user, Submission $submission): bool
    {
        return $user->hasAnyRole(['Super Admin', 'Admin', 'Managing Editor', 'Managing Admin']);
    }

    private function isAssignedReviewer(User $user, Submission $submission): bool
    {
        return \App\Models\ReviewerAssignment::query()
            ->where('submission_id', $submission->id)
            ->where('reviewer_id', $user->id)
            ->exists();
    }
}