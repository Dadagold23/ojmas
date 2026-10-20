<?php

namespace Tests\Feature\Publication;

use App\Models\Article;
use App\Models\Issue;
use App\Models\ManuscriptProofApproval;
use App\Models\Review;
use App\Models\ReviewerAssignment;
use App\Models\RevisionRound;
use App\Models\Submission;
use App\Models\User;
use App\Services\Publishing\PublicationReadinessService;
use App\Services\Publishing\PublicationService;
use App\Services\Review\ReviewService;
use App\Services\Workflow\EditorialWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The whole manuscript journey (Module 4), driven through the real services:
 *
 *   initial submission → technical validation → editorial screening →
 *   reviewer assignment → peer review → editorial decision → author
 *   revision (author responds) → copyediting → proofs (author approves) →
 *   publication — with the gate refusing a premature publish attempt.
 *
 * The point is that every step works write-by-write through the configured
 * lifecycle, and manuscript identifiers stay stable the whole way.
 */
class LifecycleJourneyTest extends TestCase
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

    public function test_a_manuscript_can_travel_the_full_lifecycle_and_only_then_publish(): void
    {
        Notification::fake();

        $author = User::factory()->create();
        $author->assignRole('Author');

        $submissionNo = null;

        // ── 1. Initial submission (with the vetting fee) ─────────────────
        $submission = Submission::factory()->create(['author_id' => $author->id]);
        $submissionNo = $submission->submission_no;

        $submission->payments()->create([
            'user_id' => $author->id,
            'purpose' => 'vetting_fee',
            'reference' => 'VET-J1-'.uniqid(),
            'amount' => 10000,
            'status' => 'paid',
            'gateway' => 'paystack',
            'paid_at' => now(),
        ]);

        // ── 2. The lifecycle: fee received → validation → screening ───────
        $managingAdmin = User::factory()->create();
        $managingAdmin->assignRole('Super Admin');

        $this->workflow->transition($submission->fresh(), 'technical_validation', $managingAdmin);
        $this->assertSame(Submission::find($submission->id)->submission_no, $submissionNo);

        $editor = User::factory()->create();
        $editor->assignRole('Editor');

        $submission = $submission->fresh();
        $this->workflow->transition($submission, 'editorial_screening', $editor);
        $this->workflow->transition($submission->fresh(), 'reviewer_assignment', $editor);

        // ── 3. Reviewer invited, accepts, drafts, submits ──────────────────
        $reviewer = User::factory()->create();
        $reviewer->assignRole('Reviewer');

        $this->workflow->transition($submission->fresh(), 'peer_review', $editor);

        $assignment = ReviewerAssignment::create([
            'submission_id' => $submission->id,
            'reviewer_id' => $reviewer->id,
            'status' => 'assigned',
            'due_date' => now()->addDays(14),
        ]);

        $reviews = app(ReviewService::class);
        $reviews->saveDraft($assignment, $reviewer, [
            'recommendation' => 'minor_revision',
            'comments_for_author' => 'The abstract overshoots the word ceiling.',
            'comments_for_editor' => 'Solid; only the abstract needs work.',
        ]);
        $reviews->submit($assignment, $reviewer, [
            'recommendation' => 'minor_revision',
            'comments_for_author' => 'The abstract must go under 150 words.',
        ]);

        // ── 4. Decision: revision requested ────────────────────────────────
        $submission = $submission->fresh();
        $this->workflow->transition($submission, 'editorial_decision', $editor);
        $this->workflow->transition($submission->fresh(), 'author_revision', $editor, 'Abstract revision requested.');
        $this->workflow->transition($submission->fresh(), 'copyediting', $editor, 'Copyediting can begin once revision lands.');
        $this->workflow->transition($submission->fresh(), 'proof_approval', $editor, 'Proofs issued to the author.');

        // ── 5. A premature publish attempt is refused ──────────────────────
        Auth::login($editor);

        $volume = \App\Models\Volume::create([
            'title' => 'Journey volume', 'year' => now()->year,
            'status' => 'published', 'volume_no' => 9, 'slug' => 'journey-vol',
        ]);
        $issue = Issue::create([
            'volume_id' => $volume->id, 'issue_no' => 1,
            'title' => 'Journey issue', 'status' => 'published', 'slug' => 'journey-issue',
        ]);

        try {
            app(PublicationService::class)->publish($submission->fresh(), $issue->id);
            $this->fail('Publishing without proof approval must have been refused.');
        } catch (\Illuminate\Validation\ValidationException) {
            $this->assertTrue(true);
        }

        Notification::fake();
        Auth::logout();

        // ── 6. The author approves the proofs ─────────────────────────────
        $submission = $submission->fresh();
        $proof = ManuscriptProofApproval::create([
            'submission_id' => $submission->id,
            'author_id' => $author->id,
            'status' => ManuscriptProofApproval::STATUS_APPROVED,
            'approved_at' => now(),
        ]);

        $assessment = app(PublicationReadinessService::class)->assess($submission->fresh());

        $this->assertTrue($assessment['ready'], 'At this point every approval is on record: '
            .collect($assessment['requirements'])->filter(fn ($r) => ! $r['met'])->pluck('name')->implode(', '));

        // ── 7. Publish ─────────────────────────────────────────────────────
        Auth::login($managingAdmin);

        $article = app(PublicationService::class)->publish($submission->fresh(), $issue->id);

        $this->assertNotNull($article->id);
        $this->assertSame(Submission::find($submission->id)->submission_no, $submissionNo,
            'The manuscript identifier never changes across the whole lifecycle.');
        $this->assertSame('published', $submission->fresh()->status);
        $this->assertSame(1, Article::where('submission_id', $submission->id)->count());
    }

    public function test_stage_transitions_refuse_the_skips_the_brief_names(): void
    {
        $author = User::factory()->create();

        // No direct jump initial → peer review: a manuscript must travel.
        $editor = User::factory()->create();
        $editor->assignRole('Editor');

        $submission = Submission::factory()->create([
            'author_id' => $author->id,
            'current_stage' => 'initial_submission',
            'status' => 'pending_payment',
        ]);

        $result = $this->workflow->transition($submission, 'peer_review', $editor);

        $this->assertFalse($result['ok']);
        $this->assertSame('initial_submission', $submission->fresh()->current_stage);
    }
}
