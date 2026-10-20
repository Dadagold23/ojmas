<?php

namespace Tests\Feature\Documents\Validation;

use App\Services\Documents\Ast\NodeType;
use App\Services\Documents\Ast\ParsedManuscript;
use App\Services\Documents\Formatting\FormattingEngine;
use App\Services\Documents\Formatting\FormattingResult;
use App\Services\Documents\Formatting\RuleFinding;
use App\Services\Documents\Formatting\StyleProfileRepository;
use App\Services\Documents\Parsing\DocxStructureParser;
use App\Services\Documents\Validation\CitationExtractor;
use App\Services\Documents\Validation\CitationValidationRule;
use App\Services\Documents\Validation\ReferenceParser;
use Tests\Fixtures\DocxFixtureFactory;
use Tests\TestCase;

class CitationValidationTest extends TestCase
{
    private DocxFixtureFactory $fixtures;

    private StyleProfileRepository $profiles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtures = new DocxFixtureFactory();
        $this->profiles = new StyleProfileRepository();
    }

    private function validate(ParsedManuscript $manuscript): FormattingResult
    {
        $engine = new FormattingEngine([new CitationValidationRule()]);

        return $engine->format($manuscript, $this->profiles->resolve('ojmas'));
    }

    /** @return array<int, RuleFinding> */
    private function withCode(FormattingResult $result, string $code): array
    {
        return array_values(array_filter($result->findings, fn (RuleFinding $f) => $f->code === $code));
    }

    // ─────────── extraction ───────────

    public function test_it_extracts_parenthetical_citations(): void
    {
        $citations = (new CitationExtractor())->extract([
            'The effect is well documented (Smith, 2020) and contested (Jones & Brown, 2019, p. 45).',
        ]);

        $this->assertCount(2, $citations);

        $this->assertSame('Smith', $citations[0]->surname);
        $this->assertSame('2020', $citations[0]->year);
        $this->assertFalse($citations[0]->narrative);

        $this->assertSame('Jones', $citations[1]->surname);
        $this->assertSame('2019', $citations[1]->year);
        $this->assertSame('p. 45', $citations[1]->locator);
    }

    public function test_it_extracts_multiple_citations_from_one_parenthetical(): void
    {
        $citations = (new CitationExtractor())->extract([
            'Several authors agree (Vygotsky, 1978; Wood et al., 1976; Belland et al., 2020).',
        ]);

        $this->assertCount(3, $citations);
        $this->assertSame(['Vygotsky', 'Wood', 'Belland'], array_map(fn ($c) => $c->surname, $citations));
    }

    public function test_it_extracts_narrative_citations(): void
    {
        $citations = (new CitationExtractor())->extract([
            'Belland et al. (2020) reported significant gains.',
        ]);

        $this->assertCount(1, $citations);
        $this->assertSame('Belland', $citations[0]->surname);
        $this->assertSame('2020', $citations[0]->year);
        $this->assertTrue($citations[0]->narrative);
    }

    public function test_it_does_not_treat_ordinary_numbers_as_citations(): void
    {
        $citations = (new CitationExtractor())->extract([
            'In 2020 we surveyed 128 students across 3 schools (see Table 1).',
            'The sample comprised (n = 2020) participants.',
        ]);

        $this->assertCount(0, $citations, 'A bare year in prose is not a citation.');
    }

    public function test_it_ignores_reference_entries_when_extracting_citations(): void
    {
        $manuscript = (new DocxStructureParser())->parse($this->fixtures->compliant('cite-compliant.docx'));

        $findings = $this->validate($manuscript);

        // The compliant fixture cites Belland, Vygotsky and Wood in the body and
        // lists all three. No citation should be reported as unmatched.
        $this->assertCount(
            0,
            $this->withCode($findings, 'citation.no_matching_reference'),
            'A fully matched manuscript must produce no citation errors.',
        );
        $this->assertCount(0, $this->withCode($findings, 'reference.never_cited'));
    }

    // ─────────── cross-reference checks ───────────

    public function test_it_flags_a_citation_with_no_reference_entry(): void
    {
        $findings = $this->validate(
            (new DocxStructureParser())->parse($this->fixtures->nonCompliant('cite-missing.docx')),
        );

        $unmatched = $this->withCode($findings, 'citation.no_matching_reference');

        $this->assertNotEmpty($unmatched);

        $messages = implode(' ', array_map(fn (RuleFinding $f) => $f->message, $unmatched));

        // "Smith, J. (2020)" is present in the reference list, so it matches.
        // "(Jones & Brown, 2019)" has no entry at all and must be reported.
        $this->assertStringContainsString('Jones', $messages);
        $this->assertStringNotContainsString('(Smith, 2020)" has no matching', $messages);
        $this->assertStringContainsString(
            'No bibliographic details have been invented',
            $messages,
        );
    }

    public function test_it_flags_a_reference_that_is_never_cited(): void
    {
        $findings = $this->validate(
            (new DocxStructureParser())->parse($this->fixtures->nonCompliant('cite-uncited.docx')),
        );

        $uncited = $this->withCode($findings, 'reference.never_cited');

        $this->assertNotEmpty($uncited);
        $this->assertStringContainsString('Brown', implode(' ', array_map(fn (RuleFinding $f) => $f->message, $uncited)));
    }

    public function test_it_detects_a_year_mismatch_between_citation_and_reference(): void
    {
        // The non-compliant fixture cites (Smith, 2020) but lists "Smyth, S. (2016)".
        $findings = $this->validate(
            (new DocxStructureParser())->parse($this->fixtures->nonCompliant('cite-year-mismatch.docx')),
        );

        $codes = array_map(fn (RuleFinding $f) => $f->code, $findings->findings);

        $this->assertContains('citation.no_matching_reference', $codes);
    }

    public function test_it_flags_incomplete_reference_entries(): void
    {
        $findings = $this->validate(
            (new DocxStructureParser())->parse($this->fixtures->nonCompliant('cite-incomplete.docx')),
        );

        $incomplete = $this->withCode($findings, 'reference.incomplete');

        $this->assertNotEmpty($incomplete);

        $message = implode(' ', array_map(fn (RuleFinding $f) => $f->message, $incomplete));
        $this->assertStringContainsString('title', $message);
        $this->assertStringContainsString('source', $message);
        $this->assertStringContainsString('Nothing has been supplied automatically', $message);
    }

    public function test_it_flags_probable_duplicate_references(): void
    {
        $findings = $this->validate(
            (new DocxStructureParser())->parse($this->fixtures->nonCompliant('cite-duplicates.docx')),
        );

        $duplicates = $this->withCode($findings, 'reference.possible_duplicate');

        $this->assertNotEmpty($duplicates);
        $this->assertStringContainsString(
            'duplicate reference',
            strtolower(implode(' ', array_map(fn (RuleFinding $f) => $f->message, $duplicates))),
        );
    }

    public function test_it_flags_a_malformed_doi(): void
    {
        $findings = $this->validate(
            (new DocxStructureParser())->parse($this->fixtures->nonCompliant('cite-doi.docx')),
        );

        $malformed = $this->withCode($findings, 'reference.malformed_doi');

        $this->assertNotEmpty($malformed);
        $this->assertStringContainsString('11.1234/not-a-doi', $malformed[0]->message);
        $this->assertStringContainsString('doi.org', $malformed[0]->message, 'APA 7 presentation should be suggested.');
    }

    public function test_a_valid_doi_is_not_flagged(): void
    {
        $findings = $this->validate(
            (new DocxStructureParser())->parse($this->fixtures->compliant('cite-good-doi.docx')),
        );

        $this->assertCount(0, $this->withCode($findings, 'reference.malformed_doi'));
    }

    public function test_every_finding_is_a_warning_not_an_error(): void
    {
        // Bibliographic problems must never reject a manuscript outright.
        $findings = $this->validate(
            (new DocxStructureParser())->parse($this->fixtures->nonCompliant('cite-severity.docx')),
        );

        $this->assertNotEmpty($findings->findings);

        foreach ($findings->findings as $finding) {
            $this->assertSame(
                RuleFinding::SEVERITY_WARNING,
                $finding->severity,
                "Finding '{$finding->code}' must be advisory, not fatal.",
            );
        }
    }

    // ─────────── reference parsing ───────────

    public function test_the_reference_parser_reads_apa_fields(): void
    {
        $record = (new ReferenceParser())->parseOne(
            'Belland, B. R., Walker, A. E., Kim, S., & Lefler, M. (2020). Synthesizing results: A meta-analysis. '
            .'Review of Educational Research, 90(4), 611-651. https://doi.org/10.3102/0034654320914221',
            1,
        );

        $this->assertSame('Belland', $record->surname);
        $this->assertSame('2020', $record->year);
        $this->assertSame('10.3102/0034654320914221', $record->doiRaw);
        $this->assertSame('611-651', $record->pagesRaw);
        $this->assertStringContainsString('Synthesizing results', (string) $record->titleRaw);
        $this->assertSame([], $record->missingFields());
    }

    public function test_the_reference_parser_reports_missing_fields_without_inventing_them(): void
    {
        $record = (new ReferenceParser())->parseOne('Nobody, N. (2021).', 1);

        $this->assertSame('Nobody', $record->surname);
        $this->assertSame('2021', $record->year);
        $this->assertNull($record->titleRaw);
        $this->assertNull($record->sourceRaw);
        $this->assertNull($record->doiRaw);

        $missing = $record->missingFields();
        $this->assertContains('title', $missing);
        $this->assertContains('source', $missing);
        $this->assertNotContains('author', $missing);
        $this->assertNotContains('year', $missing);
    }

    public function test_the_validator_handles_a_manuscript_with_no_reference_list(): void
    {
        $manuscript = (new DocxStructureParser())->parse($this->fixtures->richMedia('cite-no-refs.docx'));

        // Strip the single reference entry so the manuscript genuinely has none.
        $blocks = array_values(array_filter(
            $manuscript->blocks,
            fn ($block) => $block->type !== NodeType::REFERENCE_ENTRY && trim((string) $block->text) !== 'References',
        ));

        $withoutReferences = new ParsedManuscript(
            $blocks,
            $manuscript->sectionProperties,
            $manuscript->media,
            $manuscript->footnotes,
            $manuscript->coreProperties,
            $manuscript->security,
            $manuscript->wordCount,
            $manuscript->paragraphCount,
        );

        $this->assertSame([], $withoutReferences->referenceEntries());

        $findings = $this->validate($withoutReferences);

        $this->assertCount(0, $this->withCode($findings, 'reference.never_cited'));
        $this->assertCount(0, $this->withCode($findings, 'reference.possible_duplicate'));
        $this->assertCount(0, $this->withCode($findings, 'reference.incomplete'));
    }

    public function test_it_scales_to_a_long_reference_list(): void
    {
        $manuscript = (new DocxStructureParser())->parse($this->fixtures->manyReferences(60, 'cite-many.docx'));

        $findings = $this->validate($manuscript);

        $this->assertCount(
            0,
            $this->withCode($findings, 'citation.no_matching_reference'),
            'Sixty matched citations and sixty references should reconcile cleanly.',
        );
        $this->assertCount(0, $this->withCode($findings, 'reference.never_cited'));
        $this->assertCount(60, $manuscript->referenceEntries());
    }
}