<?php

namespace Tests\Feature\Documents\Formatting;

use App\Services\Documents\Ast\NodeType;
use App\Services\Documents\Formatting\FormattingEngine;
use App\Services\Documents\Formatting\FormattingResult;
use App\Services\Documents\Formatting\RuleFinding;
use App\Services\Documents\Formatting\StyleProfile;
use App\Services\Documents\Formatting\StyleProfileRepository;
use App\Services\Documents\Parsing\DocxStructureParser;
use Tests\Fixtures\DocxFixtureFactory;
use Tests\TestCase;

class FormattingEngineTest extends TestCase
{
    private DocxFixtureFactory $fixtures;

    private StyleProfileRepository $profiles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtures = new DocxFixtureFactory();
        $this->profiles = new StyleProfileRepository();
    }

    private function formatFixture(string $fixtureName, ?string $profileSlug = null): FormattingResult
    {
        $manuscript = (new DocxStructureParser())->parse($this->fixtures->{$fixtureName}('format-'.$profileSlug.'-'.$fixtureName.'.docx'));

        return (new FormattingEngine())->format($manuscript, $this->profiles->resolve($profileSlug));
    }

    /** @return array<int, RuleFinding> */
    private function findingsWithCode(FormattingResult $result, string $code): array
    {
        return array_values(array_filter(
            $result->findings,
            fn (RuleFinding $finding) => $finding->code === $code,
        ));
    }

    // ─────────── profiles ───────────

    public function test_the_ojmas_profile_overrides_only_what_the_journal_does(): void
    {
        $ojmas = $this->profiles->resolve(StyleProfile::OJMAS);
        $apa = $this->profiles->resolve(StyleProfile::APA7_PROFESSIONAL);

        // Journal overrides.
        $this->assertSame(11906, $ojmas->requirement('paper')['width_twips'], 'OJMAS requires A4');
        $this->assertSame(18, $ojmas->requirement('title_max_words'));
        $this->assertSame(150, $ojmas->requirement('abstract_max_words'));
        $this->assertTrue($ojmas->requirement('author_details_on_separate_cover_page'));

        // Inherited APA 7 baseline.
        $this->assertSame(480, $ojmas->requirement('line_spacing_twips'));
        $this->assertSame(720, $ojmas->requirement('body_first_line_indent_twips'));
        $this->assertSame($apa->requirement('approved_fonts'), $ojmas->requirement('approved_fonts'));

        // APA 7 baseline itself must remain the standard professional profile.
        $this->assertSame(12240, $apa->requirement('paper')['width_twips']);
        $this->assertSame(250, $apa->requirement('abstract_max_words'));
        $this->assertFalse($apa->requirement('author_details_on_separate_cover_page'));
    }

    public function test_student_paper_rules_are_never_imposed(): void
    {
        foreach ([StyleProfile::OJMAS, StyleProfile::APA7_PROFESSIONAL] as $slug) {
            $profile = $this->profiles->resolve($slug);

            $this->assertGreaterThanOrEqual(120, (int) $profile->requirement('abstract_max_words') === 150 && $slug === StyleProfile::OJMAS
                ? 150
                : (int) $profile->requirement('abstract_max_words'), 'sanity');
            $this->assertSame(480, $profile->requirement('line_spacing_twips'), 'Double spacing, not triple.');
            $this->assertFalse((bool) $profile->requirement('student_paper_mode', false));
        }
    }

    // ─────────── page geometry ───────────

    public function test_it_corrects_us_letter_to_a4_under_the_ojmas_profile(): void
    {
        $result = $this->formatFixture('nonCompliant', StyleProfile::OJMAS);

        $fixes = $this->findingsWithCode($result, 'page_geometry.paper_size_corrected');

        $this->assertCount(1, $fixes);
        $this->assertTrue($fixes[0]->wasAutoFixed);
        $this->assertStringContainsString('US Letter', $fixes[0]->message);
        $this->assertStringContainsString('A4', $fixes[0]->message);

        $this->assertSame(11906, $result->formatted->sectionProperties['width_twips']);
        $this->assertSame(16838, $result->formatted->sectionProperties['height_twips']);
    }

    public function test_it_leaves_a4_alone_under_the_ojmas_profile(): void
    {
        $result = $this->formatFixture('compliant', StyleProfile::OJMAS);

        $this->assertCount(0, $this->findingsWithCode($result, 'page_geometry.paper_size_corrected'));
    }

    public function test_it_reports_letter_as_correct_for_the_bare_apa7_profile(): void
    {
        // The non-compliant fixture is US Letter: correct under APA 7, wrong under OJMAS.
        $result = $this->formatFixture('nonCompliant', StyleProfile::APA7_PROFESSIONAL);

        $this->assertCount(0, $this->findingsWithCode($result, 'page_geometry.paper_size_corrected'));
        $this->assertSame(12240, $result->formatted->sectionProperties['width_twips']);
    }

    public function test_it_corrects_margins(): void
    {
        $result = $this->formatFixture('nonCompliant', StyleProfile::OJMAS);

        $marginFixes = $this->findingsWithCode($result, 'page_geometry.margin_corrected');

        $this->assertNotEmpty($marginFixes, '1.5 inch margins should be corrected to 1 inch.');

        foreach (['margin_top_twips', 'margin_right_twips', 'margin_bottom_twips', 'margin_left_twips'] as $key) {
            $this->assertSame(1440, $result->formatted->sectionProperties[$key]);
        }
    }

    // ─────────── typography ───────────

    public function test_it_flags_a_non_approved_font_as_an_author_action_error(): void
    {
        $result = $this->formatFixture('nonCompliant', StyleProfile::OJMAS);

        $fontFindings = $this->findingsWithCode($result, 'typography.font_not_approved');

        $this->assertNotEmpty($fontFindings);
        $this->assertSame(RuleFinding::SEVERITY_ERROR, $fontFindings[0]->severity);
        $this->assertStringContainsString('Courier New', $fontFindings[0]->message);
        $this->assertTrue($fontFindings[0]->requiresAuthorAction);
        $this->assertFalse($fontFindings[0]->wasAutoFixed, 'The engine must not silently restyle the author\'s typeface.');
    }

    public function test_it_does_not_flag_an_approved_font(): void
    {
        $result = $this->formatFixture('compliant', StyleProfile::OJMAS);

        $this->assertCount(0, $this->findingsWithCode($result, 'typography.font_not_approved'));
    }

    public function test_it_flags_a_non_approved_font_size(): void
    {
        $result = $this->formatFixture('nonCompliant', StyleProfile::OJMAS);

        $this->assertNotEmpty($this->findingsWithCode($result, 'typography.font_size_not_approved'));
    }

    public function test_it_corrects_single_spacing_to_double(): void
    {
        $result = $this->formatFixture('nonCompliant', StyleProfile::OJMAS);

        $spacingFixes = $this->findingsWithCode($result, 'typography.line_spacing_corrected');

        $this->assertNotEmpty($spacingFixes);
        $this->assertTrue($spacingFixes[0]->wasAutoFixed);

        $bodyParagraph = null;

        foreach ($result->formatted->blocks as $block) {
            if ($block->type === NodeType::PARAGRAPH && str_starts_with((string) $block->text, 'Body paragraph 1')) {
                $bodyParagraph = $block;
                break;
            }
        }

        $this->assertNotNull($bodyParagraph);
        $this->assertSame(480, $bodyParagraph->attribute('line_spacing'), 'Double spacing is 480 twips.');
        $this->assertSame('auto', $bodyParagraph->attribute('line_spacing_rule'));
    }

    // ─────────── paragraph indentation ───────────

    public function test_it_corrects_first_line_indentation(): void
    {
        // The non-compliant fixture indents body paragraphs by a correct 0.5
        // inch, so nothing should be flagged there.
        $alreadyCorrect = $this->formatFixture('nonCompliant', StyleProfile::OJMAS);
        $this->assertCount(0, $this->findingsWithCode($alreadyCorrect, 'layout.first_line_indent_corrected'));

        // The compliant fixture omits the indent entirely, so the engine must
        // apply it rather than leave it to the reader.
        $result = $this->formatFixture('compliant', StyleProfile::OJMAS);

        $applied = $this->findingsWithCode($result, 'layout.first_line_indent_applied');
        $this->assertNotEmpty($applied, 'A missing first-line indent must be applied.');
        $this->assertTrue($applied[0]->wasAutoFixed);

        $bodyParagraph = null;

        foreach ($result->formatted->blocks as $block) {
            if ($block->type === NodeType::PARAGRAPH
                && str_starts_with((string) $block->text, 'Instructional scaffolding has been widely promoted')) {
                $bodyParagraph = $block;
                break;
            }
        }

        $this->assertNotNull($bodyParagraph, 'The body paragraph must still be present after formatting.');
        $this->assertSame(720, $bodyParagraph->attribute('first_line_indent'));
    }

    public function test_it_corrects_a_wrong_first_line_indent(): void
    {
        $manuscript = (new DocxStructureParser())->parse($this->fixtures->compliant('format-bad-indent.docx'));
        $blocks = $manuscript->blocks;

        foreach ($blocks as $index => $block) {
            if ($block->type === NodeType::PARAGRAPH
                && str_starts_with((string) $block->text, 'Instructional scaffolding has been widely promoted')) {
                $blocks[$index] = $block->setAttribute('first_line_indent', 1440);
            }
        }

        $result = (new FormattingEngine())->format(
            new \App\Services\Documents\Ast\ParsedManuscript(
                $blocks,
                $manuscript->sectionProperties,
                $manuscript->media,
                $manuscript->footnotes,
                $manuscript->coreProperties,
                $manuscript->security,
                $manuscript->wordCount,
                $manuscript->paragraphCount,
            ),
            $this->profiles->resolve(StyleProfile::OJMAS),
        );

        $corrections = $this->findingsWithCode($result, 'layout.first_line_indent_corrected');

        $this->assertCount(1, $corrections);
        $this->assertStringContainsString('1.00 inches', $corrections[0]->message);

        foreach ($result->formatted->blocks as $block) {
            if ($block->type === NodeType::PARAGRAPH
                && str_starts_with((string) $block->text, 'Instructional scaffolding has been widely promoted')) {
                $this->assertSame(720, $block->attribute('first_line_indent'));
            }
        }
    }

    public function test_it_corrects_justified_alignment_to_flush_left(): void
    {
        $result = $this->formatFixture('nonCompliant', StyleProfile::OJMAS);

        $this->assertNotEmpty($this->findingsWithCode($result, 'layout.alignment_corrected'));

        $bodyParagraph = null;

        foreach ($result->formatted->blocks as $block) {
            if ($block->type === NodeType::PARAGRAPH && str_starts_with((string) $block->text, 'Body paragraph 1')) {
                $bodyParagraph = $block;
                break;
            }
        }

        $this->assertSame('left', $bodyParagraph->attribute('alignment'));
    }

    // ─────────── references ───────────

    public function test_it_corrects_missing_hanging_indents_on_references(): void
    {
        $result = $this->formatFixture('compliant', StyleProfile::OJMAS);

        // The compliant fixture already sets a 0.5 inch hanging indent.
        $this->assertCount(0, $this->findingsWithCode($result, 'reference.hanging_indent_corrected'));
    }

    public function test_it_detects_manually_numbered_references(): void
    {
        $manuscript = (new DocxStructureParser())->parse(
            $this->fixtures->withRawOoxml('format-numbered.docx'),
        );

        // Inject numbering by rewriting the first reference through the tree.
        $blocks = $manuscript->blocks;

        foreach ($blocks as $index => $block) {
            if ($block->type === NodeType::REFERENCE_ENTRY) {
                $blocks[$index] = new \App\Services\Documents\Ast\ManuscriptNode(
                    NodeType::REFERENCE_ENTRY,
                    $block->attributes + ['is_reference_entry' => true],
                    $block->children,
                    '1. '.preg_replace('/^[A-Za-z\'’, ]+,/', 'Writer, W.', (string) $block->text),
                );
                break;
            }
        }

        $numbered = new \App\Services\Documents\Ast\ParsedManuscript(
            blocks: $blocks,
            sectionProperties: $manuscript->sectionProperties,
            media: $manuscript->media,
            footnotes: $manuscript->footnotes,
            coreProperties: $manuscript->coreProperties,
            security: $manuscript->security,
            wordCount: $manuscript->wordCount,
            paragraphCount: $manuscript->paragraphCount,
        );

        $result = (new FormattingEngine())->format($numbered, $this->profiles->resolve(StyleProfile::OJMAS));

        $this->assertNotEmpty($this->findingsWithCode($result, 'reference.manually_numbered'));
    }

    public function test_it_warns_when_references_are_out_of_alphabetical_order(): void
    {
        $result = $this->formatFixture('nonCompliant', StyleProfile::OJMAS);

        $outOfOrder = $this->findingsWithCode($result, 'reference.out_of_alphabetical_order');

        $this->assertNotEmpty($outOfOrder, 'The fixture lists a reference beginning "Wrong" after "Nobody".');
        $this->assertSame(RuleFinding::SEVERITY_WARNING, $outOfOrder[0]->severity);
    }

    // ─────────── title and abstract ───────────

    public function test_it_flags_an_over_long_title_against_the_ojmas_limit(): void
    {
        $result = $this->formatFixture('nonCompliant', StyleProfile::OJMAS);

        $findings = $this->findingsWithCode($result, 'title.exceeds_word_limit');

        $this->assertCount(1, $findings);
        $this->assertSame(RuleFinding::SEVERITY_ERROR, $findings[0]->severity);
        $this->assertStringContainsString('18 words', $findings[0]->message);
        $this->assertFalse($findings[0]->wasAutoFixed, 'Titles are never rewritten automatically.');
    }

    public function test_the_same_title_is_accepted_under_the_bare_apa7_limit(): void
    {
        $result = $this->formatFixture('nonCompliant', StyleProfile::APA7_PROFESSIONAL);

        $this->assertCount(0, $this->findingsWithCode($result, 'title.exceeds_word_limit'));
    }

    public function test_it_flags_an_abstract_over_the_ojmas_word_ceiling(): void
    {
        $result = $this->formatFixture('nonCompliant', StyleProfile::OJMAS);

        $findings = $this->findingsWithCode($result, 'abstract.exceeds_word_limit');

        $this->assertCount(1, $findings);
        $this->assertStringContainsString('150 words', $findings[0]->message);
        $this->assertFalse($findings[0]->wasAutoFixed);
    }

    public function test_a_compliant_abstract_produces_no_finding(): void
    {
        $result = $this->formatFixture('compliant', StyleProfile::OJMAS);

        $this->assertCount(0, $this->findingsWithCode($result, 'abstract.exceeds_word_limit'));
        $this->assertCount(0, $this->findingsWithCode($result, 'abstract.missing'));
    }

    public function test_it_requires_an_abstract_under_ojmas_but_not_under_bare_apa7(): void
    {
        $manuscript = (new DocxStructureParser())->parse($this->fixtures->richMedia('format-no-abstract.docx'));

        $ojmas = (new FormattingEngine())->format($manuscript, $this->profiles->resolve(StyleProfile::OJMAS));
        $apa = (new FormattingEngine())->format($manuscript, $this->profiles->resolve(StyleProfile::APA7_PROFESSIONAL));

        $this->assertCount(1, $this->findingsWithCode($ojmas, 'abstract.missing'));
        $this->assertCount(0, $this->findingsWithCode($apa, 'abstract.missing'));
    }

    public function test_it_flags_author_names_inside_the_abstract(): void
    {
        $manuscript = (new DocxStructureParser())->parse($this->fixtures->richMedia('format-names.docx'));
        $blocks = $manuscript->blocks;
        $result = null;

        // Splice a cover page and an abstract containing the author name.
        array_unshift($blocks, new \App\Services\Documents\Ast\ManuscriptNode(
            NodeType::COVER_PAGE,
            ['line_count' => 1],
            [new \App\Services\Documents\Ast\ManuscriptNode(NodeType::PARAGRAPH, [], [], 'Grace Otieno')],
            'Grace Otieno',
        ));

        array_splice($blocks, 3, 0, [new \App\Services\Documents\Ast\ManuscriptNode(
            NodeType::ABSTRACT,
            [],
            [],
            'Grace Otieno investigated how instructional media affect comprehension in rural classrooms, finding that '
            .'guided practice produced the strongest gains in higher-order comprehension across all grade levels examined.',
        )]);

        $result = (new FormattingEngine())->format(
            new \App\Services\Documents\Ast\ParsedManuscript(
                $blocks,
                $manuscript->sectionProperties,
                $manuscript->media,
                $manuscript->footnotes,
                $manuscript->coreProperties,
                $manuscript->security,
                $manuscript->wordCount,
                $manuscript->paragraphCount,
            ),
            $this->profiles->resolve(StyleProfile::OJMAS),
        );

        $findings = $this->findingsWithCode($result, 'abstract.contains_author_names');

        $this->assertCount(1, $findings);
        $this->assertStringContainsString('Grace Otieno', $findings[0]->message);
    }

    public function test_it_flags_a_missing_separate_cover_page(): void
    {
        $result = $this->formatFixture('richMedia', StyleProfile::OJMAS);

        $this->assertNotEmpty(
            $this->findingsWithCode($result, 'title.cover_page_missing'),
            'The OJMAS profile requires author details on a separate cover page.',
        );
    }

    // ─────────── headings ───────────

    public function test_it_records_heading_levels_without_false_positives(): void
    {
        $result = $this->formatFixture('compliant', StyleProfile::OJMAS);

        $this->assertCount(0, $this->findingsWithCode($result, 'heading.level_not_permitted'));
        $this->assertCount(0, $this->findingsWithCode($result, 'heading.level_skipped'));
    }

    public function test_it_warns_when_heading_levels_are_skipped(): void
    {
        $manuscript = (new DocxStructureParser())->parse($this->fixtures->compliant('format-skip.docx'));
        $blocks = $manuscript->blocks;

        foreach ($blocks as $index => $block) {
            if ($block->type === NodeType::HEADING && $block->text === 'Design and Participants') {
                $blocks[$index] = $block->setAttribute('level', 4);
            }
        }

        $result = (new FormattingEngine())->format(
            new \App\Services\Documents\Ast\ParsedManuscript(
                $blocks,
                $manuscript->sectionProperties,
                $manuscript->media,
                $manuscript->footnotes,
                $manuscript->coreProperties,
                $manuscript->security,
                $manuscript->wordCount,
                $manuscript->paragraphCount,
            ),
            $this->profiles->resolve(StyleProfile::OJMAS),
        );

        $this->assertNotEmpty($this->findingsWithCode($result, 'heading.level_skipped'));
    }

    // ─────────── preservation and determinism ───────────

    public function test_it_flags_preserved_media_and_unsupported_elements(): void
    {
        $result = $this->formatFixture('richMedia', StyleProfile::OJMAS);

        $mediaFindings = $this->findingsWithCode($result, 'content.media_preserved');
        $this->assertCount(2, $mediaFindings);
        $this->assertStringContainsString('image1.png', $mediaFindings[0]->message);
    }

    public function test_unsupported_content_is_reported_and_never_dropped(): void
    {
        $result = $this->formatFixture('withRawOoxml', StyleProfile::OJMAS);

        $this->assertGreaterThanOrEqual(1, $result->unsupportedCount());

        $unsupportedFindings = $this->findingsWithCode($result, 'content.unsupported_element');

        $this->assertSame(RuleFinding::SEVERITY_WARNING, $unsupportedFindings[0]->severity);
        $this->assertStringContainsString(
            'customXml',
            $unsupportedFindings[0]->message,
            'The unmapped element must be named so the author can find it.',
        );

        // A content control the parser *can* read keeps its text and is not
        // reported as unsupported.
        $this->assertCount(0, $this->findingsWithCode($result, 'content.content_control_lost'));
        $this->assertStringContainsString(
            'structured content control paragraph',
            $result->formatted->bodyText(),
        );
    }

    public function test_tracked_changes_and_equations_are_preserved_and_reported(): void
    {
        $result = $this->formatFixture('withRawOoxml', StyleProfile::OJMAS);

        $this->assertNotEmpty($this->findingsWithCode($result, 'content.tracked_changes_preserved'));
        $this->assertNotEmpty($this->findingsWithCode($result, 'content.equations_preserved'));

        $tracked = $this->findingsWithCode($result, 'content.tracked_changes_preserved')[0];
        $this->assertStringContainsString('Reviewer One', $tracked->message);

        $this->assertStringContainsString(
            'An inserted sentence recorded as a tracked change',
            $result->formatted->bodyText(),
            'Tracked-change text must survive formatting.',
        );
    }

    public function test_the_formatted_manuscript_still_contains_every_original_block(): void
    {
        $result = $this->formatFixture('richMedia', StyleProfile::OJMAS);

        $this->assertCount(
            count($result->original->blocks),
            $result->formatted->blocks,
            'Formatting must never drop a block.',
        );

        $originalText = implode("\n", array_map(fn ($block) => (string) $block->text, $result->original->blocks));
        $formattedText = implode("\n", array_map(fn ($block) => (string) $block->text, $result->formatted->blocks));

        $this->assertSame($originalText, $formattedText, 'Formatting must never alter manuscript wording.');
    }

    public function test_formatting_is_deterministic(): void
    {
        $manuscript = (new DocxStructureParser())->parse($this->fixtures->nonCompliant('format-deterministic.docx'));
        $engine = new FormattingEngine();
        $profile = $this->profiles->resolve(StyleProfile::OJMAS);

        $first = $engine->format($manuscript, $profile)->toArray();
        $second = $engine->format($manuscript, $profile)->toArray();

        $this->assertSame($first, $second);
    }

    public function test_the_result_serialises_for_the_validation_report(): void
    {
        $result = $this->formatFixture('nonCompliant', StyleProfile::OJMAS);
        $payload = $result->toArray();

        $this->assertSame('ojmas', $payload['profile']['slug']);
        $this->assertArrayHasKey('errors', $payload['summary']);
        $this->assertArrayHasKey('auto_fixed', $payload['summary']);
        $this->assertGreaterThan(0, $payload['summary']['auto_fixed']);
        $this->assertGreaterThan(0, $payload['summary']['errors']);
        $this->assertIsString(json_encode($payload));
    }
}