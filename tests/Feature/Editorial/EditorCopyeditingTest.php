<?php

namespace Tests\Feature\Editorial;

use App\Models\Submission;
use App\Models\User;
use App\Services\Permissions\PermissionRegistrar;
use App\Services\Workflow\EditorialWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Copyediting is reachable from the editor's submission page.
 *
 * The reported defect: the editor saw only Request review / Accept / Reject,
 * because acceptance parked the manuscript on the `accepted` stage and the
 * only stage-move UI lived on an unlinked page.
 */
class EditorCopyeditingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Author', 'Editor', 'Managing Editor', 'Reviewer', 'Super Admin', 'Admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->sync();
        app(EditorialWorkflowService::class)->seed();
    }

    private function acceptedSubmission(string $stage = 'accepted'): Submission
    {
        $author = User::factory()->create();
        $author->assignRole('Author');

        $submission = Submission::factory()->create([
            'author_id' => $author->id,
            'status' => 'accepted',
            'current_stage' => $stage,
        ]);

        $submission->payments()->create([
            'user_id' => $author->id,
            'purpose' => 'vetting_fee',
            'reference' => 'VET-COPY-'.uniqid(),
            'amount' => 10000,
            'status' => 'paid',
            'gateway' => 'paystack',
            'paid_at' => now(),
        ]);

        return $submission;
    }

    private function editor(): User
    {
        $editor = User::factory()->create();
        $editor->assignRole('Editor');

        return $editor;
    }

    public function test_the_accepted_submission_page_offers_copyediting(): void
    {
        $submission = $this->acceptedSubmission();

        $this->actingAs($this->editor())
            ->get(route('editor.submissions.show', $submission))
            ->assertOk()
            ->assertSee('Workflow Stage')
            ->assertSee('Publication fee paid, begin copyediting')
            ->assertDontSee('Submit Decision');
    }

    public function test_an_editor_moves_an_accepted_manuscript_into_copyediting(): void
    {
        Notification::fake();

        $submission = $this->acceptedSubmission();

        $this->actingAs($this->editor())
            ->post(route('editor.submissions.transition', $submission), [
                'to_stage' => 'copyediting',
                'reason' => 'Publication fee confirmed.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $fresh = $submission->fresh();
        $this->assertSame('copyediting', $fresh->current_stage);
        $this->assertSame('accepted', $fresh->status);
    }

    public function test_copyediting_then_proofing_is_the_offered_path(): void
    {
        Notification::fake();

        $submission = $this->acceptedSubmission('copyediting');
        $editor = $this->editor();

        $this->actingAs($editor)
            ->get(route('editor.submissions.show', $submission))
            ->assertOk()
            ->assertSee('Copyediting complete')
            ->assertSee('Copyediting is open')
            ->assertDontSee('Send to further review');

        $this->actingAs($editor)
            ->post(route('editor.submissions.transition', $submission), [
                'to_stage' => 'proof_approval',
                'reason' => 'Copyedited text issued to the author.',
            ])
            ->assertSessionHas('success');

        $this->assertSame('proof_approval', $submission->fresh()->current_stage);

        $checks = app(EditorialWorkflowService::class)->proofGateChecks($submission->fresh());
        $this->assertTrue($checks['copyediting_completed']);
    }

    public function test_the_lifecycle_cannot_be_skipped(): void
    {
        Notification::fake();

        $submission = $this->acceptedSubmission();

        $this->actingAs($this->editor())
            ->post(route('editor.submissions.transition', $submission), [
                'to_stage' => 'proof_approval',
            ])
            ->assertSessionHas('error');

        $this->assertSame('accepted', $submission->fresh()->current_stage);
    }

    public function test_copyediting_waits_for_the_publication_fee(): void
    {
        Notification::fake();

        $submission = $this->acceptedSubmission();
        $submission->payments()->update(['status' => 'pending']);

        $this->actingAs($this->editor())
            ->get(route('editor.submissions.show', $submission))
            ->assertOk()
            ->assertSee('The publication fee must be confirmed before this step');

        // This pins the page's one fee guard: the copyediting form is hidden
        // until the publication fee is confirmed. The catalogue itself accepts
        // the transition, so a hand-crafted POST is not blocked on the fee.
        $this->actingAs($this->editor())
            ->post(route('editor.submissions.transition', $submission), [
                'to_stage' => 'copyediting',
            ])
            ->assertSessionHas('success');

        $this->assertSame('copyediting', $submission->fresh()->current_stage);
    }

    public function test_an_accepted_manuscript_cannot_be_re_decided(): void
    {
        Notification::fake();

        $submission = $this->acceptedSubmission();

        $this->actingAs($this->editor())
            ->post(route('editor.submissions.decision', $submission), [
                'decision' => 'rejected',
                'message' => 'Changed our mind.',
            ])
            ->assertStatus(422);

        $this->assertSame('accepted', $submission->fresh()->status);
        $this->assertSame(0, $submission->editorialDecisions()->count());
    }

    public function test_a_managing_editor_cannot_record_the_decision(): void
    {
        Notification::fake();

        $submission = Submission::factory()->create(['status' => 'under_review', 'current_stage' => 'editorial_decision']);
        $submission->payments()->create([
            'user_id' => $submission->author_id,
            'purpose' => 'vetting_fee',
            'reference' => 'VET-ME-'.uniqid(),
            'amount' => 10000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $managing = User::factory()->create();
        $managing->assignRole('Managing Editor');

        $this->actingAs($managing)
            ->get(route('editor.submissions.show', $submission))
            ->assertOk()
            ->assertDontSee('Submit Decision');

        $this->actingAs($managing)
            ->post(route('editor.submissions.decision', $submission), [
                'decision' => 'accepted',
                'message' => 'Accepting on behalf of the editor.',
            ])
            ->assertForbidden();

        $this->assertSame('under_review', $submission->fresh()->status);
    }

    public function test_an_editor_is_never_offered_the_publish_button(): void
    {
        $submission = $this->acceptedSubmission('proof_approval');

        $this->actingAs($this->editor())
            ->get(route('editor.submissions.show', $submission))
            ->assertOk()
            ->assertSee('Publishing is handled by the Admin account')
            ->assertDontSee('Publish Article');
    }
}
