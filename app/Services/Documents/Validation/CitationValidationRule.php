<?php

namespace App\Services\Documents\Validation;

use App\Services\Documents\Ast\NodeType;
use App\Services\Documents\Ast\ParsedManuscript;
use App\Services\Documents\Formatting\FormattingRule;
use App\Services\Documents\Formatting\RuleFinding;
use App\Services\Documents\Formatting\StyleProfile;

/**
 * Cross-checks in-text citations against the reference list.
 *
 * Every check here is a comparison between two things the author actually
 * wrote. The service never invents an author, a title, a year, a DOI or a page
 * range, and it never asserts that a citation *supports* a claim — it only
 * reports whether the bibliography is internally consistent.
 *
 * Optional external metadata lookup is supported but disabled by default; when
 * enabled its results are recorded as `verified_external`, never merged
 * silently into the manuscript.
 */
class CitationValidationRule implements FormattingRule
{
    private const VERSION = '1.0.0';

    public function __construct(
        private readonly CitationExtractor $extractor = new CitationExtractor(),
        private readonly ReferenceParser $referenceParser = new ReferenceParser(),
    ) {
    }

    public function id(): string
    {
        return 'apa7.citation_reference_consistency';
    }

    public function category(): string
    {
        return 'citation';
    }

    public function evaluate(ParsedManuscript $manuscript, StyleProfile $profile): array
    {
        $citations = $this->extractor->extract($this->bodyParagraphTexts($manuscript));
        $references = $this->referenceParser->parse(
            array_map(fn ($node) => (string) $node->text, $manuscript->referenceEntries()),
        );

        $findings = [];

        $findings = array_merge($findings, $this->citationsWithoutReferences($citations, $references));
        $findings = array_merge($findings, $this->uncitedReferences($citations, $references));
        $findings = array_merge($findings, $this->incompleteReferences($references));
        $findings = array_merge($findings, $this->duplicateReferences($references));
        $findings = array_merge($findings, $this->malformedIdentifiers($references));
        $findings = array_merge($findings, $this->patternsNeedingReview($citations));

        return $findings;
    }

    /**
     * Body paragraphs in document order, with the reference list excluded so a
     * reference entry is never mistaken for a citation.
     *
     * @return array<int, string>
     */
    private function bodyParagraphTexts(ParsedManuscript $manuscript): array
    {
        $texts = [];
        $index = 0;

        foreach ($manuscript->blocks as $block) {
            if (in_array($block->type, [
                NodeType::REFERENCE_LIST,
                NodeType::REFERENCE_ENTRY,
            ], true)) {
                continue;
            }

            $text = trim((string) $block->text);

            if ($text === '') {
                continue;
            }

            $texts[$index] = $text;
            $index++;
        }

        return $texts;
    }

    /**
     * @param  array<int, CitationRecord>  $citations
     * @param  array<int, ReferenceRecord>  $references
     * @return array<int, RuleFinding>
     */
    private function citationsWithoutReferences(array $citations, array $references): array
    {
        $byKey = [];

        foreach ($references as $reference) {
            $byKey[$reference->matchKey()] = true;
            $byKey[$reference->yearOnlyKey()] = true;
        }

        $findings = [];
        $reported = [];

        foreach ($citations as $citation) {
            $key = $citation->matchKey();

            if (isset($byKey[$key])) {
                continue;
            }

            if (isset($reported[$key])) {
                continue;
            }

            $reported[$key] = true;

            $findings[] = RuleFinding::warning(
                $this->category(),
                'citation.no_matching_reference',
                sprintf(
                    'The in-text citation "%s" has no matching entry in the reference list. Either add the reference entry or '
                    .'correct the in-text citation. No bibliographic details have been invented for it: a missing reference must be '
                    .'supplied from the original source, never guessed.',
                    $citation->rawText,
                ),
                $this->id(),
                self::VERSION,
                'Check the reference list for this author and year.',
                [
                    'citation' => $citation->toArray(),
                    'paragraph_index' => $citation->paragraphIndex,
                ],
            );
        }

        return $findings;
    }

    /**
     * @param  array<int, CitationRecord>  $citations
     * @param  array<int, ReferenceRecord>  $references
     * @return array<int, RuleFinding>
     */
    private function uncitedReferences(array $citations, array $references): array
    {
        $cited = [];

        foreach ($citations as $citation) {
            $cited[$citation->yearOnlyKey()] = true;
        }

        $findings = [];

        foreach ($references as $reference) {
            $key = $reference->yearOnlyKey();

            if ($key === '' || isset($cited[$key])) {
                continue;
            }

            $findings[] = RuleFinding::warning(
                $this->category(),
                'reference.never_cited',
                sprintf(
                    'Reference %d ("%s") does not appear to be cited anywhere in the manuscript body. '
                    .'APA 7 requires every reference entry to correspond to at least one in-text citation.',
                    $reference->ordinal,
                    $this->summarise($reference),
                ),
                $this->id(),
                self::VERSION,
                'Cite this work in the text, or remove it from the reference list. The entry has been left in place.',
                ['ordinal' => $reference->ordinal, 'reference' => $reference->toArray()],
            );
        }

        return $findings;
    }

    /** @return array<int, RuleFinding> */
    private function incompleteReferences(array $references): array
    {
        $findings = [];

        foreach ($references as $reference) {
            $missing = $reference->missingFields();

            if ($missing === []) {
                continue;
            }

            $findings[] = RuleFinding::warning(
                $this->category(),
                'reference.incomplete',
                sprintf(
                    'Reference %d is missing %s. APA 7 requires an author, a year, a title and a source. Nothing has been supplied '
                    .'automatically: an invented author, title or year would misrepresent your work, so these fields must be '
                    .'completed from the original source.',
                    $reference->ordinal,
                    implode(', ', $missing),
                ),
                $this->id(),
                self::VERSION,
                'Complete the missing fields from the original source.',
                ['ordinal' => $reference->ordinal, 'missing_fields' => $missing],
            );
        }

        return $findings;
    }

    /** @return array<int, RuleFinding> */
    private function duplicateReferences(array $references): array
    {
        $seen = [];
        $findings = [];

        foreach ($references as $reference) {
            $key = $reference->duplicateKey();

            if ($key === '|' || mb_strlen($key) < 8) {
                continue;
            }

            if (! isset($seen[$key])) {
                $seen[$key] = $reference->ordinal;
                continue;
            }

            $findings[] = RuleFinding::warning(
                $this->category(),
                'reference.possible_duplicate',
                sprintf(
                    'Reference %d appears to duplicate reference %d. They share the same author and title.',
                    $reference->ordinal,
                    $seen[$key],
                ),
                $this->id(),
                self::VERSION,
                'If these are the same work, remove one entry. If they are genuinely different works with similar titles, '
                .'distinguish them.',
                ['ordinal' => $reference->ordinal, 'duplicate_of_ordinal' => $seen[$key]],
            );
        }

        return $findings;
    }

    /** @return array<int, RuleFinding> */
    private function malformedIdentifiers(array $references): array
    {
        $findings = [];

        foreach ($references as $reference) {
            $doi = (string) $reference->doiRaw;

            if ($doi !== '' && preg_match('/^10\.\d{4,9}\/\S+$/u', $doi) !== 1) {
                $findings[] = RuleFinding::warning(
                    $this->category(),
                    'reference.malformed_doi',
                    sprintf(
                        'Reference %d contains "%s", which is not a valid DOI. A DOI must begin with "10." followed by a '
                        .'registrant code and a slash, and APA 7 presents it as https://doi.org/…',
                        $reference->ordinal,
                        $doi,
                    ),
                    $this->id(),
                    self::VERSION,
                    'Check the DOI against the publisher record and present it as a https://doi.org/ URL.',
                    ['ordinal' => $reference->ordinal, 'doi' => $doi],
                );
            }

            $url = (string) $reference->urlRaw;

            if ($url !== '' && preg_match('#^https?://\S+$#iu', $url) !== 1) {
                $findings[] = RuleFinding::warning(
                    $this->category(),
                    'reference.malformed_url',
                    sprintf('Reference %d contains a URL that is not well formed: "%s".', $reference->ordinal, $url),
                    $this->id(),
                    self::VERSION,
                    'Check the URL.',
                    ['ordinal' => $reference->ordinal, 'url' => $url],
                );
            }
        }

        return $findings;
    }

    /**
     * Citation patterns the machine cannot settle. These are phrased as
     * questions for the author, never as assertions about the scholarship.
     *
     * @param  array<int, CitationRecord>  $citations
     * @return array<int, RuleFinding>
     */
    private function patternsNeedingReview(array $citations): array
    {
        $findings = [];
        $reported = [];

        foreach ($citations as $citation) {
            $key = 'year-mismatch|'.$citation->yearOnlyKey();

            if (isset($reported[$key])) {
                continue;
            }

            $reported[$key] = true;

            if ($citation->stringAuthorCount >= 4) {
                $findings[] = RuleFinding::warning(
                    $this->category(),
                    'citation.author_count_needs_review',
                    sprintf(
                        'The in-text citation "%s" names %d authors. APA 7 lists up to 20 authors in the first citation.',
                        $citation->rawText,
                        $citation->stringAuthorCount,
                    ),
                    $this->id(),
                    self::VERSION,
                    'Confirm the author list is correct and that the citation follows APA 7 author-count rules.',
                    ['citation' => $citation->toArray()],
                );
            }

            if ($citation->locator !== null && preg_match('/^(?:p{1,2}\.?\s*)?[\d,\-–—\s]+$/u', $citation->locator) !== 1) {
                $findings[] = RuleFinding::warning(
                    $this->category(),
                    'citation.locator_needs_review',
                    sprintf(
                        'The in-text citation "%s" has an unrecognised locator "%s". APA 7 locators are page numbers, '
                        .'paragraph numbers or section headings.',
                        $citation->rawText,
                        $citation->locator,
                    ),
                    $this->id(),
                    self::VERSION,
                    'Check the locator.',
                    ['citation' => $citation->toArray()],
                );
            }
        }

        return $findings;
    }

    private function summarise(ReferenceRecord $reference): string
    {
        $surname = $reference->surname ?? 'Unknown author';
        $year = $reference->year !== null ? $reference->year : 'no year';

        return trim($surname.' ('.$year.')');
    }
}