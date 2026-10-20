<?php

namespace App\Services\Documents\Formatting\Rules;

use App\Services\Documents\Ast\NodeType;
use App\Services\Documents\Ast\ParsedManuscript;
use App\Services\Documents\Formatting\FormattingRule;
use App\Services\Documents\Formatting\RuleFinding;
use App\Services\Documents\Formatting\StyleProfile;

/**
 * APA 7 §9.44-9.47 reference list presentation: a 0.5 inch hanging indent,
 * alphabetical by author surname, and no manual numbering.
 *
 * The engine corrects indentation, which is mechanical, but only *reports*
 * alphabetical order and manual numbering. Reordering a reference list changes
 * citation numbering in any style that uses it and is the author's call.
 */
class ReferencePresentationRule implements FormattingRule
{
    private const VERSION = '1.0.0';

    public function id(): string
    {
        return 'apa7.reference_presentation';
    }

    public function category(): string
    {
        return 'structure';
    }

    public function evaluate(ParsedManuscript $manuscript, StyleProfile $profile): array
    {
        $entries = $manuscript->referenceEntries();

        if ($entries === []) {
            return [];
        }

        $expectedHanging = (int) $profile->requirement('reference_hanging_indent_twips', 720);
        $findings = [];
        $previousSurname = null;
        $entryIndexes = [];

        foreach ($manuscript->blocks as $index => $block) {
            if ($block->type === NodeType::REFERENCE_ENTRY) {
                $entryIndexes[] = $index;
            }
        }

        foreach ($entryIndexes as $index) {
            $entry = $manuscript->blocks[$index];
            $location = ['block_index' => $index, 'ordinal' => $entry->attribute('ordinal')];

            $hanging = $entry->attribute('hanging_indent');
            $left = $entry->attribute('left_indent');

            if ($hanging === null || (int) $hanging !== $expectedHanging) {
                $findings[] = RuleFinding::autoFixed(
                    $this->category(),
                    'reference.hanging_indent_corrected',
                    sprintf(
                        'The hanging indent on this reference was %s. It was set to %.2f inches as APA 7 requires.',
                        $hanging === null ? 'not set' : sprintf('%.2f inches', ((int) $hanging) / 1440),
                        $expectedHanging / 1440,
                    ),
                    $this->id(),
                    self::VERSION,
                    ['operation' => 'set_block_property', 'payload' => [
                        'block_index' => $index,
                        'properties' => [
                            'hanging_indent' => $expectedHanging,
                            'left_indent' => $expectedHanging,
                        ],
                    ]],
                    $location,
                );
            } elseif ($left !== $expectedHanging) {
                $findings[] = RuleFinding::autoFixed(
                    $this->category(),
                    'reference.left_indent_corrected',
                    sprintf('The left indent on this reference was %s twips. It was aligned to the %.2f inch hanging indent.',
                        (string) $left, $expectedHanging / 1440),
                    $this->id(),
                    self::VERSION,
                    ['operation' => 'set_block_property', 'payload' => [
                        'block_index' => $index,
                        'properties' => ['left_indent' => $expectedHanging],
                    ]],
                    $location,
                );
            }

            $text = trim((string) $entry->text);

            if (preg_match('/^\s*(\d+)[.)]\s+/u', $text, $matches) === 1) {
                $findings[] = RuleFinding::warning(
                    $this->category(),
                    'reference.manually_numbered',
                    sprintf(
                        'Reference %d appears to be manually numbered ("%s"). APA 7 reference lists are ordered alphabetically and '
                        .'are not numbered. It has been left unchanged because renumbering affects any numbered citation in the text.',
                        (int) $entry->attribute('ordinal', 0),
                        trim($matches[0]),
                    ),
                    $this->id(),
                    self::VERSION,
                    'Remove the manual numbering and order the reference list alphabetically by surname.',
                    $location,
                );
            }

            $surname = $this->leadingSurname($text);

            if ($surname === null) {
                $findings[] = RuleFinding::warning(
                    $this->category(),
                    'reference.author_unparseable',
                    sprintf(
                        'The author of reference %d could not be read from the entry text. Alphabetical order could not be verified '
                        .'for this entry.',
                        (int) $entry->attribute('ordinal', 0),
                    ),
                    $this->id(),
                    self::VERSION,
                    'Check that the entry begins with the author surname in surname, initials. (year) form.',
                    $location,
                );
            } elseif ($previousSurname !== null && strcasecmp($surname, $previousSurname) < 0) {
                $findings[] = RuleFinding::warning(
                    $this->category(),
                    'reference.out_of_alphabetical_order',
                    sprintf(
                        'Reference %d begins with "%s", which sorts before the preceding entry ("%s"). APA 7 reference lists are '
                        .'ordered alphabetically by surname.',
                        (int) $entry->attribute('ordinal', 0),
                        $surname,
                        $previousSurname,
                    ),
                    $this->id(),
                    self::VERSION,
                    'Reorder the reference list alphabetically. This was not done automatically because reordering also affects '
                    .'any numbered citation in the manuscript body.',
                    $location,
                );
            }

            if ($previousSurname !== null && $surname !== null) {
                $previousSurname = strcasecmp($surname, $previousSurname) === 0 ? $previousSurname : $surname;
            } elseif ($surname !== null) {
                $previousSurname = $surname;
            }
        }

        return $findings;
    }

    /** Returns the leading surname, or null when the entry does not start with one. */
    private function leadingSurname(string $text): ?string
    {
        if (preg_match('/^\s*([A-Z][A-Za-z\'’\-]+)\s*,/u', $text, $matches) === 1) {
            return trim($matches[1]);
        }

        if (preg_match('/^\s*([A-Z][A-Za-z\'’\-]+),\s*[A-Z]\./u', $text, $matches) === 1) {
            return trim($matches[1]);
        }

        return null;
    }
}