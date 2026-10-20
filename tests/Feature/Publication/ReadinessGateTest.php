<?php

namespace Tests\Feature\Publication;

use App\Models\EditorialStage;
use App\Models\EditorialDecision;
use App\Models\Issue;
use App\Models\ManuscriptProofApproval;
use App\Models\Review;
use App\Models\ReviewerAssignment;
use App\Models\RevisionRound;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\ProofApprovalNotification;
use App\Services\Publishing\PublicationReadinessService;
use App\Services\Publishing\PublicationService;
use App\Services\Workflow\EditorialWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * §4.39: "Do not allow a manuscript to be published simply because an editor
 * has finished editing it. Publication must follow the required editorial
 * approvals."
 *
 * The gate sits inside PublicationService, so every publish path — editor
 * tool or admin tool — inherits it.
 */
class ReadinessGateTest extends TestCase
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

    private function submissionAtStage(string $stage, string $status): Submission
    {
        $author = User::factory()->create();
        $author->assignRole('Author');

        $submission = Submission::factory()->create([
            'author_id' => $author->id,
            'current_stage' => $stage,
            'status' => $status,
        ]);

        // A confirmed vetting payment: the journal's own record of it.
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

    /** Issues belong to volumes; tests need a real one. */
    private function issue(int $number, string $slug): Issue
    {
        $volume = \App\Models\Volume::create([
            'title' => 'Volume for '.$slug,
            'year' => now()->year,
            'status' => 'published',
            'volume_no' => $number,
            'slug' => 'vol-'.$slug,
        ]);

        return Issue::create([
            'volume_id' => $volume->id,
            'issue_no' => $number,
            'title' => 'Issue '.$number,
            'status' => 'published',
            'slug' => $slug,
        ]);
    }

    public function test_a_freshly_submitted_manuscript_is_not_ready(): void
    {
        $submission = $this->submissionAtStage('initial_submission', 'pending_payment');

        $assessment = app(PublicationReadinessService::class)->assess($submission);

        $this->assertFalse($assessment['ready']);

        $unmet = collect($assessment['requirements'])->filter(fn ($r) => ! $r['met'])->pluck('name')->all();

        foreach ([
            'Editorial decision recorded',
            'Peer review concluded',
            'Copyediting completed',
            'Author proof approval',
        ] as $name) {
            $this->assertContains($name, $unmet, "$name should be unmet at the start of the lifecycle.");
        }
    }

    public function test_publishing_is_refused_until_editorial_approvals_exist(): void
    {
        Notification::fake();

        $submission = $this->submissionAtStage('copyediting', 'accepted');

        $issue = $this->issue(1, 'test-issue');

        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        Auth::login($admin);

        try {
            app(PublicationService::class)->publish($submission, $issue->id);
            $this->fail('Publication should be refused without the required approvals.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $messages = collect($exception->errors()['readiness'] ?? [])->flatten()->implode(' ');

            $this->assertStringContainsString('reviewer report', $messages);
            $this->assertStringContainsString('proof', $messages);
        }

        // The refusal is in the workflow log, not silently swallowed.
        $this->assertDatabaseHas('manuscript_workflow_events', [
            'submission_id' => $submission->id,
            'event' => 'publication_refused',
        ]);

        // And nothing was published.
        $this->assertDatabaseMissing('articles', ['submission_id' => $submission->id]);
        $this->assertNotSame('published', $submission->fresh()->status);
    }

    public function test_publishing_succeeds_once_every_approval_is_on_record(): void
    {
        Notification::fake();
        Auth::login(User::factory()->create(['role' => 'Super Admin']));

        $submission = $this->submissionAtStage('proof_approval', 'accepted');
        $submission->current_stage = 'published';

        // Editorial decision, accepted.
        $submission->editorialDecisions()->create([
            'editor_id' => 1,
            'decision' => 'accepted',
            'message' => 'Accepted after the revision.',
        ]);

        // A submitted reviewer report.
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

        // An author proof approval.
        ManuscriptProofApproval::create([
            'submission_id' => $submission->id,
            'author_id' => $submission->author_id,
            'status' => ManuscriptProofApproval::STATUS_APPROVED,
            'approved_at' => now(),
        ]);

        $assessment = app(PublicationReadinessService::class)->assess($submission->fresh());
        $this->assertTrue($assessment['ready']);

        $issue = $this->issue(2, 'vol4-issue1');

        $article = app(PublicationService::class)->publish(
            $submission->fresh(),
            $issue->id,
        );

        $this->assertNotNull($article->id);
        $this->assertDatabaseHas('articles', ['submission_id' => $submission->id]);
        $this->assertSame('published', $submission->fresh()->status);
    }

    public function test_an_open_revision_round_blocks_even_a_stage_adherent_paper(): void
    {
        $submission = $this->submissionAtStage('proof_approval', 'accepted');

        // Everything else in place…
        $submission->editorialDecisions()->create([
            'editor_id' => 1,
            'decision' => 'accepted',
            'message' => 'Accepted.',
        ]);

        $reviewer = User::factory()->create();
        $reviewer->assignRole('Reviewer');
        $submission->reviewerAssignments()->create(['reviewer_id' => $reviewer->id, 'status' => 'completed']);

        $assignment = ReviewerAssignment::where('submission_id', $submission->id)->first();
        Review::create([
            'reviewer_assignment_id' => $assignment->id,
            'recommendation' => 'accept',
            'comments_for_author' => 'Fine.',
            'status' => Review::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        ManuscriptProofApproval::create([
            'submission_id' => $submission->id,
            'author_id' => $submission->author_id,
            'status' => ManuscriptProofApproval::STATUS_APPROVED,
            'approved_at' => now(),
        ]);

        // …but the author is still expected to answer an open revision round.
        RevisionRound::create([
            'submission_id' => $submission->id,
            'round_number' => 2,
            'status' => RevisionRound::STATUS_OPEN,
            'opened_by' => 1,
            'opened_at' => now(),
            'due_at' => now()->addDays(10),
        ]);

        $assessment = app(PublicationReadinessService::class)->assess($submission->fresh());

        $this->assertFalse($assessment['ready']);
        $this->assertStringContainsString(
            'still open',
            collect($assessment['requirements'])->firstWhere('name', 'Revision rounds closed')['detail'],
        );
    }

    public function test_forms_without_copyediting_are_detected_from_the_stage_record(): void
    {
        $submission = $this->submissionAtStage('accepted_placeholder_never', 'accepted');

        // Simulate a submission whose workflow events show copyediting was
        // passed, whatever current_stage says.
        $this->assertNotNull(EditorialStage::where('slug', 'copyediting')->first());

        app(EditorialWorkflowService::class)->log(
            $submission,
            null,
            'stage_transition',
            'copyediting',
            'proof_approval',
            ['transition' => 'Copyediting complete'],
        );

        $assessment = app(PublicationReadinessService::class)->assess($submission);

        $copyedited = collect($assessment['requirements'])->firstWhere('name', 'Copyediting completed');
        $this->assertTrue($copyedited['met']);
    }
}
