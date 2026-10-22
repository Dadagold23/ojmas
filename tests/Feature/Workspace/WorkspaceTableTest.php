<?php

namespace Tests\Feature\Workspace;

use App\Models\Submission;
use App\Models\User;
use App\Services\Documents\Ast\ParsedManuscript;
use App\Services\Documents\ManuscriptProcessingService;
use App\Services\Workspace\WorkspaceDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\Fixtures\DocxFixtureFactory;
use Tests\TestCase;

/**
 * Tables in the browser manuscript workspace.
 *
 * The full table path: Word tables are imported as real editable Tiptap
 * tables (not flattened pipe text), tables the editor produces round-trip
 * through save and GET, and the DOCX export writes a genuine Word grid —
 * merged columns as w:gridSpan, merged rows as w:vMerge pairs. Documents
 * that were imported before real tables existed (consecutive pipe-delimited
 * `table_row` paragraphs) still export as a grid and are never degraded.
 */
class WorkspaceTableTest extends TestCase
{
    use RefreshDatabase;

    private DocxFixtureFactory $fixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        $this->fixtures = new DocxFixtureFactory();

        foreach (['Author', 'Editor'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function user(string $role = 'Author'): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->assignRole($role);

        return $user;
    }

    private function workspaceFor(string $fixture = 'compliant'): array
    {
        $author = $this->user('Author');
        $submission = Submission::factory()->create(['author_id' => $author->id]);

        $version = app(ManuscriptProcessingService::class)->ingest(
            $submission,
            new UploadedFile($this->fixtures->{$fixture}(), 'manuscript.docx', null, null, true),
            $author->id,
        )['version'];

        $document = app(ManuscriptProcessingService::class)->parse($version);

        $documents = app(WorkspaceDocumentService::class);
        $workspace = $documents->forDocument($document, $submission->id);

        $parsed = ParsedManuscript::fromArray($document->structure_json);
        $documents->importFromParsed($workspace, $parsed, $author);

        return [$submission->fresh(), $workspace->fresh(), $author];
    }

    /**
     * A 3-column table with a spanning header cell and a cell merged over the
     * bottom two rows — the merge shapes the DOCX exporter must reproduce.
     */
    private function mergedTableDoc(): array
    {
        return ['type' => 'doc', 'content' => [[
            'type' => 'table',
            'content' => [
                ['type' => 'tableRow', 'content' => [
                    ['type' => 'tableHeader', 'attrs' => ['colspan' => 2, 'rowspan' => 1, 'colwidth' => null],
                        'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Trait']]]]],
                    ['type' => 'tableHeader', 'attrs' => ['colspan' => 1, 'rowspan' => 1, 'colwidth' => null],
                        'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Value']]]]],
                ]],
                ['type' => 'tableRow', 'content' => [
                    ['type' => 'tableCell', 'attrs' => ['colspan' => 1, 'rowspan' => 2, 'colwidth' => null],
                        'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Memory']]]]],
                    ['type' => 'tableCell', 'attrs' => ['colspan' => 1, 'rowspan' => 1, 'colwidth' => null],
                        'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Fast']]]]],
                ]],
                ['type' => 'tableRow', 'content' => [
                    ['type' => 'tableCell', 'attrs' => ['colspan' => 1, 'rowspan' => 1, 'colwidth' => null],
                        'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Cheap']]]]],
                ]],
            ],
        ]]];
    }

    // ───────────────────────── import ─────────────────────────

    public function test_tables_in_the_original_upload_become_real_editable_tables(): void
    {
        [, $workspace, ] = $this->workspaceFor('compliant');

        $blocks = $workspace->content()['content'];
        $tables = array_values(array_filter($blocks, fn ($block) => ($block['type'] ?? null) === 'table'));

        $this->assertCount(1, $tables, 'The imported document must carry the original table as a real table node.');

        $table = $tables[0];

        $this->assertCount(3, $table['content'], 'Three rows must be imported.');

        $headerRow = $table['content'][0];
        $this->assertSame('tableRow', $headerRow['type']);
        $this->assertCount(3, $headerRow['content']);
        $this->assertSame('tableHeader', $headerRow['content'][0]['type'], 'The Word header row must import as header cells.');

        $secondRow = $table['content'][1];
        $this->assertSame('tableCell', $secondRow['content'][0]['type']);

        $text = json_encode($table);
        $this->assertStringContainsString('Scaffolded', $text);
        $this->assertStringContainsString('72.4', $text);
        $this->assertStringContainsString('9.7', $text);

        // The old flattening is gone: no pipe-joined rows, no legacy marker.
        $this->assertStringNotContainsString('Scaffolded | 72.4', $text);
        $this->assertStringNotContainsString('table_row', $text);
    }

    public function test_imported_cells_carry_a_tiptap_valid_shell(): void
    {
        [, $workspace, ] = $this->workspaceFor('compliant');

        $table = array_values(array_filter(
            $workspace->content()['content'],
            fn ($block) => ($block['type'] ?? null) === 'table',
        ))[0];

        $cell = $table['content'][1]['content'][0];

        // Every cell is block content plus the colspan/rowspan/colwidth
        // attribute triple the Tiptap schema declares.
        $this->assertArrayHasKey('content', $cell);
        $this->assertNotEmpty($cell['content']);
        $this->assertSame('paragraph', $cell['content'][0]['type']);
        $this->assertSame(1, $cell['attrs']['colspan']);
        $this->assertSame(1, $cell['attrs']['rowspan']);
    }

    // ───────────────────────── saving ─────────────────────────

    public function test_an_editor_saved_table_round_trips(): void
    {
        [$submission, $workspace, $author] = $this->workspaceFor('compliant');
        $editor = $this->user('Editor');

        // The compliant import already contains a table; replacing it with a
        // merged one through the save endpoint proves the editor's own tables
        // persist exactly as the browser would send them.
        $response = $this->actingAs($editor)
            ->post(route('workspace.save', $submission), [
                'content' => $this->mergedTableDoc(),
                'baseRevision' => $workspace->revision,
            ])
            ->assertOk()
            ->assertJson(['outcome' => 'saved', 'saved' => true]);

        // The save response is an outcome envelope; the persisted document is
        // the thing under test.
        $workspace->refresh();
        $returned = $workspace->content();
        $this->assertSame('doc', $returned['type']);
        $table = $returned['content'][0];
        $this->assertSame('table', $table['type']);
        $this->assertSame('tableRow', $table['content'][0]['type']);
        $this->assertSame(2, $table['content'][1]['content'][0]['attrs']['rowspan']);
        $this->assertSame('Fast', $table['content'][1]['content'][1]['content'][0]['content'][0]['text']);

        // Reading it back gives the same structure.
        $this->actingAs($editor)
            ->get(route('workspace.document', $submission))
            ->assertOk()
            ->assertJsonPath('content.content.0.type', 'table')
            ->assertJsonPath('content.content.0.content.0.content.0.type', 'tableHeader');

        $workspace->refresh();
        $this->assertSame(['Trait', 'Value', 'Memory', 'Fast', 'Cheap'], $this->cellTexts($workspace->plain_text));
        $this->assertSame(5, $workspace->word_count, 'Table words are real words and are counted.');
    }

    public function test_table_words_enter_the_reported_counts(): void
    {
        [$submission, , $author] = $this->workspaceFor('compliant');

        $wordCount = $this->actingAs($author)
            ->get(route('workspace.document', $submission))
            ->json('wordCount');

        // The compliant fixture's table contributes at least: Group, M, SD,
        // Scaffolded, 72.4, 8.1, Control, 64.9, 9.7.
        $this->assertGreaterThanOrEqual(9, $wordCount);
    }

    // ───────────────────────── export ─────────────────────────

    public function test_the_export_writes_a_genuine_word_grid_for_merged_tables(): void
    {
        [$submission, $workspace, , ] = $this->workspaceFor('compliant');
        $editor = $this->user('Editor');

        $this->actingAs($editor)
            ->post(route('workspace.save', $submission), [
                'content' => $this->mergedTableDoc(),
                'baseRevision' => $workspace->revision,
            ])
            ->assertOk();

        $xml = $this->documentXml($this->exportedPath(
            $this->actingAs($editor)->get(route('workspace.export', $submission)),
        ));

        $this->assertStringContainsString('<w:tbl>', $xml);
        $this->assertStringContainsString('<w:gridSpan w:val="2"/>', $xml, 'The spanning header must export as a true column merge.');
        $this->assertStringContainsString('<w:vMerge w:val="restart"/>', $xml, 'The two-row cell must open a vertical merge.');
        $this->assertStringContainsString('<w:vMerge/>', $xml, 'The covered row must carry the vMerge continuation cell.');
        $this->assertStringContainsString('<w:tblHeader/>', $xml, 'The header row must also be a Word header row.');

        foreach (['Trait', 'Value', 'Memory', 'Fast', 'Cheap'] as $text) {
            $this->assertStringContainsString('>'.$text.'<', $xml);
        }

        // The continuation cell must appear in the covered row, before that
        // row's own cell — exactly where Word places it.
        $this->assertTrue(strpos($xml, 'Memory') < strpos($xml, '<w:vMerge/>'));
        $this->assertTrue(strpos($xml, '<w:vMerge/>') < strpos($xml, 'Cheap'));
    }

    public function test_legacy_pipe_rows_still_export_as_a_grid(): void
    {
        [$submission, $workspace, , ] = $this->workspaceFor('compliant');
        $editor = $this->user('Editor');

        $legacy = ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'attrs' => ['submissionBlockType' => 'table_row'],
                'content' => [['type' => 'text', 'text' => 'Group | M | SD']]],
            ['type' => 'paragraph', 'attrs' => ['submissionBlockType' => 'table_row'],
                'content' => [['type' => 'text', 'text' => 'Scaffolded | 72.4 | 8.1']]],
        ]];

        $this->actingAs($editor)
            ->post(route('workspace.save', $submission), [
                'content' => $legacy,
                'baseRevision' => $workspace->revision,
            ])
            ->assertOk();

        $table = $this->tableXml($this->documentXml($this->exportedPath(
            $this->actingAs($editor)->get(route('workspace.export', $submission)),
        )));

        $this->assertNotSame('', $table, 'The legacy rows must still assemble into a Word grid.');
        $this->assertSame(2, substr_count($table, '<w:tr>'));
        $this->assertStringContainsString('72.4', $table);
    }

    // ───────────────────────── helpers ─────────────────────────

    /**
     * The plain-text projection of the merged-table doc: every cell is its
     * own paragraph, and a table concatenates its rows without separators —
     * exactly what workspace plainText() computes.
     */
    private function cellTexts(string $plain): array
    {
        $texts = array_values(array_filter(array_map('trim', explode("\n", $plain))));

        return $texts;
    }

    private function tableXml(string $documentXml): string
    {
        preg_match('/<w:tbl>.*<\/w:tbl>/s', $documentXml, $matches);

        return $matches[0] ?? '';
    }

    // Mirrors WorkspaceTest's helpers so both suites test the same contract.
    private function exportedPath($response): string
    {
        $disposition = (string) $response->headers->get('content-disposition');
        preg_match('/filename="?([^"]+)"?/', $disposition, $matches);

        return Storage::disk('private')->path(
            'manuscripts/formatted/'.($matches[1] ?? 'export.docx'),
        );
    }

    private function documentXml(string $path): string
    {
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path, \ZipArchive::RDONLY) === true, 'The export must be a readable DOCX.');
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        return $xml;
    }
}
