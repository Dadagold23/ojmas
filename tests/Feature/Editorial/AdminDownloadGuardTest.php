<?php

namespace Tests\Feature\Editorial;

use App\Models\ArticleFile;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use ZipArchive;

/**
 * Audit fixes verified here:
 *
 * S4 — the admin download route used to silently substitute a *different*
 * file when the file id in the URL did not belong to the submission in the
 * URL (a confused-deputy pattern with non-deterministic results). It must
 * now 404 on mismatch, and still serve the matched file normally.
 */
class AdminDownloadGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);

        Storage::fake('private');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
    }

    private function submissionWithFile(string $diskPath, string $content): array
    {
        Storage::disk('private')->put($diskPath, $content);

        $submission = Submission::factory()->create();

        $file = ArticleFile::create([
            'submission_id' => $submission->id,
            'file_type' => 'manuscript',
            'original_name' => 'MANUSCRIPT-TEST.docx',
            'file_path' => $diskPath,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_size' => Storage::disk('private')->size($diskPath),
        ]);

        return [$submission, $file];
    }

    public function test_the_matched_file_downloads_normally_for_admin(): void
    {
        [$submission, $file] = $this->submissionWithFile(
            'submissions/manuscript/test-a.docx', 'A-natural-manuscript-content',
        );

        $response = $this->actingAs($this->admin)
            ->get(route('admin.submissions.files.download', ['submission' => $submission, 'file' => $file]));

        $response->assertOk();
        $this->assertSame('A-natural-manuscript-content', $response->streamedContent());
    }

    public function test_a_file_from_another_submission_is_never_substituted(): void
    {
        [$submissionA, $fileA] = $this->submissionWithFile(
            'submissions/manuscript/test-a.docx', 'A-natural-manuscript-content',
        );
        [$submissionB, $fileB] = $this->submissionWithFile(
            'submissions/manuscript/test-b.docx', 'B-different-content',
        );

        // B's file id, walked in through A's URL — the exact S4 shape.
        $response = $this->actingAs($this->admin)
            ->get(route('admin.submissions.files.download', ['submission' => $submissionA, 'file' => $fileB]));

        $response->assertNotFound();

        // And the same in the other direction.
        $this->actingAs($this->admin)
            ->get(route('admin.submissions.files.download', ['submission' => $submissionB, 'file' => $fileA]))
            ->assertNotFound();
    }

    public function test_a_file_whose_private_path_no_longer_resolves_is_refused(): void
    {
        [$submission, $file] = $this->submissionWithFile(
            'submissions/manuscript/vanished.docx', 'this bytes will be deleted',
        );

        Storage::disk('private')->delete('submissions/manuscript/vanished.docx');

        $this->actingAs($this->admin)
            ->get(route('admin.submissions.files.download', ['submission' => $submission, 'file' => $file]))
            ->assertNotFound();
    }
}
