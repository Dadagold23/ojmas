<?php

namespace Tests\Feature\Workspace;

use App\Models\ArticleFile;
use App\Models\ManuscriptVersion;
use App\Models\ManuscriptWorkspaceDocument;
use App\Models\ReviewerAssignment;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\Fixtures\DocxFixtureFactory;
use Tests\TestCase;

/**
 * Bootstrapping the workspace from an ORIGINAL upload (reported bug).
 *
 * Before this fix, the browser workspace could only open manuscripts that had
 * gone through the Module 1 ingest + parse pipeline, because the working
 * document is chained version → parsed document → workspace document. Staff
 * opening a real submission that predated that pipeline (or was uploaded
 * out-of-band) found an empty editor and "could not open the document for
 * editing except by downloading it".
 *
 * These tests pin the new behaviour: the first open bootstraps the workspace
 * from the original stored DOCX — with no re-upload, no file copy, and no
 * modification of the original — and reports honestly when the original is
 * simply not a readable DOCX.
 */
class WorkspaceBootstrapTest extends TestCase
{
    use RefreshDatabase;

    private DocxFixtureFactory $fixtures;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Editor', 'Reviewer'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        Storage::fake('private');
        $this->fixtures = new DocxFixtureFactory();
    }

    private function legacySubmission(string $filename, string $fileType = 'manuscript', ?string $bytes = null): array
    {
        $path = 'submissions/manuscript/'.Str::of($filename)->before('.')->slug()->toString().'-'.uniqid().'.docx';

        Storage::disk('private')->put(
            $path,
            $bytes ?? (string) file_get_contents($this->fixtures->compliant('legacy-source.docx')),
        );

        $submission = Submission::factory()->create();

        $file = ArticleFile::create([
            'submission_id' => $submission->id,
            'file_type' => $fileType,
            'original_name' => $filename,
            'file_path' => $path,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_size' => Storage::disk('private')->size($path),
        ]);

        return [$submission, $file];
    }

    private function staffEditor(): User
    {
        $editor = User::factory()->create();
        $editor->assignRole('Editor');

        return $editor;
    }

    public function test_a_staff_member_can_open_and_edit_a_manuscript_that_was_never_ingested(): void
    {
        [$submission, $file] = $this->legacySubmission('legacy-paper.docx');
        $filesOnDiskBefore = count(Storage::disk('private')->allFiles());

        $editor = $this->staffEditor();

        $this->actingAs($editor)
            ->get(route('workspace.show', $submission))
            ->assertOk();

        // The version chain was created from the ORIGINAL file — no copy, no
        // re-upload, and the original file record untouched.
        $version = ManuscriptVersion::query()->firstOrFail();
        $this->assertSame($submission->id, $version->submission_id);
        $this->assertSame($file->id, $version->source_file_id);
        $this->assertSame($file->file_path, $version->storage_path);
        $this->assertSame('original', $version->format);
        $this->assertSame($version->id, $submission->fresh()->current_version_id);

        $this->assertSame(1, $submission->files()->count(), 'No second upload was fabricated.');
        $this->assertSame(
            $filesOnDiskBefore,
            count(Storage::disk('private')->allFiles()),
            'No file bytes were copied or created on the private disk.',
        );

        // The editor has a real editable document.
        $this->assertDatabaseCount('manuscript_workspace_documents', 1);

        $document = $this->actingAs($editor)->getJson(route('workspace.document', $submission));

        $document->assertOk();
        $revision = $document->json('revision');
        $this->assertIsInt($revision);
        $this->assertGreaterThan(0, $revision);

        $save = $this->actingAs($editor)
            ->postJson(route('workspace.save', $submission), [
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [['type' => 'text', 'text' => 'Edited by the editor in the browser.']],
                        ],
                    ],
                ],
                'baseRevision' => $revision,
            ]);

        $save->assertOk();
        $this->assertSame($revision + 1, (int) $save->json('revision'));
    }

    public function test_reopening_never_creates_a_second_version(): void
    {
        [$submission] = $this->legacySubmission('legacy-paper.docx');

        $editor = $this->staffEditor();

        $this->actingAs($editor)->get(route('workspace.show', $submission))->assertOk();
        $this->actingAs($editor)->get(route('workspace.show', $submission))->assertOk();

        $this->assertSame(1, ManuscriptVersion::where('submission_id', $submission->id)->count());
        $this->assertDatabaseCount('manuscript_workspace_documents', 1);
    }

    public function test_an_assigned_reviewer_can_read_but_not_edit_the_bootstrapped_manuscript(): void
    {
        [$submission] = $this->legacySubmission('legacy-paper.docx');

        $reviewer = User::factory()->create();
        $reviewer->assignRole('Reviewer');

        ReviewerAssignment::create([
            'submission_id' => $submission->id,
            'reviewer_id' => $reviewer->id,
            'status' => 'assigned',
            'due_date' => now()->addDays(14),
        ]);

        $this->actingAs($reviewer)
            ->get(route('workspace.show', $submission))
            ->assertOk();

        $document = $this->actingAs($reviewer)->getJson(route('workspace.document', $submission));
        $document->assertOk();
        $this->assertFalse((bool) $document->json('canEdit'));

        $this->actingAs($reviewer)
            ->postJson(route('workspace.save', $submission), [
                'content' => ['type' => 'doc', 'content' => []],
                'baseRevision' => 1,
            ])
            ->assertForbidden();

        $this->assertSame(1, (int) ManuscriptWorkspaceDocument::first()->revision);
    }

    public function test_a_non_docx_original_reports_honestly_and_edits_nothing(): void
    {
        [$submission] = $this->legacySubmission(
            'legacy-scan.pdf',
            bytes: '%PDF-1.4 fake bytes pretending to be a manuscript',
        );

        $editor = $this->staffEditor();

        $response = $this->actingAs($editor)
            ->get(route('workspace.show', $submission));

        $response->assertOk();
        $response->assertSee('The browser editor could not open this manuscript');
        $response->assertSee('APA 7 formatting tools remain available');

        $this->assertSame(0, ManuscriptVersion::where('submission_id', $submission->id)->count());
        $this->assertDatabaseCount('manuscript_workspace_documents', 0);

        $this->actingAs($editor)
            ->postJson(route('workspace.save', $submission), [
                'content' => ['type' => 'doc', 'content' => []],
                'baseRevision' => 1,
            ])
            ->assertNotFound();
    }
}
