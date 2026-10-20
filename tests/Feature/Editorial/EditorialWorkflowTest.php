<?php

namespace Tests\Feature\Editorial;

use App\Models\EditorialStage;
use App\Models\ManuscriptWorkflowEvent;
use App\Models\Submission;
use App\Models\User;
use App\Services\Permissions\PermissionRegistrar;
use App\Services\Workflow\EditorialWorkflowService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The configurable lifecycle (§3.3, §3.4, §3.5).
 *
 * A stage move happens only when the catalogue allows it and the actor holds
 * the required permission — and every move is written to an append-only log.
 */
class EditorialWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private EditorialWorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Author', 'Editor', 'Reviewer', 'Super Admin', 'Managing Editor', 'Admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->sync();

        $this->workflow = app(EditorialWorkflowService::class);
    }

    private function userAtStage(string $role, string $stage, string $status = 'submitted'): array
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        $submission = Submission::factory()->create([
            'status' => $status,
            'current_stage' => $stage,
        ]);

        return [$user, $submission];
    }

    public function test_the_default_lifecycle_is_seeded(): void
    {
        $this->workflow->seed();

        $this->assertDatabaseHas('editorial_stages', ['slug' => 'peer_review', 'blinded_at_this_stage' => true]);
        $this->assertDatabaseHas('editorial_stage_transitions', [
            'from_stage_slug' => 'editorial_decision',
            'to_stage_slug' => 'author_revision',
        ]);

        // Seeding twice must not duplicate.
        $this->workflow->seed();
        $this->assertSame(1, EditorialStage::where('slug', 'peer_review')->count());
    }

    public function test_the_author_cannot_move_the_pipeline(): void
    {
        $this->workflow->seed();

        [$author, $submission] = $this->userAtStage('Author', 'editorial_screening');

        $result = $this->workflow->transition($submission, 'reviewer_assignment', $author);

        $this->assertFalse($result['ok']);
        $this->assertSame('editorial_screening', $submission->fresh()->current_stage);
    }

    public function test_transitions_write_to_the_append_only_log(): void
    {
        $this->workflow->seed();

        [$editor, $submission] = $this->userAtStage('Editor', 'editorial_screening');
        $editor->assignRole('Editor');

        $result = $this->workflow->transition($submission, 'reviewer_assignment', $editor, 'Screening satisfied');

        $this->assertTrue($result['ok']);
        $this->assertSame('reviewer_assignment', $submission->fresh()->current_stage);

        $event = ManuscriptWorkflowEvent::where('submission_id', $submission->id)
            ->where('event', 'stage_transition')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame($editor->id, $event->actor_id);
        $this->assertSame('Screening satisfied', $event->reason);
    }

    public function test_the_legacy_status_is_kept_in_step(): void
    {
        $this->workflow->seed();

        [$editor, $submission] = $this->userAtStage('Editor', 'editorial_decision', 'under_review');
        $editor->assignRole('Editor');

        $this->workflow->transition($submission, 'author_revision', $editor, 'Major structural concerns; revision window opened.');

        $fresh = $submission->fresh();
        $this->assertSame('author_revision', $fresh->current_stage);
        $this->assertSame('revision_requested', $fresh->status);
        $this->assertNotNull($fresh->decision_due_at);
    }

    public function test_unconfigured_transitions_are_refused(): void
    {
        $this->workflow->seed();

        [$admin, $submission] = $this->userAtStage('Admin', 'peer_review', 'under_review');
        $admin->assignRole('Admin');

        $result = $this->workflow->transition($submission, 'published', $admin);

        $this->assertFalse($result['ok']);
    }

    public function test_transitions_demand_the_stage_permission_even_from_staff(): void
    {
        $this->workflow->seed();

        // A plain Editor holds decision.record but not publication.publish,
        // so proofs → published is out of reach.
        [$editor, $submission] = $this->userAtStage('Editor', 'proof_approval', 'accepted');
        $editor->assignRole('Editor');

        $result = $this->workflow->transition($submission, 'published', $editor);

        $this->assertFalse($result['ok']);
        $this->assertSame('proof_approval', $submission->fresh()->current_stage);
    }

    public function test_a_managing_editor_can_move_but_never_publish(): void
    {
        $this->workflow->seed();

        [$managing, $submission] = $this->userAtStage('Managing Editor', 'editorial_screening');
        $managing->assignRole('Managing Editor');

        $this->assertTrue($this->workflow->transition($submission, 'reviewer_assignment', $managing)['ok']);
        $this->assertFalse($managing->can('publication.publish'));
    }
}
