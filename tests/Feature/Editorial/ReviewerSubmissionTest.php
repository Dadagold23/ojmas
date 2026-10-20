<?php

namespace Tests\Feature\Editorial;

use App\Models\ReviewerAssignment;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\ReviewCompletedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression coverage for Module 0 audit finding B1.
 *
 * The reviewer review endpoint wrote `submission_id`, `reviewer_id` and
 * `comments` into the `reviews` table. None of those columns exist, so every
 * reviewer review submission failed with an SQL error and no reviewer report
 * could ever be recorded.
 */
class ReviewerSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Author', 'Editor', 'Reviewer', 'Super Admin', 'Managing Editor'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function reviewer(): User
    {
        $user = User::factory()->create(['role' => 'Reviewer']);
        $user->assignRole('Reviewer');

        return $user;
    }

    private function assignment(): ReviewerAssignment
    {
        $submission = Submission::factory()->create(['status' => 'under_review']);

        return ReviewerAssignment::create([
            'submission_id' => $submission->id,
            'reviewer_id' => $this->reviewer()->id,
            'status' => 'assigned',
            'due_date' => now()->addDays(14),
        ]);
    }

    public function test_a_reviewer_can_submit_a_review(): void
    {
        Notification::fake();

        $assignment = $this->assignment();
        $reviewer = User::find($assignment->reviewer_id);

        $response = $this->actingAs($reviewer)->post(
            route('reviewer.reviews.store', $assignment),
            [
                'recommendation' => 'minor_revision',
                'comments_for_author' => 'The method is sound but the sample needs a power analysis.',
                'comments_for_editor' => 'I would be comfortable with minor revisions.',
            ],
        );

        $response->assertRedirect(route('reviewer.assignments.index'));

        $this->assertDatabaseHas('reviews', [
            'reviewer_assignment_id' => $assignment->id,
            'recommendation' => 'minor_revision',
            'comments_for_author' => 'The method is sound but the sample needs a power analysis.',
        ]);

        $this->assertSame('completed', $assignment->fresh()->status);
    }

    public function test_a_second_review_is_refused(): void
    {
        $assignment = $this->assignment();
        $reviewer = User::find($assignment->reviewer_id);

        $payload = [
            'recommendation' => 'accept',
            'comments_for_author' => 'Good work.',
        ];

        $this->actingAs($reviewer)->post(route('reviewer.reviews.store', $assignment), $payload);

        $this->actingAs($reviewer)
            ->from(route('reviewer.assignments.index'))
            ->post(route('reviewer.reviews.store', $assignment), $payload)
            ->assertSessionHas('error');

        $this->assertSame(1, \App\Models\Review::where('reviewer_assignment_id', $assignment->id)->count());
    }

    public function test_another_reviewer_cannot_submit_for_this_assignment(): void
    {
        $assignment = $this->assignment();

        $intruder = User::factory()->create(['role' => 'Reviewer']);
        $intruder->assignRole('Reviewer');

        $this->actingAs($intruder)->post(route('reviewer.reviews.store', $assignment), [
            'recommendation' => 'accept',
            'comments_for_author' => 'Not my manuscript.',
        ])->assertForbidden();

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_an_invalid_recommendation_is_rejected(): void
    {
        $assignment = $this->assignment();
        $reviewer = User::find($assignment->reviewer_id);

        $this->actingAs($reviewer)->post(route('reviewer.reviews.store', $assignment), [
            'recommendation' => 'publish_immediately',
            'comments_for_author' => 'x',
        ])->assertSessionHasErrors('recommendation');

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_the_submission_is_not_accepted_or_rejected_by_a_review(): void
    {
        $assignment = $this->assignment();
        $reviewer = User::find($assignment->reviewer_id);

        $this->actingAs($reviewer)->post(route('reviewer.reviews.store', $assignment), [
            'recommendation' => 'reject',
            'comments_for_author' => 'Not suitable.',
        ]);

        $this->assertSame(
            'under_review',
            $assignment->submission->fresh()->status,
            'A reviewer recommendation must never change the submission status.',
        );
    }
}