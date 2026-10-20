<?php

namespace Tests\Feature\Documents\Parsing;

use App\Services\Documents\Ast\NodeType;
use App\Services\Documents\Ingestion\DocxSecurityValidator;
use App\Services\Documents\Parsing\DocxStructureParser;
use Tests\Fixtures\DocxFixtureFactory;
use Tests\TestCase;

class DocxStructureParserTest extends TestCase
{
    private DocxFixtureFactory $fixtures;

    private function parser(): DocxStructureParser
    {
        return new DocxStructureParser();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtures = new DocxFixtureFactory();
    }

    public function test_it_parses_headings_paragraphs_and_references(): void
    {
        $manuscript = $this->parser()->parse($this->fixtures->compliant('parse-compliant.docx'));

        $headings = array_map(fn ($node) => trim((string) $node->text), $manuscript->headings());

        $this->assertContains('Introduction', $headings);
        $this->assertContains('Method', $headings);
        $this->assertContains('Design and Participants', $headings);
        $this->assertContains('References', $headings);

        $this->assertCount(3, $manuscript->referenceEntries(), 'All three references should be captured.');

        $firstReference = $manuscript->referenceEntries()[0];
        $this->assertSame(1, $firstReference->attribute('ordinal'));
        $this->assertStringContainsString('Belland', (string) $firstReference->text);
        $this->assertTrue($firstReference->attribute('is_reference_entry'));
    }

    public function test_it_records_heading_levels(): void
    {
        $manuscript = $this->parser()->parse($this->fixtures->compliant('parse-levels.docx'));

        $levels = [];

        foreach ($manuscript->headings() as $heading) {
            $levels[trim((string) $heading->text)] = $heading->attribute('level');
        }

        $this->assertSame(1, $levels['Introduction']);
        $this->assertSame(1, $levels['Method']);
        $this->assertSame(2, $levels['Design and Participants']);
    }

    public function test_it_records_page_geometry_from_the_source(): void
    {
        $manuscript = $this->parser()->parse($this->fixtures->compliant('parse-geometry.docx'));

        $this->assertSame(11906, $manuscript->sectionProperties['width_twips'], 'A4 width');
        $this->assertSame(16838, $manuscript->sectionProperties['height_twips'], 'A4 height');
        $this->assertSame(1440, $manuscript->sectionProperties['margin_top_twips']);
        $this->assertSame(1440, $manuscript->sectionProperties['margin_left_twips']);
    }

    public function test_it_records_non_compliant_geometry_from_a_letter_document(): void
    {
        $manuscript = $this->parser()->parse($this->fixtures->nonCompliant('parse-letter.docx'));

        $this->assertSame(12240, $manuscript->sectionProperties['width_twips'], 'US Letter width');
        $this->assertSame(2160, $manuscript->sectionProperties['margin_top_twips'], '1.5 inch margin');
    }

    public function test_it_records_paragraph_formatting_that_rules_act_on(): void
    {
        $manuscript = $this->parser()->parse($this->fixtures->nonCompliant('parse-formatting.docx'));

        $bodyParagraphs = array_values(array_filter(
            $manuscript->blocks,
            fn ($node) => $node->type === NodeType::PARAGRAPH
                && str_starts_with((string) $node->text, 'Body paragraph 1'),
        ));

        $this->assertCount(1, $bodyParagraphs);

        $paragraph = $bodyParagraphs[0];

        $this->assertSame('Courier New', $paragraph->attribute('font'));
        $this->assertSame(22, $paragraph->attribute('font_size_half_points'), '11pt is 22 half-points');
        $this->assertSame(240, $paragraph->attribute('line_spacing'), 'Single spacing');
        $this->assertSame(720, $paragraph->attribute('first_line_indent'), '0.5 inch');
        $this->assertSame('both', $paragraph->attribute('alignment'), 'Justified');
    }

    public function test_it_records_hanging_indent_on_reference_entries(): void
    {
        $manuscript = $this->parser()->parse($this->fixtures->compliant('parse-hanging.docx'));

        $firstReference = $manuscript->referenceEntries()[0];

        $this->assertSame(720, $firstReference->attribute('hanging_indent'), '0.5 inch hanging indent');
        $this->assertSame(720, $firstReference->attribute('left_indent'));
    }

    public function test_it_extracts_tables_with_structure(): void
    {
        $manuscript = $this->parser()->parse($this->fixtures->compliant('parse-tables.docx'));

        $tables = $manuscript->blocksOfType(NodeType::TABLE);

        $this->assertCount(1, $tables);

        $table = $tables[0];
        $this->assertSame(3, $table->attribute('row_count'));
        $this->assertSame(3, $table->attribute('column_count'));
        $this->assertTrue($table->attribute('has_header_row'));

        $firstRow = $table->children[0];
        $this->assertSame(NodeType::TABLE_ROW, $firstRow->type);
        $this->assertSame('Group', $firstRow->children[0]->text);
        $this->assertSame('SD', $firstRow->children[2]->text);
    }

    public function test_it_preserves_images_and_reports_media_parts(): void
    {
        $manuscript = $this->parser()->parse($this->fixtures->richMedia('parse-media.docx'));

        $this->assertCount(2, $manuscript->media, 'Both embedded images should be catalogued.');
        $this->assertSame('word/media/image1.png', $manuscript->media[0]['part']);
        $this->assertGreaterThan(0, $manuscript->media[0]['bytes']);
    }

    public function test_it_preserves_content_controls_without_losing_their_text(): void
    {
        $manuscript = $this->parser()->parse($this->fixtures->withRawOoxml('parse-raw.docx'));

        $allText = $manuscript->bodyText();

        $this->assertStringContainsString(
            'A structured content control paragraph.',
            $allText,
            'Content inside a structured document tag must survive parsing.',
        );
    }

    public function test_it_recognises_omml_equations(): void
    {
        $manuscript = $this->parser()->parse($this->fixtures->withRawOoxml('parse-equation.docx'));

        $equations = $manuscript->blocksOfType(NodeType::EQUATION);

        foreach ($manuscript->blocks as $block) {
            $equations = array_merge($equations, $block->descendantsOfType(NodeType::EQUATION));
        }

        $this->assertNotEmpty($equations, 'An OMML equation should be recognised, not dropped.');
        $this->assertSame('omml', $equations[0]->attribute('format'));
        $this->assertStringContainsString('E = mc^2', (string) $equations[0]->text);
    }

    public function test_it_records_core_properties_that_leak_authorship(): void
    {
        $manuscript = $this->parser()->parse($this->fixtures->compliant('parse-coreprops.docx'));

        $this->assertSame('Amina Bello', $manuscript->coreProperties['creator']);
        $this->assertSame('Amina Bello', $manuscript->coreProperties['last_modified_by']);
    }

    public function test_it_promotes_the_abstract_under_a_heading(): void
    {
        $manuscript = $this->parser()->parse($this->fixtures->abstractUnderHeading('parse-abstract.docx'));

        $abstract = $manuscript->abstract();

        $this->assertNotNull($abstract, 'The abstract paragraph should be promoted out of front matter.');
        $this->assertStringContainsString('social media usage', (string) $abstract->text);
    }

    public function test_it_identifies_the_title_block(): void
    {
        $manuscript = $this->parser()->parse($this->fixtures->compliant('parse-title.docx'));

        $this->assertNotNull($manuscript->title());
        $this->assertStringContainsString('Instructional Scaffolding', (string) $manuscript->title());
    }

    public function test_it_counts_words_and_paragraphs(): void
    {
        $manuscript = $this->parser()->parse($this->fixtures->compliant('parse-counts.docx'));

        $this->assertGreaterThan(200, $manuscript->wordCount);
        $this->assertGreaterThan(0, $manuscript->paragraphCount);
    }

    public function test_it_round_trips_through_the_serialised_array_form(): void
    {
        $manuscript = $this->parser()->parse($this->fixtures->compliant('parse-serialise.docx'));

        $serialised = $manuscript->toArray();
        $restored = \App\Services\Documents\Ast\ParsedManuscript::fromArray($serialised);

        $this->assertCount(count($manuscript->blocks), $restored->blocks);
        $this->assertSame($manuscript->wordCount, $restored->wordCount);
        $this->assertSame($manuscript->title(), $restored->title());
    }

    public function test_it_attaches_a_security_report_when_supplied(): void
    {
        $path = $this->fixtures->compliant('parse-security.docx');

        $security = (new DocxSecurityValidator([
            'max_upload_bytes' => 104857600,
            'max_entries' => 3000,
            'max_entry_uncompressed_bytes' => 268435456,
            'max_total_uncompressed_bytes' => 536870912,
            'max_compression_ratio' => 120,
            'required_parts' => ['[Content_Types].xml', 'word/document.xml'],
            'forbidden_parts' => ['vbaProject.bin'],
        ]))->validate($path, basename($path));

        $manuscript = $this->parser()->parse($path, $security);

        $this->assertNotNull($manuscript->security);
        $this->assertFalse($manuscript->security->hasMacros());
    }

    public function test_it_never_writes_to_the_source_document(): void
    {
        $path = $this->fixtures->richMedia('parse-immutable.docx');
        $before = hash_file('sha256', $path);

        $this->parser()->parse($path);

        $this->assertSame($before, hash_file('sha256', $path));
    }
}