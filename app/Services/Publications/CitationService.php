<?php

namespace App\Services\Publications;

use App\Models\Article;

/**
 * Builds citations for a published article from the verified publication
 * record. Two hard rules from the brief:
 *
 *  1. Never invent a DOI, page range, publication year or author name.
 *  2. Where a required field is unavailable, the format degrades honestly —
 *     the emitted citation is still valid, and the caller's `limitations`
 *     list states exactly what was missing so it can be reported to the user.
 *
 * Author rendering uses ArticleBylineService so the byline in a citation is
 * byte-for-byte the same list the archive and article pages display.
 */
class CitationService
{
    public function __construct(private ArticleBylineService $byline) {}

    /**
     * @return array{apa: string, mla: string, chicago: string, bibtex: string, ris: string, limitations: array<int, string>}
     */
    public function for(Article $article): array
    {
        $article->loadMissing('issue.volume', 'submission.articleAuthors');

        $authors = $this->byline->for($article);
        $journalTitle = config('journal.title', config('app.name', 'OJMAS Journal'));
        $year = $this->firstYear($article);

        $limitations = [];

        if (! $article->doi) {
            $limitations[] = 'No DOI is recorded for this article, so citations cannot include one.';
        }

        if (! $article->pages) {
            $limitations[] = 'No page range is recorded for this article.';
        }

        if ($article->issue?->volume?->volume_no === null) {
            $limitations[] = 'The volume number cannot be resolved for this issue.';
        }

        if ($article->issue?->issue_no === null) {
            $limitations[] = 'The issue number cannot be resolved.';
        }

        $apa = $this->apa($authors, $year, $article, $journalTitle);
        $mla = $this->mla($authors, $article, $journalTitle);
        $chicago = $this->chicago($authors, $year, $article, $journalTitle);
        $ris = $this->ris($authors, $year, $article, $journalTitle);

        $bibtex = $this->bibtex($authors, $year, $article, $journalTitle);

        return [
            'apa' => $apa,
            'mla' => $mla,
            'chicago' => $chicago,
            'bibtex' => $bibtex,
            'ris' => $ris,
            'limitations' => $limitations,
        ];
    }

    /* ─────────────── APA 7 ─────────────── */

    private function apa(array $authors, ?string $year, Article $article, string $journalTitle): string
    {
        $authorList = $this->apaAuthorList($authors);

        $head = trim($authorList.' ('.$year.'). '.$article->title.'.');

        $source = $this->volumeIssuePages($article);

        $segments = [$head, $journalTitle.($source !== '' ? ', '.$source : ''), $this->doiSuffix($article)];

        return trim(collect($segments)->filter()->implode(' '));
    }

    private function apaAuthorList(array $authors): string
    {
        $names = array_map(fn (array $a) => $this->apaName($a['name']), $authors);

        if (count($names) === 1) {
            return $names[0];
        }

        if (count($names) === 2) {
            return $names[0].', & '.$names[1];
        }

        if (count($names) > 20) {
            // APA 7: list first 19, ellipsis, then the final author.
            $head = array_slice($names, 0, 19);
            $tail = $names[count($names) - 1];

            return implode(', ', $head).', ... & '.$tail;
        }

        return implode(', ', array_slice($names, 0, count($names) - 1)).', & '.$names[count($names) - 1];
    }

    private function apaName(string $fullName): string
    {
        // "Bello, A." — surname first per APA 7. The submission form records
        // full names; split on the last word being the surname, which matches
        // how the form asks for "Surname First name Middle name" ordering and
        // degrades gracefully to a raw complement otherwise.
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];
        if (count($parts) < 2) {
            return $fullName;
        }

        $surname = array_pop($parts);

        return $surname.', '.collect($parts)->map(fn ($p) => $p[0].'.')->implode(' ');
    }

    /* ─────────────── MLA (9th core elements, minimal) ─────────────── */

    private function mla(array $authors, Article $article, string $journalTitle): string
    {
        $names = array_map(fn (array $a) => $a['name'], $authors);
        $authorList = $names[0] ?? '';
        if (count($names) === 2) {
            $authorList = $names[0].', and '.$names[1];
        } elseif (count($names) > 2) {
            $authorList = $names[0].', et al.';
        }

        $source = $this->volumeIssuePages($article);
        $year = $this->firstYear($article);

        $citation = trim(($authorList !== '' ? $authorList.'.' : '').
            ' "'.$article->title.'." '.($article->doi ? '' : ''));

        $citation .= $journalTitle.($source !== '' ? ', '.$source : '');

        if ($year !== null) {
            $citation .= ', '.$year;
        }

        if ($article->doi) {
            $citation .= ', https://doi.org/'.(string) $article->doi.'.';
        } else {
            $citation .= '.';
        }

        return trim($citation);
    }

    /* ─────────────── Chicago (author–date bibliography entry) ─────────────── */

    private function chicago(array $authors, ?string $year, Article $article, string $journalTitle): string
    {
        $names = array_map(fn (array $a) => $a['name'], $authors);
        $authorList = $names[0] ?? '';
        if (count($names) === 2) {
            $authorList = $names[0].', and '.$names[1];
        } elseif (count($names) > 2) {
            $authorList = $names[0].', et al.';
        }

        $source = $this->volumeIssuePages($article);

        $citation = $authorList.($year !== null ? '. '.$year : '.').' '.$article->title.'.';

        $citation .= ' '.$journalTitle.' '.$this->chicagoSource($article);

        if ($article->doi) {
            $citation .= ' https://doi.org/'.(string) $article->doi.'.';
        }

        return trim($citation);
    }

    private function chicagoSource(Article $article): string
    {
        $volume = $article->issue?->volume?->volume_no;
        $issue = $article->issue?->issue_no;

        $segment = '';
        if ($volume !== null) {
            $segment .= (string) $volume;
        }
        if ($issue !== null) {
            $segment .= ', no. '.$issue;
        }
        if ($segment !== '') {
            $segment .= ' ('.$this->firstYear($article).')';
        }
        if ($article->pages) {
            $segment .= ($segment !== '' ? ': ' : '').$article->pages;
        }

        return $segment !== '' ? $segment : '(publication metadata incomplete)';
    }

    /* ─────────────── BibTeX ─────────────── */

    private function bibtex(array $authors, ?string $year, Article $article, string $journalTitle): string
    {
        $key = $this->citationKey($article, $year);
        [$volume, $number] = $this->volumeAndNumber($article);

        $lines = [
            '@article{'.$key.',',
            '  title = {'.$this->latexEscape($article->title).'},',
        ];

        if ($year !== null) {
            $lines[] = '  year = {'.$year.'},';
        }

        $lines[] = '  journal = {'.$this->latexEscape($journalTitle).'},';

        if ($volume !== null) {
            $lines[] = '  volume = {'.$volume.'},';
        }
        if ($number !== null) {
            $lines[] = '  number = {'.$number.'},';
        }
        if ($article->pages) {
            $lines[] = '  pages = {'.$article->pages.'},';
        }
        if ($article->doi) {
            $lines[] = '  doi = {'.$article->doi.'},';
        }
        if ($article->keywords) {
            $lines[] = '  keywords = {'.$this->latexEscape($article->keywords).'},';
        }

        $lines[] = '}';

        return implode("\n", $lines);
    }

    /* ─────────────── RIS ─────────────── */

    private function ris(array $authors, ?string $year, Article $article, string $journalTitle): string
    {
        $lines = [
            'TY  - JOUR',
        ];

        foreach ($authors as $author) {
            $parts = preg_split('/\s+/', trim($author['name'])) ?: [];
            if (count($parts) < 2) {
                $lines[] = 'AU  - '.$author['name'];
            } else {
                $surname = array_pop($parts);
                $lines[] = 'AU  - '.$surname.', '.implode(' ', $parts);
            }
        }

        $lines[] = 'TI  - '.$article->title;

        if ($year !== null) {
            $lines[] = 'PY  - '.$year;
        }

        $lines[] = 'JO  - '.$journalTitle;
        [$volume, $number] = $this->volumeAndNumber($article);

        if ($volume !== null) {
            $lines[] = 'VL  - '.$volume;
        }
        if ($number !== null) {
            $lines[] = 'IS  - '.$number;
        }
        if ($article->pages) {
            $lines[] = 'SP  - '.$article->pages;
        }
        if ($article->doi) {
            $lines[] = 'DO  - '.$article->doi;
        }
        if ($article->keywords) {
            foreach (array_map('trim', explode(',', $article->keywords)) as $keyword) {
                if ($keyword !== '') {
                    $lines[] = 'KW  - '.$keyword;
                }
            }
        }

        $lines[] = 'ER  - ';

        return implode("\n", $lines);
    }

    /* ─────────────── shared helpers ─────────────── */

    /** '3(1), 45-60' / '3(1)' fragment used by the APA and MLA strings. */
    private function volumeIssuePages(Article $article): string
    {
        $volume = $article->issue?->volume?->volume_no;
        $issue = $article->issue?->issue_no;

        $segment = '';
        if ($volume !== null && $issue !== null) {
            $segment = $volume.'('.$issue.')';
        } elseif ($volume !== null) {
            $segment = (string) $volume;
        } elseif ($issue !== null) {
            $segment = '('.$issue.')';
        }

        if ($article->pages) {
            $segment .= ($segment !== '' ? ', ' : '').$article->pages;
        }

        return $segment;
    }

    private function doiSuffix(Article $article): string
    {
        return $article->doi ? 'https://doi.org/'.(string) $article->doi : '';
    }

    /** @return array{0: string|null, 1: string|null} volume, number */
    private function volumeAndNumber(Article $article): array
    {
        return [
            $article->issue?->volume?->volume_no !== null ? (string) $article->issue->volume->volume_no : null,
            $article->issue?->issue_no !== null ? (string) $article->issue->issue_no : null,
        ];
    }

    private function firstYear(Article $article): ?string
    {
        $published = $article->published_on ?? $article->issue->published_on ?? null;

        return $published !== null ? substr((string) $published, 0, 4) : null;
    }

    private function citationKey(Article $article, ?string $year): string
    {
        // BibTeX keys conventionally lead with the first author's surname;
        // the byline's first name entry ends with the surname in this journal
        // ("Given Surname"), so take its last word and strip punctuation.
        $parts = preg_split('/\s+/', trim($this->byline->for($article)[0]['name'] ?? '')) ?: [];
        $surname = count($parts) > 0 ? end($parts) : '';
        $surname = strtolower((string) preg_replace('/[^A-Za-z]/', '', $surname));

        if ($surname === '') {
            $surname = 'ojmas';
        }

        return $surname.($year !== null ? $year : '').'o'.($article->id ?? 'x');
    }

    private function latexEscape(string $value): string
    {
        return str_replace(['&', '%', '#'], ['\&', '\%', '\#'], $value);
    }
}
