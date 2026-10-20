<?php

namespace Tests\Feature\Documents\Formatting;

use App\Services\Documents\Ast\NodeType;
use App\Services\Documents\Formatting\DocxRenderer;
use App\Services\Documents\Formatting\FormattingEngine;
use App\Services\Documents\Formatting\StyleProfileRepository;
use App\Services\Documents\Ingestion\DocxSecurityValidator;
use App\Services\Documents\Parsing\DocxStructureParser;
use App\Services\Documents\Parsing\OoxmlPackage;
use Tests\Fixtures\DocxFixtureFactory;
use Tests\TestCase;
use ZipArchive;

class DocxRendererTest extends TestCase
{
    private DocxFixtureFactory $fixtures;

    private StyleProfileRepository $profiles;

    private string $outputDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtures = new DocxFixtureFactory();
        $this->profiles = new StyleProfileRepository();
        $this->outputDirectory = sys_get_temp_dir().'/ojmas-rendered';

        if (! is_dir($this->outputDirectory)) {
            mkdir($this->outputDirectory, 0777, true);
        }
    }

    private function renderFixture(string $fixture, ?string $profileSlug = null): array
    {
        $profileSlug ??= 'ojmas';
        $profile = $this->profiles->resolve($profileSlug);

        $sourcePath = $this->fixtures->{$fixture}();
        $sourceHash = hash_file('sha256', $sourcePath);

        $manuscript = (new DocxStructureParser())->parse($sourcePath);
        $result = (new FormattingEngine())->format($manuscript, $profile);

        $outputPath = $this->outputDirectory.'/'.$fixture.'-'.$profileSlug.'.docx';

        (new DocxRenderer($profile))->render(
            $result->formatted,
            $outputPath,
            OoxmlPackage::open($sourcePath),
        );

        return [$outputPath, $sourcePath, $sourceHash, $result];
    }

    private function assertIsOpenableDocx(string $path, string $context): void
    {
        $this->assertFileExists($path, $context);

        $zip = new ZipArchive();
        $opened = $zip->open($path, ZipArchive::RDONLY);

        $this->assertTrue($opened === true, "{$context}: the output must be a readable ZIP container.");

        try {
            $this->assertNotFalse($zip->locateName('[Content_Types].xml'), "{$context}: [Content_Types].xml is required.");
            $this->assertNotFalse($zip->locateName('word/document.xml'), "{$context}: word/document.xml is required.");
            $this->assertNotFalse($zip->locateName('word/_rels/document.xml.rels'), "{$context}: document relationships are required.");
            $this->assertNotFalse($zip->locateName('word/styles.xml'), "{$context}: styles.xml is required.");

            $documentXml = $zip->getFromName('word/document.xml');
            $this->assertIsString($documentXml);

            // The body XML must actually parse, which is the real test of
            // whether a word processor will open the file.
            $previous = libxml_use_internal_errors(true);
            $dom = new \DOMDocument();
            $parsed = $dom->loadXML($documentXml, LIBXML_NONET);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            $this->assertTrue($parsed, "{$context}: word/document.xml must be well-formed XML.");
            $this->assertNotNull($dom->getElementsByTagNameNS(
                'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
                'body',
            )->item(0), "{$context}: a w:body element is required.");
        } finally {
            $zip->close();
        }
    }

    private function documentText(string $path): string
    {
        $zip = new ZipArchive();
        $zip->open($path);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        $dom = new \DOMDocument();
        $dom->loadXML($xml, LIBXML_NONET);

        return $dom->textContent;
    }

    // ─────────── openability ───────────

    public function test_it_renders_an_openable_docx_for_every_fixture(): void
    {
        foreach (['compliant', 'nonCompliant', 'richMedia', 'manyReferences', 'abstractUnderHeading', 'withRawOoxml'] as $fixture) {
            [$outputPath] = $this->renderFixture($fixture);

            $this->assertIsOpenableDocx($outputPath, "Fixture: {$fixture}");
        }
    }

    public function test_the_rendered_document_survives_a_second_parse(): void
    {
        [$outputPath] = $this->renderFixture('compliant');

        $reparsed = (new DocxStructureParser())->parse($outputPath);

        $this->assertNotSame([], $reparsed->blocks);
        $this->assertStringContainsString('Instructional Scaffolding', (string) $reparsed->title());
        $this->assertCount(3, $reparsed->referenceEntries());
    }

    public function test_the_rendered_document_records_the_corrected_page_geometry(): void
    {
        [$outputPath] = $this->renderFixture('nonCompliant');

        $reparsed = (new DocxStructureParser())->parse($outputPath);

        $this->assertSame(11906, $reparsed->sectionProperties['width_twips'], 'A4 must be carried into the output.');
        $this->assertSame(16838, $reparsed->sectionProperties['height_twips']);
        $this->assertSame(1440, $reparsed->sectionProperties['margin_top_twips'], 'The corrected 1 inch margin must persist.');
    }

    // ─────────── preservation ───────────

    public function test_it_never_modifies_the_source_document(): void
    {
        [, $sourcePath, $sourceHash] = $this->renderFixture('richMedia');

        $this->assertSame(
            $sourceHash,
            hash_file('sha256', $sourcePath),
            'The author\'s original file must be byte-identical after a formatting run.',
        );
    }

    public function test_it_copies_embedded_media_byte_for_byte(): void
    {
        [$outputPath, $sourcePath] = $this->renderFixture('richMedia');

        $source = new ZipArchive();
        $source->open($sourcePath);
        $original = (string) $source->getFromName('word/media/image1.png');
        $source->close();

        $output = new ZipArchive();
        $output->open($outputPath);
        $carried = $output->getFromName('word/media/image1.png');
        $output->close();

        $this->assertNotFalse($carried, 'Embedded media must be carried into the formatted document.');
        $this->assertSame(
            md5($original),
            md5((string) $carried),
            'Embedded media must be byte-identical.',
        );
    }

    public function test_it_preserves_equations(): void
    {
        [$outputPath] = $this->renderFixture('withRawOoxml');

        $zip = new ZipArchive();
        $zip->open($outputPath);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertStringContainsString(
            'oMath',
            $xml,
            'An OMML equation must be written back as real OMML, not flattened to text.',
        );
        $this->assertStringContainsString('E = mc^2', $this->documentText($outputPath));
    }

    public function test_it_preserves_tracked_changes_with_their_authorship(): void
    {
        [$outputPath] = $this->renderFixture('withRawOoxml');

        $zip = new ZipArchive();
        $zip->open($outputPath);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertStringContainsString('<w:ins', $xml, 'Tracked insertions must survive as tracked insertions.');
        $this->assertStringContainsString('Reviewer One', $xml);
        $this->assertStringContainsString('An inserted sentence recorded as a tracked change', $this->documentText($outputPath));
    }

    public function test_it_preserves_unmapped_elements(): void
    {
        [$outputPath] = $this->renderFixture('withRawOoxml');

        $this->assertStringContainsString(
            'Text inside an unmapped custom XML block',
            $this->documentText($outputPath),
            'An unmapped block must not be silently deleted.',
        );
    }

    public function test_it_preserves_content_control_text(): void
    {
        [$outputPath] = $this->renderFixture('withRawOoxml');

        $this->assertStringContainsString(
            'A structured content control paragraph',
            $this->documentText($outputPath),
        );
    }

    public function test_it_preserves_table_structure(): void
    {
        [$outputPath] = $this->renderFixture('richMedia');

        $reparsed = (new DocxStructureParser())->parse($outputPath);
        $tables = $reparsed->blocksOfType(NodeType::TABLE);

        $this->assertCount(1, $tables);
        $this->assertSame(3, $tables[0]->attribute('row_count'), 'A header row plus two data rows.');
        $this->assertSame(4, $tables[0]->attribute('column_count'));
        $this->assertTrue($tables[0]->attribute('has_header_row'));

        $headerCells = array_map(fn ($cell) => $cell->text, $tables[0]->children[0]->children);
        $this->assertSame(['Variable', 'M', 'SD', 'N'], $headerCells);
    }

    public function test_it_preserves_every_reference_entry(): void
    {
        [$outputPath] = $this->renderFixture('nonCompliant');

        $reparsed = (new DocxStructureParser())->parse($outputPath);

        $this->assertCount(7, $reparsed->referenceEntries());
    }

    public function test_it_does_not_alter_the_authors_wording(): void
    {
        [$outputPath] = $this->renderFixture('nonCompliant');
        $outputText = $this->documentText($outputPath);

        foreach ([
            'This abstract is deliberately written far beyond',
            'Body paragraph 1. This paragraph cites',
            'Smith, J. (2020). A work that is cited in the body.',
        ] as $excerpt) {
            $this->assertStringContainsString($excerpt, $outputText, 'Formatting must never rewrite the author.');
        }
    }

    // ─────────── confidentiality ───────────

    public function test_the_output_metadata_does_not_leak_the_authors_name(): void
    {
        [$outputPath] = $this->renderFixture('compliant');

        $zip = new ZipArchive();
        $zip->open($outputPath);
        $core = (string) $zip->getFromName('docProps/core.xml');
        $zip->close();

        $this->assertStringNotContainsString('Amina Bello', $core);
        $this->assertStringContainsString('OJMAS Journal', $core);
    }

    // ─────────── security ───────────

    public function test_the_rendered_output_passes_the_security_validator(): void
    {
        $validator = new DocxSecurityValidator(config('manuscripts.limits'));

        foreach (['compliant', 'richMedia', 'withRawOoxml'] as $fixture) {
            [$outputPath] = $this->renderFixture($fixture);

            $report = $validator->validate($outputPath, basename($outputPath));

            $this->assertFalse($report->hasMacros(), "Fixture {$fixture}: no macros may be introduced.");
            $this->assertContains('word/document.xml', $report->partNames);
        }
    }

    // ─────────── determinism ───────────

    public function test_rendering_is_deterministic(): void
    {
        $profile = $this->profiles->resolve('ojmas');
        $sourcePath = $this->fixtures->compliant('render-deterministic.docx');

        $manuscript = (new DocxStructureParser())->parse($sourcePath);
        $result = (new FormattingEngine())->format($manuscript, $profile);

        $first = $this->outputDirectory.'/deterministic-a.docx';
        $second = $this->outputDirectory.'/deterministic-b.docx';

        (new DocxRenderer($profile))->render($result->formatted, $first, OoxmlPackage::open($sourcePath));
        (new DocxRenderer($profile))->render($result->formatted, $second, OoxmlPackage::open($sourcePath));

        $this->assertSame(
            $this->documentText($first),
            $this->documentText($second),
            'The same input must produce the same output.',
        );

        // Word embeds no timestamps here, so the whole package should match.
        $this->assertSame(hash_file('sha256', $first), hash_file('sha256', $second));
    }

    public function test_it_handles_a_manuscript_with_no_section_properties(): void
    {
        $profile = $this->profiles->resolve('ojmas');
        $manuscript = (new DocxStructureParser())->parse($this->fixtures->compliant('render-nosect.docx'));

        $result = (new FormattingEngine())->format($manuscript, $profile);
        $output = $this->outputDirectory.'/no-section.docx';

        (new DocxRenderer($profile))->render($result->formatted, $output);

        $this->assertIsOpenableDocx($output, 'No source section properties');
    }
}