<?php

namespace App\Services\Documents\Validation;

/**
 * Reads in-text citations out of manuscript prose.
 *
 * Extraction is purely syntactic and deterministic. A candidate is only accepted
 * when it contains a plausible four-digit year, which keeps ordinary numbers
 * ("128 students", "2020 samples were drawn") out of the results.
 *
 * The reader never decides whether a citation is *correct* — only where it is
 * and what it claims to say.
 */
class CitationExtractor
{
    /** A parenthetical group, e.g. "(Smith, 2020; Jones & Brown, 2019, p. 5)". */
    private const PARENTHETICAL = '/\(([^()]{2,200}?)\)/u';

    /** A narrative citation, e.g. "Smith et al. (2020) argued" or "Jones (2019)". */
    private const NARRATIVE = '/(?<author>[A-Z][\p{L}\'’\-]*(?:\s+(?:et\s+al\.?|and|&)\s+[A-Z][\p{L}\'’\-]*)*(?:\s+et\s+al\.?)?)\s*\((?<year>\d{4}[a-z]?)\)/u';

    private const YEAR = '/\b(1[89]\d{2}|20\d{2}|21\d{2})[a-z]?\b/u';

    /** Words that precede a bracketed year but are not author names. */
    private const NON_AUTHOR_PREFIXES = [
        'see', 'e.g', 'cf', 'ibid', 'op', 'cit', 'vol', 'no', 'p', 'pp',
        'for', 'after', 'before', 'since', 'until', 'in', 'on', 'at', 'by',
    ];

    /**
     * @param  array<int, string>  $paragraphTexts  Ordered body paragraphs, excluding the reference list.
     * @return array<int, CitationRecord>
     */
    public function extract(array $paragraphTexts): array
    {
        $citations = [];

        foreach ($paragraphTexts as $paragraphIndex => $text) {
            $text = (string) $text;

            // Narrative citations first, then blank them out so the same
            // reference is not counted again by the parenthetical pass.
            $withoutNarrative = $text;

            if (preg_match_all(self::NARRATIVE, $text, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) > 0) {
                foreach ($matches as $match) {
                    $authorToken = trim($match['author'][0]);
                    $year = $match['year'][0];
                    $full = $match[0][0];

                    if (! $this->looksLikeAuthor($authorToken)) {
                        continue;
                    }

                    $citations[] = $this->build($full, $authorToken, $year, null, $paragraphIndex, true);

                    $withoutNarrative = str_replace(
                        $full,
                        str_repeat(' ', strlen($full)),
                        $withoutNarrative,
                    );
                }
            }

            if (preg_match_all(self::PARENTHETICAL, $withoutNarrative, $parentheticals, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) > 0) {
                foreach ($parentheticals as $match) {
                    $inner = $match[1][0];
                    $full = $match[0][0];

                    foreach (explode(';', $inner) as $segment) {
                        $segment = trim($segment);

                        if ($segment === '' || preg_match(self::YEAR, $segment) !== 1) {
                            continue;
                        }

                        [$authorToken, $year, $locator] = $this->splitSegment($segment);

                        if ($authorToken === null || $year === null) {
                            continue;
                        }

                        if (! $this->looksLikeAuthor($authorToken)) {
                            continue;
                        }

                        $citations[] = $this->build($full, $authorToken, $year, $locator, $paragraphIndex, false);
                    }
                }
            }
        }

        return $citations;
    }

    /**
     * Splits "Smith, 2020, p. 5" into its author, year and locator parts.
     *
     * @return array{0: string|null, 1: string|null, 2: string|null}
     */
    private function splitSegment(string $segment): array
    {
        if (preg_match(self::YEAR, $segment, $yearMatch, PREG_OFFSET_CAPTURE) !== 1) {
            return [null, null, null];
        }

        $year = $yearMatch[0][0];
        $yearOffset = $yearMatch[0][1];

        $authorPart = trim(substr($segment, 0, $yearOffset));
        $authorPart = rtrim($authorPart, " \t,");
        $authorPart = trim(preg_replace('/^(?:see|cf|e\.g\.|ibid\.?|op\.\s*cit\.?)[,\s]*/iu', '', $authorPart) ?? '');

        $locatorPart = trim(substr($segment, $yearOffset + strlen($year)));
        $locatorPart = ltrim($locatorPart, " \t,");

        // APA allows "Author, 2020" and "Author et al., 2020"; anything with
        // digits in the author part is not an author name.
        if ($authorPart !== '' && preg_match('/\d/u', $authorPart) === 1) {
            return [null, null, null];
        }

        return [$authorPart === '' ? null : $authorPart, $year, $locatorPart === '' ? null : $locatorPart];
    }

    private function build(
        string $rawText,
        string $authorToken,
        string $year,
        ?string $locator,
        int $paragraphIndex,
        bool $narrative,
    ): CitationRecord {
        $surname = $this->surnameFrom($authorToken);
        $authorCount = $this->countAuthors($authorToken);

        return new CitationRecord(
            rawText: $rawText,
            authorToken: trim($authorToken),
            surname: $surname,
            year: trim($year),
            locator: $locator,
            paragraphIndex: $paragraphIndex,
            narrative: $narrative,
            stringAuthorCount: $authorCount,
        );
    }

    private function surnameFrom(string $authorToken): ?string
    {
        // "Belland et al." -> Belland; "Jones & Brown" -> Jones (first-listed).
        // The connectors may appear at the end of the token ("Smith et al."), so
        // the trailing form must be stripped as well as the infix form.
        $token = preg_split('/\s+(?:et\s+al\.?|and|&)\s+/iu', trim($authorToken))[0] ?? $authorToken;
        $token = preg_replace('/\s+et\s+al\.?$/iu', '', $token) ?? $token;
        $token = trim(preg_replace('/\s*,\s*$/u', '', $token) ?? $token);

        return $token === '' ? null : $token;
    }

    private function countAuthors(string $authorToken): int
    {
        $parts = preg_split('/\s+(?:and|&)\s+/iu', trim($authorToken)) ?: [];

        return max(1, count($parts));
    }

    private function looksLikeAuthor(string $token): bool
    {
        $token = trim($token);

        if ($token === '' || mb_strlen($token) > 80) {
            return false;
        }

        // Strip the "et al." / "and" connectors before inspecting the first word.
        $first = preg_split('/\s+(?:et\s+al\.?|and|&)\s+/iu', $token)[0] ?? '';
        $first = strtolower(rtrim(trim($first), ',.'));

        if (in_array($first, self::NON_AUTHOR_PREFIXES, true)) {
            return false;
        }

        // Must contain a letter, contain no digits, and consist only of
        // characters that can legitimately appear in a personal name.
        if (preg_match('/\p{L}/u', $token) !== 1) {
            return false;
        }

        if (preg_match('/\d/u', $token) === 1) {
            return false;
        }

        return preg_match('/^[\p{L}\s.,&\'’\-]+$/u', $token) === 1;
    }
}