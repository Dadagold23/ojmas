<?php

namespace App\Services\Documents\Validation;

use App\Services\Documents\Ast\NodeType;
use App\Services\Documents\Ast\ParsedManuscript;

/**
 * Parses APA 7 reference entries into the fields the validator compares.
 *
 * This is a structural reader, not a bibliographic resolver: it records what
 * the author actually wrote. Anything it cannot read stays null and is reported
 * as incomplete.
 */
class ReferenceParser
{
    /** APA 7 DOI syntax: 10.NNNN/suffix, optionally behind https://doi.org/. */
    private const DOI_PATTERN = '/\b(10\.\d{4,9}\/[^\s"<>]+)/u';

    /** APA 7 presents DOIs as https://doi.org/10.… */
    private const DOI_URL_PATTERN = '#https?://(?:dx\.)?doi\.org/(10\.\d{4,9}/\S+)#iu';

    private const URL_PATTERN = '#\bhttps?://[^\s<>"\']+#iu';

    /**
     * @param  array<int, string>  $referenceTexts
     * @return array<int, ReferenceRecord>
     */
    public function parse(array $referenceTexts): array
    {
        $records = [];

        foreach (array_values($referenceTexts) as $index => $text) {
            $records[] = $this->parseOne((string) $text, $index + 1);
        }

        return $records;
    }

    public function parseOne(string $text, int $ordinal): ReferenceRecord
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        $authorRaw = null;
        $surname = null;
        $year = null;
        $titleRaw = null;
        $sourceRaw = null;
        $pagesRaw = null;
        $doiRaw = null;
        $urlRaw = null;

        // Author block: everything before the parenthesised year in APA form,
        // "Smith, J. (2020)." or the looser "Smith (2020)".
        if (preg_match('/^(?<author>.{1,300}?)\s*\((?<year>\d{4}[a-z]?)[^)]*\)\s*\.?/u', $text, $match) === 1) {
            $authorRaw = trim($match['author'], " ,");
            $year = trim($match['year']);
        } elseif (preg_match('/^(?<author>.{1,120}?)\s+(?<year>\d{4})[.,]\s/u', $text, $match) === 1) {
            $authorRaw = trim($match['author'], " ,");
            $year = trim($match['year']);
        }

        $surname = $this->surnameFrom((string) $authorRaw);

        $remainder = $text;

        if ($authorRaw !== null && $year !== null) {
            $remainder = trim((string) preg_replace(
                '/^.{1,300}?\s*\(\d{4}[a-z]?[^)]*\)\s*\.?/u',
                '',
                $text,
            ));
        } elseif ($authorRaw !== null) {
            $remainder = trim((string) preg_replace('/^.{1,120}?\s+\d{4}[.,]\s/u', '', $text));
        }

        // Title: up to the next sentence boundary that precedes a capitalised
        // source, or the first 300 characters.
        $remainder = trim((string) preg_replace('/\s+/u', ' ', $remainder));

        if ($remainder !== '') {
            $titleRaw = $this->splitTitle($remainder, $sourceRaw);
        }

        // Page range. APA places it after the volume and issue, which may itself be
        // followed by a DOI or URL, so it is matched in the trailing segment
        // rather than only at the very end of the entry.
        $tail = $this->trailingSegment($text);

        // Take the last page-range-shaped token that is not part of a DOI.
        $withoutDoi = preg_replace('#https?://\S+#iu', ' ', $tail) ?? $tail;

        if (preg_match_all('/(?<!\d)\d{1,6}\s*[-–—]\s*\d{1,6}(?!\d)/u', $withoutDoi, $matches) > 0) {
            $pagesRaw = trim($matches[0][count($matches[0]) - 1]);
        }

        // DOI, either bare or as a doi.org URL. A "doi:"-prefixed token is captured
        // even when malformed so the validator can report it rather than
        // silently ignoring what the author clearly intended as a DOI.
        if (preg_match(self::DOI_URL_PATTERN, $text, $match) === 1) {
            $doiRaw = rtrim($match[1], '.');
        } elseif (preg_match('/\bdoi:\s*(?<candidate>\S+)/iu', $text, $match) === 1) {
            $doiRaw = rtrim($match['candidate'], '.,;');
        } elseif (preg_match(self::DOI_PATTERN, $text, $match) === 1) {
            $doiRaw = rtrim($match[1], '.,;');
        }

        if (preg_match(self::URL_PATTERN, $text, $match) === 1) {
            $urlRaw = rtrim($match[0], '.,;');
        }

        return new ReferenceRecord(
            ordinal: $ordinal,
            rawText: $text,
            authorRaw: $authorRaw,
            surname: $surname,
            year: $year,
            titleRaw: $titleRaw,
            sourceRaw: $sourceRaw,
            doiRaw: $doiRaw,
            urlRaw: $urlRaw,
            pagesRaw: $pagesRaw,
            parsed: ['has_parenthesised_year' => $year !== null && str_contains($text, '(')],
        );
    }

    private function surnameFrom(string $authorRaw): ?string
    {
        if ($authorRaw === '') {
            return null;
        }

        if (preg_match('/^\s*([A-Z][A-Za-z\'’\-]+)/u', $authorRaw, $match) === 1) {
            return trim($match[1]);
        }

        return null;
    }

    private function isWellFormedDoi(string $doi): bool
    {
        return preg_match('/^10\.\d{4,9}\/\S+$/u', $doi) === 1;
    }

    /**
     * The portion of the entry after the title, where APA places the journal
     * name, volume, issue, page range and any DOI or URL.
     */
    private function trailingSegment(string $text): string
    {
        // Everything from the first volume/issue marker onwards, or the last
        // sentence-like segment when no volume is present.
        if (preg_match('/,\s*\d{1,4}\s*(?:\(\d[^)]*\))?\s*,/u', $text, $match, PREG_OFFSET_CAPTURE) === 1) {
            return substr($text, (int) $match[0][1]);
        }

        $segments = preg_split('/\.\s+/u', $text) ?: [$text];

        return (string) end($segments);
    }

    /**
     * APA entries are "Author. (Year). Title. Source, volume(issue), pages."
     * The title ends at the first ". " that is followed by a capitalised word.
     *
     * @param  string|null  $source
     */
    private function splitTitle(string $remainder, ?string &$source): ?string
    {
        if (preg_match('/^(?<title>.+?)\.\s+(?<source>(?:[A-Z]|https?:\/\/))/u', $remainder, $match) === 1) {
            $title = trim($match['title'], " .");
            $source = trim($match['source']);

            // A title that stops suspiciously early is not trustworthy.
            if (mb_strlen($title) >= 3) {
                return $title;
            }
        }

        return $remainder === '' ? null : $remainder;
    }
}