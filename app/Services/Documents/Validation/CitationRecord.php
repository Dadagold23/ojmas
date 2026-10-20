<?php

namespace App\Services\Documents\Validation;

use App\Services\Documents\Ast\ManuscriptNode;

/**
 * A single in-text citation occurrence, read from the document and nothing else.
 *
 * Every field here was literally present in the manuscript text. The validator
 * never synthesises an author, a year or a locator.
 */
final class CitationRecord
{
    public function __construct(
        public readonly string $rawText,
        public readonly ?string $authorToken,
        public readonly ?string $surname,
        public readonly ?string $year,
        public readonly ?string $locator,
        public readonly ?int $paragraphIndex,
        public readonly bool $narrative = false,
        public readonly int $stringAuthorCount = 1,
    ) {
    }

    /**
     * Canonical key used to pair a citation with a reference entry.
     * Falls back to the raw author token when no year was found.
     */
    public function matchKey(): string
    {
        $surname = self::normaliseSurname((string) $this->surname);

        if ($this->year !== null) {
            return $surname.'|'.$this->year;
        }

        return $surname !== '' ? $surname : mb_strtolower(trim($this->rawText));
    }

    public function yearOnlyKey(): string
    {
        $surname = self::normaliseSurname((string) $this->surname);

        return $surname !== '' ? $surname : mb_strtolower(trim($this->rawText));
    }

    /** Collapses spelling and punctuation so "O'Brien" matches "OBrien". */
    public static function normaliseSurname(string $surname): string
    {
        $surname = mb_strtolower(trim($surname));
        $surname = str_replace(["'", '’', '-', '.'], '', $surname);

        return trim($surname);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'raw_text' => $this->rawText,
            'author_token' => $this->authorToken,
            'year' => $this->year,
            'locator' => $this->locator,
            'paragraph_index' => $this->paragraphIndex,
            'narrative' => $this->narrative,
            'match_key' => $this->matchKey(),
        ];
    }
}