<?php

namespace Tests\Feature\Publishing;

use App\Models\Article;
use App\Models\Issue;
use App\Models\ManuscriptProofApproval;
use App\Models\Review;
use App\Models\ReviewerAssignment;
use App\Models\Submission;
use App\Models\User;
use App\Services\Workflow\EditorialWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The admin publish form the journal restored (commit d983088) used to create a
 * standalone PUB-* submission, which could never pass the Module 4 readiness
 * gate — and the editor publish route used to write an Article directly,
 * bypassing the gate entirely. Both entry points now publish existing pipeline
 * manuscripts through PublicationService, so the readiness gate is reached
 * from every UI surface.
 *
 * Readiness-gate fixtures mirror ReadinessGateTest.
 */
class PublicationPipelineRoutingTest extends TestCase
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

    /** A manuscript at an advanced stage with the vetting fee settled. */
    private function submissionAtStage(string $stage, string $status): Submission
    {
        $author = User::factory()->create();
        $author->assignRole('Author');

        $submission = Submission::factory()->create([
            'author_id' => $author->id,
            'current_stage' => $stage,
            'status' => $status,
        ]);

        $submission->payments()->create([
            'user_id' => $author->id,
            'purpose' => 'vetting_fee',
            'reference' => 'VET-TEST-'.uniqid(),
            'amount' => 10000,
            'status' => 'paid',
            'gateway' => 'paystack',
            'paid_at' => now(),
        ]);

        return $submission;
    }

    /** Brings the submission to every requirement of the readiness gate. */
    private function makeReady(Submission $submission): void
    {
        $submission->payments()->create([
            'user_id' => $submission->author_id,
            'purpose' => 'publication_fee',
            'reference' => 'PUB-TEST-'.uniqid(),
            'amount' => 25000,
            'status' => 'paid',
            'gateway' => 'paystack',
            'paid_at' => now(),
        ]);

        $submission->editorialDecisions()->create([
            'editor_id' => 1,
            'decision' => 'accepted',
            'message' => 'Accepted after the revision.',
        ]);

        $reviewer = User::factory()->create();
        $reviewer->assignRole('Reviewer');

        $submission->reviewerAssignments()->create([
            'reviewer_id' => $reviewer->id,
            'status' => 'completed',
        ]);

        Review::create([
            'reviewer_assignment_id' => ReviewerAssignment::where('submission_id', $submission->id)->first()->id,
            'recommendation' => 'accept',
            'comments_for_author' => 'Fine as revised.',
            'status' => Review::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        ManuscriptProofApproval::create([
            'submission_id' => $submission->id,
            'author_id' => $submission->author_id,
            'status' => ManuscriptProofApproval::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
    }

    private function issue(): Issue
    {
        $volume = \App\Models\Volume::create([
            'title' => 'Volume pipeline',
            'year' => now()->year,
            'status' => 'published',
            'volume_no' => 7,
            'slug' => 'vol-pipeline-'.uniqid(),
        ]);

        return Issue::create([
            'volume_id' => $volume->id,
            'issue_no' => 1,
            'title' => 'Pipeline Issue',
            'status' => 'published',
            'slug' => 'pipeline-'.uniqid(),
        ]);
    }

    // ─────────── the admin publish form ───────────

    public function test_the_admin_publish_form_lists_pipeline_manuscripts(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $submission = $this->submissionAtStage('copyediting', 'accepted');

        // Already-published manuscripts are not offered again.
        $publishedSubmission = $this->submissionAtStage('proof_approval', 'accepted');
        $publishedSubmission->payments()->create([
            'user_id' => $publishedSubmission->author_id,
            'purpose' => 'publication_fee',
            'reference' => 'PUB-TEST-'.uniqid(),
            'amount' => 25000,
            'status' => 'paid',
            'gateway' => 'paystack',
            'paid_at' => now(),
        ]);
        $publishedSubmission->editorialDecisions()->create([
            'editor_id' => 1, 'decision' => 'accepted', 'message' => 'Accepted.',
        ]);
        Article::create([
            'submission_id' => $publishedSubmission->id,
            'issue_id' => $this->issue()->id,
            'title' => $publishedSubmission->title,
            'slug' => 'is-published-'.uniqid(),
            'abstract' => $publishedSubmission->abstract ?? 'Already published fixture abstract.',
            'published_on' => now(),
            'status' => 'published',
        ]);
        // Mirror a real publication: the pipeline sets the submission status.
        $publishedSubmission->update(['status' => 'published']);

        $response = $this->actingAs($admin)->get(route('admin.publications.create'));

        $response->assertOk();
        $response->assertSee($submission->submission_no);
        $response->assertDontSee($publishedSubmission->submission_no);
    }

    public function test_the_admin_publish_form_publishes_through_the_gate(): void
    {
        Auth::shouldUse('web');

        $submission = $this->submissionAtStage('proof_approval', 'accepted');
        $this->makeReady($submission);

        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $response = $this->actingAs($admin)->post(route('admin.publications.store'), [
            'submission_id' => $submission->id,
            'issue_id' => $this->issue()->id,
            'notify_author' => '1',
        ]);

        $response->assertRedirect(route('admin.publications.index'));
        $response->assertSessionHas('success');

        // The pipeline manuscript itself was published — no fresh PUB- record.
        $this->assertDatabaseHas('articles', ['submission_id' => $submission->id]);
        $this->assertSame('published', $submission->fresh()->status);

        // The publication record is present and audited.
        $article = Article::where('submission_id', $submission->id)->first();
        $this->assertDatabaseHas('article_publications', ['article_id' => $article->id]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'published_article',
            'loggable_id' => $article->id,
        ]);
    }

    public function test_the_admin_publish_form_is_refused_while_approvals_are_missing(): void
    {
        $submission = $this->submissionAtStage('copyediting', 'accepted');

        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $response = $this->actingAs($admin)->post(route('admin.publications.store'), [
            'submission_id' => $submission->id,
            'issue_id' => $this->issue()->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors();

        $messages = collect(session('errors')->all())->flatten()->implode(' ');

        $this->assertStringContainsString('reviewer report', $messages);
        $this->assertStringContainsString('proof', $messages);

        // Nothing was published, and the refusal is on the workflow log.
        $this->assertDatabaseMissing('articles', ['submission_id' => $submission->id]);
        $this->assertDatabaseHas('manuscript_workflow_events', [
            'submission_id' => $submission->id,
            'event' => 'publication_refused',
        ]);
    }

    public function test_the_admin_publish_form_may_not_publish_a_manuscript_twice(): void
    {
        $submission = $this->submissionAtStage('proof_approval', 'accepted');
        $this->makeReady($submission);

        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $issueId = $this->issue()->id;

        $this->actingAs($admin)->post(route('admin.publications.store'), [
            'submission_id' => $submission->id,
            'issue_id' => $issueId,
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.publications.store'), [
            'submission_id' => $submission->id,
            'issue_id' => $issueId,
        ]);

        $this->assertSame(1, Article::where('submission_id', $submission->id)->count());
        $this->assertSame(1, \App\Models\ArticlePublication::count());
    }

    // ─────────── the editor publish route ───────────

    public function test_the_editor_publish_route_is_refused_until_the_gate_passes(): void
    {
        $submission = $this->submissionAtStage('copyediting', 'accepted');

        $submission->payments()->create([
            'user_id' => $submission->author_id,
            'purpose' => 'publication_fee',
            'reference' => 'PUB-TEST-'.uniqid(),
            'amount' => 25000,
            'status' => 'paid',
            'gateway' => 'paystack',
            'paid_at' => now(),
        ]);

        $editor = User::factory()->create();
        $editor->assignRole('Editor');

        $response = $this->actingAs($editor)->post(
            route('editor.submissions.publish', $submission),
            ['issue_id' => $this->issue()->id]
        );

        $response->assertRedirect();
        $response->assertSessionHasErrors();

        $messages = collect(session('errors')->all())->flatten()->implode(' ');
        $this->assertStringContainsString('reviewer report', $messages);

        $this->assertDatabaseMissing('articles', ['submission_id' => $submission->id]);
        $this->assertNotSame('published', $submission->fresh()->status);
    }

    public function test_the_editor_publish_route_publishes_through_the_gate(): void
    {
        $submission = $this->submissionAtStage('proof_approval', 'accepted');
        $this->makeReady($submission);

        $editor = User::factory()->create();
        $editor->assignRole('Editor');

        $publishedOn = now()->subDay()->toDateString();

        $response = $this->actingAs($editor)->post(
            route('editor.submissions.publish', $submission),
            [
                'issue_id' => $this->issue()->id,
                'published_on' => $publishedOn,
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $article = Article::where('submission_id', $submission->id)->first();

        $this->assertNotNull($article, 'Publishing through the gate must create the article.');
        $this->assertSame($publishedOn, $article->published_on->toDateString());
        $this->assertSame('published', $submission->fresh()->status);

        // The legacy side-effects are preserved: status history in the old
        // status → new status direction.
        $this->assertDatabaseHas('submission_status_histories', [
            'submission_id' => $submission->id,
            'old_status' => 'accepted',
            'new_status' => 'published',
        ]);
    }
}
