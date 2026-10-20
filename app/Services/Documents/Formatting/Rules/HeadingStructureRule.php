<?php

namespace App\Services\Documents\Formatting\Rules;

use App\Services\Documents\Ast\NodeType;
use App\Services\Documents\Ast\ParsedManuscript;
use App\Services\Documents\Formatting\FormattingRule;
use App\Services\Documents\Formatting\RuleFinding;
use App\Services\Documents\Formatting\StyleProfile;

/**
 * APA 7 §2.27 heading levels.
 *
 * APA 7 defines exactly five levels and requires them to be used without
 * skipping. The engine reports both problems but changes neither: choosing a
 * heading level changes how a section reads, which is an editorial decision.
 */
class HeadingStructureRule implements FormattingRule
{
    private const VERSION = '1.0.0';

    public function id(): string
    {
        return 'apa7.heading_structure';
    }

    public function category(): string
    {
        return 'structure';
    }

    public function evaluate(ParsedManuscript $manuscript, StyleProfile $profile): array
    {
        $allowed = array_map('intval', (array) $profile->requirement('heading_levels', [1, 2, 3, 4, 5]));
        $enforceSequence = (bool) $profile->requirement('enforce_heading_level_sequence', true);

        $findings = [];
        $previousLevel = null;

        foreach ($manuscript->headings() as $heading) {
            $level = (int) $heading->attribute('level', 0);
            $location = [
                'paragraph_index' => $heading->attribute('paragraph_index'),
                'heading_text' => $heading->text,
            ];

            if (! in_array($level, $allowed, true)) {
                $findings[] = RuleFinding::error(
                    $this->category(),
                    'heading.level_not_permitted',
                    sprintf(
                        'The heading "%s" uses level %d. APA 7 permits levels %s only.',
                        (string) $heading->text,
                        $level,
                        implode(', ', $allowed),
                    ),
                    $this->id(),
                    self::VERSION,
                    'Restyle this heading to one of the five APA heading levels.',
                    $location,
                );

                continue;
            }

            if ($enforceSequence && $previousLevel !== null && $level > $previousLevel + 1) {
                $findings[] = RuleFinding::warning(
                    $this->category(),
                    'heading.level_skipped',
                    sprintf(
                        'The heading "%s" is level %d but follows a level %d heading. APA 7 headings should not skip a level.',
                        (string) $heading->text,
                        $level,
                        $previousLevel,
                    ),
                    $this->id(),
                    self::VERSION,
                    'Either promote the intervening heading or demote this one so the outline stays continuous.',
                    $location,
                );
            }

            $previousLevel = $level;
        }

        if ($manuscript->headings() === []) {
            $findings[] = RuleFinding::warning(
                $this->category(),
                'heading.none_found',
                'No styled headings were found in the document. Headings must use the Word Heading 1-5 styles for the outline to be '
                .'readable to editors and screen readers.',
                $this->id(),
                self::VERSION,
                'Apply Word heading styles to your section headings before resubmitting.',
                [],
            );
        }

        return $findings;
    }
}