<?php

namespace App\Policies;

use App\Models\Submission;
use App\Models\User;

class SubmissionPolicy
{
    public function view(User $user, Submission $submission): bool
    {
        return $submission->author_id === $user->id
            || $user->hasAnyRole(['Admin', 'Editor', 'Managing Editor', 'Super Admin', 'Reviewer']);
    }

    public function delete(User $user, Submission $submission): bool
    {
        return $submission->author_id === $user->id && in_array($submission->status, ['draft', 'pending_payment', 'submitted']);
    }

    public function assignReviewer(User $user): bool
    {
        return $user->hasAnyRole(['Editor', 'Managing Editor', 'Super Admin']);
    }

    public function publish(User $user): bool
    {
        return $user->hasAnyRole(['Editor', 'Managing Editor', 'Super Admin']);
    }
}
