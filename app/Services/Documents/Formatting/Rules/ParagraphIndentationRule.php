<?php

namespace App\Services\Documents\Formatting\Rules;

use App\Services\Documents\Ast\ManuscriptNode;
use App\Services\Documents\Ast\NodeType;
use App\Services\Documents\Ast\ParsedManuscript;
use App\Services\Documents\Formatting\FormattingRule;
use App\Services\Documents\Formatting\RuleFinding;
use App\Services\Documents\Formatting\StyleProfile;

/**
 * APA 7 §2.11-2.24 paragraph mechanics.
 *
 * Body paragraphs take a 0.5 inch first-line indent and are flush left with a
 * ragged right margin. The abstract, the keyword line, headings, reference
 * entries, block quotations and list items follow different conventions and are
 * explicitly excluded, because indenting them is a real formatting error rather
 * than a missing one.
 *
 * Indentation is a purely visual convention with an unambiguous correct value,
 * so it is auto-corrected rather than raised as an error.
 */
class ParagraphIndentationRule implements FormattingRule
{
    private const VERSION = '1.0.0';

    /** Node types that must never carry a first-line indent. */
    private const NEVER_INDENTED_TYPES = [
        NodeType::TITLE,
        NodeType::COVER_PAGE,
        NodeType::HEADING,
        NodeType::APPENDIX_HEADING,
        NodeType::ABSTRACT,
        NodeType::KEYWORDS,
        NodeType::REFERENCE_LIST,
        NodeType::REFERENCE_ENTRY,
        NodeType::BLOCKQUOTE,
        NodeType::TABLE,
        NodeType::FIGURE,
        NodeType::PAGE_BREAK,
        NodeType::UNSUPPORTED,
    ];

    public function id(): string
    {
        return 'apa7.paragraph_indentation';
    }

    public function category(): string
    {
        return 'layout';
    }

    public function evaluate(ParsedManuscript $manuscript, StyleProfile $profile): array
    {
        $expected = (int) $profile->requirement('body_first_line_indent_twips', 720);
        $expectedAlignment = (string) $profile->requirement('body_alignment', 'left');
        $findings = [];

        foreach ($manuscript->blocks as $index => $block) {
            if ($block->type !== NodeType::PARAGRAPH || $block->attribute('is_blank') === true) {
                continue;
            }

            $location = [
                'block_index' => $index,
                'paragraph_index' => $block->attribute('paragraph_index'),
            ];

            // List items carry their own hanging indentation.
            if ($block->attribute('is_list_item') === true) {
                continue;
            }

            if ($this->isFrontMatterLine($block)) {
                $findings = array_merge(
                    $findings,
                    $this->normaliseIndent($index, $block, $expected, $location, 'unindented'),
                );

                continue;
            }

            $findings = array_merge(
                $findings,
                $this->normaliseIndent($index, $block, $expected, $location, 'body'),
            );

            $alignment = $block->attribute('alignment');

            if ($alignment !== null && $alignment !== $expectedAlignment) {
                $findings[] = RuleFinding::autoFixed(
                    $this->category(),
                    'layout.alignment_corrected',
                    sprintf(
                        'Paragraph alignment was "%s". It was set to flush left with a ragged right margin as APA 7 requires.',
                        (string) $alignment,
                    ),
                    $this->id(),
                    self::VERSION,
                    ['operation' => 'set_block_property', 'payload' => [
                        'block_index' => $index,
                        'properties' => ['alignment' => $expectedAlignment],
                    ]],
                    $location,
                );
            }
        }

        return $findings;
    }

    /**
     * Detects a front-matter paragraph that must stay flush left: the abstract
     * body, or a keyword line.
     */
    private function isFrontMatterLine(ManuscriptNode $block): bool
    {
        $role = (string) $block->attribute('role', '');

        if (in_array($role, ['abstract', 'keywords'], true)) {
            return true;
        }

        return preg_match('/^\s*(keywords|key\s+words)\s*:/iu', (string) $block->text) === 1;
    }

    /**
     * @param  array<string, mixed>  $location
     * @return array<int, RuleFinding>
     */
    private function normaliseIndent(int $index, ManuscriptNode $block, int $expected, array $location, string $mode): array
    {
        $current = $block->attribute('first_line_indent');
        $currentIndent = $current === null ? null : (int) $current;

        if ($mode === 'unindented') {
            if ($currentIndent === null || $currentIndent === 0) {
                return [];
            }

            return [RuleFinding::autoFixed(
                $this->category(),
                'layout.front_matter_indent_removed',
                sprintf(
                    'This line carried a %.2f inch first-line indent. The abstract and keyword line are presented flush left, '
                    .'so the indent was removed.',
                    $currentIndent / 1440,
                ),
                $this->id(),
                self::VERSION,
                ['operation' => 'set_block_property', 'payload' => [
                    'block_index' => $index,
                    'properties' => ['first_line_indent' => 0],
                ]],
                $location,
            )];
        }

        if ($currentIndent === $expected) {
            return [];
        }

        if ($currentIndent === null) {
            return [RuleFinding::autoFixed(
                $this->category(),
                'layout.first_line_indent_applied',
                sprintf('No first-line indent was set. A %.2f inch first-line indent was applied.', $expected / 1440),
                $this->id(),
                self::VERSION,
                ['operation' => 'set_block_property', 'payload' => [
                    'block_index' => $index,
                    'properties' => ['first_line_indent' => $expected],
                ]],
                $location,
            )];
        }

        return [RuleFinding::autoFixed(
            $this->category(),
            'layout.first_line_indent_corrected',
            sprintf(
                'The first-line indent was %.2f inches. It was set to %.2f inches.',
                $currentIndent / 1440,
                $expected / 1440,
            ),
            $this->id(),
            self::VERSION,
            ['operation' => 'set_block_property', 'payload' => [
                'block_index' => $index,
                'properties' => ['first_line_indent' => $expected],
            ]],
            $location,
        )];
    }
}