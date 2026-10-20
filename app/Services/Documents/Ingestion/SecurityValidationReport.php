<?php

namespace App\Services\Documents\Ingestion;

/**
 * Immutable result of a successful security validation pass.
 */
final readonly class SecurityValidationReport
{
    public function __construct(
        public int $entryCount,
        public int $totalUncompressedBytes,
        public int $mediaPartCount,
        public float $maxCompressionRatioObserved,
        /** @var array<int, string> */
        public array $partNames,
    ) {
    }

    public function hasMedia(): bool
    {
        return $this->mediaPartCount > 0;
    }

    public function hasFootnotes(): bool
    {
        return in_array('word/footnotes.xml', $this->partNames, true);
    }

    public function hasEndnotes(): bool
    {
        return in_array('word/endnotes.xml', $this->partNames, true);
    }

    public function hasComments(): bool
    {
        return in_array('word/comments.xml', $this->partNames, true);
    }

    public function hasNumberingDefinitions(): bool
    {
        return in_array('word/numbering.xml', $this->partNames, true);
    }

    public function hasMacros(): bool
    {
        foreach ($this->partNames as $part) {
            if (str_contains($part, 'vbaproject.bin')) {
                return true;
            }
        }

        return false;
    }

    public function toArray(): array
    {
        return [
            'entry_count' => $this->entryCount,
            'total_uncompressed_bytes' => $this->totalUncompressedBytes,
            'media_part_count' => $this->mediaPartCount,
            'max_compression_ratio_observed' => $this->maxCompressionRatioObserved,
            'has_media' => $this->hasMedia(),
            'has_footnotes' => $this->hasFootnotes(),
            'has_endnotes' => $this->hasEndnotes(),
            'has_comments' => $this->hasComments(),
            'has_numbering_definitions' => $this->hasNumberingDefinitions(),
            'has_macros' => $this->hasMacros(),
        ];
    }
}