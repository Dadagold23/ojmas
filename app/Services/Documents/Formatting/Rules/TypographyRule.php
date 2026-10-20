<?php

namespace App\Services\Documents\Formatting\Rules;

use App\Services\Documents\Ast\NodeType;
use App\Services\Documents\Ast\ParsedManuscript;
use App\Services\Documents\Formatting\FormattingRule;
use App\Services\Documents\Formatting\RuleFinding;
use App\Services\Documents\Formatting\StyleProfile;

/**
 * APA 7 §6.22 typography: approved font family, approved size, and double
 * spacing applied to body paragraphs.
 *
 * APA 7 permits several approved faces; OJMAS keeps the Times New Roman list
 * from the APA baseline. Fonts outside the approved list are reported as an
 * error requiring author action rather than being silently substituted,
 * because replacing a typeface changes the visual identity of the manuscript
 * and the author should confirm it.
 */
class TypographyRule implements FormattingRule
{
    private const VERSION = '1.0.0';

    public function id(): string
    {
        return 'apa7.typography';
    }

    public function category(): string
    {
        return 'typography';
    }

    public function evaluate(ParsedManuscript $manuscript, StyleProfile $profile): array
    {
        $findings = [];
        $expectedSpacing = (int) $profile->requirement('line_spacing_twips', 480);
        $expectedRule = (string) $profile->requirement('line_spacing_rule', 'auto');

        foreach ($manuscript->blocks as $index => $block) {
            if (! in_array($block->type, [
                NodeType::PARAGRAPH,
                NodeType::ABSTRACT,
                NodeType::KEYWORDS,
                NodeType::REFERENCE_ENTRY,
            ], true)) {
                continue;
            }

            $location = ['block_index' => $index, 'paragraph_index' => $block->attribute('paragraph_index')];

            $font = $block->attribute('font');

            if ($font === null) {
                $findings[] = RuleFinding::info(
                    $this->category(),
                    'typography.font_inherited',
                    'Font could not be read from the paragraph; the document default was assumed to apply and was left unchanged.',
                    $this->id(),
                    self::VERSION,
                    $location,
                );
            } elseif (! $profile->isFontApproved((string) $font)) {
                $findings[] = RuleFinding::error(
                    $this->category(),
                    'typography.font_not_approved',
                    sprintf(
                        'The typeface "%s" is not one of the fonts approved by the submission profile (%s). '
                        .'Please restyle the manuscript before resubmitting.',
                        (string) $font,
                        implode(', ', $profile->approvedFonts()),
                    ),
                    $this->id(),
                    self::VERSION,
                    'Change the manuscript typeface to an approved font. Substituting it automatically would alter how your work looks.',
                    $location,
                );
            }

            $size = $block->attribute('font_size_half_points');

            if ($size !== null && ! $profile->isFontSizeApproved((int) $size)) {
                $findings[] = RuleFinding::error(
                    $this->category(),
                    'typography.font_size_not_approved',
                    sprintf(
                        'The font size in this paragraph is %.0f pt. The submission profile requires %.0f pt.',
                        ((int) $size) / 2,
                        $this->describeApprovedSize($profile),
                    ),
                    $this->id(),
                    self::VERSION,
                    'Set the paragraph font size to the size required by the submission profile.',
                    $location,
                );
            }

            $lineSpacing = $block->attribute('line_spacing');

            if ($lineSpacing === null) {
                $findings[] = RuleFinding::info(
                    $this->category(),
                    'typography.line_spacing_inherited',
                    'Line spacing was not set on this paragraph; the document default was assumed to apply.',
                    $this->id(),
                    self::VERSION,
                    $location,
                );

                continue;
            }

            if ((int) $lineSpacing === $expectedSpacing) {
                continue;
            }

            $findings[] = RuleFinding::autoFixed(
                $this->category(),
                'typography.line_spacing_corrected',
                sprintf(
                    'Line spacing was %.2f lines. It was set to double spacing as required by the submission profile.',
                    $this->linesFromTwips((int) $lineSpacing),
                ),
                $this->id(),
                self::VERSION,
                ['operation' => 'set_block_property', 'payload' => [
                    'block_index' => $index,
                    'properties' => [
                        'line_spacing' => $expectedSpacing,
                        'line_spacing_rule' => $expectedRule,
                    ],
                ]],
                $location,
            );
        }

        return $findings;
    }

    private function describeApprovedSize(StyleProfile $profile): float
    {
        $sizes = array_map('intval', (array) $profile->requirement('approved_font_sizes_half_points', [24]));

        return ($sizes === [] ? 24 : $sizes[0]) / 2;
    }

    private function linesFromTwips(int $twips): float
    {
        return round($twips / 240, 2);
    }
}