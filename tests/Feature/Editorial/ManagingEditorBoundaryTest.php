<?php

namespace Tests\Feature\Editorial;

use App\Models\ReviewerInvitation;
use App\Models\RevisionRound;
use App\Models\Submission;
use App\Models\User;
use App\Services\Permissions\PermissionRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * §3.4 and §3.7: the Managing Editor ceiling, exercised over HTTP with
 * representative accounts performing both permitted and forbidden actions.
 */
class ManagingEditorBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Author', 'Editor', 'Reviewer', 'Super Admin', 'Managing Editor'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->sync();
    }

    protected function account(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    // ─────────── permitted: workflow coordination ───────────

    public function test_a_managing_editor_sees_the_pipeline_dashboard(): void
    {
        $this->actingAs($this->account('Managing Editor'))
            ->get(route('admin.workflow.index'))
            ->assertOk();
    }

    public function test_a_managing_editor_tracks_reviewers(): void
    {
        $this->actingAs($this->account('Managing Editor'))
            ->get(route('admin.workflow.reviewers'))
            ->assertOk();
    }

    public function test_the_super_admin_runs_the_configuration_screens(): void
    {
        $this->actingAs($this->account('Super Admin'))
            ->get(route('admin.configuration.permissions'))
            ->assertOk();

        $this->get(route('admin.configuration.style-profiles'))
            ->assertOk();

        $this->get(route('admin.configuration.workflow'))
            ->assertOk();

        $this->get(route('admin.configuration.retention'))
            ->assertOk();
    }

    public function test_reminders_are_recorded_by_the_coordinator(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Reviewer');

        $submission = Submission::factory()->underReview()->create();

        $invitation = ReviewerInvitation::create([
            'submission_id' => $submission->id,
            'reviewer_id' => $user->id,
            'invited_at' => now(),
            'due_at' => now()->addDays(10),
            'response' => ReviewerInvitation::RESPONSE_PENDING,
        ]);

        $this->actingAs($this->account('Managing Editor'))
            ->post(route('admin.workflow.invitations.remind', $invitation))
            ->assertRedirect();

        $this->assertSame(1, $invitation->fresh()->reminder_count);
    }

    // ─────────── forbidden: the ceiling ───────────

    public function test_a_managing_editor_cannot_record_an_editorial_decision(): void
    {
        $author = User::factory()->create();
        $author->assignRole('Author');

        $submission = Submission::factory()->underReview()->create(['author_id' => $author->id]);

        $this->actingAs($this->account('Managing Editor'))
            ->post(route('editor.workspace.decision', $submission), [
                'decision' => 'rejected',
                'message' => 'Not suitable.',
            ])
            ->assertForbidden();

        // The decision must not have been written either.
        $this->assertDatabaseMissing('editorial_decisions', ['submission_id' => $submission->id]);
    }

    public function test_a_managing_editor_cannot_reach_the_configuration_screens(): void
    {
        $managing = $this->account('Managing Editor');

        $this->actingAs($managing)->get(route('admin.configuration.permissions'))
            ->assertForbidden();

        $this->actingAs($managing)->get(route('admin.configuration.style-profiles'))
            ->assertForbidden();

        $this->actingAs($managing)->get(route('admin.configuration.workflow'))
            ->assertForbidden();

        $this->actingAs($managing)->get(route('admin.configuration.retention'))
            ->assertForbidden();
    }

    public function test_a_managing_editor_cannot_update_role_permissions(): void
    {
        $role = Role::where('name', 'Reviewer')->first();
        $managing = $this->account('Managing Editor');

        $this->actingAs($managing)
            ->put(route('admin.configuration.permissions.role', $role), [
                'permissions' => ['publication.publish'],
            ])
            ->assertForbidden();

        // Nothing was written.
        $this->assertFalse($role->fresh()->hasPermissionTo('publication.publish'));
    }

    public function test_a_managing_editor_cannot_publish_or_manage_users(): void
    {
        $author = User::factory()->create();
        $author->assignRole('Author');
        $submission = Submission::factory()->underReview()->create(['author_id' => $author->id]);

        $managing = $this->account('Managing Editor');

        // Editors drive publishing through their own controllers; here the
        // policy-based gate blocks the Managing Editor wherever the original
        // admin tools check `publish`.
        $this->assertFalse($managing->can('publish', $submission));
        $this->assertFalse($managing->can('manageRoles', $submission));
        $this->assertFalse($managing->can('manageRetention', $submission));
        $this->assertFalse($managing->can('configureWorkflowSettings', $submission));
    }

    public function test_editorial_review_surfaces_are_read_only_outside_the_reviewer_workspace(): void
    {
        // There is deliberately no editor-side route that writes to `reviews`:
        // a submitted report can only be changed by its reviewer. The editor
        // workspace exposes recommendations but never a store/put/patch on
        // them.
        $reviewWritingRoutes = collect(app()['router']->getRoutes()->getRoutes())
            ->map(fn ($route) => $route->getName())
            ->filter(fn ($name) => $name !== null && str_starts_with($name, 'editor.'))
            ->filter(
                fn ($name) => str_contains($name, 'review') || str_contains($name, 'workspace'),
            );

        $editorPostOnly = collect(app()['router']->getRoutes()->getRoutes())
            ->filter(fn ($route) => $route->getName() !== null && str_starts_with($route->getName(), 'editor.'))
            ->filter(fn ($route) => in_array('POST', $route->methods(), true))
            ->map(fn ($route) => $route->getName());

        // The editor POSTs decisions, assignments and transitions — never
        // modifications to reviewer reports.
        $this->assertNotEmpty($editorPostOnly->all());

        foreach ($editorPostOnly as $name) {
            $this->assertFalse(
                str_contains($name, 'review'),
                "Editor route {$name} must not mutate reviewer reports.",
            );
        }
    }

    public function test_an_editor_cannot_touch_the_configuration_screens(): void
    {
        $editor = $this->account('Editor');

        $this->actingAs($editor)->get(route('admin.configuration.retention'))
            ->assertForbidden();

        $this->actingAs($editor)->get(route('admin.configuration.permissions'))
            ->assertForbidden();
    }

    public function test_an_author_cannot_see_the_workflow_dashboard(): void
    {
        $this->actingAs($this->account('Author'))
            ->get(route('admin.workflow.index'))
            ->assertForbidden();
    }
}
