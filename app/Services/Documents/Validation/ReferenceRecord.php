<?php

namespace App\Services\Documents\Validation;

/**
 * A single reference-list entry, parsed only as far as the entry's own text
 * supports.
 *
 * Every field is read from the document. When a field cannot be read it stays
 * null and is reported as incomplete; it is never invented, guessed, or
 * completed from an external service unless that service is explicitly enabled
 * and its answer is labelled `verified_external`.
 */
final class ReferenceRecord
{
    public const VERIFICATION_UNVERIFIED = 'unverified';
    public const VERIFICATION_EXTERNAL = 'verified_external';
    public const VERIFICATION_SUGGESTION = 'unverified_suggestion';

    /**
     * @param  array<string, mixed>  $parsed
     */
    public function __construct(
        public readonly int $ordinal,
        public readonly string $rawText,
        public readonly ?string $authorRaw,
        public readonly ?string $surname,
        public readonly ?string $year,
        public readonly ?string $titleRaw,
        public readonly ?string $sourceRaw,
        public readonly ?string $doiRaw,
        public readonly ?string $urlRaw,
        public readonly ?string $pagesRaw,
        public readonly array $parsed = [],
        public readonly string $verificationStatus = self::VERIFICATION_UNVERIFIED,
    ) {
    }

    public function matchKey(): string
    {
        $surname = CitationRecord::normaliseSurname((string) $this->surname);

        if ($this->year !== null) {
            return $surname.'|'.$this->year;
        }

        return $surname !== '' ? $surname : mb_strtolower(trim($this->rawText));
    }

    /**
     * Key ignoring the year.
     *
     * Used to detect a reference that is never cited, and to tell "no entry at
     * all for this author" apart from "an entry exists but its year disagrees".
     */
    public function yearOnlyKey(): string
    {
        $surname = CitationRecord::normaliseSurname((string) $this->surname);

        return $surname !== '' ? $surname : mb_strtolower(trim($this->rawText));
    }

    /**
     * A weaker fingerprint used only to detect probable duplicates.
     * It deliberately ignores the year so that two entries for the same work
     * appearing under different years are still compared.
     */
    public function duplicateKey(): string
    {
        $surname = CitationRecord::normaliseSurname((string) $this->surname);
        $title = mb_strtolower(trim((string) $this->titleRaw));
        $title = preg_replace('/[^a-z0-9 ]+/u', '', $title) ?? '';
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? '');

        if (strlen($title) < 12) {
            $title = mb_strtolower(trim($this->rawText));
            $title = substr(preg_replace('/[^a-z0-9 ]+/u', '', $title) ?? '', 0, 60);
        }

        return $surname.'|'.$title;
    }

    /** @return array<int, string> Field names that APA 7 expects but the entry lacks. */
    public function missingFields(): array
    {
        $missing = [];

        if ($this->surname === null || trim($this->authorRaw ?? '') === '') {
            $missing[] = 'author';
        }
        if ($this->year === null) {
            $missing[] = 'year';
        }
        if ($this->titleRaw === null || trim($this->titleRaw) === '') {
            $missing[] = 'title';
        }
        if ($this->sourceRaw === null || trim($this->sourceRaw) === '') {
            $missing[] = 'source';
        }

        return $missing;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'ordinal' => $this->ordinal,
            'raw_text' => $this->rawText,
            'author_raw' => $this->authorRaw,
            'surname' => $this->surname,
            'year' => $this->year,
            'title_raw' => $this->titleRaw,
            'source_raw' => $this->sourceRaw,
            'doi_raw' => $this->doiRaw,
            'url_raw' => $this->urlRaw,
            'pages_raw' => $this->pagesRaw,
            'match_key' => $this->matchKey(),
            'verification_status' => $this->verificationStatus,
        ];
    }
}