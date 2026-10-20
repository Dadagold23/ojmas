<?php

namespace App\Services\Documents\Formatting;

use App\Services\Documents\Ast\ManuscriptNode;
use App\Services\Documents\Ast\ParsedManuscript;
use App\Services\Documents\Formatting\Rules\ContentPreservationRule;
use App\Services\Documents\Formatting\Rules\HeadingStructureRule;
use App\Services\Documents\Formatting\Rules\PageGeometryRule;
use App\Services\Documents\Formatting\Rules\ParagraphIndentationRule;
use App\Services\Documents\Formatting\Rules\ReferencePresentationRule;
use App\Services\Documents\Formatting\Rules\TitleAndAbstractRule;
use App\Services\Documents\Formatting\Rules\TypographyRule;

/**
 * Runs the versioned rule set over a parsed manuscript.
 *
 * The engine is deliberately split into two phases:
 *
 *  1. **Evaluate** every rule against the *original* tree. Rules therefore see
 *     the author's actual document, not a partially rewritten one.
 *  2. **Apply** the deterministic fixes those rules proposed, producing the
 *     formatted tree.
 *
 * That ordering matters: a rule must never be able to "fix" a problem that a
 * later rule would then report as fixed, and a corrected manuscript must never
 * change what an independent rule measures.
 */
class FormattingEngine
{
    /** @var array<int, FormattingRule> */
    private array $rules;

    /**
     * @param  array<int, FormattingRule>|null  $rules
     */
    public function __construct(?array $rules = null)
    {
        $this->rules = $rules ?? $this->defaultRules();
    }

    /** @return array<int, FormattingRule> */
    public function defaultRules(): array
    {
        return [
            new PageGeometryRule(),
            new TypographyRule(),
            new ParagraphIndentationRule(),
            new HeadingStructureRule(),
            new ReferencePresentationRule(),
            new TitleAndAbstractRule(),
            new ContentPreservationRule(),
        ];
    }

    public function format(ParsedManuscript $manuscript, StyleProfile $profile): FormattingResult
    {
        $findings = [];

        foreach ($this->rules as $rule) {
            foreach ($rule->evaluate($manuscript, $profile) as $finding) {
                $findings[] = $finding;
            }
        }

        $formatted = $this->applyFixes($manuscript, $findings, $profile);

        usort($findings, fn (RuleFinding $a, RuleFinding $b) => $this->severityRank($a) <=> $this->severityRank($b));

        return new FormattingResult(
            original: $manuscript,
            formatted: $formatted,
            findings: $findings,
            profile: $profile,
        );
    }

    /**
     * Applies only the fixes the rules marked as deterministic corrections.
     * Content findings are never applied.
     */
    private function applyFixes(ParsedManuscript $manuscript, array $findings, StyleProfile $profile): ParsedManuscript
    {
        $blocks = $manuscript->blocks;
        $section = $manuscript->sectionProperties;

        foreach ($findings as $finding) {
            if (! $finding->wasAutoFixed || $finding->fix === null) {
                continue;
            }

            $operation = $finding->fix['operation'];
            $payload = $finding->fix['payload'];

            if ($operation === 'set_page_geometry') {
                $margins = (array) ($payload['margins_twips'] ?? []);

                $section = [
                    'width_twips' => (int) $payload['width_twips'],
                    'height_twips' => (int) $payload['height_twips'],
                    'orientation' => 'portrait',
                    'margin_top_twips' => (int) ($margins['top'] ?? 1440),
                    'margin_right_twips' => (int) ($margins['right'] ?? 1440),
                    'margin_bottom_twips' => (int) ($margins['bottom'] ?? 1440),
                    'margin_left_twips' => (int) ($margins['left'] ?? 1440),
                    'header_distance_twips' => (int) ($section['header_distance_twips'] ?? 720),
                    'footer_distance_twips' => (int) ($section['footer_distance_twips'] ?? 720),
                ];

                continue;
            }

            if ($operation === 'set_block_property') {
                $index = (int) ($payload['block_index'] ?? -1);

                if (! array_key_exists($index, $blocks)) {
                    continue;
                }

                $properties = (array) ($payload['properties'] ?? []);

                // The abstract, keywords and reference entries are never
                // first-line indented; corrections there are alignment only.
                $blocks[$index] = $blocks[$index]->withAttributes($properties);

                continue;
            }
        }

        return new ParsedManuscript(
            blocks: $blocks,
            sectionProperties: $section,
            media: $manuscript->media,
            footnotes: $manuscript->footnotes,
            coreProperties: $manuscript->coreProperties,
            security: $manuscript->security,
            wordCount: $manuscript->wordCount,
            paragraphCount: $manuscript->paragraphCount,
        );
    }

    private function severityRank(RuleFinding $finding): int
    {
        return match ($finding->severity) {
            RuleFinding::SEVERITY_ERROR => 0,
            RuleFinding::SEVERITY_WARNING => 1,
            default => 2,
        };
    }
}