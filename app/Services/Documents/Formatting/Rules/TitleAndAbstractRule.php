<?php

namespace App\Services\Documents\Formatting\Rules;

use App\Services\Documents\Ast\NodeType;
use App\Services\Documents\Ast\ParsedManuscript;
use App\Services\Documents\Formatting\FormattingRule;
use App\Services\Documents\Formatting\RuleFinding;
use App\Services\Documents\Formatting\StyleProfile;

/**
 * The OJMAS title-page and abstract rules, layered on top of whatever the
 * profile requires.
 *
 * These are journal policy rules, not APA rules, and they are the reason the
 * engine carries a separate OJMAS profile at all. Every threshold is read from
 * the profile so the journal can change it without a code change.
 */
class TitleAndAbstractRule implements FormattingRule
{
    private const VERSION = '1.0.0';

    public function id(): string
    {
        return 'ojmas.title_and_abstract';
    }

    public function category(): string
    {
        return 'structure';
    }

    public function evaluate(ParsedManuscript $manuscript, StyleProfile $profile): array
    {
        $findings = [];

        $findings = array_merge($findings, $this->evaluateTitle($manuscript, $profile));
        $findings = array_merge($findings, $this->evaluateAbstract($manuscript, $profile));

        return $findings;
    }

    private function evaluateTitle(ParsedManuscript $manuscript, StyleProfile $profile): array
    {
        $title = $manuscript->title();
        $findings = [];

        if ($title === null || trim($title) === '') {
            return [RuleFinding::error(
                $this->category(),
                'title.missing',
                'No manuscript title could be identified. Add a title in the Title style or as the first centred line of the document.',
                $this->id(),
                self::VERSION,
                'Apply the Word "Title" style to the manuscript title so it can be identified automatically.',
                ['block_index' => 0],
            )];
        }

        $wordCount = $this->countWords($title);
        $maxWords = (int) $profile->requirement('title_max_words', 120);

        if ($wordCount > $maxWords) {
            $findings[] = RuleFinding::error(
                $this->category(),
                'title.exceeds_word_limit',
                sprintf(
                    'The title is %d words. The submission profile allows at most %d words. '
                    .'The title has not been shortened, because shortening research titles involves judgement about meaning.',
                    $wordCount,
                    $maxWords,
                ),
                $this->id(),
                self::VERSION,
                'Shorten the title to the profile limit while preserving the research question it names.',
                ['word_count' => $wordCount, 'max_words' => $maxWords],
            );
        }

        if ((bool) $profile->requirement('author_details_on_separate_cover_page', false)) {
            $hasCoverPage = $manuscript->blocksOfType(NodeType::COVER_PAGE) !== [];

            if (! $hasCoverPage) {
                $findings[] = RuleFinding::error(
                    $this->category(),
                    'title.cover_page_missing',
                    'Author names and affiliations must appear on a separate cover page. No author block was found before the first '
                    .'manuscript heading.',
                    $this->id(),
                    self::VERSION,
                    'Place the author names, affiliations and corresponding-author details on a page before the abstract, '
                    .'and begin the manuscript body with a page break.',
                    [],
                );
            }
        }

        return $findings;
    }

    private function evaluateAbstract(ParsedManuscript $manuscript, StyleProfile $profile): array
    {
        $findings = [];

        if ((bool) $profile->requirement('require_abstract', false)) {
            $abstract = $manuscript->abstract();

            if ($abstract === null) {
                return [RuleFinding::error(
                    $this->category(),
                    'abstract.missing',
                    'No abstract could be identified. The submission profile requires an abstract.',
                    $this->id(),
                    self::VERSION,
                    'Add an abstract under a heading reading "Abstract".',
                    [],
                )];
            }
        } else {
            $abstract = $manuscript->abstract();

            if ($abstract === null) {
                return $findings;
            }
        }

        $wordCount = $this->countWords((string) $abstract->text);
        $maxWords = (int) $profile->requirement('abstract_max_words', 250);
        $minWords = (int) $profile->requirement('abstract_min_words', 0);

        if ($wordCount > $maxWords) {
            $findings[] = RuleFinding::error(
                $this->category(),
                'abstract.exceeds_word_limit',
                sprintf(
                    'The abstract is %d words. The submission profile allows at most %d words. '
                    .'It has not been shortened automatically, because cutting an abstract can change what the paper claims.',
                    $wordCount,
                    $maxWords,
                ),
                $this->id(),
                self::VERSION,
                'Reduce the abstract to the profile limit yourself, preserving the research question, method and main finding.',
                ['word_count' => $wordCount, 'max_words' => $maxWords],
            );
        } elseif ($wordCount < $minWords) {
            $findings[] = RuleFinding::error(
                $this->category(),
                'abstract.below_word_limit',
                sprintf('The abstract is %d words. The submission profile requires at least %d words.', $wordCount, $minWords),
                $this->id(),
                self::VERSION,
                'Expand the abstract to include the research question, method, sample and principal finding.',
                ['word_count' => $wordCount, 'min_words' => $minWords],
            );
        }

        if ((bool) $profile->requirement('abstract_must_exclude_author_names', false)) {
            $abstractText = (string) $abstract->text;
            $namesInAbstract = $this->looksLikeAuthorList($manuscript, $abstractText);

            if ($namesInAbstract !== null) {
                $findings[] = RuleFinding::error(
                    $this->category(),
                    'abstract.contains_author_names',
                    sprintf(
                        'The abstract appears to contain author name(s) ("%s"). The submission profile requires the abstract to be '
                        .'presented without author names, for blinded review.',
                        $namesInAbstract,
                    ),
                    $this->id(),
                    self::VERSION,
                    'Remove author names from the abstract. Author details belong on the cover page only.',
                    [],
                );
            }
        }

        if ((bool) $profile->requirement('abstract_requires_separate_page', false)) {
            $abstractIndex = $this->indexOf($manuscript, $abstract);

            if ($abstractIndex !== null) {
                $precededByPageBreak = false;

                for ($i = $abstractIndex - 1; $i >= 0; $i--) {
                    $previous = $manuscript->blocks[$i];

                    if ($previous->type === NodeType::PAGE_BREAK) {
                        $precededByPageBreak = true;
                        break;
                    }

                    if (in_array($previous->type, [NodeType::TITLE, NodeType::COVER_PAGE], true)) {
                        break;
                    }
                }

                if (! $precededByPageBreak) {
                    $findings[] = RuleFinding::error(
                        $this->category(),
                        'abstract.not_on_separate_sheet',
                        'The submission profile requires the abstract to appear on a separate sheet. No page break was found '
                        .'separating it from the title page.',
                        $this->id(),
                        self::VERSION,
                        'Insert a page break after the cover page so the abstract begins on its own sheet.',
                        ['block_index' => $abstractIndex],
                    );
                }
            }
        }

        return $findings;
    }

    /**
     * Detects an author name inside the abstract by comparing against the
     * cover-page lines, which is a far safer test than a name-pattern guess.
     */
    private function looksLikeAuthorList(ParsedManuscript $manuscript, string $abstractText): ?string
    {
        $haystack = mb_strtolower($abstractText);

        foreach ($manuscript->blocksOfType(NodeType::COVER_PAGE) as $coverPage) {
            foreach ($coverPage->children as $line) {
                $candidate = trim((string) $line->text);

                if ($candidate === '' || mb_strlen($candidate) > 60) {
                    continue;
                }

                foreach (preg_split('/\s*,\s*/u', $candidate) ?: [] as $part) {
                    $token = trim($part);

                    if (mb_strlen($token) < 4 || ! ctype_alpha(str_replace([' ', "'", '-'], '', $token))) {
                        continue;
                    }

                    if (str_contains($haystack, mb_strtolower($token))) {
                        return $token;
                    }
                }
            }
        }

        return null;
    }

    private function indexOf(ParsedManuscript $manuscript, object $needle): ?int
    {
        foreach ($manuscript->blocks as $index => $block) {
            if ($block === $needle) {
                return $index;
            }
        }

        return null;
    }

    private function countWords(string $text): int
    {
        $text = preg_replace('/[\x{FFFC}\s]+/u', ' ', trim($text)) ?? '';

        return count(array_filter(explode(' ', $text), fn (string $word) => $word !== ''));
    }
}