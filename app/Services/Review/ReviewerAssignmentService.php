<?php

namespace App\Services\Review;

use App\Models\ReviewerAssignment;
use App\Models\ReviewerInvitation;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\ReviewerAssignedNotification;
use App\Notifications\RevisionRequestedNotification;
use App\Services\Workflow\EditorialWorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Assigning reviewers and tracking whether they accept.
 *
 * §3.4 requires the Managing Editor to be able to coordinate assignments and
 * track invitations and responses, so assignment and invitation are modelled
 * together: inviting creates the invitation; the reviewer responding updates
 * both the invitation and the assignment so the two never disagree.
 */
class ReviewerAssignmentService
{
    public function __construct(private readonly EditorialWorkflowService $workflow = new EditorialWorkflowService())
    {
    }

    /**
     * Invites a reviewer to a manuscript.
     *
     * @throws ValidationException
     */
    public function invite(
        Submission $submission,
        User $reviewer,
        User $actor,
        ?\Illuminate\Support\Carbon $dueAt = null,
        ?int $roundId = null,
        bool $blind = true,
    ): ReviewerAssignment {
        if ($reviewer->id === $submission->author_id) {
            throw ValidationException::withMessages([
                'reviewer_id' => 'A manuscript cannot be sent for review to its own author.',
            ]);
        }

        if ($blind) {
            // Catch a reviewer who could identify the author from their profile
            // before we send them a "blinded" copy.
            $this->assertReviewerIsIndependent($reviewer, $submission);
        }

        return DB::transaction(function () use ($submission, $reviewer, $actor, $dueAt, $roundId, $blind) {
            $assignment = ReviewerAssignment::firstOrCreate(
                ['submission_id' => $submission->id, 'reviewer_id' => $reviewer->id],
                [
                    'status' => 'assigned',
                    'due_date' => $dueAt,
                    'round_id' => $roundId,
                    'blind_code' => $blind ? strtoupper(substr(hash('sha256', $submission->id.'-'.$reviewer->id), 0, 10)) : null,
                    'invited_at' => now(),
                ],
            );

            ReviewerInvitation::updateOrCreate(
                ['submission_id' => $submission->id, 'reviewer_id' => $reviewer->id, 'round_id' => $roundId],
                [
                    'invited_by' => $actor->id,
                    'invited_at' => now(),
                    'due_at' => $dueAt,
                    'response' => ReviewerInvitation::RESPONSE_PENDING,
                ],
            );

            if ($submission->current_stage === null) {
                $submission->forceFill(['current_stage' => 'reviewer_assignment'])->save();
            }

            $this->workflow->log($submission, $actor, 'reviewer_invited', null, null, [
                'reviewer_id' => $reviewer->id,
                'due_at' => $dueAt?->toDateString(),
                'blinded' => $blind,
            ]);

            return $assignment;
        });
    }

    /**
     * Records the reviewer's answer.
     */
    public function respond(ReviewerAssignment $assignment, User $reviewer, string $response, ?string $note = null): ReviewerAssignment
    {
        if ((int) $assignment->reviewer_id !== (int) $reviewer->id) {
            abort(403, 'This invitation is not addressed to you.');
        }

        if (! in_array($response, [ReviewerInvitation::RESPONSE_ACCEPTED, ReviewerInvitation::RESPONSE_DECLINED], true)) {
            throw ValidationException::withMessages(['response' => 'Unknown response.']);
        }

        return DB::transaction(function () use ($assignment, $reviewer, $response, $note) {
            $assignment->update([
                'status' => $response === ReviewerInvitation::RESPONSE_ACCEPTED ? 'accepted' : 'declined',
                'responded_at' => now(),
            ]);

            ReviewerInvitation::where('submission_id', $assignment->submission_id)
                ->where('reviewer_id', $reviewer->id)
                ->whereNull('responded_at')
                ->update([
                    'response' => $response,
                    'responded_at' => now(),
                    'response_note' => $note,
                ]);

            $this->workflow->log($assignment->submission, $reviewer, 'reviewer_'.$response, null, null, [
                'note' => $note,
            ]);

            return $assignment->fresh();
        });
    }

    /**
     * Records a reminder without sending mail; the notification itself is the
     * caller's concern so it can be queued separately.
     */
    public function recordReminder(ReviewerInvitation $invitation, User $actor): ReviewerInvitation
    {
        $invitation->update([
            'reminder_count' => (int) $invitation->reminder_count + 1,
            'last_reminded_at' => now(),
        ]);

        $this->workflow->log($invitation->submission, $actor, 'reviewer_reminded', null, null, [
            'reviewer_id' => $invitation->reviewer_id,
            'reminder_count' => $invitation->reminder_count,
        ]);

        return $invitation->fresh();
    }

    /**
     * Rejects a reviewer whose own profile would defeat the blind.
     *
     * §3.6 is explicit that anonymity must hold through every channel, and a
     * reviewer's institutional affiliation is one of them.
     */
    private function assertReviewerIsIndependent(User $reviewer, Submission $submission): void
    {
        $submission->loadMissing(['author', 'coAuthors']);

        $authorInstitution = mb_strtolower(trim((string) $submission->author?->institution));

        if ($authorInstitution === '') {
            return;
        }

        $reviewerInstitution = mb_strtolower(trim((string) $reviewer->institution));

        if ($reviewerInstitution !== '' && str_contains($reviewerInstitution, $authorInstitution)) {
            throw ValidationException::withMessages([
                'reviewer_id' => 'That reviewer shares the author\'s institution, which would defeat blinded review.',
            ]);
        }

        foreach ($submission->coAuthors as $coAuthor) {
            if (str_contains($reviewerInstitution, mb_strtolower(trim((string) $coAuthor->institution)))) {
                throw ValidationException::withMessages([
                    'reviewer_id' => 'That reviewer shares a co-author\'s institution, which would defeat blinded review.',
                ]);
            }
        }
    }
}