<?php

namespace Tests\Feature\Workflow;

use App\Models\RevisionRound;
use App\Models\Submission;
use App\Models\SubmissionStatusHistory;
use App\Models\User;
use App\Notifications\SubmissionAcceptedNotification;
use App\Notifications\SubmissionRejectedNotification;
use App\Services\Workflow\EditorialWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Module 4 lifecycle bookkeeping.
 *
 * Stage transitions are the engine of the workflow, so their side effects
 * must be exact: honest status history, revision rounds opened and closed,
 * and author notifications through the existing queue.
 */
class TransitionBookkeepingTest extends TestCase
{
    use RefreshDatabase;

    private EditorialWorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Author', 'Editor', 'Reviewer', 'Super Admin', 'Managing Editor'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->workflow = app(EditorialWorkflowService::class);
        $this->workflow->seed();
    }

    private function submissionAt(string $stage, string $status, int $authorId): Submission
    {
        return Submission::factory()->create([
            'author_id' => $authorId,
            'current_stage' => $stage,
            'status' => $status,
        ]);
    }

    public function test_the_status_history_records_honest_old_and_new_values(): void
    {
        $author = User::factory()->create();
        $editor = User::factory()->create();
        $editor->assignRole('Editor');

        $submission = $this->submissionAt('editorial_decision', 'under_review', $author->id);
        $statusBefore = $submission->status;

        $this->workflow->transition($submission, 'accepted', $editor, 'Recommended by all reviewers.');

        $history = SubmissionStatusHistory::where('submission_id', $submission->id)
            ->latest('id')->first();

        $this->assertNotSame($history->old_status, $history->new_status, 'old and new status must differ when the status actually changed');
        $this->assertSame($statusBefore, $history->old_status);
        $this->assertSame('accepted', $history->new_status);
    }

    public function test_history_stays_accurate_even_when_only_the_stage_moves(): void
    {
        $author = User::factory()->create();
        $editor = User::factory()->create();
        $editor->assignRole('Editor');

        $submission = $this->submissionAt('technical_validation', 'submitted', $author->id);
        $statusBefore = $submission->status;

        $this->workflow->transition($submission, 'editorial_screening', $editor);

        $history = SubmissionStatusHistory::where('submission_id', $submission->id)
            ->latest('id')->first();

        // Each stage carries its own legacy_status: the ENUM follows the
        // stage honestly in both directions of the row.
        $this->assertSame($statusBefore, $history->old_status);
        $this->assertSame('screening', $history->new_status);
        $this->assertStringContainsString('Editorial Screening', $history->note);
    }

    public function test_entering_author_revision_opens_a_revision_round(): void
    {
        $author = User::factory()->create();
        $editor = User::factory()->create();
        $editor->assignRole('Editor');

        $submission = $this->submissionAt('editorial_decision', 'under_review', $author->id);

        $result = $this->workflow->transition($submission, 'author_revision', $editor, 'Method section needs work.');
        $this->assertTrue($result['ok']);

        $fresh = $submission->fresh();

        $this->assertSame(1, $fresh->revision_round);
        $this->assertSame('revision_requested', $fresh->status);
        $this->assertNotNull($fresh->decision_due_at);

        $round = $fresh->currentRevisionRound();
        $this->assertNotNull($round, 'A open revision round must exist for the author to answer.');
        $this->assertSame(RevisionRound::STATUS_OPEN, $round->status);
        $this->assertSame(1, $round->round_number);
        $this->assertNotNull($round->due_at);
    }

    public function test_entering_copyediting_closes_the_open_revision_round(): void
    {
        $author = User::factory()->create();
        $editor = User::factory()->create();
        $editor->assignRole('Editor');

        $submission = $this->submissionAt('author_revision', 'revision_requested', $author->id);

        $round = $submission->revisionRounds()->create([
            'round_number' => 1,
            'status' => RevisionRound::STATUS_OPEN,
            'opened_by' => $editor->id,
            'opened_at' => now()->subDays(3),
            'due_at' => now()->addDays(20),
        ]);

        $submission->revision_round = 1;
        $submission->save();

        $result = $this->workflow->transition($submission, 'copyediting', $editor);
        $this->assertTrue($result['ok']);

        $this->assertSame(RevisionRound::STATUS_CLOSED, $round->fresh()->status);
        $this->assertNotNull($round->fresh()->closed_at);
    }

    public function test_the_author_is_notified_when_the_transition_says_to(): void
    {
        Notification::fake();

        $author = User::factory()->create();
        $editor = User::factory()->create();
        $editor->assignRole('Editor');

        $submission = $this->submissionAt('editorial_decision', 'under_review', $author->id);

        // author_revision notifies (revision request).
        $this->workflow->transition($submission->fresh(), 'author_revision', $editor, 'Needs revision.');
        Notification::assertSentTo($author, \App\Notifications\RevisionRequestedNotification::class);

        // author_revision → copyediting does NOT notify the author.
        Notification::fake();
        $this->workflow->transition($submission->fresh(), 'copyediting', $editor);
        Notification::assertNothingSentTo($author);

        // Once at proofs, nothing author-facing happens until publish.
        Notification::assertNothingSent();
    }

    public function test_publication_stage_transitions_demand_publish_permission(): void
    {
        $editor = User::factory()->create();
        $editor->assignRole('Editor');
        $author = User::factory()->create();

        $submission = $this->submissionAt('proof_approval', 'accepted', $author->id);

        $result = $this->workflow->transition($submission, 'published', $editor);

        $this->assertFalse($result['ok'], 'An editor without publication rights must not publish by stage move .');
        $this->assertSame('proof_approval', $submission->fresh()->current_stage);
    }
}
