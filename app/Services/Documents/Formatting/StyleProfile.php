<?php

namespace App\Services\Documents\Formatting;

/**
 * An immutable, versioned set of manuscript formatting requirements.
 *
 * Two kinds of profile exist:
 *
 *  - `apa7_professional` — the bare APA 7 baseline for a professional paper.
 *  - `ojmas` — the APA 7 baseline with the journal's published overrides,
 *    derived from the OJMAS author guidelines: A4 paper, an 18-word title
 *    ceiling, author details on a separate cover page, and a separate
 *    anonymous abstract sheet capped at 150 words.
 *
 * Profiles are data, not code, so the journal can change a requirement without
 * a deployment, and so every validation report can state precisely which
 * version of which rules produced it.
 */
final class StyleProfile
{
    public const APA7_PROFESSIONAL = 'apa7_professional';
    public const OJMAS = 'ojmas';

    /**
     * @param  array<string, mixed>  $requirements
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $name,
        public readonly string $version,
        public readonly array $requirements,
        public readonly string $description = '',
        public readonly ?int $savedProfileId = null,
    ) {
    }

    public function requirement(string $key, mixed $default = null): mixed
    {
        return $this->requirements[$key] ?? $default;
    }

    /** @return array<int, string> */
    public function approvedFonts(): array
    {
        return array_map('strval', (array) $this->requirement('approved_fonts', ['Times New Roman']));
    }

    public function isFontApproved(string $font): bool
    {
        foreach ($this->approvedFonts() as $approved) {
            if (strcasecmp(trim($font), trim($approved)) === 0) {
                return true;
            }
        }

        return false;
    }

    public function isFontSizeApproved(int $halfPoints): bool
    {
        $approved = array_map('intval', (array) $this->requirement('approved_font_sizes_half_points', [24]));

        return in_array($halfPoints, $approved, true);
    }

    /** @return array<int, string> */
    public function describe(): array
    {
        $lines = [];

        foreach ($this->requirements as $key => $value) {
            $lines[] = $key.': '.(is_array($value) ? json_encode($value) : var_export($value, true));
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $requirements
     */
    public function withRequirements(array $requirements): self
    {
        return new self(
            $this->slug,
            $this->name,
            $this->version,
            $requirements + $this->requirements,
            $this->description,
            $this->savedProfileId,
        );
    }
}