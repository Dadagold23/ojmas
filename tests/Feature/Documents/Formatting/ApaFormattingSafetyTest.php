<?php

namespace Tests\Feature\Documents\Formatting;

use App\Models\ArticleFile;
use App\Models\ManuscriptDocument;
use App\Models\ManuscriptValidation;
use App\Models\ManuscriptVersion;
use App\Services\Documents\Ast\ManuscriptNode;
use App\Services\Documents\Ast\NodeType;
use App\Services\Documents\Exceptions\DocumentProcessingException;
use App\Services\Documents\Formatting\FormattingEngine;
use App\Services\Documents\Formatting\RuleFinding;
use App\Services\Documents\Formatting\StyleProfile;
use App\Services\Documents\Formatting\StyleProfileRepository;
use App\Services\Documents\ManuscriptProcessingResult;
use App\Services\Documents\ManuscriptProcessingService;
use App\Services\Documents\Parsing\DocxStructureParser;
use App\Services\Documents\Parsing\OoxmlPackage;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\DocxFixtureFactory;
use Tests\TestCase;
use ZipArchive;

/**
 * Corrective Module 5 — APA 7 formatting without content destruction.
 *
 * These tests pin the structural contract of the APA formatting derivative:
 * paragraphs, tables (rows, columns and every cell), figures, captions,
 * footnote anchors and note bodies, numerical results and author-facing text
 * must all survive the engine and the DOCX renderer unchanged. The style
 * changes the engine may make are formatting attributes only; anything it
 * cannot decide about must be flagged for review instead of changed.
 */
class ApaFormattingSafetyTest extends TestCase
{
    use RefreshDatabase;

    private DocxFixtureFactory $fixtures;

    private ManuscriptProcessingService $service;

    private StyleProfileRepository $profiles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtures = new DocxFixtureFactory();
        $this->service = $this->app->make(ManuscriptProcessingService::class);
        $this->profiles = new StyleProfileRepository();
    }

    private function submission(): Submission
    {
        $author = User::factory()->create();

        return Submission::factory()->create(['author_id' => $author->id]);
    }

    private function upload(string $path): UploadedFile
    {
        return new UploadedFile($path, basename($path), null, null, true);
    }

    /** @return array<int, RuleFinding> */
    private function findingsWithCode(\App\Services\Documents\Formatting\FormattingResult $result, string $code): array
    {
        return array_values(array_filter(
            $result->findings,
            fn (RuleFinding $finding) => $finding->code === $code,
        ));
    }

    private function engineFormat(string $fixtureName): \App\Services\Documents\Formatting\FormattingResult
    {
        $manuscript = (new DocxStructureParser())->parse($this->fixtures->{$fixtureName}('m5-'.uniqid().'.docx'));

        return (new FormattingEngine())->format($manuscript, $this->profiles->resolve(StyleProfile::OJMAS));
    }

    // ─────────────── tables, numbers and figures through the full pipeline ───────────────

    public function test_the_full_apa_pipeline_preserves_every_table_row_column_and_cell(): void
    {
        $submission = $this->submission();
        $version = $this->service->ingest($submission, $this->upload($this->fixtures->richMedia('m5-tables.docx')))['version'];

        $result = $this->service->process($version);
        $this->assertInstanceOf(ManuscriptProcessingResult::class, $result);

        $formatted = (new DocxStructureParser())->parse(Storage::disk('private')->path($result->formattedPath));
        $original = \App\Services\Documents\Ast\ParsedManuscript::fromArray($result->document->structure_json);

        $originalTables = $original->blocksOfType(NodeType::TABLE);
        $formattedTables = $formatted->blocksOfType(NodeType::TABLE);

        $this->assertCount(1, $originalTables);
        $this->assertCount(1, $formattedTables, 'Formatting must not delete a table.');

        $this->assertSame(
            $originalTables[0]->attribute('row_count'),
            $formattedTables[0]->attribute('row_count'),
            'Formatting must not delete rows.',
        );
        $this->assertSame(
            $originalTables[0]->attribute('column_count'),
            $formattedTables[0]->attribute('column_count'),
            'Formatting must not delete columns.',
        );

        $originalCells = $originalTables[0]->descendantsOfType(NodeType::TABLE_CELL);
        $formattedCells = $formattedTables[0]->descendantsOfType(NodeType::TABLE_CELL);
        $this->assertCount(count($originalCells), $formattedCells);

        foreach ($originalCells as $index => $cell) {
            $this->assertSame(
                (string) $cell->text,
                (string) $formattedCells[$index]->text,
                'Formatting must not alter any table value ("scientific meaning" is untouchable).',
            );
        }
    }

    public function test_the_full_apa_pipeline_preserves_paragraph_wording_figures_and_references(): void
    {
        $submission = $this->submission();
        $version = $this->service->ingest($submission, $this->upload($this->fixtures->richMedia('m5-structure.docx')))['version'];

        $result = $this->service->process($version);

        $formatted = (new DocxStructureParser())->parse(Storage::disk('private')->path($result->formattedPath));

        // Every top-level block survives, in order, with identical text.
        $this->assertCount(count($result->document->structure_json['blocks']), $formatted->blocks);

        foreach ($formatted->blocks as $index => $block) {
            $this->assertSame(
                (string) $result->document->structure_json['blocks'][$index]['text'],
                (string) $block->text,
                'Formatting must not rewrite a single word of the manuscript.',
            );
        }

        // Both figures survive as real inline images pointing at real parts.
        $images = $formatted->blocksOfType(NodeType::INLINE_IMAGE);
        $blocks = $formatted->blocks;

        foreach ($blocks as $block) {
            $images = array_merge($images, $block->descendantsOfType(NodeType::INLINE_IMAGE));
        }

        $this->assertCount(2, $images, 'Both figures must survive the APA render.');

        foreach ($images as $image) {
            $this->assertNotEmpty($image->attribute('media_part'));
        }

        // The reference entry survives with its text.
        $entries = $formatted->referenceEntries();
        $this->assertCount(1, $entries);
        $this->assertSame('Author, A. (2020). A cited work. Publisher.', (string) $entries[0]->text);
    }

    public function test_the_original_version_file_and_records_survive_the_full_pipeline(): void
    {
        $submission = $this->submission();
        $version = $this->service->ingest($submission, $this->upload($this->fixtures->richMedia('m5-recovery.docx')))['version'];

        $shaBefore = $version->sha256;
        $originalPath = $version->absolutePath();
        $originalBytesBefore = file_get_contents((string) $originalPath);

        $result = $this->service->process($version);

        // The original file is byte-identical; the derivative is a new file.
        $this->assertSame($shaBefore, $version->fresh()->sha256);
        $this->assertSame(hash('sha256', (string) $originalBytesBefore), $shaBefore);
        $this->assertNotSame($version->storage_path, $result->formattedPath);
        $this->assertDatabaseHas('article_files', [
            'id' => $version->source_file_id,
            'file_type' => 'manuscript',
            'sha256' => $shaBefore,
        ]);

        // And the original can still be re-read as the recovery route.
        $recovered = (new DocxStructureParser())->parse((string) $originalPath);
        $this->assertSame('A Media-Rich Manuscript for Preservation Testing', (string) $recovered->title());
    }

    public function test_a_failed_formatting_run_leaves_a_recoverable_state(): void
    {
        $submission = $this->submission();

        // A stored version whose file has gone missing from the private disk
        // (the same recovery scenario the ingest contract documented).
        $version = ManuscriptVersion::create([
            'submission_id' => $submission->id,
            'version_number' => 1,
            'storage_path' => 'submissions/manuscript/missing-file-m5.docx',
            'sha256' => hash('sha256', 'missing'),
            'format' => 'original',
            'origin' => 'author_upload',
        ]);

        try {
            $this->service->process($version);
            $this->fail('A missing stored file must raise a processing exception.');
        } catch (DocumentProcessingException) {
            $document = ManuscriptDocument::where('manuscript_version_id', $version->id)->first();
            $this->assertNotNull($document);
            $this->assertSame(ManuscriptDocument::STATUS_FAILED, $document->status);
            $this->assertNotEmpty($document->error_message);

            // Nothing half-formatted was persisted: no validation report can
            // exist for a run that failed, so the state stays recoverable by
            // simply re-uploading or re-running.
            $this->assertSame(0, ManuscriptValidation::where('submission_id', $submission->id)->count());
        }
    }

    // ─────────────── captions are left as authored and flagged ───────────────

    public function test_caption_paragraphs_are_left_unindented_and_flagged_for_review(): void
    {
        $result = $this->engineFormat('footnoted');

        $flagged = $this->findingsWithCode($result, 'layout.caption_left_for_review');
        $this->assertCount(2, $flagged, 'The table caption and the note line must be flagged.');

        foreach ($flagged as $finding) {
            $this->assertFalse($finding->wasAutoFixed);
        }

        $caption = null;
        $note = null;

        foreach ($result->formatted->blocks as $block) {
            if ($block->type === NodeType::PARAGRAPH && str_starts_with((string) $block->text, 'Table 1.')) {
                $caption = $block;
            }

            if ($block->type === NodeType::PARAGRAPH && str_starts_with((string) $block->text, 'Note.')) {
                $note = $block;
            }
        }

        $this->assertNotNull($caption, 'The caption paragraph must survive formatting.');
        $this->assertNotNull($note, 'The note paragraph must survive formatting.');
        $this->assertSame('Table 1. District register coverage', (string) $caption->text);
        $this->assertNull($caption->attribute('first_line_indent'), 'The engine must not silently indent a caption it cannot style.');
        $this->assertNull($note->attribute('first_line_indent'));
    }

    public function test_a_body_sentence_that_mentions_table_1_is_not_treated_as_a_caption(): void
    {
        $result = $this->engineFormat('nonCompliant');

        $this->assertCount(
            0,
            $this->findingsWithCode($result, 'layout.caption_left_for_review'),
            '"Table 1 reports the results." is a body sentence and keeps the body corrections.',
        );

        foreach ($result->formatted->blocks as $block) {
            if ($block->type === NodeType::PARAGRAPH && str_starts_with((string) $block->text, 'Table 1 reports')) {
                $this->assertSame(720, $block->attribute('first_line_indent'), 'Body sentences keep the APA indent.');
                return;
            }
        }

        $this->fail('The body sentence starting with "Table 1" must still be present.');
    }

    // ─────────────── footnotes through the full pipeline ───────────────

    public function test_the_parser_records_footnote_anchors_that_reach_the_engine_tree(): void
    {
        $parsed = (new DocxStructureParser())->parse($this->fixtures->footnoted('m5-parse.docx'));

        $footnoteRefs = [];
        $endnoteRefs = [];

        foreach ($parsed->blocks as $block) {
            foreach ($block->descendants() as $node) {
                if ($node->type === NodeType::FOOTNOTE_REFERENCE) {
                    $footnoteRefs[] = $node;
                }

                if ($node->type === 'endnote_reference') {
                    $endnoteRefs[] = $node;
                }
            }
        }

        $this->assertCount(2, $footnoteRefs, 'Both footnote anchors must be inline nodes now.');
        $this->assertCount(1, $endnoteRefs);
        $this->assertSame('1', $footnoteRefs[0]->attribute('note_id'));
        $this->assertSame('2', $footnoteRefs[1]->attribute('note_id'));
        $this->assertSame('1', $endnoteRefs[0]->attribute('note_id'));

        // The note bodies must be indexed with their texts.
        $texts = array_map(fn ($note) => $note['text'] ?? '', $parsed->footnotes);
        $this->assertContains('The register lists all 214 schools in the district.', $texts);
        $this->assertContains('Fieldwork dates were verified against the school calendar.', $texts);
    }

    public function test_the_apa_formatted_docx_carries_the_note_parts_byte_for_byte(): void
    {
        $submission = $this->submission();
        $fixturePath = $this->fixtures->footnoted('m5-footnotes.docx');
        $version = $this->service->ingest($submission, $this->upload($fixturePath))['version'];

        $result = $this->service->process($version);

        $source = OoxmlPackage::open($fixturePath);
        $formatted = OoxmlPackage::open(Storage::disk('private')->path($result->formattedPath));

        $this->assertNotNull($formatted->footnotesXml(), 'The formatted DOCX must carry the footnotes part.');
        $this->assertNotNull($formatted->endnotesXml(), 'The formatted DOCX must carry the endnotes part.');

        $this->assertSame($source->footnotesXml(), $formatted->footnotesXml(), 'Footnote bodies must be carried byte-for-byte.');
        $this->assertSame($source->endnotesXml(), $formatted->endnotesXml(), 'Endnote bodies must be carried byte-for-byte.');
    }

    public function test_the_apa_formatted_docx_re_emits_every_footnote_anchor(): void
    {
        $submission = $this->submission();
        $version = $this->service->ingest($submission, $this->upload($this->fixtures->footnoted('m5-anchors.docx')))['version'];

        $result = $this->service->process($version);
        $absolute = Storage::disk('private')->path($result->formattedPath);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($absolute, ZipArchive::RDONLY) === true);
        $documentXml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertStringContainsString('<w:footnoteReference w:id="1"/>', $documentXml);
        $this->assertStringContainsString('<w:footnoteReference w:id="2"/>', $documentXml);
        $this->assertStringContainsString('<w:endnoteReference w:id="1"/>', $documentXml);
        $this->assertStringNotContainsString('[figure unavailable', $documentXml);
        $this->assertStringNotContainsString('[note]', $documentXml, 'Anchors must bind to the carried note parts, not degrade to placeholders.');

        // And the derivative round-trips: re-parsing it finds the same anchors.
        $reparsed = (new DocxStructureParser())->parse($absolute);

        $footnoteRefs = [];

        foreach ($reparsed->blocks as $block) {
            foreach ($block->descendants() as $node) {
                if ($node->type === NodeType::FOOTNOTE_REFERENCE) {
                    $footnoteRefs[] = $node;
                }
            }
        }

        $this->assertCount(2, $footnoteRefs, 'The anchors must survive export and reimport.');
        $this->assertSame('1', $footnoteRefs[0]->attribute('note_id'));
        $this->assertSame('2', $footnoteRefs[1]->attribute('note_id'));
    }

    public function test_anchor_markers_supersede_the_original_runs_in_the_engine_tree_without_changing_text(): void
    {
        $original = (new DocxStructureParser())->parse($this->fixtures->footnoted('m5-compare.docx'));
        $result = (new FormattingEngine())->format($original, $this->profiles->resolve(StyleProfile::OJMAS));

        $this->assertCount(
            count($result->original->blocks),
            $result->formatted->blocks,
            'Formatting must never drop a block from the footnoted manuscript either.',
        );

        $originalText = implode("\n", array_map(fn ($block) => (string) $block->text, $result->original->blocks));
        $formattedText = implode("\n", array_map(fn ($block) => (string) $block->text, $result->formatted->blocks));

        $this->assertSame($originalText, $formattedText, 'Formatting must never alter the footnote manuscript wording.');
    }

    public function test_the_validation_report_says_the_notes_reach_the_formatted_document(): void
    {
        $result = $this->engineFormat('footnoted');

        $noteFindings = $this->findingsWithCode($result, 'content.note_preserved');

        $this->assertCount(3, $noteFindings, 'Two footnotes and one endnote must all be reported.');

        $report = json_encode(array_map(fn (RuleFinding $finding) => $finding->toArray(), $noteFindings));
        $this->assertIsString($report);
        $this->assertStringContainsString('214 schools', $report, 'The report must identify the actual note text.');
        $this->assertStringContainsString('school calendar', $report);
    }
}
