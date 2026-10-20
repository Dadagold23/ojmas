<?php

namespace Tests\Feature\Editorial;

use App\Models\ReviewerAssignment;
use App\Models\Review;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Reproduction of the reported 500:
 *
 *   ErrorException: Undefined variable $submittedReviews
 *   resources/views/editor/submissions/show.blade.php:244
 *   GET /editor/submissions/1
 */
class SubmissionShowBladeRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_editor_submission_page_renders_with_and_without_reviews(): void
    {
        Role::firstOrCreate(['name' => 'Editor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Reviewer', 'guard_name' => 'web']);

        Storage::fake('private');

        $editor = User::factory()->create();
        $editor->assignRole('Editor');

        $submission = Submission::factory()->create();

        // No reviews at all yet.
        $this->actingAs($editor)
            ->get(route('editor.submissions.show', $submission))
            ->assertOk();

        // With a submitted review attached to an assignment.
        $reviewer = User::factory()->create();
        $reviewer->assignRole('Reviewer');

        $assignment = ReviewerAssignment::create([
            'submission_id' => $submission->id,
            'reviewer_id' => $reviewer->id,
            'status' => 'completed',
            'due_date' => now()->addDays(7),
        ]);

        Review::create([
            'reviewer_assignment_id' => $assignment->id,
            'recommendation' => 'minor_revision',
            'comments_for_author' => 'Tighten section 2.',
            'comments_for_editor' => 'Otherwise sound.',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $this->actingAs($editor)
            ->get(route('editor.submissions.show', $submission))
            ->assertOk()
            ->assertSee('Reviewer Reports')
            ->assertSee('Minor revision');
    }
}
