<?php

namespace Tests\Feature\Author;

use App\Models\RevisionRound;
use App\Models\Submission;
use App\Models\User;
use App\Services\Permissions\PermissionRegistrar;
use App\Services\Workflow\EditorialWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * §3.1 over HTTP: the author's revision response, and the things an author
 * must never be able to do.
 */
class AuthorRevisionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Author', 'Editor', 'Reviewer', 'Super Admin', 'Managing Editor'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->sync();

        app(EditorialWorkflowService::class)->seed();
    }

    private function authorWithRevisionWindow(?string $due = 'in future'): array
    {
        $author = User::factory()->create();
        $author->assignRole('Author');

        $submission = Submission::factory()->create([
            'author_id' => $author->id,
            'status' => 'revision_requested',
            'decision_due_at' => $due === 'in future' ? now()->addDays(21) : now()->subDays(3),
        ]);

        $round = RevisionRound::create([
            'submission_id' => $submission->id,
            'round_number' => 1,
            'status' => RevisionRound::STATUS_OPEN,
            'opened_by' => $author->id,
            'opened_at' => now()->subDays(2),
            'due_at' => $submission->decision_due_at,
        ]);

        return [$author, $submission, $round];
    }

    // ─────────── permitted actions ───────────

    public function test_the_author_sees_their_revision_record(): void
    {
        [$author, $submission] = $this->authorWithRevisionWindow();

        $this->actingAs($author)
            ->get(route('author.revisions.show', $submission))
            ->assertOk()
            ->assertSee($submission->submission_no);
    }

    public function test_the_author_can_respond_while_the_window_is_open(): void
    {
        [$author, $submission, $round] = $this->authorWithRevisionWindow();

        $this->actingAs($author)
            ->post(route('author.revisions.respond', $submission), [
                'response' => 'Revised the methods and added the missing power analysis (Section 3.2).',
            ])
            ->assertRedirect();

        $this->assertSame(RevisionRound::STATUS_CLOSED, $round->fresh()->status);
        $this->assertStringContainsString(
            'power analysis',
            (string) $round->fresh()->author_response,
        );

        // The response is in the audit trail.
        $this->assertDatabaseHas('manuscript_workflow_events', [
            'submission_id' => $submission->id,
            'event' => 'author_revision_response',
            'actor_id' => $author->id,
        ]);
    }

    public function test_the_author_can_compare_their_versions(): void
    {
        [$author, $submission] = $this->authorWithRevisionWindow();

        $this->actingAs($author)
            ->get(route('author.revisions.compare', $submission))
            ->assertOk();
    }

    // ─────────── forbidden actions ───────────

    public function test_a_stranger_cannot_read_someone_elses_revisions(): void
    {
        [, $submission] = $this->authorWithRevisionWindow();

        $stranger = User::factory()->create();
        $stranger->assignRole('Author');

        $this->actingAs($stranger)
            ->get(route('author.revisions.show', $submission))
            ->assertForbidden();

        $this->actingAs($stranger)
            ->get(route('author.revisions.compare', $submission))
            ->assertForbidden();
    }

    public function test_the_author_cannot_respond_after_the_window_closes(): void
    {
        [$author, $submission] = $this->authorWithRevisionWindow('in the past');

        $this->actingAs($author)
            ->post(route('author.revisions.respond', $submission), [
                'response' => 'Too late, but still sending.',
            ])
            ->assertForbidden();
    }

    public function test_the_author_cannot_record_an_editorial_decision(): void
    {
        [$author, $submission] = $this->authorWithRevisionWindow();

        $this->actingAs($author)
            ->post(route('editor.workspace.decision', $submission), [
                'decision' => 'accepted',
                'message' => 'Self-acceptance is not a thing.',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('editorial_decisions', ['submission_id' => $submission->id]);
    }

    public function test_the_author_cannot_move_the_pipeline(): void
    {
        [$author, $submission] = $this->authorWithRevisionWindow();

        $this->actingAs($author)
            ->post(route('editor.workspace.transition', $submission), [
                'to_stage' => 'published',
                'reason' => 'Publishing myself.',
            ])
            ->assertForbidden();

        $this->assertNotSame('published', $submission->fresh()->current_stage);
    }

    public function test_the_author_cannot_read_the_editorial_audit_log(): void
    {
        [$author, $submission] = $this->authorWithRevisionWindow();

        $this->actingAs($author)
            ->get(route('editor.workspace.audit', $submission))
            ->assertForbidden();
    }
}
