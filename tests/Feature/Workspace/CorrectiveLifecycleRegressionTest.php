<?php

namespace Tests\Feature\Workspace;

use App\Models\ManuscriptDocument;
use App\Models\ManuscriptMedia;
use App\Models\ManuscriptVersion;
use App\Models\ManuscriptWorkspaceDocument;
use App\Services\Documents\Ast\NodeType;
use App\Services\Documents\Exceptions\CorruptDocumentException;
use App\Services\Documents\Exceptions\DocumentProcessingException;
use App\Services\Documents\ManuscriptProcessingService;
use App\Services\Documents\Parsing\DocxStructureParser;
use App\Services\Workspace\WorkspaceDocumentService;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\DocxFixtureFactory;
use Tests\TestCase;
use ZipArchive;

/**
 * Corrective Modules 6–7: the complete lifecycle regression suite.
 *
 * Module 6 pins the WHOLE lifecycle — upload, initial conversion, first
 * display, editing and rearranging, autosave, manual save, close and reopen,
 * version history and restoration, DOCX export and reimport-of-export — and
 * records honestly that workspace PDF export does not exist.
 *
 * Module 7 pins the brief's permanent acceptance suites A–F: paragraphs,
 * simple tables, complex tables, images, mixed manuscripts and failure
 * recovery. A visually improved page is not evidence; structure is.
 *
 * The upload step is `ManuscriptProcessingService::ingest` — the same
 * validation, storage and versioning the real upload flow runs — and every
 * workspace stage (display, autosave, manual save, versions, export) goes
 * through the real HTTP endpoints a browser would call.
 */
class CorrectiveLifecycleRegressionTest extends TestCase
{
    use RefreshDatabase;

    private DocxFixtureFactory $fixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        $this->fixtures = new DocxFixtureFactory();

        foreach (['Author', 'Editor', 'Reviewer'] as $role) {
            \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function user(string $role = 'Author'): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->assignRole($role);

        return $user;
    }

    /** Uploads a fixture through the real pipeline and completes workspace import. */
    private function workspaceFor(string $fixture): array
    {
        $author = $this->user('Author');
        $submission = Submission::factory()->create(['author_id' => $author->id]);

        $service = app(ManuscriptProcessingService::class);
        $version = $service->ingest(
            $submission,
            new UploadedFile($this->fixtures->{$fixture}('m67-'.uniqid().'.docx'), 'manuscript.docx', null, null, true),
            $author->id,
        )['version'];

        $document = $service->parse($version);

        $documents = app(WorkspaceDocumentService::class);
        $workspace = $documents->forDocument($document, $submission->id);
        $documents->importFromParsed(
            $workspace,
            \App\Services\Documents\Ast\ParsedManuscript::fromArray($document->structure_json),
            $author,
        );

        return [$submission->fresh(), $workspace->fresh(), $author];
    }

    private function documentPayload(Submission $submission, User $user): array
    {
        return $this->actingAs($user)->get(route('workspace.document', $submission))->assertOk()->json();
    }

    private function currentRevision(Submission $submission): int
    {
        $workspace = app(WorkspaceDocumentService::class)->resolveForSubmission($submission);
        $this->assertNotNull($workspace);

        return (int) $workspace->revision;
    }

    /** Exported through the real route; returns the private path of the DOCX. */
    private function exportDocx(Submission $submission, User $editor): string
    {
        $response = $this->actingAs($editor)->get(route('workspace.export', $submission));
        $response->assertOk();

        preg_match('/filename="?([^"]+)"?/', (string) $response->headers->get('content-disposition'), $m);

        return Storage::disk('private')->path('manuscripts/formatted/'.($m[1] ?? 'export.docx'));
    }

    /**
     * Reimport step: the exported DOCX goes back through the normal pipeline
     * into a fresh TEST submission — generalised round trip, not the original
     * workspace.
     */
    private function reimportIntoTestEnvironment(string $docxPath): \App\Models\ManuscriptWorkspaceDocument
    {
        $author = $this->user('Author');
        $submission = Submission::factory()->create(['author_id' => $author->id]);

        $service = app(ManuscriptProcessingService::class);
        $version = $service->ingest(
            $submission,
            new UploadedFile($docxPath, 'reimported.docx', null, null, true),
            $author->id,
        )['version'];

        $document = $service->parse($version);

        $documents = app(WorkspaceDocumentService::class);
        $workspace = $documents->forDocument($document, $submission->id);

        return $documents->importFromParsed(
            $workspace,
            \App\Services\Documents\Ast\ParsedManuscript::fromArray($document->structure_json),
            $author,
        );
    }

    /** Drops blank-paragraph rows — layout artifacts, not content. */
    private function nonEmpty(array $fingerprints): array
    {
        return array_values(array_filter($fingerprints, fn (string $row) => $row !== 'paragraph|' && $row !== 'heading|' && $row !== ''));
    }

    private function documentXml(string $path): string
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path, ZipArchive::RDONLY) === true, 'The export must be a readable DOCX.');
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        return $xml;
    }

    /** @return array<int, array<string, mixed>> */
    private function blocksOf(array $content): array
    {
        return array_values(array_filter((array) ($content['content'] ?? []), 'is_array'));
    }

    private function tablesOf(array $content): array
    {
        return array_values(array_filter($this->blocksOf($content), fn ($block) => ($block['type'] ?? null) === 'table'));
    }

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

        foreach ($this->blocksOf($content) as $block) {
            $walk($block);
        }

        return $found;
    }

    private function walkText(array $node, \Closure $visit): void
    {
        if (($node['type'] ?? null) === 'text') {
            $visit((string) $node['text']);
        }

        foreach ((array) ($node['content'] ?? []) as $child) {
            if (is_array($child)) {
                $this->walkText($child, $visit);
            }
        }
    }

    private function fingerprints(array $content): array
    {
        $rows = [];

        foreach ($this->blocksOf($content) as $block) {
            $rows[] = ($block['type'] ?? 'unknown').'|'.$this->blockText($block);
        }

        return $rows;
    }

    /** Flat text of a block (walks paragraphs, cells and inline nodes). */
    private function blockText(array $block): string
    {
        $text = '';
        $this->walkText($block, function (string $part) use (&$text) {
            $text .= $part;
        });

        return $text;
    }

    private function replaceBlocks(array $content, array $replacement): array
    {
        $content['content'] = $replacement;

        return $content;
    }

    // ───────────────────────── Module 6 — lifecycle ─────────────────────────

    public function test_the_complete_lifecycle_survives_for_a_mixed_manuscript(): void
    {
        [$submission, $workspace, $author] = $this->workspaceFor('regressionMixed');
        $editor = $this->user('Editor');

        // 1–2: upload + initial conversion produced a versioned original with
        // its checksum, and a parsed structure.
        $version = ManuscriptVersion::where('submission_id', $submission->id)->sole();
        $this->assertNotNull($version->sha256);
        $originalBytes = (string) file_get_contents($version->absolutePath());

        // 3: first display — the full editor payload round-trips.
        $payload = $this->documentPayload($submission, $author);
        $fingerprintImported = $this->fingerprints($payload['content']);
        $this->assertGreaterThan(10, count($fingerprintImported));
        $this->assertStringContainsString('Figure 1.', implode("\n", $fingerprintImported));

        // 4: editing and rearranging — move the last paragraph to the front.
        $blocks = $payload['content']['content'];
        $moved = array_pop($blocks);
        array_unshift($blocks, $moved);
        $arranged = $this->replaceBlocks($payload['content'], $blocks);

        $afterMove = $this->actingAs($author)->post(route('workspace.save', $submission), [
            'content' => $arranged,
            'baseRevision' => $payload['revision'],
        ]);
        $afterMove->assertOk()->assertJson(['outcome' => 'saved']);
        $revisionAfterMove = $afterMove->json('revision');
        $this->assertGreaterThan($payload['revision'], $revisionAfterMove);

        // 5: autosave — another save through the same contract.
        $this->actingAs($author)->post(route('workspace.save', $submission), [
            'content' => $arranged,
            'baseRevision' => $revisionAfterMove,
        ])->assertOk()->assertJson(['outcome' => 'saved']);

        // 6: manual save, recorded as a named version.
        $this->actingAs($author)->post(route('workspace.versions.save', $submission), [
            'label' => 'Post-lifecycle manual save',
        ])->assertStatus(201);

        // 7: close and reopen — a fresh request sees exactly that state.
        $reopened = $this->documentPayload($submission, $author);
        $this->assertSame($this->fingerprints($arranged), $this->fingerprints($reopened['content']));

        // 8: version history carries the import and the manual save, and
        // restoring the import snapshot produces a NEW revision carrying the
        // imported content — history is never rewritten in place.
        $snapshots = collect(
            $this->actingAs($author)->get(route('workspace.versions', $submission))->assertOk()->json('snapshots'),
        );
        $importSnapshot = $snapshots->firstWhere('label', 'Imported from the submitted document');
        $manualSnapshot = $snapshots->firstWhere('label', 'Post-lifecycle manual save');
        $this->assertNotNull($importSnapshot);
        $this->assertNotNull($manualSnapshot);
        $revisionBeforeRestore = $this->currentRevision($submission);

        // Restoring a version is editorial-staff-only by standing design
        // (WorkspacePolicy::restore; pinned in the Module 2 suites): the
        // editor performs it, history is never rewritten, and the author
        // simply sees the restored state.
        $restored = $this->actingAs($editor)
            ->post(route('workspace.versions.restore', [$submission, $importSnapshot['id']]))
            ->assertOk()
            ->assertJsonStructure(['revision', 'content', 'restoredFrom']);
        $this->assertGreaterThan($revisionBeforeRestore, $restored->json('revision'));
        $this->assertSame((int) $importSnapshot['revision'], $restored->json('restoredFrom'), 'The restore records which snapshot it came from.');

        $afterRestore = $this->documentPayload($submission, $author);
        $this->assertSame($fingerprintImported, $this->fingerprints($afterRestore['content']));

        // 9: export to DOCX — an openable package with real figure drawings.
        $exportPath = $this->exportDocx($submission, $editor);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($exportPath, ZipArchive::RDONLY) === true);
        $this->assertNotFalse($zip->locateName('word/document.xml'));
        $data = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        $this->assertSame(2, substr_count($data, '<w:drawing>'));

        // 11: reimport the exported DOCX into a test environment — every
        // element is back in place. Blank paragraphs are layout artifacts and
        // are excluded from the comparison: the reimported package can carry
        // one extra empty paragraph next to a drawing, but every real element
        // (own text, order, headings, tables, figures) must be identical.
        $workspaceReimported = $this->reimportIntoTestEnvironment($exportPath);
        $this->assertSame(
            $this->nonEmpty($fingerprintImported),
            $this->nonEmpty($this->fingerprints($workspaceReimported->content())),
        );

        // And the ORIGINAL upload is byte-identical at the end of the run.
        $this->assertSame($originalBytes, (string) file_get_contents($version->absolutePath()));
    }

    public function test_workspace_pdf_export_does_not_exist_and_the_boundary_is_recorded(): void
    {
        [$submission, , $author] = $this->workspaceFor('regressionTableSimple');

        $response = $this->actingAs($author)->get(route('workspace.export', $submission))->assertOk();

        $this->assertStringStartsWith(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            (string) $response->headers->get('content-type'),
            'Workspace export is DOCX only; no PDF export exists, recorded here for the module report.',
        );
    }

    // ───────────────────────── Module 7 — Test A ─────────────────────────

    public function test_regression_A_paragraph_boundaries_survive_the_full_lifecycle(): void
    {
        [$submission, $workspace, $author] = $this->workspaceFor('regressionParagraphs');

        $payload = $this->documentPayload($submission, $author);
        $imported = $payload['content'];
        $fpImported = $this->fingerprints($imported);

        $markerCount = function (array $fp, string $prefix): int {
            return count(array_filter($fp, fn (string $row) => str_contains($row, $prefix)));
        };

        $this->assertSame(12, $markerCount($fpImported, 'PARA-'), 'All twelve paragraphs must exist as separate blocks.');
        $this->assertSame(3, $markerCount($fpImported, 'ITEM-'), 'List items are separate paragraphs as well.');
        // Title + Introduction + Method + Participants + Results + Subscores
        // + Domain detail + Discussion + Implications = nine heading rows.
        $this->assertCount(9, array_filter($fpImported, fn (string $row) => str_starts_with($row, 'heading|')), 'Headings on three levels.');
        $this->assertStringContainsString('paragraph|PARA-01 First paragraph of the introduction', implode("\n", $fpImported));

        // Inline emphasis survives as marks (italic, bold, superscript,
        // underline) and the soft line break lives inside PARA-07 as one
        // hardBreak — never flattened into the baseline or a new paragraph.
        $inlineFound = false;
        $hardBreakFound = false;

        foreach ($this->blocksOf($imported) as $block) {
            $json = json_encode($block);

            if (str_contains((string) $json, 'PARA-06')) {
                $this->assertStringContainsString('"italic"', (string) $json);
                $this->assertStringContainsString('"bold"', (string) $json);
                $this->assertStringContainsString('"superscript"', (string) $json);
                $this->assertStringContainsString('"underline"', (string) $json);
                $inlineFound = true;
            }

            if (str_contains((string) $json, 'PARA-07')) {
                $this->assertStringContainsString('hardBreak', (string) $json);
                $hardBreakFound = true;
            }
        }

        $this->assertTrue($inlineFound);
        $this->assertTrue($hardBreakFound);

        // Save, close/reopen, export, reimport — the same census everywhere.
        $editor = $this->user('Editor');
        $this->actingAs($editor)->post(route('workspace.save', $submission), [
            'content' => $imported,
            'baseRevision' => $workspace->revision,
        ])->assertOk();

        $reopened = $this->documentPayload($submission, $author);
        $this->assertSame($fpImported, $this->fingerprints($reopened['content']), 'Close/reopen must not merge or reflow paragraphs.');

        $exportPath = $this->exportDocx($submission, $editor);
        $data = $this->documentXml($exportPath);

        foreach (['PARA-01', 'PARA-06', 'PARA-12', 'ITEM-01', 'ITEM-02', 'ITEM-03'] as $marker) {
            $this->assertStringContainsString($marker, $data, $marker.' must survive export.');
        }

        $reparsed = (new DocxStructureParser())->parse($exportPath);
        $paragraphCount = 0;

        foreach ($reparsed->blocks as $block) {
            foreach (array_merge([$block], $block->descendants()) as $node) {
                if ($node->type === NodeType::PARAGRAPH && str_starts_with((string) $node->text, 'PARA-')) {
                    $paragraphCount++;
                }
            }
        }

        $this->assertSame(12, $paragraphCount, 'Paragraph boundaries survive export and reimport.');
    }

    // ───────────────────────── Module 7 — Test B ─────────────────────────

    public function test_regression_B_simple_table_is_a_real_table_with_values_in_their_cells(): void
    {
        [$submission, $workspace, $author] = $this->workspaceFor('regressionTableSimple');

        $payload = $this->documentPayload($submission, $author);
        $tables = $this->tablesOf($payload['content']);
        $this->assertCount(1, $tables);

        $rows = $tables[0]['content'];
        $this->assertCount(4, $rows, 'Exactly four rows.');
        $this->assertCount(3, $rows[0]['content'], 'Exactly three columns.');

        $cellText = fn ($cell) => $this->blockText($cell);
        $this->assertSame('Variable', $cellText($rows[0]['content'][0]));
        $this->assertSame('Mean score', $cellText($rows[0]['content'][1]));
        $this->assertSame('Standard deviation', $cellText($rows[0]['content'][2]));
        $this->assertSame('72.4', $cellText($rows[1]['content'][1]));
        $this->assertSame('64.9', $cellText($rows[2]['content'][1]));
        $this->assertSame('7.5', $cellText($rows[3]['content'][1]));

        // Edit one number in place, save, reopen. The mutation goes through
        // the direct nested chain on $payload — deriving a copy via $tables
        // would not write back (arrays are value types in PHP).
        $blocks = $payload['content']['content'];
        $tableIndex = null;

        foreach ($blocks as $index => $block) {
            if (($block['type'] ?? null) === 'table') {
                $tableIndex = $index;
            }
        }

        // table > tableRow > tableCell > paragraph > text.
        $payload['content']['content'][$tableIndex]['content'][1]['content'][1]['content'][0]['content'][0]['text'] = '73.1';

        $this->actingAs($author)->post(route('workspace.save', $submission), [
            'content' => $payload['content'],
            'baseRevision' => $workspace->revision,
        ])->assertOk();

        $reopened = $this->tablesOf($this->documentPayload($submission, $author)['content']);
        $this->assertSame('73.1', $cellText($reopened[0]['content'][1]['content'][1]), 'The cell edit persisted across reopen.');
        $this->assertSame('64.9', $cellText($reopened[0]['content'][2]['content'][1]));

        // Export a genuine DOCX table, and reimport keeps every value.
        $exportPath = $this->exportDocx($submission, $this->user('Editor'));
        $xml = $this->documentXml($exportPath);
        $this->assertStringContainsString('<w:tbl>', $xml);
        $this->assertSame(4, substr_count($xml, '<w:tr>'), 'The exported table keeps its four rows.');
        $this->assertStringContainsString('73.1', $xml);

        $reparsed = (new DocxStructureParser())->parse($exportPath);
        $parsedTables = $reparsed->blocksOfType(NodeType::TABLE);
        $this->assertCount(1, $parsedTables);
        $cells = $parsedTables[0]->descendantsOfType(NodeType::TABLE_CELL);
        $this->assertCount(12, $cells, '4 rows x 3 columns = 12 cells.');
        $this->assertSame('73.1', (string) $cells[4]->text);
        $this->assertSame('Standard deviation', (string) $cells[2]->text);
    }

    // ───────────────────────── Module 7 — Test C ─────────────────────────

    public function test_regression_C_complex_table_keeps_merges_notes_and_long_cells(): void
    {
        [$submission, $workspace, $author] = $this->workspaceFor('regressionTableComplex');

        $payload = $this->documentPayload($submission, $author);
        $fp = $this->fingerprints($payload['content']);

        // Caption above the table, "Note." line below it — positions matter.
        $captionIndex = null;
        $tableIndex = null;
        $noteIndex = null;

        foreach ($fp as $index => $row) {
            if (str_starts_with($row, 'paragraph|Table 3.')) {
                $captionIndex ??= $index;
            }
            if (str_starts_with($row, 'table|')) {
                $tableIndex ??= $index;
            }
            if (str_starts_with($row, 'paragraph|Note.')) {
                $noteIndex ??= $index;
            }
        }

        $this->assertNotNull($captionIndex);
        $this->assertNotNull($tableIndex);
        $this->assertNotNull($noteIndex);
        $this->assertTrue($captionIndex < $tableIndex && $tableIndex < $noteIndex);

        $table = $this->tablesOf($payload['content'])[0];

        // The merge is real: the header's first cell spans two columns.
        $spans = [];

        foreach ($table['content'] as $row) {
            $spans[] = array_map(fn ($cell) => $cell['attrs']['colspan'] ?? 1, $row['content']);
        }

        $this->assertSame([[2, 1], [1, 1, 1], [1, 1, 1]], $spans, 'Merged header cells carry their colspan.');

        // Long cell text keeps both of its paragraphs.
        $firstCell = $table['content'][1]['content'][0];
        $this->assertCount(2, $firstCell['content'], 'Both paragraphs inside the long cell survive.');
        $this->assertStringContainsString('NORTH WEST ZONE', $this->blockText($firstCell));
        $this->assertStringContainsString('second paragraph inside the same cell', $this->blockText($firstCell));

        // Re-save unchanged, reopen, export, reimport — structure identical.
        $this->actingAs($author)->post(route('workspace.save', $submission), [
            'content' => $payload['content'],
            'baseRevision' => $workspace->revision,
        ])->assertOk();

        $reopened = $this->documentPayload($submission, $author);
        $this->assertSame($fp, $this->fingerprints($reopened['content']));

        $exportPath = $this->exportDocx($submission, $this->user('Editor'));
        $xml = $this->documentXml($exportPath);
        $this->assertStringContainsString('<w:gridSpan w:val="2"/>', $xml, 'The merge exports as a real Word gridSpan.');
        $this->assertStringContainsString('NORTH WEST ZONE: The largest zone', $xml, 'Long cell text is not truncated.');

        $tables = (new DocxStructureParser())->parse($exportPath)->blocksOfType(NodeType::TABLE);
        $this->assertCount(1, $tables);
        $cells = $tables[0]->descendantsOfType(NodeType::TABLE_CELL);
        $this->assertSame(2, (int) $cells[0]->attribute('grid_span'), 'The reimported table carries the merge again.');
        // Cell order: merged header, share header, then the long-text cell
        // (row 1, column 1) with its two internal paragraphs.
        $this->assertStringContainsString(
            'second paragraph inside the same cell',
            (string) $cells[2]->text,
            'Long cell text survives export and reimport.',
        );
        $this->assertSame('Merged region header', (string) $cells[0]->text);
        $this->assertSame('486 schools', (string) $cells[3]->text);
        $this->assertSame('38.2%', (string) $cells[4]->text);
    }

    // ───────────────────────── Module 7 — Test D ─────────────────────────

    public function test_regression_D_images_display_persist_and_export_in_order(): void
    {
        [$submission, $workspace, $author] = $this->workspaceFor('mediaGallery');

        $payload = $this->documentPayload($submission, $author);
        $images = $this->imagesOf($payload['content']);
        $this->assertCount(3, $images, 'Three figures of different declared sizes.');
        $this->assertSame([30, 120, 20], array_map(fn ($node) => (int) $node['attrs']['width'], $images), 'Order and sizes.');
        $this->assertSame('Small square figure between paragraphs', (string) $images[0]['attrs']['alt'], 'One figure carries caption text.');

        $media = ManuscriptMedia::where('submission_id', $submission->id)->orderBy('id')->get();
        $this->assertSame(3, $media->count());
        $expectedBytes = (int) strlen(base64_decode(DocxFixtureFactory::PIXEL_PNG_BASE64));

        foreach ($media as $row) {
            $this->assertSame($expectedBytes, (int) Storage::disk('private')->size($row->storage_path), 'Persistence keeps full-resolution copies.');
        }

        // Save-remove-restore: delete the middle figure, reopen, then the
        // import snapshot restores all three, in order.
        $withoutFigure = array_values(array_filter(
            $this->blocksOf($payload['content']),
            fn ($block) => ($block['type'] ?? null) !== 'submissionImage' || (int) $block['attrs']['width'] !== 120,
        ));

        $this->actingAs($author)->post(route('workspace.save', $submission), [
            'content' => $this->replaceBlocks($payload['content'], $withoutFigure),
            'baseRevision' => $this->currentRevision($submission),
        ])->assertOk();

        $this->assertCount(2, $this->imagesOf($this->documentPayload($submission, $author)['content']), 'The deletion is real and persisted.');

        $snapshots = collect(
            $this->actingAs($author)->get(route('workspace.versions', $submission))->assertOk()->json('snapshots'),
        );
        $importSnapshot = $snapshots->firstWhere('label', 'Imported from the submitted document');

        // Restoring is editorial-staff-only by standing design; the editor
        // brings every figure back.
        $this->actingAs($editorUser = $this->user('Editor'))
            ->post(route('workspace.versions.restore', [$submission, $importSnapshot['id']]))
            ->assertOk();

        $this->assertCount(3, $this->imagesOf($this->documentPayload($submission, $author)['content']), 'Restore brings every figure back.');

        // Export: three real drawings bound to extracted media parts.
        $exportPath = $this->exportDocx($submission, $this->user('Editor'));
        $xml = $this->documentXml($exportPath);
        $this->assertSame(3, substr_count($xml, '<w:drawing>'));

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($exportPath, ZipArchive::RDONLY) === true);
        $mediaParts = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            if (str_starts_with(strtolower($name), 'word/media/')) {
                $mediaParts[] = $name;
            }
        }
        $zip->close();
        $this->assertCount(3, $mediaParts, 'All three figures exist as package parts.');

        // Reimport: the gallery comes back with sizes and alt text.
        $workspaceReimported = $this->reimportIntoTestEnvironment($exportPath);
        $images = $this->imagesOf($workspaceReimported->content());
        $this->assertCount(3, $images);
        $this->assertSame([30, 120, 20], array_map(fn ($node) => (int) $node['attrs']['width'], $images));
        $this->assertSame('Small square figure between paragraphs', (string) $images[0]['attrs']['alt']);
    }

    // ───────────────────────── Module 7 — Test E ─────────────────────────

    public function test_regression_E_every_mixed_manuscript_element_stays_in_place(): void
    {
        [$submission, $workspace, $author] = $this->workspaceFor('regressionMixed');

        $payload = $this->documentPayload($submission, $author);
        $fp = $this->fingerprints($payload['content']);

        $indexOf = function (string $prefix) use ($fp): ?int {
            foreach ($fp as $index => $row) {
                if (str_starts_with($row, $prefix)) {
                    return $index;
                }
            }

            return null;
        };

        $indexOfSecond = function (string $prefix) use ($fp): ?int {
            $seen = false;

            foreach ($fp as $index => $row) {
                if (str_starts_with($row, $prefix)) {
                    if ($seen) {
                        return $index;
                    }

                    $seen = true;
                }
            }

            return null;
        };

        $tableCaption = $indexOf('paragraph|Table 4.');
        $table = $indexOf('table|');
        $fig1Caption = $indexOf('paragraph|Figure 1.');
        $fig1 = $indexOf('submissionImage|');
        $fig2Caption = $indexOf('paragraph|Figure 2.');
        $fig2 = $indexOfSecond('submissionImage|');

        $this->assertNotNull($tableCaption);
        $this->assertNotNull($table);
        $this->assertNotNull($fig1Caption);
        $this->assertNotNull($fig1);
        $this->assertNotNull($fig2Caption);
        $this->assertNotNull($fig2);

        $this->assertTrue($tableCaption < $table, 'The table caption precedes its table.');
        $this->assertTrue($table < $fig1Caption, 'The table precedes the figure captions.');
        $this->assertTrue($fig1Caption < $fig1, 'Figure 1 caption directly precedes figure 1.');
        $this->assertTrue($fig1 < $fig2Caption && $fig2Caption < $fig2, 'Figure 2 caption directly precedes figure 2.');
        $this->assertTrue($fig2 < $indexOf('paragraph|RESULTS-P3 '), 'Body text after the figures stays after them.');

        // Save/reopen identity, then export-reimport census.
        $this->actingAs($author)->post(route('workspace.save', $submission), [
            'content' => $payload['content'],
            'baseRevision' => $workspace->revision,
        ])->assertOk();

        $this->assertSame($fp, $this->fingerprints($this->documentPayload($submission, $author)['content']));

        $exportPath = $this->exportDocx($submission, $this->user('Editor'));

        // Reference ENTRIES are pinned at the parse level here; reference
        // database rows are persisted by the APA formatting stage and are
        // covered by its own suites (ManuscriptProcessingService::format).
        $reparsedRefs = (new DocxStructureParser())->parse($exportPath)->referenceEntries();
        $this->assertGreaterThanOrEqual(2, count($reparsedRefs), 'The reference list survives the lifecycle as genuine reference entries.');
        $workspaceReimported = $this->reimportIntoTestEnvironment($exportPath);
        $reparsedFp = $this->fingerprints($workspaceReimported->content());
        $joined = implode(' %% ', $reparsedFp);

        foreach ([
            'EVIDENCE-P1', 'EVIDENCE-P2', 'METHOD-P1', 'METHOD-P2', 'RESULTS-P1', 'Table 4.',
            'RESULTS-P2', 'Figure 1.', 'Figure 2.', 'RESULTS-P3', 'DISCUSSION-P1', 'Belland', 'Okonkwo',
        ] as $needle) {
            $this->assertStringContainsString($needle, $joined);
        }

        $this->assertSame(1, count(array_filter($reparsedFp, fn ($row) => str_starts_with($row, 'table|'))));
        $this->assertSame(2, count(array_filter($reparsedFp, fn ($row) => str_starts_with($row, 'submissionImage|'))));
    }

    // ───────────────────────── Module 7 — Test F ─────────────────────────

    public function test_regression_F_a_corrupt_document_is_rejected_without_creating_a_version(): void
    {
        $author = $this->user('Author');
        $submission = Submission::factory()->create(['author_id' => $author->id]);

        try {
            app(ManuscriptProcessingService::class)->ingest(
                $submission,
                new UploadedFile($this->fixtures->corrupt('m67-corrupt.docx'), 'corrupt.docx', null, null, true),
                $author->id,
            );
            $this->fail('A corrupt upload must be rejected with a document exception.');
        } catch (CorruptDocumentException $exception) {
            $this->assertStringContainsString('The uploaded file', $exception->getMessage(), 'The problem is reported clearly.');
        }

        // Nothing was silently saved: no version, no workspace document.
        $this->assertSame(0, ManuscriptVersion::where('submission_id', $submission->id)->count());
        $this->assertSame(0, ManuscriptWorkspaceDocument::where('submission_id', $submission->id)->count());
    }

    public function test_regression_F_unsupported_and_broken_figures_are_preserved_and_diagnosed(): void
    {
        [$submission, , $author] = $this->workspaceFor('regressionImageProblems');

        $payload = $this->documentPayload($submission, $author);
        $images = $this->imagesOf($payload['content']);
        $this->assertCount(3, $images, 'Three real drawings survive: PNG, HEIC and the fake-PNG one.');

        $unsupported = $images[1];
        $this->assertSame('A HEIC figure the browser cannot show', (string) $unsupported['attrs']['alt']);
        $this->assertTrue((bool) ($unsupported['attrs']['unsupported'] ?? false), 'The unsupported format is flagged.');
        $this->assertStringContainsString('cannot display', (string) $unsupported['attrs']['diagnostic']);

        $media = ManuscriptMedia::where('submission_id', $submission->id)->orderBy('id')->get();
        $this->assertSame('image/png', $media[0]->mime_type);
        $this->assertSame('unhandled/heic', $media[1]->mime_type, 'The unsupported part is recorded with its fault; it is not dropped.');
        $this->assertSame('invalid/png', $media[2]->mime_type, 'The claim mismatch is recorded instead of a fake healthy mime.');

        $fakePng = $images[2];
        $this->assertSame('A part with fake PNG bytes', (string) $fakePng['attrs']['alt']);
        $this->assertTrue((bool) ($fakePng['attrs']['unsupported'] ?? false), 'The claim mismatch is flagged.');
        $this->assertStringContainsString('do not decode', (string) $fakePng['attrs']['diagnostic']);

        // A drawing whose relationship id does not exist at all degrades to
        // the historic marker inside its paragraph — position stays, the
        // problem is visible, nothing is invented.
        $markerCount = 0;

        foreach ($this->blocksOf($payload['content']) as $block) {
            $this->walkText($block, function (string $text) use (&$markerCount) {
                if (str_contains($text, "\u{FFFC}")) {
                    $markerCount++;
                }
            });
        }

        $this->assertSame(1, $markerCount, 'The missing relationship stays an explicit visible marker.');

        // Closing text after all four problem figures is intact.
        $blocks = $this->blocksOf($payload['content']);
        $last = $blocks[count($blocks) - 1];
        $this->assertStringContainsString('Closing paragraph, placed after all four problem figures.', $this->blockText($last));

        // The original file was never written over by this conversion run.
        $version = ManuscriptVersion::where('submission_id', $submission->id)->sole();
        $sha = hash('sha256', (string) file_get_contents($version->absolutePath()));
        $this->assertSame($version->sha256, $sha);
    }

    public function test_regression_F_a_failed_conversion_reports_clearly_and_keeps_the_original(): void
    {
        $author = $this->user('Author');
        $submission = Submission::factory()->create(['author_id' => $author->id]);

        // A stored version whose file has gone missing — a conversion that
        // cannot be completed.
        $version = ManuscriptVersion::create([
            'submission_id' => $submission->id,
            'version_number' => 1,
            'storage_path' => 'submissions/manuscript/gone-m67.docx',
            'sha256' => hash('sha256', 'gone'),
            'format' => 'original',
            'origin' => 'author_upload',
        ]);

        try {
            app(ManuscriptProcessingService::class)->parse($version);
            $this->fail('A conversion of a missing file must fail loudly.');
        } catch (DocumentProcessingException $exception) {
            $this->assertStringContainsString('could not be found', $exception->getMessage());
        }

        $document = ManuscriptDocument::where('manuscript_version_id', $version->id)->first();
        $this->assertNotNull($document);
        $this->assertSame('failed', $document->status, 'The failure is recorded on the parse record.');
        $this->assertStringContainsString('could not be found', (string) $document->error_message);

        // The workspace NEVER gets a half-built document out of the failure —
        // the browser is told clearly that there is nothing to author yet.
        $this->assertSame(0, ManuscriptWorkspaceDocument::where('submission_id', $submission->id)->count());

        $this->actingAs($author)
            ->get(route('workspace.document', $submission))
            ->assertNotFound()
            ->assertJson(['code' => 'workspace_not_initialised']);
    }
}
