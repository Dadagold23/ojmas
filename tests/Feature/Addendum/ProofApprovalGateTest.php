<?php

namespace Tests\Feature\Addendum;

use App\Models\EditorialStage;
use App\Models\ManuscriptProofApproval;
use App\Models\Submission;
use App\Models\User;
use App\Services\Permissions\PermissionRegistrar;
use App\Services\Workflow\EditorialWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The author's proof-approval gate.
 *
 * The proofs workspace verifies, computed from the submission's real stage
 * position (never from submitted data):
 *   1. peer review concluded — True/False
 *   2. copyediting completed — True/False
 * and the approval can be recorded only while both are True, by the
 * corresponding author alone.
 */
class ProofApprovalGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Author', 'Editor', 'Reviewer', 'Super Admin', 'Admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        app(EditorialWorkflowService::class)->seed();
        app(PermissionRegistrar::class)->sync();
    }

    /* ─────────────── fixtures ─────────────── */

    private function proofedSubmission(): Submission
    {
        $author = User::factory()->create();
        $author->assignRole('Author');

        return Submission::factory()->create([
            'author_id' => $author->id,
            'status' => 'accepted',
            'current_stage' => 'proof_approval',
        ]);
    }

    private function correspondingAuthor(Submission $submission): User
    {
        return User::find($submission->author_id);
    }

    /* ─────────────── the checks, from real stage position ─────────────── */

    public function test_proof_checks_read_from_the_stage_position(): void
    {
        $service = app(EditorialWorkflowService::class);
        $submission = $this->proofedSubmission();

        foreach ([
            'reviewer_assignment' => [false, false],
            'peer_review' => [false, false],
            'editorial_decision' => [true, false],
            'author_revision' => [true, false],
            'further_review' => [false, false], // review is open again
            'accepted' => [true, false],
            'copyediting' => [true, false],
            'proof_approval' => [true, true],
            'published' => [true, true],
        ] as $stageSlug => [$reviewExpected, $copyeditingExpected]) {
            $submission->current_stage = $stageSlug;

            $checks = $service->proofGateChecks($submission);

            $this->assertSame(
                $reviewExpected,
                $checks['peer_review_concluded'],
                "peer_review_concluded wrong at {$stageSlug}"
            );
            $this->assertSame(
                $copyeditingExpected,
                $checks['copyediting_completed'],
                "copyediting_completed wrong at {$stageSlug}"
            );
        }
    }

    /* ─────────────── workspace authorisation ─────────────── */

    public function test_only_the_corresponding_author_sees_the_proofs_workspace(): void
    {
        $submission = $this->proofedSubmission();
        $author = $this->correspondingAuthor($submission);

        $this->actingAs($author)
            ->get(route('author.proofs.show', $submission))
            ->assertOk()
            ->assertSee('Peer review concluded')
            ->assertSee('Copyediting completed')
            ->assertSee('True');

        $stranger = User::factory()->create();
        $stranger->assignRole('Author');
        $this->actingAs($stranger)
            ->get(route('author.proofs.show', $submission))
            ->assertForbidden();
    }

    public function test_guests_are_redirected_from_the_proofs_workspace(): void
    {
        $submission = $this->proofedSubmission();

        $this->get(route('author.proofs.show', $submission))
            ->assertRedirect(route('login'));
    }

    public function test_checks_show_false_and_the_form_is_locked_before_copyediting(): void
    {
        $submission = $this->proofedSubmission();
        $submission->current_stage = 'copyediting';
        $submission->save();

        // The policy admits the author at copyediting? No — the gate itself is
        // proof_approval only; verify the refusal here and verify the visual
        // lock on the page through a stage whose row the policy admits.
        $this->actingAs($this->correspondingAuthor($submission))
            ->get(route('author.proofs.show', $submission))
            ->assertForbidden();
    }

    /* ─────────────── creating the proof approval ─────────────── */

    public function test_author_records_a_proof_approval_when_the_gate_is_satisfied(): void
    {
        $submission = $this->proofedSubmission();
        $author = $this->correspondingAuthor($submission);

        $this->actingAs($author)
            ->post(route('author.proofs.approve', $submission), [
                'status' => 'approved',
                'notes' => 'Read in full; figures are correct.',
            ])
            ->assertRedirect();

        $this->assertSame(1, ManuscriptProofApproval::where('submission_id', $submission->id)
            ->where('status', 'approved')
            ->where('author_id', $author->id)
            ->count());
    }

    public function test_an_approval_is_refused_while_a_check_is_false(): void
    {
        $submission = $this->proofedSubmission();
        $author = $this->correspondingAuthor($submission);

        // Force a gate failure while the policy still admits the author:
        // push the editorial decision above proofing so 'peer review
        // concluded' reads False at the proofing stage.
        EditorialStage::where('slug', 'editorial_decision')->update(['sort_order' => 500]);

        $this->actingAs($author)
            ->post(route('author.proofs.approve', $submission), [
                'status' => 'approved',
            ])
            ->assertSessionHasErrors('proofs');

        $this->assertSame(0, ManuscriptProofApproval::where('submission_id', $submission->id)->count());

        // And the workspace page itself shows the True/False state honestly.
        $this->actingAs($author)
            ->get(route('author.proofs.show', $submission))
            ->assertOk()
            ->assertSee('Peer review concluded')
            ->assertSee('False')
            ->assertSee('Locked while the checks above show False');
    }

    public function test_corrections_requests_are_refused_the_same_way_by_the_gate(): void
    {
        $submission = $this->proofedSubmission();
        $author = $this->correspondingAuthor($submission);

        EditorialStage::where('slug', 'editorial_decision')->update(['sort_order' => 500]);

        $this->actingAs($author)
            ->post(route('author.proofs.approve', $submission), [
                'status' => 'corrections_requested',
            ])
            ->assertSessionHasErrors('proofs');

        $this->assertSame(0, ManuscriptProofApproval::where('submission_id', $submission->id)->count());
    }
}
