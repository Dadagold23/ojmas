<?php

namespace Tests\Feature\Workspace;

use App\Models\ManuscriptMedia;
use App\Models\ManuscriptSnapshot;
use App\Models\ReviewerAssignment;
use App\Models\Submission;
use App\Models\User;
use App\Services\Documents\Ast\ParsedManuscript;
use App\Services\Documents\Formatting\DocxRenderer;
use App\Services\Documents\Formatting\FormattingEngine;
use App\Services\Documents\Formatting\StyleProfileRepository;
use App\Services\Documents\ManuscriptProcessingService;
use App\Services\Documents\Parsing\DocxStructureParser;
use App\Services\Documents\Parsing\OoxmlPackage;
use App\Services\Workspace\WorkspaceDocxExporter;
use App\Services\Workspace\WorkspaceDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\Fixtures\DocxFixtureFactory;
use Tests\TestCase;

/**
 * Embedded images in the browser manuscript workspace (Corrective Module 3).
 *
 * The full image path is pinned end to end: an uploaded package's media parts
 * are extracted once into the private disk, the import places `submissionImage`
 * nodes at their exact positions (between paragraphs in the body and inside
 * table cells, at their declared sizes, with alt text), images survive save /
 * reopen / version restore, the media endpoint only ever serves authorized
 * roles through the same `openWorkspace` policy gate as the workspace itself,
 * the DOCX export carries real media parts and real `<w:drawing>` output bound
 * by proper relationships, the APA formatting writer embeds real drawings and
 * no longer leaks the placeholder marker, and an exported DOCX re-imported
 * through the normal pipeline recovers the images — the table preserved as its
 * own acceptance requirement.
 */
class MediaWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private DocxFixtureFactory $fixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        $this->fixtures = new DocxFixtureFactory();

        foreach (['Author', 'Editor', 'Reviewer'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function user(string $role = 'Author'): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->assignRole($role);

        return $user;
    }

    /** Uploads the gallery fixture through the real pipeline and imports it. */
    private function galleryWorkspace(): array
    {
        $author = $this->user('Author');
        $submission = Submission::factory()->create(['author_id' => $author->id]);

        $version = app(ManuscriptProcessingService::class)->ingest(
            $submission,
            new UploadedFile($this->fixtures->mediaGallery(), 'manuscript.docx', null, null, true),
            $author->id,
        )['version'];

        $document = app(ManuscriptProcessingService::class)->parse($version);

        $documents = app(WorkspaceDocumentService::class);
        $workspace = $documents->forDocument($document, $submission->id);

        $parsed = ParsedManuscript::fromArray($document->structure_json);
        $documents->importFromParsed($workspace, $parsed, $author);

        return [$submission->fresh(), $workspace->fresh(), $author];
    }

    /** Every submissionImage block of a content tree, flattened in order. */
    private function imagesOf(array $content): array
    {
        $found = [];

        $walk = function (array $node) use (&$walk, &$found): void {
            if (($node['type'] ?? null) === 'submissionImage') {
                $found[] = $node;
            }

            foreach ((array) ($node['content'] ?? []) as $child) {
                if (is_array($child)) {
                    $walk($child);
                }
            }
        };

        foreach ((array) ($content['content'] ?? []) as $block) {
            if (is_array($block)) {
                $walk($block);
            }
        }

        return $found;
    }

    // ───────────────────────── import ─────────────────────────

    public function test_uploaded_images_are_extracted_and_imported_at_their_positions(): void
    {
        [$submission, $workspace, ] = $this->galleryWorkspace();

        $images = $this->imagesOf($workspace->content());

        $this->assertCount(
            3,
            $images,
            'The gallery manuscript embeds three images; all three must appear in the editor content.',
        );

        // Every figure is a real extracted copy on the private disk, scoped to
        // this submission's version — nothing lives at a public path.
        $media = ManuscriptMedia::where('submission_id', $submission->id)->get();
        $this->assertCount(3, $media);

        $bytes = base64_decode(DocxFixtureFactory::PIXEL_PNG_BASE64);

        foreach ($media as $row) {
            $this->assertStringStartsWith(
                'manuscripts/media/'.$submission->id.'/',
                $row->storage_path,
                'Extracted media must live under the submission-scoped private prefix.',
            );
            Storage::disk('private')->assertExists($row->storage_path);
            $this->assertSame('image/png', $row->mime_type);
            $this->assertSame(strlen($bytes), $row->size_bytes);
        }

        // Editor attrs carry the policy-gated src and the declared sizes.
        $first = $images[0]['attrs'];
        $this->assertNotNull($first['mediaId']);
        $this->assertStringContainsString('media/'.$first['mediaId'], (string) $first['src']);
        $this->assertSame(30, $first['width'], '285750 EMU = 30 px.');
        $this->assertSame(30, $first['height']);
        $this->assertSame('Small square figure between paragraphs', $first['alt'], 'The Word docPr alt text is carried.');

        $second = $images[1]['attrs'];
        $this->assertSame(120, $second['width'], '1143000 EMU = 120 px.');
        $this->assertSame(60, $second['height'], '571500 EMU = 60 px.');

        $third = $images[2]['attrs'];
        $this->assertSame(20, $third['width'], '190500 EMU = 20 px.');
        $this->assertNotNull($third['mediaId']);
    }

    public function test_a_figure_between_paragraphs_stays_between_those_paragraphs(): void
    {
        [, $workspace, ] = $this->galleryWorkspace();

        $blocks = $workspace->content()['content'];
        $firstFigureIndex = null;
        $introIndex = null;
        $afterIndex = null;

        foreach ($blocks as $index => $block) {
            $text = $this->blockText($block);

            if (($block['type'] ?? null) === 'paragraph' && $text === 'Each figure is embedded at its own declared size.') {
                $introIndex ??= $index;
            }

            if (($block['type'] ?? null) === 'submissionImage' && $firstFigureIndex === null) {
                $firstFigureIndex = $index;
            }

            if (($block['type'] ?? null) === 'paragraph' && $text === 'The paragraph right after the first figure.') {
                $afterIndex ??= $index;
            }
        }

        $this->assertNotNull($introIndex, 'The paragraph before the figure must exist.');
        $this->assertNotNull($firstFigureIndex, 'The figure must be a block of the document.');
        $this->assertNotNull($afterIndex, 'The paragraph after the figure must exist.');

        $this->assertTrue(
            $introIndex < $firstFigureIndex && $firstFigureIndex < $afterIndex,
            "The figure must sit BETWEEN its source paragraphs (intro at $introIndex, figure at $firstFigureIndex, after at $afterIndex).",
        );

        // The figure's host (figure-only, textless paragraph) precedes the
        // image itself and is kept — never merged into a neighbour or blanked.
        $this->assertSame('paragraph', $blocks[$firstFigureIndex - 1]['type']);
    }

    public function test_an_image_inside_a_table_cell_stays_inside_the_cell(): void
    {
        [, $workspace, ] = $this->galleryWorkspace();

        $tables = array_values(array_filter(
            $workspace->content()['content'],
            fn ($block) => ($block['type'] ?? null) === 'table',
        ));

        $this->assertCount(1, $tables, 'Imported tables remain real tables (Module 2 regression guard).');

        $row = $tables[0]['content'][0];
        $this->assertSame('tableRow', $row['type']);
        $this->assertCount(2, $row['content'], 'The source table has two cells.');

        $figureCell = $row['content'][1];
        $this->assertSame('tableCell', $figureCell['type']);

        $kinds = array_map(fn ($child) => $child['type'], $figureCell['content']);
        $this->assertContains('submissionImage', $kinds, 'The figure must be inside the cell, not beside the table.');
        $this->assertSame('Figure inside a table cell', $this->imagesOf(
            ['type' => 'doc', 'content' => [$figureCell]],
        )[0]['attrs']['alt']);
    }

    public function test_extraction_is_idempotent_across_reimports(): void
    {
        [$submission, $workspace, $author] = $this->galleryWorkspace();

        $firstRowIds = ManuscriptMedia::where('submission_id', $submission->id)
            ->orderBy('id')->pluck('id')->all();
        $this->assertCount(3, $firstRowIds);

        // A second workspace import of the SAME version must not duplicate
        // extraction.
        app(WorkspaceDocumentService::class)
            ->importFromParsed($workspace, ParsedManuscript::fromArray($workspace->document->structure_json), $author);

        $secondRowIds = ManuscriptMedia::where('submission_id', $submission->id)
            ->orderBy('id')->pluck('id')->all();

        $this->assertSame($firstRowIds, $secondRowIds, 'Re-import must never duplicate media rows.');

        // And opening the workspace again (the lazy-open path) neither
        // duplicates nor deletes anything.
        app(WorkspaceDocumentService::class)->resolveForSubmission($submission);

        $this->assertSame(
            $firstRowIds,
            ManuscriptMedia::where('submission_id', $submission->id)->orderBy('id')->pluck('id')->all(),
        );
    }

    // ───────────────────────── serving ─────────────────────────

    public function test_the_author_can_view_their_media_through_the_policy_gated_endpoint(): void
    {
        [$submission, , $author] = $this->galleryWorkspace();
        $media = ManuscriptMedia::where('submission_id', $submission->id)->first();

        $response = $this->actingAs($author)
            ->get(route('workspace.media.show', ['submission' => $submission->id, 'media' => $media->id]));

        $response->assertOk();
        $this->assertStringStartsWith('image/png', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('inline', (string) $response->headers->get('content-disposition'));
        $this->assertSame(
            sha1(Storage::disk('private')->get($media->storage_path)),
            sha1($this->responseBody($response)),
            'The bytes served are exactly the extracted copy.',
        );
    }

    /** The raw body of a download response, whether buffered or streamed. */
    private function responseBody($response): string
    {
        $content = $response->getContent();

        if ($content === false || $content === null) {
            ob_start();
            $response->sendContent(false);
            $content = (string) ob_get_clean();
        }

        return (string) $content;
    }

    public function test_assigned_reviewers_and_editors_may_view_images(): void
    {
        [$submission, , ] = $this->galleryWorkspace();
        $media = ManuscriptMedia::where('submission_id', $submission->id)->first();

        $reviewer = $this->user('Reviewer');
        ReviewerAssignment::create([
            'submission_id' => $submission->id,
            'reviewer_id' => $reviewer->id,
            'status' => 'assigned',
        ]);

        $this->actingAs($reviewer)
            ->get(route('workspace.media.show', ['submission' => $submission->id, 'media' => $media->id]))
            ->assertOk();

        $editor = $this->user('Editor');
        $this->actingAs($editor)
            ->get(route('workspace.media.show', ['submission' => $submission->id, 'media' => $media->id]))
            ->assertOk();
    }

    public function test_unauthorized_roles_never_reach_an_image(): void
    {
        [$submission, , ] = $this->galleryWorkspace();
        $media = ManuscriptMedia::where('submission_id', $submission->id)->first();

        // Guests hit the auth middleware.
        $this->get(route('workspace.media.show', ['submission' => $submission->id, 'media' => $media->id]))
            ->assertRedirect();

        // A reviewer assigned to NOTHING gets the policy's 403.
        $strangerReviewer = $this->user('Reviewer');
        $this->actingAs($strangerReviewer)
            ->get(route('workspace.media.show', ['submission' => $submission->id, 'media' => $media->id]))
            ->assertForbidden();

        // Another author is not this submission's author: 403.
        $strangerAuthor = $this->user('Author');
        $this->actingAs($strangerAuthor)
            ->get(route('workspace.media.show', ['submission' => $submission->id, 'media' => $media->id]))
            ->assertForbidden();
    }

    public function test_a_media_id_from_another_submission_returns_404_not_a_leak(): void
    {
        [$submission, , $owner] = $this->galleryWorkspace();

        $otherAuthor = $this->user('Author');
        $other = Submission::factory()->create(['author_id' => $otherAuthor->id]);

        // Guessing a real media id against the WRONG submission is 404 —
        // even for the OTHER submission's legitimate author.
        $realMedia = ManuscriptMedia::where('submission_id', $submission->id)->firstOrFail();
        $this->actingAs($otherAuthor)->get(
            route('workspace.media.show', ['submission' => $other->id, 'media' => $realMedia->id]),
        )->assertNotFound();

        // Unknown ids are plain look-up failures too.
        $this->actingAs($owner)->get(
            route('workspace.media.show', ['submission' => $submission->id, 'media' => 999999]),
        )->assertNotFound();
    }

    // ───────────────────────── lifecycle ─────────────────────────

    public function test_images_survive_a_version_restore(): void
    {
        [$submission, $workspace, $author] = $this->galleryWorkspace();

        $importSnapshot = ManuscriptSnapshot::where('manuscript_workspace_document_id', $workspace->id)->first();
        $this->assertNotNull($importSnapshot, 'The import must have created a snapshot.');

        // Save a deliberately changed document: figures removed.
        $stripped = ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'The figures were deleted from this revision.']]],
        ]];

        $this->actingAs($author)
            ->post(route('workspace.save', $submission), [
                'content' => $stripped,
                'baseRevision' => $workspace->revision,
            ])
            ->assertOk();

        $afterRemoval = $this->actingAs($author)
            ->get(route('workspace.document', $submission))
            ->json('content');

        $this->assertCount(
            0,
            $this->imagesOf($afterRemoval),
            'The author\'s edit that removed figures is honoured — nothing is re-injected against them.',
        );

        // Restore is an editorial-staff action by standing policy
        // (WorkspacePolicy::restore); the figures come back for everyone.
        $editor = $this->user('Editor');

        // Restore the import snapshot: the figures come back.
        $this->actingAs($editor)
            ->post(route('workspace.versions.restore', ['submission' => $submission->id, 'snapshot' => $importSnapshot->id]))
            ->assertOk();

        $restored = $this->actingAs($author)
            ->get(route('workspace.document', $submission))
            ->json('content');

        $this->assertCount(
            3,
            $this->imagesOf($restored),
            'Restoring the earlier revision brings the figures back.',
        );

        // ...and the whole cycle never duplicated the media rows.
        $this->assertCount(3, ManuscriptMedia::where('submission_id', $submission->id)->get());
    }

    // ───────────────────────── export ─────────────────────────

    public function test_the_export_carries_real_media_parts_relationships_and_drawings(): void
    {
        [$submission, , ] = $this->galleryWorkspace();
        $editor = $this->user('Editor');

        $exported = $this->exportedPath(
            $this->actingAs($editor)->get(route('workspace.export', $submission)),
        );

        $zip = $this->openExport($exported);

        // Real media parts copied into the package.
        $mediaParts = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (str_starts_with($name, 'word/media/')) {
                $mediaParts[] = $name;
            }
        }
        $this->assertCount(3, $mediaParts, 'All three figures must exist as package parts.');

        $rels = (string) $zip->getFromName('word/_rels/document.xml.rels');
        $document = (string) $zip->getFromName('word/document.xml');
        $contentTypes = (string) $zip->getFromName('[Content_Types].xml');
        $zip->close();

        for ($i = 1; $i <= 3; $i++) {
            $this->assertStringContainsString(
                strtolower('id="rIdImg'.$i.'" type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image"'),
                strtolower($rels),
                'Each figure carries a genuine image relationship.',
            );
        }

        // Real drawings, bound to those relationships.
        $this->assertSame(3, substr_count($document, '<w:drawing>'));
        $this->assertStringContainsString('<a:blip', $document);
        $this->assertStringContainsString('r:embed="rIdImg1"', $document);

        // Declared sizes survive as WP extents.
        $this->assertStringContainsString('<wp:extent cx="285750" cy="285750"/>', $document);
        $this->assertStringContainsString('<wp:extent cx="1143000" cy="571500"/>', $document);

        // The alt text recorded in Word is in the docPr descr.
        $this->assertStringContainsString('descr="Small square figure between paragraphs"', $document);

        $this->assertStringContainsString('Default Extension="png" ContentType="image/png"', $contentTypes);
    }

    public function test_export_summary_counts_the_figures(): void
    {
        [$submission, ,] = $this->galleryWorkspace();

        $workspace = app(WorkspaceDocumentService::class)->resolveForSubmission($submission);
        $absolute = Storage::disk('private')->path('manuscripts/formatted/summary-test.docx');

        if (! is_dir(dirname($absolute))) {
            mkdir(dirname($absolute), 0775, true);
        }

        $summary = app(WorkspaceDocxExporter::class)->export($workspace, $absolute);

        $this->assertSame(3, $summary['summary']['images'], 'The export summary honestly counts figure parts.');
    }

    public function test_a_figure_without_extracted_media_exports_as_a_labelled_note(): void
    {
        [$submission, $workspace, $author] = $this->galleryWorkspace();

        // A document referencing a media id that does not exist — the only
        // fate a stale id can ever meet. Honesty over invention.
        $broken = ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Before the broken figure.']]],
            ['type' => 'submissionImage', 'attrs' => [
                'src' => null,
                'mediaId' => 9876543,
                'alt' => 'A vanished figure',
                'preserved' => true,
            ]],
        ]];

        $this->actingAs($author)
            ->post(route('workspace.save', $submission), [
                'content' => $broken,
                'baseRevision' => $workspace->revision,
            ])
            ->assertOk();

        $absolute = Storage::disk('private')->path('manuscripts/formatted/broken-figure.docx');
        if (! is_dir(dirname($absolute))) {
            mkdir(dirname($absolute), 0775, true);
        }

        app(WorkspaceDocxExporter::class)->export(
            app(WorkspaceDocumentService::class)->resolveForSubmission($submission),
            $absolute,
        );

        $xml = $this->documentXml($absolute);

        $this->assertStringContainsString('[figure unavailable: A vanished figure]', $xml);
        $this->assertStringNotContainsString('<w:drawing>', $xml, 'No fake drawing frames are invented.');
    }

    public function test_the_exported_docx_reimport_recovers_images_and_the_table(): void
    {
        [$submission, , ] = $this->galleryWorkspace();
        $editor = $this->user('Editor');

        $exported = $this->exportedPath(
            $this->actingAs($editor)->get(route('workspace.export', $submission)),
        );

        // Round trip: the exported DOCX goes back through the normal pipeline.
        $roundAuthor = $this->user('Author');
        $roundSubmission = Submission::factory()->create(['author_id' => $roundAuthor->id]);

        $version = app(ManuscriptProcessingService::class)->ingest(
            $roundSubmission,
            new UploadedFile($exported, 'reimported.docx', null, null, true),
            $roundAuthor->id,
        )['version'];

        $document = app(ManuscriptProcessingService::class)->parse($version);
        $this->assertTrue($document->hasParsedSuccessfully(), 'The workspace export must re-import cleanly.');

        $documents = app(WorkspaceDocumentService::class);
        $roundWorkspace = $documents->forDocument($document, $roundSubmission->id);
        $documents->importFromParsed($roundWorkspace, ParsedManuscript::fromArray($document->structure_json), $roundAuthor);

        $content = $roundWorkspace->fresh()->content();
        $images = $this->imagesOf($content);

        $this->assertCount(
            3,
            $images,
            'Reimporting the exported DOCX must recover all three figures at their positions.',
        );

        // The gallery's table survives the full cycle as a real table whose
        // figure stays in its cell (the table acceptance requirement).
        $tables = array_values(array_filter($content['content'], fn ($b) => ($b['type'] ?? null) === 'table'));
        $this->assertCount(1, $tables);
        $this->assertSame('tableRow', $tables[0]['content'][0]['type']);
        $this->assertSame('tableCell', $tables[0]['content'][0]['content'][0]['type']);

        $figureCell = $tables[0]['content'][0]['content'][1];
        $kinds = array_map(fn ($c) => $c['type'], $figureCell['content']);
        $this->assertContains('submissionImage', $kinds, 'The cell figure is still inside the cell.');
        $this->assertStringContainsString('Cell text beside the figure', $this->cellParagraphText($tables[0]));

        // And the re-imported submission manages extraction of its own copy.
        $this->assertCount(
            3,
            ManuscriptMedia::where('submission_id', $roundSubmission->id)->get(),
            'The exported package\'s media parts must extract as this submission\'s own media.',
        );
    }

    // ───────────────────────── APA formatting path ─────────────────────────

    public function test_the_apa_formatted_docx_embeds_real_drawings(): void
    {
        $sourcePath = $this->fixtures->mediaGallery();
        $profile = app(StyleProfileRepository::class)->resolve('ojmas');

        $manuscript = app(DocxStructureParser::class)->parse($sourcePath);
        $result = app(FormattingEngine::class)->format($manuscript, $profile);

        $out = sys_get_temp_dir().'/ojmas-media-gallery-formatted.docx';
        @unlink($out);

        (new DocxRenderer($profile))->render(
            $result->formatted,
            $out,
            OoxmlPackage::open($sourcePath),
        );

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($out, \ZipArchive::RDONLY) === true);
        $document = (string) $zip->getFromName('word/document.xml');
        $rels = (string) $zip->getFromName('word/_rels/document.xml.rels');
        $zip->close();

        $this->assertSame(
            3,
            substr_count($document, '<w:drawing>'),
            'The formatted document must carry all three figures as real drawings (audit finding A14 fixed).',
        );
        $this->assertStringContainsString('r:embed="rIdImg1"', $document);
        $this->assertStringContainsString('Target="media/image1.png"', $rels);
        $this->assertStringContainsString('descr="Small square figure between paragraphs"', $document);

        // And the placeholder marker never leaks into text (A15 fixed).
        $this->assertStringNotContainsString("\u{FFFC}", $document);
    }

    // ───────────────────────── helpers ─────────────────────────

    private function blockText(array $block): string
    {
        $parts = [];

        foreach ((array) ($block['content'] ?? []) as $child) {
            if (($child['type'] ?? null) === 'text') {
                $parts[] = (string) $child['text'];
            }
        }

        return implode('', $parts);
    }

    private function cellParagraphText(array $table): string
    {
        $texts = [];

        foreach ($table['content'][0]['content'] as $cell) {
            foreach ($cell['content'] as $child) {
                if (($child['type'] ?? null) === 'paragraph') {
                    foreach ($child['content'] as $piece) {
                        if (($piece['type'] ?? null) === 'text') {
                            $texts[] = $piece['text'];
                        }
                    }
                }
            }
        }

        return implode(' ', $texts);
    }

    private function exportedPath($response): string
    {
        $disposition = (string) $response->headers->get('content-disposition');
        preg_match('/filename="?([^"]+)"?/', $disposition, $matches);

        return Storage::disk('private')->path(
            'manuscripts/formatted/'.($matches[1] ?? 'export.docx'),
        );
    }

    private function openExport(string $path): \ZipArchive
    {
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path, \ZipArchive::RDONLY) === true, 'The export must be a readable DOCX.');

        return $zip;
    }

    private function documentXml(string $path): string
    {
        $zip = $this->openExport($path);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        return $xml;
    }
}
