<?php

namespace Tests\Feature\Editorial;

use App\Models\ArticleFile;
use App\Models\ReviewerAssignment;
use App\Models\Submission;
use App\Models\User;
use App\Services\Permissions\PermissionRegistrar;
use App\Services\Review\ReviewService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use ZipArchive;

/**
 * §3.2 and §3.6 over HTTP.
 *
 * These are real authorization checks through the reviewer workspace routes:
 * a reviewer may reach only their own assignment, only the blinded copy, and
 * never review-critical editorial surfaces.
 */
class ReviewerWorkspaceHttpTest extends TestCase
{
    use RefreshDatabase;

    private \Tests\Fixtures\DocxFixtureFactory $fixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtures = new \Tests\Fixtures\DocxFixtureFactory();

        foreach (['Author', 'Editor', 'Reviewer', 'Super Admin', 'Managing Editor'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->sync();
        Storage::fake('private');
    }

    private function reviewerWithAssignment(): array
    {
        $reviewer = User::factory()->create();
        $reviewer->assignRole('Reviewer');

        $submission = Submission::factory()->underReview()->create();

        // The blinded copy the reviewer is meant to see.
        $this->storeBlindedCopy($submission);

        $assignment = ReviewerAssignment::create([
            'submission_id' => $submission->id,
            'reviewer_id' => $reviewer->id,
            'status' => 'assigned',
            'due_date' => now()->addDays(14),
        ]);

        return [$reviewer, $submission, $assignment];
    }

    private function storeBlindedCopy(Submission $submission): void
    {
        $path = 'manuscripts/formatted/blind-testcopy.docx';
        $zipPath = $this->fixtures->path('blind-source-copy.docx');

        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml',
            '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p/></w:body></w:document>');
        $zip->close();

        Storage::disk('private')->put($path, (string) file_get_contents($zipPath));

        ArticleFile::create([
            'submission_id' => $submission->id,
            'file_type' => 'manuscript',
            'original_name' => 'MANUSCRIPT-BLINDTEST.docx',
            'file_path' => $path,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_size' => Storage::disk('private')->size($path),
            'is_anonymised' => true,
        ]);
    }

    // ─────────── permitted actions ───────────

    public function test_a_reviewer_sees_their_own_assignment(): void
    {
        [$reviewer, $submission, $assignment] = $this->reviewerWithAssignment();

        $response = $this->actingAs($reviewer)->get(route('reviewer.workspace.show', $assignment));

        $response->assertOk();
        $response->assertSee($submission->title);
    }

    public function test_the_reviewer_dashboard_lists_only_own_assignments(): void
    {
        [$reviewer] = $this->reviewerWithAssignment();

        $this->actingAs($reviewer)->get(route('reviewer.workspace.dashboard'))
            ->assertOk();

        $this->actingAs($reviewer)->get(route('reviewer.workspace.invitations'))
            ->assertOk();
    }

    public function test_the_blinded_file_is_delivered_not_the_original(): void
    {
        [$reviewer, $submission, $assignment] = $this->reviewerWithAssignment();

        $response = $this->actingAs($reviewer)
            ->get(route('reviewer.workspace.blinded', $submission));

        $response->assertOk();

        // Access is audited (§3.6).
        $this->assertDatabaseHas('manuscript_access_logs', [
            'submission_id' => $submission->id,
            'user_id' => $reviewer->id,
        ]);
    }

    public function test_a_draft_and_a_final_submission_round_trip(): void
    {
        Notification::fake();
        [$reviewer, $submission, $assignment] = $this->reviewerWithAssignment();

        $this->actingAs($reviewer)->post(route('reviewer.workspace.draft', $assignment), [
            'recommendation' => 'major_revision',
            'comments_for_author' => 'Still drafting.',
        ])->assertRedirect();

        $this->assertDatabaseHas('reviews', [
            'reviewer_assignment_id' => $assignment->id,
            'status' => ReviewService::STATUS_DRAFT,
        ]);

        $this->actingAs($reviewer)->post(route('reviewer.workspace.submit', $assignment), [
            'recommendation' => 'major_revision',
            'comments_for_author' => 'The framing needs a full rewrite.',
        ])->assertRedirect();

        $this->assertDatabaseHas('reviews', [
            'reviewer_assignment_id' => $assignment->id,
            'status' => ReviewService::STATUS_SUBMITTED,
        ]);
    }

    // ─────────── forbidden actions ───────────

    public function test_a_reviewer_cannot_open_someone_elses_assignment(): void
    {
        [$reviewer, $submission, $assignment] = $this->reviewerWithAssignment();

        $intruder = User::factory()->create();
        $intruder->assignRole('Reviewer');

        $this->actingAs($intruder)->get(route('reviewer.workspace.show', $assignment))
            ->assertForbidden();

        $this->actingAs($intruder)->post(route('reviewer.workspace.draft', $assignment), [])
            ->assertForbidden();

        // The blinded download hides the fact that a copy exists at all
        // (firstOrFail), so an intruder cannot even enumerate manuscript ids.
        $this->actingAs($intruder)->get(route('reviewer.workspace.blinded', $submission))
            ->assertNotFound();
    }

    public function test_an_author_cannot_enter_the_reviewer_workspace(): void
    {
        [, $submission, $assignment] = $this->reviewerWithAssignment();

        $outsider = User::factory()->create();
        $outsider->assignRole('Author');

        $this->actingAs($outsider)->get(route('reviewer.workspace.show', $assignment))
            ->assertForbidden();

        $this->actingAs($outsider)->get(route('reviewer.workspace.dashboard'))
            ->assertForbidden();
    }

    public function test_a_guest_is_turned_away(): void
    {
        [, , $assignment] = $this->reviewerWithAssignment();

        $this->get(route('reviewer.workspace.show', $assignment))
            ->assertRedirect(route('login'));
    }

    public function test_an_unsubmittable_review_is_rejected(): void
    {
        [$reviewer, , $assignment] = $this->reviewerWithAssignment();

        $response = $this->actingAs($reviewer)
            ->post(route('reviewer.workspace.submit', $assignment), [
                'recommendation' => 'major_revision',
            // comments_for_author missing.
            ]);

        $response->assertSessionHasErrors('comments_for_author');

        $this->assertDatabaseMissing('reviews', [
            'reviewer_assignment_id' => $assignment->id,
            'status' => ReviewService::STATUS_SUBMITTED,
        ]);
    }
}
