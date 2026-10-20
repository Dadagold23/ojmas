<?php

namespace Tests\Feature\Publication;

use App\Models\ManuscriptProofApproval;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\ProofApprovalNotification;
use App\Services\Workflow\EditorialWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * §4.11: proofreading and author approval.
 *
 * The approval recorded here is what allows an approved manuscript to reach
 * production; without it the readiness gate refuses. The flow is reachable
 * only by the corresponding author, only at the proofs stage.
 */
class ProofApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Author', 'Editor', 'Reviewer', 'Super Admin', 'Managing Editor'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        app(EditorialWorkflowService::class)->seed();
    }

    private function authorAtProofStage(string $stage = 'proof_approval'): array
    {
        $author = User::factory()->create();
        $author->assignRole('Author');

        $editor = User::factory()->create();
        $editor->assignRole('Editor');

        $submission = Submission::factory()->create([
            'author_id' => $author->id,
            'current_stage' => $stage,
            'status' => 'accepted',
            'assigned_editor_id' => $editor->id,
        ]);

        return [$author, $editor, $submission];
    }

    public function test_the_author_sees_the_proof_screen_at_the_right_stage(): void
    {
        [$author, , $submission] = $this->authorAtProofStage();

        $this->actingAs($author)
            ->get(route('author.proofs.show', $submission))
            ->assertOk()
            ->assertSee($submission->submission_no);
    }

    public function test_the_author_approval_is_recorded_and_the_editor_is_notified(): void
    {
        Notification::fake();
        [$author, $editor, $submission] = $this->authorAtProofStage();

        $this->actingAs($author)
            ->post(route('author.proofs.approve', $submission), [
                'status' => 'approved',
                'notes' => 'Figures 2 and 5 checked against the data.',
            ])
            ->assertRedirect();

        $approval = ManuscriptProofApproval::where('submission_id', $submission->id)->first();

        $this->assertNotNull($approval);
        $this->assertSame('approved', $approval->status);
        $this->assertNotNull($approval->approved_at);
        $this->assertSame($author->id, $approval->author_id);

        // The approval travels through the standard notification queue.
        Notification::assertSentTo($editor, ProofApprovalNotification::class);

        // And it is in the workflow log.
        $this->assertDatabaseHas('manuscript_workflow_events', [
            'submission_id' => $submission->id,
            'event' => 'proofs_approved',
            'actor_id' => $author->id,
        ]);
    }

    public function test_a_correction_request_is_recorded_but_is_not_an_approval(): void
    {
        Notification::fake();
        [$author, , $submission] = $this->authorAtProofStage();

        $this->actingAs($author)
            ->post(route('author.proofs.approve', $submission), [
                'status' => 'corrections_requested',
                'notes' => 'Table 3 rows misaligned in the proofs.',
            ])
            ->assertRedirect();

        $approval = ManuscriptProofApproval::where('submission_id', $submission->id)->first();

        $this->assertSame('corrections_requested', $approval->status);
        $this->assertNull($approval->approved_at);

        // The gate keeps counting this as missing approval.
        $assessment = app(\App\Services\Publishing\PublicationReadinessService::class)->assess($submission->fresh());
        $proof = collect($assessment['requirements'])->firstWhere('name', 'Author proof approval');
        $this->assertFalse($proof['met']);

        $this->assertDatabaseHas('manuscript_workflow_events', [
            'submission_id' => $submission->id,
            'event' => 'proof_corrections_requested',
        ]);
    }

    public function test_approval_is_impossible_before_the_proofs_stage(): void
    {
        [$author, , $submission] = $this->authorAtProofStage('copyediting');

        $this->actingAs($author)
            ->post(route('author.proofs.approve', $submission), ['status' => 'approved'])
            ->assertForbidden();

        $this->assertDatabaseMissing('manuscript_proof_approvals', ['submission_id' => $submission->id]);
    }

    public function test_a_non_author_cannot_sign_someone_elses_proofs(): void
    {
        [, , $submission] = $this->authorAtProofStage();

        $stranger = User::factory()->create();
        $stranger->assignRole('Author');

        $response = $this->actingAs($stranger)
            ->get(route('author.proofs.show', $submission));
        $status = $response->getStatusCode();

        // A stranger must never see the manuscript content (never 200).
        $this->assertNotSame(200, $status);

        $this->actingAs($stranger)
            ->post(route('author.proofs.approve', $submission), ['status' => 'approved'])
            ->assertForbidden();

        // And an editor cannot sign on the author's behalf either.
        $editor = User::factory()->create();
        $editor->assignRole('Editor');

        $this->actingAs($editor)
            ->post(route('author.proofs.approve', $submission), ['status' => 'approved'])
            ->assertForbidden();

        $this->assertDatabaseMissing('manuscript_proof_approvals', ['submission_id' => $submission->id]);
    }
}
