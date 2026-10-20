<?php

namespace Tests\Feature\Editorial;

use App\Models\ManuscriptWorkflowEvent;
use App\Models\Review;
use App\Models\ReviewerAssignment;
use App\Models\Submission;
use App\Models\User;
use App\Services\Review\ReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The reviewer report lifecycle (§3.2).
 *
 * A submitted review is immutable: it can be withdrawn, which is recorded,
 * but it can never be silently edited by anyone. Drafts are private to their
 * author and never influence a decision.
 */
class ReviewLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private ReviewService $service;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Author', 'Editor', 'Reviewer', 'Super Admin', 'Managing Editor'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->service = app(ReviewService::class);
    }

    private function assignment(string $role = 'Reviewer'): array
    {
        $submission = Submission::factory()->underReview()->create();
        $reviewer = User::factory()->create();
        $reviewer->assignRole($role);

        $assignment = ReviewerAssignment::create([
            'submission_id' => $submission->id,
            'reviewer_id' => $reviewer->id,
            'status' => 'assigned',
            'due_date' => now()->addDays(14),
        ]);

        return [$assignment, $reviewer, $submission];
    }

    public function test_a_draft_is_saved_and_is_private(): void
    {
        [$assignment, $reviewer, $submission] = $this->assignment();

        $review = $this->service->saveDraft($assignment, $reviewer, [
            'recommendation' => 'major_revision',
            'comments_for_author' => 'Section 3 is not yet finished.',
            'comments_for_editor' => 'This one may take a while.',
        ]);

        $this->assertSame(Review::STATUS_DRAFT, $review->status);

        // The editor-facing count ignores drafts entirely.
        $this->assertSame(0, $this->service->recommendationSummary($submission)['total']);
    }

    public function test_submitting_requires_a_recommendation_and_author_comments(): void
    {
        [$assignment, $reviewer] = $this->assignment();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->service->submit($assignment, $reviewer, []);
    }

    public function test_a_submitted_review_reaches_the_editor_facings(): void
    {
        Notification::fake();

        [$assignment, $reviewer, $submission] = $this->assignment();

        $review = $this->service->submit($assignment, $reviewer, [
            'recommendation' => 'minor_revision',
            'comments_for_author' => 'Please tighten the abstract.',
            'comments_for_editor' => 'Sound paper, minor fixes only.',
        ]);

        $this->assertSame(Review::STATUS_SUBMITTED, $review->status);
        $this->assertNotNull($review->submitted_at);
        $this->assertSame('completed', $assignment->fresh()->status);

        $summary = $this->service->recommendationSummary($submission);
        $this->assertSame(1, $summary['total']);
        $this->assertSame(1, $summary['counts']['minor_revision']);
    }

    public function test_a_submitted_review_cannot_be_quietly_edited(): void
    {
        [$assignment, $reviewer] = $this->assignment();

        $this->service->submit($assignment, $reviewer, [
            'recommendation' => 'accept',
            'comments_for_author' => 'Original answer, for the record.',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('already been submitted');

        $this->service->saveDraft($assignment, $reviewer, [
            'recommendation' => 'reject',
            'comments_for_author' => 'A very different answer after the fact.',
        ]);
    }

    public function test_withdrawal_is_recorded_and_removes_the_recommendation(): void
    {
        [$assignment, $reviewer, $submission] = $this->assignment();

        $this->service->submit($assignment, $reviewer, [
            'recommendation' => 'accept',
            'comments_for_author' => 'For the record.',
        ]);

        $this->service->withdraw($assignment, $reviewer, 'Realised the method section was misread.');

        $this->assertSame(Review::STATUS_WITHDRAWN, $assignment->fresh()->review->status);
        $this->assertSame(0, $this->service->recommendationSummary($submission)['total']);
        $this->assertDatabaseHas('manuscript_workflow_events', ['event' => 'review_withdrawn']);
    }

    public function test_a_reviewer_cannot_touch_someone_elses_assignment(): void
    {
        [$assignment] = $this->assignment();

        $intruder = User::factory()->create();
        $intruder->assignRole('Reviewer');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        $this->service->saveDraft($assignment, $intruder, ['recommendation' => 'reject']);
    }

    public function test_withdrawn_reviews_are_kept_in_the_record(): void
    {
        [$assignment, $reviewer] = $this->assignment();

        $this->service->submit($assignment, $reviewer, [
            'recommendation' => 'reject',
            'comments_for_author' => 'Withdrawn later.',
        ]);

        $this->service->withdraw($assignment, $reviewer);

        $this->assertDatabaseHas('reviews', [
            'reviewer_assignment_id' => $assignment->id,
            'status' => Review::STATUS_WITHDRAWN,
            'comments_for_author' => 'Withdrawn later.',
        ]);
    }
}
