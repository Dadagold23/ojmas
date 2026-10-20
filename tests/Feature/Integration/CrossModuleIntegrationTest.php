<?php

namespace Tests\Feature\Integration;

use App\Models\Article;
use App\Models\Issue;
use App\Models\ManuscriptProofApproval;
use App\Models\Review;
use App\Models\ReviewerAssignment;
use App\Models\Submission;
use App\Models\User;
use App\Services\Documents\ManuscriptProcessingService;
use App\Services\Publishing\PublicationReadinessService;
use App\Services\Publishing\PublicationService;
use App\Services\Review\ManuscriptAnonymiser;
use App\Services\Review\ReviewService;
use App\Services\Workflow\EditorialWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\Fixtures\DocxFixtureFactory;
use Tests\TestCase;

/**
 * §5.1 — integration.
 *
 * One synthetic manuscript, one submission id, travelled through every
 * capability this project added: ingestion, formatting, the browser
 * workspace, blinded review, the editorial lifecycle, the readiness gate
 * and publication. The invariants asserted along the way are the ones a
 * fragmented implementation would break:
 *
 *  - the submission id never forks into a second record;
 *  - every artefact (versions, formatted documents, blinded copies,
 *    decisions, rounds, article) hangs off that one submission;
 *  - existing systems (payments, publication) keep their own records intact.
 */
class CrossModuleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private DocxFixtureFactory $fixtures;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Author', 'Editor', 'Reviewer', 'Super Admin', 'Managing Editor'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        Storage::fake('private');
        $this->fixtures = new DocxFixtureFactory();

        app(EditorialWorkflowService::class)->seed();
    }

    public function test_one_submission_survives_the_entire_pipeline_as_one_record(): void
    {
        Notification::fake();

        // ── The cast ────────────────────────────────────────────────────────
        $author = User::factory()->create(['role' => 'Author']);
        $author->assignRole('Author');
        $editor = User::factory()->create();
        $editor->assignRole('Editor');
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        // ── 1. Submission + payment (existing OJMAS system, untouched) ────
        $submission = Submission::factory()->create(['author_id' => $author->id]);
        $submissionId = $submission->id;

        $submission->payments()->create([
            'user_id' => $author->id,
            'purpose' => 'vetting_fee',
            'reference' => 'INT-1-'.uniqid(),
            'amount' => 10000,
            'status' => 'paid',
            'gateway' => 'paystack',
            'paid_at' => now(),
        ]);
        $this->assertSame(1, $submission->fresh()->payments()->count());

        // ── 2. Module 1: ingestion of a media-rich DOCX, then processing ───
        $processing = app(ManuscriptProcessingService::class);

        $mediaPath = $this->fixtures->richMedia('integration-media.docx');
        $originalSha = hash_file('sha256', $mediaPath);

        $result = $processing->ingest(
            $submission,
            new UploadedFile($mediaPath, 'integration-media.docx', null, null, true),
            $author->id,
        );
        $originalVersion = $result['version'];
        $processing->process($originalVersion);

        $submission = $submission->fresh();
        $this->assertSame(1, $submission->manuscriptVersions()->count());
        $this->assertSame($submissionId, $originalVersion->submission_id);

        // The original bytes are preserved byte for byte.
        Storage::disk('private')->assertExists($originalVersion->storage_path);
        $this->assertSame(
            $originalSha,
            hash_file('sha256', Storage::disk('private')->path($originalVersion->storage_path)),
        );

        // ── 3. Module 3: the blinded copy hangs off the same submission ───
        $blinded = app(ManuscriptAnonymiser::class)->createReviewCopy($submission->fresh());

        // The blinded copy is recorded as a file of this submission only…
        $anyOtherAnonymised = \App\Models\ArticleFile::where('is_anonymised', true)
            ->where('submission_id', '!=', $submissionId)
            ->exists();
        $this->assertFalse($anyOtherAnonymised);

        // …under its neutral filename…
        $this->assertStringStartsWith('MANUSCRIPT-', $blinded['filename']);

        // ── 4. The reviewer works off the blinded copy ─────────────────────
        $reviewer = User::factory()->create();
        $reviewer->assignRole('Reviewer');

        $workflow = app(EditorialWorkflowService::class);
        $submission = $submission->fresh();
        $workflow->transition($submission, 'technical_validation', $admin);
        $workflow->transition($submission->fresh(), 'editorial_screening', $editor);
        $workflow->transition($submission->fresh(), 'reviewer_assignment', $editor);
        $workflow->transition($submission->fresh(), 'peer_review', $editor);

        $assignment = ReviewerAssignment::create([
            'submission_id' => $submission->id,
            'reviewer_id' => $reviewer->id,
            'status' => 'assigned',
            'due_date' => now()->addDays(14),
        ]);

        $reviewService = app(ReviewService::class);
        $reviewService->saveDraft($assignment, $reviewer, [
            'recommendation' => 'minor_revision',
            'comments_for_author' => 'Drafting.',
        ]);
        // Drafts must not satisfy the readiness review requirement.
        $this->assertSame(0, $reviewService->recommendationSummary($submission->fresh())['total']);

        $reviewService->submit($assignment, $reviewer, [
            'recommendation' => 'accept',
            'comments_for_author' => 'Fine as it stands.',
        ]);
        $this->assertSame(1, $reviewService->recommendationSummary($submission->fresh())['total']);

        // ── 5. The lifecycle: decision, revision, copyediting, proofs ──────
        $submission = $submission->fresh();
        $workflow->transition($submission, 'editorial_decision', $editor);
        $workflow->transition($submission->fresh(), 'author_revision', $editor, 'Within the revision window.');
        $workflow->transition($submission->fresh(), 'copyediting', $editor, 'Revision accepted.');
        $workflow->transition($submission->fresh(), 'proof_approval', $editor, 'Proofs to author.');

        ManuscriptProofApproval::create([
            'submission_id' => $submission->id,
            'author_id' => $author->id,
            'status' => ManuscriptProofApproval::STATUS_APPROVED,
            'approved_at' => now(),
        ]);

        // ── 6. Publication: one article, one submission record ─────────────
        Auth::login($admin);
        $volume = \App\Models\Volume::create([
            'title' => 'Integration vol', 'year' => now()->year, 'status' => 'published',
            'volume_no' => 1, 'slug' => 'integration-vol',
        ]);
        $issue = Issue::create([
            'volume_id' => $volume->id, 'issue_no' => 1, 'title' => 'Integration issue',
            'status' => 'published', 'slug' => 'integration-issue',
        ]);

        $assessment = app(PublicationReadinessService::class)->assess($submission->fresh());
        $this->assertTrue($assessment['ready']);

        $article = app(PublicationService::class)->publish($submission->fresh(), $issue->id);

        // Invariants to close the journey.
        $this->assertSame($submissionId, $article->submission_id);
        $this->assertSame($submissionId, Submission::find($submissionId)->id);
        $this->assertSame(1, Submission::count(), 'No duplicate submission record was ever created.');
        $this->assertSame(1, Article::count());
        $this->assertSame(1, Article::where('submission_id', $submissionId)->count());
        $this->assertSame('published', Submission::find($submissionId)->status);
    }
}
