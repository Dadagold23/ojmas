<?php

namespace App\Services\Documents\Formatting\Rules;

use App\Services\Documents\Ast\NodeType;
use App\Services\Documents\Ast\ParsedManuscript;
use App\Services\Documents\Formatting\FormattingRule;
use App\Services\Documents\Formatting\RuleFinding;
use App\Services\Documents\Formatting\StyleProfile;

/**
 * APA 7 §2.22 page geometry, with the OJMAS A4 override applied automatically
 * through the profile.
 *
 * This is the single highest-value auto-correction: page size and margins
 * cannot be "argued about", and getting them wrong makes every later page
 * calculation in the manuscript unreliable.
 */
class PageGeometryRule implements FormattingRule
{
    private const VERSION = '1.0.0';

    public function id(): string
    {
        return 'apa7.page_geometry';
    }

    public function category(): string
    {
        return 'layout';
    }

    public function evaluate(ParsedManuscript $manuscript, StyleProfile $profile): array
    {
        $paper = (array) $profile->requirement('paper', []);
        $margins = (array) $profile->requirement('margins_twips', []);

        $expectedWidth = (int) ($paper['width_twips'] ?? 12240);
        $expectedHeight = (int) ($paper['height_twips'] ?? 15840);
        $label = (string) ($paper['label'] ?? 'the required paper size');

        $actual = $manuscript->sectionProperties;
        $findings = [];

        if ($actual === []) {
            return [RuleFinding::autoFixed(
                $this->category(),
                'page_geometry.missing',
                "No page geometry was declared in the source document. {$label} with 1 inch margins was applied.",
                $this->id(),
                self::VERSION,
                ['operation' => 'set_page_geometry', 'payload' => [
                    'width_twips' => $expectedWidth,
                    'height_twips' => $expectedHeight,
                    'margins_twips' => $margins,
                ]],
            )];
        }

        $actualWidth = (int) ($actual['width_twips'] ?? 0);
        $actualHeight = (int) ($actual['height_twips'] ?? 0);

        $paperMatches = $actualWidth === $expectedWidth && $actualHeight === $expectedHeight;

        if (! $paperMatches) {
            $findings[] = RuleFinding::autoFixed(
                $this->category(),
                'page_geometry.paper_size_corrected',
                sprintf(
                    'Paper size was %s (%d x %d twips). It was changed to %s (%d x %d twips) as required by the submission profile.',
                    $this->describePaper($actualWidth, $actualHeight),
                    $actualWidth,
                    $actualHeight,
                    $label,
                    $expectedWidth,
                    $expectedHeight,
                ),
                $this->id(),
                self::VERSION,
                ['operation' => 'set_page_geometry', 'payload' => [
                    'width_twips' => $expectedWidth,
                    'height_twips' => $expectedHeight,
                    'margins_twips' => $margins,
                ]],
                ['paper_width_twips' => $actualWidth, 'paper_height_twips' => $actualHeight],
            );
        }

        foreach (['top' => 'margin_top_twips', 'right' => 'margin_right_twips', 'bottom' => 'margin_bottom_twips', 'left' => 'margin_left_twips'] as $side => $key) {
            $expected = (int) ($margins[$side] ?? 1440);
            $found = $actual[$key] ?? null;

            if ($found === null || (int) $found === $expected) {
                continue;
            }

            $findings[] = RuleFinding::autoFixed(
                $this->category(),
                'page_geometry.margin_corrected',
                sprintf(
                    'The %s margin was %.2f inches. It was set to %.2f inches as required by the submission profile.',
                    $side,
                    ((int) $found) / 1440,
                    $expected / 1440,
                ),
                $this->id(),
                self::VERSION,
                ['operation' => 'set_page_geometry', 'payload' => [
                    'width_twips' => $expectedWidth,
                    'height_twips' => $expectedHeight,
                    'margins_twips' => $margins,
                ]],
                ['side' => $side, 'actual_twips' => (int) $found, 'expected_twips' => $expected],
            );
        }

        return $findings;
    }

    private function describePaper(int $width, int $height): string
    {
        return match (true) {
            $width === 11906 && $height === 16838 => 'A4',
            $width === 12240 && $height === 15840 => 'US Letter',
            $width === 15840 && $height === 12240 => 'US Letter (landscape)',
            default => 'non-standard',
        };
    }
}