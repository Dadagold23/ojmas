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
 * Inline fidelity of the browser manuscript workspace — corrective module 1.
 *
 * Every separate paragraph must import as its own block; a line break inside
 * a paragraph must stay a break inside that paragraph (not merged text and
 * not a new paragraph); superscript, subscript, strikethrough and underline
 * must survive into both the editor document and the Word export.
 */
class InlineFidelityTest extends TestCase
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

    private function workspaceFor(string $fixture): array
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
        $documents->importFromParsed($workspace, ParsedManuscript::fromArray($document->structure_json), $author);

        return [$submission->fresh(), $workspace->fresh(), $author];
    }

    /** Flattens every text node of a block into one string, for content assertions. */
    private function blockText(array $block): string
    {
        $text = '';

        foreach ($block['content'] as $node) {
            $text .= $node['type'] === 'hardBreak' ? "\n" : (string) $node['text'];
        }

        return $text;
    }

    /** Marks of the inline nodes matched to a text fragment. */
    private function marksFor(array $block, string $fragment): array
    {
        foreach ($block['content'] as $node) {
            if (($node['type'] ?? '') === 'text' && $node['text'] === $fragment) {
                return array_map(fn ($mark) => $mark['type'], $node['marks'] ?? []);
            }
        }

        return [];
    }

    public function test_paragraphs_stay_separate_and_blank_spacing_survives(): void
    {
        [, $workspace, ] = $this->workspaceFor('inlineFidelity');

        $blocks = $workspace->content()['content'];

        // Title, blank separator, four body paragraphs, References heading,
        // one hanging-indent reference — every paragraph its own block.
        $types = array_map(fn ($block) => $block['type'], $blocks);
        $this->assertSame(['heading', 'paragraph', 'paragraph', 'paragraph', 'paragraph', 'paragraph', 'heading', 'paragraph'], $types);

        $this->assertSame(1, count(array_filter($blocks, fn ($b) => $this->blockText($b) === 'First visual line of a soft-wrapped paragraph.'."\n".'Second visual line follows the break directly.')), 'The soft-wrapped paragraph must stay ONE paragraph.');

        // The blank paragraph is real spacing, not a dropped paragraph.
        $this->assertSame([], $blocks[1]['content'], 'The blank separator paragraph must keep its emptiness.');
    }

    public function test_a_line_break_inside_a_paragraph_becomes_a_real_hard_break(): void
    {
        [, $workspace, ] = $this->workspaceFor('inlineFidelity');

        $blocks = $workspace->content()['content'];
        $softWrapped = $blocks[2];

        $types = array_map(fn ($node) => $node['type'], $softWrapped['content']);

        $this->assertContains('hardBreak', $types, 'The soft line break must survive as a hardBreak inline node.');
        $this->assertCount(3, $softWrapped['content'], 'Two lines joined by exactly one break — no hidden merged text node.');

        // The break sits precisely between the two visual lines.
        $this->assertSame('hardBreak', $softWrapped['content'][1]['type']);
        $this->assertSame('First visual line of a soft-wrapped paragraph.', $softWrapped['content'][0]['text']);
        $this->assertSame('Second visual line follows the break directly.', $softWrapped['content'][2]['text']);
    }

    public function test_superscript_subscript_strikethrough_and_underline_survive(): void
    {
        [, $workspace, ] = $this->workspaceFor('inlineFidelity');

        [, , , $superBlock, $subBlock, $struckBlock] = $workspace->content()['content'];

        $this->assertSame(['superscript'], $this->marksFor($superBlock, '2'), 'The exponent must stay a superscript mark.');
        $this->assertSame(['text', 'text', 'text'], array_map(fn ($n) => $n['type'], $superBlock['content']));
        $this->assertSame([], $this->marksFor($superBlock, 'The squared measure was x'), 'Surrounding text must keep its plain form.');

        $this->assertSame(['subscript'], $this->marksFor($subBlock, '2'), 'The formula index must stay a subscript mark.');

        // Strike and underline on the same run.
        foreach (['strike', 'underline'] as $mark) {
            $this->assertContains($mark, $this->marksFor($struckBlock, 'not part of the scholarly record'), "The struck run must carry the {$mark} mark.");
        }
    }

    public function test_inline_detail_round_trips_through_save_and_word_export(): void
    {
        [$submission, $workspace, ] = $this->workspaceFor('inlineFidelity');
        $editor = $this->user('Editor');

        // The editor touches nothing structural; the document as saved must
        // carry the same inline detail it imported with.
        $content = $workspace->content();

        $this->actingAs($editor)
            ->post(route('workspace.save', $submission), [
                'content' => $content,
                'baseRevision' => $workspace->revision,
            ])
            ->assertOk()
            ->assertJson(['outcome' => 'saved']);

        $xml = $this->documentXml($this->exportedPath(
            $this->actingAs($editor)->get(route('workspace.export', $submission)),
        ));

        // A line break inside the paragraph must export as w:br, never as a
        // paragraph split or a dropped character.
        $this->assertStringContainsString('First visual line of a soft-wrapped paragraph.', $xml);
        $this->assertStringContainsString('<w:br/>', $xml);
        $this->assertStringContainsString('Second visual line follows the break directly.', $xml);

        $this->assertStringContainsString('<w:vertAlign w:val="superscript"/>', $xml);
        $this->assertStringContainsString('<w:vertAlign w:val="subscript"/>', $xml);
        $this->assertStringContainsString('<w:strike/>', $xml);
        $this->assertStringContainsString('<w:u w:val="single"/>', $xml);

        // The editor's read endpoint returns the same structure.
        $this->actingAs($editor)
            ->get(route('workspace.document', $submission))
            ->assertOk()
            ->assertJsonPath('content.content.2.content.1.type', 'hardBreak');
    }

    // Mirrors the WorkspaceTest helper contract.
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
