<?php

namespace App\Services\Review;

use App\Models\Review;
use App\Models\ReviewerAssignment;
use App\Models\Submission;
use App\Models\User;
use App\Services\Workflow\EditorialWorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reviewer report drafting and submission.
 *
 * Two rules the module brief is explicit about:
 *
 *  - A reviewer may save a **draft** as often as they like before submitting.
 *  - **A submitted review is immutable.** An editor cannot alter a reviewer's
 *    report; if something is wrong, the reviewer must withdraw and re-submit,
 *    and the withdrawal is recorded. The only fields that ever change after
 *    submission are the resolution fields, which belong to the editor.
 */
class ReviewService
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_WITHDRAWN = 'withdrawn';

    /** @var array<int, string> The journal's configured recommendation options. */
    public const RECOMMENDATIONS = ['accept', 'minor_revision', 'major_revision', 'reject'];

    public function __construct(private readonly EditorialWorkflowService $workflow = new EditorialWorkflowService())
    {
    }

    public function recommendationLabels(): array
    {
        return [
            'accept' => 'Accept',
            'minor_revision' => 'Minor revision',
            'major_revision' => 'Major revision',
            'reject' => 'Reject',
        ];
    }

    /**
     * The reviewer's own report for an assignment, if any.
     */
    public function reviewFor(ReviewerAssignment $assignment): ?Review
    {
        return Review::where('reviewer_assignment_id', $assignment->id)
            ->where('status', '!=', self::STATUS_WITHDRAWN)
            ->first();
    }

    /**
     * Creates or updates the reviewer's DRAFT.
     *
     * A draft is private to its author. It is not visible to an editor and does
     * not appear in any recommendation count.
     */
    public function saveDraft(ReviewerAssignment $assignment, User $reviewer, array $attributes): Review
    {
        $this->assertMayReview($assignment, $reviewer);

        $review = $this->reviewFor($assignment);

        if ($review !== null && $review->status === self::STATUS_SUBMITTED) {
            throw ValidationException::withMessages([
                'review' => 'Your review has already been submitted. It cannot be edited; withdraw it first if it must change.',
            ]);
        }

        $payload = [
            'recommendation' => $attributes['recommendation'] ?? null,
            'comments_for_author' => $attributes['comments_for_author'] ?? null,
            'comments_for_editor' => $attributes['comments_for_editor'] ?? null,
            'status' => self::STATUS_DRAFT,
        ];

        return DB::transaction(function () use ($assignment, $review, $payload) {
            if ($review !== null) {
                $review->update($payload);

                return $review->fresh();
            }

            return $assignment->review()->create($payload);
        });
    }

    /**
     * Submits the review for the record.
     *
     * A recommendation and author-facing comments are required; the
     * editor-facing section is optional but strongly signposted.
     */
    public function submit(ReviewerAssignment $assignment, User $reviewer, array $attributes): Review
    {
        $this->assertMayReview($assignment, $reviewer);

        $review = $this->reviewFor($assignment);

        if ($review !== null && $review->status === self::STATUS_SUBMITTED) {
            throw ValidationException::withMessages([
                'review' => 'You have already submitted a review for this manuscript.',
            ]);
        }

        $recommendation = $attributes['recommendation'] ?? $review?->recommendation;
        $commentsForAuthor = $attributes['comments_for_author'] ?? $review?->comments_for_author;

        if (! in_array($recommendation, self::RECOMMENDATIONS, true)) {
            throw ValidationException::withMessages(['recommendation' => 'Please choose a recommendation.']);
        }

        if (trim((string) $commentsForAuthor) === '') {
            throw ValidationException::withMessages([
                'comments_for_author' => 'Please provide comments for the author before submitting.',
            ]);
        }

        $review = DB::transaction(function () use ($assignment, $review, $recommendation, $attributes, $commentsForAuthor) {
            $payload = [
                'recommendation' => $recommendation,
                'comments_for_author' => $commentsForAuthor,
                'comments_for_editor' => $attributes['comments_for_editor'] ?? $review?->comments_for_editor,
                'status' => self::STATUS_SUBMITTED,
                'submitted_at' => now(),
            ];

            if ($review !== null) {
                $review->update($payload);
            } else {
                $review = $assignment->review()->create($payload);
            }

            $assignment->update(['status' => 'completed', 'responded_at' => now()]);

            return $review->fresh();
        });

        $this->workflow->log($review->assignment->submission, $reviewer, 'review_submitted', null, null, [
            'recommendation' => $review->recommendation,
        ]);

        return $review;
    }

    /**
     * Withdraws a submitted review so the reviewer can correct it.
     *
     * Recorded rather than deleted: the withdrawal itself is part of the
     * editorial record.
     */
    public function withdraw(ReviewerAssignment $assignment, User $reviewer, ?string $reason = null): Review
    {
        $this->assertMayReview($assignment, $reviewer);

        $review = $this->reviewFor($assignment);

        if ($review === null || $review->status !== self::STATUS_SUBMITTED) {
            throw ValidationException::withMessages([
                'review' => 'There is no submitted review to withdraw.',
            ]);
        }

        $review->update([
            'status' => self::STATUS_WITHDRAWN,
            'submitted_at' => null,
        ]);

        $assignment->update(['status' => 'assigned', 'responded_at' => null]);

        $this->workflow->log($review->assignment->submission, $reviewer, 'review_withdrawn', null, null, [
            'reason' => $reason,
        ]);

        return $review->fresh();
    }

    /**
     * Every SUBMITTED review on a submission, newest first.
     *
     * Withdrawn reviews are excluded from this list so they cannot influence a
     * decision, but they remain in the database and in the audit log.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Review>
     */
    public function submittedFor(Submission $submission)
    {
        return Review::with(['assignment.reviewer'])
            ->whereHas('assignment', fn ($query) => $query->where('submission_id', $submission->id))
            ->where('status', self::STATUS_SUBMITTED)
            ->latest('submitted_at')
            ->get();
    }

    /**
     * The editor-facing recommendation summary.
     *
     * @return array<string, mixed>
     */
    public function recommendationSummary(Submission $submission): array
    {
        $reviews = $this->submittedFor($submission);

        $counts = array_fill_keys(self::RECOMMENDATIONS, 0);

        foreach ($reviews as $review) {
            if (array_key_exists($review->recommendation, $counts)) {
                $counts[$review->recommendation]++;
            }
        }

        return [
            'total' => $reviews->count(),
            'counts' => $counts,
            'labels' => $this->recommendationLabels(),
            // A consensus signal, never an automatic decision.
            'consensus' => $this->consensus($counts, $reviews->count()),
        ];
    }

    /** @param array<string, int> $counts */
    private function consensus(array $counts, int $total): string
    {
        if ($total === 0) {
            return 'none';
        }

        $max = max($counts);
        $leaders = array_keys(array_filter($counts, fn (int $count) => $count === $max && $count > 0));

        if ($max === 0 || count($leaders) > 1) {
            return 'split';
        }

        $leader = $leaders[0];

        return $max === $total
            ? 'unanimous_'.$leader
            : 'majority_'.$leader.'_'.(int) round($max / $total * 100).'pct';
    }

    private function assertMayReview(ReviewerAssignment $assignment, User $reviewer): void
    {
        if ((int) $assignment->reviewer_id !== (int) $reviewer->id) {
            abort(403, 'You are not the assigned reviewer for this manuscript.');
        }
    }
}