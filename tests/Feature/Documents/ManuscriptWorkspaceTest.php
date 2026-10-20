<?php

namespace Tests\Feature\Documents;

use App\Models\ManuscriptAccessLog;
use App\Models\ManuscriptValidation;
use App\Models\ReviewerAssignment;
use App\Models\Submission;
use App\Models\User;
use App\Services\Documents\ManuscriptProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\Fixtures\DocxFixtureFactory;
use Tests\TestCase;

class ManuscriptWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private DocxFixtureFactory $fixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');

        $this->fixtures = new DocxFixtureFactory();

        foreach (['Author', 'Editor', 'Reviewer', 'Super Admin', 'Managing Editor', 'Admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function author(): User
    {
        $user = User::factory()->create(['role' => 'Author']);
        $user->assignRole('Author');

        return $user;
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->assignRole($role);

        return $user;
    }

    private function submissionWithFormattedManuscript(string $fixture = 'nonCompliant'): Submission
    {
        $author = $this->author();
        $submission = Submission::factory()->create(['author_id' => $author->id]);

        $path = $this->fixtures->{$fixture}('workspace-'.$fixture.'.docx');
        $upload = new UploadedFile($path, 'manuscript.docx', null, null, true);

        $version = app(ManuscriptProcessingService::class)->ingest($submission, $upload, $author->id)['version'];
        app(ManuscriptProcessingService::class)->process($version);

        return $submission->fresh();
    }

    // ─────────── access control ───────────

    public function test_the_author_can_open_their_own_workspace(): void
    {
        $submission = $this->submissionWithFormattedManuscript();
        $author = $submission->author;

        $this->actingAs($author)
            ->get(route('author.manuscripts.show', $submission))
            ->assertOk();
    }

    public function test_another_author_cannot_open_someone_elses_workspace(): void
    {
        $submission = $this->submissionWithFormattedManuscript();
        $intruder = $this->author();

        $this->actingAs($intruder)
            ->get(route('author.manuscripts.show', $submission))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->get(route('author.manuscripts.download', $submission))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->post(route('author.manuscripts.approve', $submission))
            ->assertForbidden();
    }

    public function test_an_unassigned_reviewer_cannot_open_a_manuscript(): void
    {
        // Regression guard for audit finding B5: reviewer access used to be
        // granted to any account holding the Reviewer role.
        $submission = $this->submissionWithFormattedManuscript();
        $reviewer = $this->userWithRole('Reviewer');

        $this->actingAs($reviewer)
            ->get(route('author.manuscripts.show', $submission))
            ->assertForbidden();
    }

    public function test_an_assigned_reviewer_is_granted_access_at_the_policy_level_only(): void
    {
        $submission = $this->submissionWithFormattedManuscript();
        $reviewer = $this->userWithRole('Reviewer');

        ReviewerAssignment::create([
            'submission_id' => $submission->id,
            'reviewer_id' => $reviewer->id,
            'status' => 'assigned',
        ]);

        // Holding an assignment grants read access to this one manuscript…
        $this->assertTrue(Gate::forUser($reviewer)->allows('viewReport', $submission));
        $this->assertTrue(Gate::forUser($reviewer)->allows('downloadFormatted', $submission));

        // …but never the authority to approve it.
        $this->assertFalse(Gate::forUser($reviewer)->allows('approve', $submission));
        $this->assertFalse(Gate::forUser($reviewer)->allows('runFormatting', $submission));

        // The author-facing route group is a separate surface; reviewers have
        // their own workspace and must not reach the author area at all.
        $this->actingAs($reviewer)
            ->get(route('author.manuscripts.show', $submission))
            ->assertForbidden();
    }

    public function test_an_editor_may_run_formatting_but_not_approve_on_the_authors_behalf(): void
    {
        $submission = $this->submissionWithFormattedManuscript();
        $editor = $this->userWithRole('Editor');

        $this->assertTrue(Gate::forUser($editor)->allows('viewReport', $submission));
        $this->assertTrue(Gate::forUser($editor)->allows('runFormatting', $submission));
        $this->assertFalse(
            Gate::forUser($editor)->allows('approve', $submission),
            'An editor must not be able to approve a manuscript on the author\'s behalf.',
        );

        // And the author route itself refuses the editor.
        $this->actingAs($editor)
            ->post(route('author.manuscripts.approve', $submission))
            ->assertForbidden();
    }

    public function test_a_report_from_another_submission_cannot_be_read(): void
    {
        $submission = $this->submissionWithFormattedManuscript();
        $other = $this->submissionWithFormattedManuscript('compliant');

        $validation = ManuscriptValidation::where('submission_id', $other->id)->latest()->firstOrFail();

        $this->actingAs($submission->author)
            ->get(route('author.manuscripts.report', [$submission, $validation]))
            ->assertNotFound();
    }

    public function test_guests_are_redirected(): void
    {
        $submission = $this->submissionWithFormattedManuscript();

        $this->get(route('author.manuscripts.show', $submission))->assertRedirect();
    }

    // ─────────── workflow ───────────

    public function test_the_author_can_run_the_formatting_check(): void
    {
        $author = $this->author();
        $submission = Submission::factory()->create(['author_id' => $author->id]);

        $version = app(ManuscriptProcessingService::class)->ingest(
            $submission,
            new UploadedFile($this->fixtures->nonCompliant('run-check.docx'), 'manuscript.docx', null, null, true),
            $author->id,
        )['version'];

        $response = $this->actingAs($author)
            ->post(route('author.manuscripts.format', $submission));

        $response->assertRedirect(route('author.manuscripts.show', $submission));
        $response->assertSessionHas('success');

        $validation = ManuscriptValidation::where('submission_id', $submission->id)->latest()->firstOrFail();
        $this->assertGreaterThan(0, $validation->auto_fixed_count);
        $this->assertGreaterThan(0, $validation->error_count);
    }

    public function test_formatting_does_not_change_the_submission_status(): void
    {
        $author = $this->author();
        $submission = Submission::factory()->create(['author_id' => $author->id, 'status' => 'submitted']);

        $this->actingAs($author)->post(route('author.manuscripts.format', $submission));

        $this->assertSame('submitted', $submission->fresh()->status);
    }

    public function test_the_author_can_download_the_formatted_document(): void
    {
        $submission = $this->submissionWithFormattedManuscript();

        $response = $this->actingAs($submission->author)
            ->get(route('author.manuscripts.download', $submission));

        $response->assertOk();
        $response->assertDownload('OJMAS-'.$submission->submission_no.'-formatted.docx');
    }

    public function test_download_is_refused_before_a_formatting_run(): void
    {
        $author = $this->author();
        $submission = Submission::factory()->create(['author_id' => $author->id]);

        $this->actingAs($author)
            ->get(route('author.manuscripts.download', $submission))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_the_author_can_approve_their_manuscript(): void
    {
        $submission = $this->submissionWithFormattedManuscript();
        $author = $submission->author;

        $this->actingAs($author)
            ->from(route('author.manuscripts.show', $submission))
            ->post(route('author.manuscripts.approve', $submission))
            ->assertRedirect(route('author.manuscripts.show', $submission))
            ->assertSessionHas('success');

        $validation = ManuscriptValidation::where('submission_id', $submission->id)->latest()->firstOrFail();

        $this->assertTrue($validation->isApproved());
        $this->assertSame($author->id, $validation->approved_by);
        $this->assertNotNull($validation->approved_at);
    }

    public function test_approval_is_recorded_in_the_status_history(): void
    {
        $submission = $this->submissionWithFormattedManuscript();

        $this->actingAs($submission->author)
            ->post(route('author.manuscripts.approve', $submission));

        $this->assertDatabaseHas('submission_status_histories', [
            'submission_id' => $submission->id,
            'changed_by' => $submission->author_id,
        ]);
    }

    public function test_approval_requires_a_completed_run(): void
    {
        $author = $this->author();
        $submission = Submission::factory()->create(['author_id' => $author->id]);

        $this->actingAs($author)
            ->from(route('author.manuscripts.show', $submission))
            ->post(route('author.manuscripts.approve', $submission))
            ->assertSessionHas('error');
    }

    public function test_the_full_report_page_renders(): void
    {
        $submission = $this->submissionWithFormattedManuscript();
        $validation = ManuscriptValidation::where('submission_id', $submission->id)->latest()->firstOrFail();

        $this->actingAs($submission->author)
            ->get(route('author.manuscripts.report', [$submission, $validation]))
            ->assertOk()
            ->assertSee('Issues you must resolve')
            ->assertSee('Corrected automatically');
    }

    // ─────────── auditing ───────────

    public function test_viewing_and_downloading_are_audited(): void
    {
        $submission = $this->submissionWithFormattedManuscript();
        $author = $submission->author;

        ManuscriptAccessLog::query()->delete();

        $this->actingAs($author)->get(route('author.manuscripts.show', $submission));
        $this->actingAs($author)->get(route('author.manuscripts.download', $submission));

        $this->assertSame(2, ManuscriptAccessLog::where('submission_id', $submission->id)->count());
        $this->assertSame(1, ManuscriptAccessLog::where('action', 'download')->count());
        $this->assertSame($author->id, ManuscriptAccessLog::first()->user_id);
    }

    public function test_a_denied_access_does_not_produce_an_audit_entry(): void
    {
        $submission = $this->submissionWithFormattedManuscript();

        ManuscriptAccessLog::query()->delete();

        $this->actingAs($this->author())->get(route('author.manuscripts.show', $submission));

        $this->assertSame(0, ManuscriptAccessLog::count());
    }

    // ─────────── existing features still work ───────────

    public function test_the_original_submission_page_still_works(): void
    {
        $submission = $this->submissionWithFormattedManuscript();

        $this->actingAs($submission->author)
            ->get(route('author.submissions.show', $submission))
            ->assertOk();
    }

    public function test_the_author_dashboard_still_works(): void
    {
        $this->submissionWithFormattedManuscript();

        $this->actingAs(User::where('email', $this->author()->email)->first())
            ->get(route('author.dashboard'))
            ->assertOk();
    }
}