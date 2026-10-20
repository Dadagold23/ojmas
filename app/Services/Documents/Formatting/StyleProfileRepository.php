<?php

namespace App\Services\Documents\Formatting;

use App\Models\JournalStyleProfile;

/**
 * Resolves the active style profile.
 *
 * Precedence:
 *   1. A profile saved in `journal_style_profiles` (editable by a Super Admin).
 *   2. The built-in definition for the requested slug.
 *
 * The built-in definitions live here rather than in config because they are a
 * statement of the journal's editorial policy, not a deployment setting, and
 * they must remain available even if the settings table is empty or a database
 * connection is unavailable.
 */
class StyleProfileRepository
{
    public const BUILT_IN_VERSION = '1.0.0';

    /**
     * APA 7 baseline for a professional paper: 8.5 x 11 in, 1 in margins,
     * 12 pt Times New Roman, double spacing, 0.5 in first-line indent,
     * 0.5 in hanging indent on references.
     *
     * @return array<string, mixed>
     */
    public static function apa7ProfessionalRequirements(): array
    {
        return [
            'paper' => [
                'width_twips' => 12240,   // 8.5 in
                'height_twips' => 15840,  // 11 in
                'label' => 'US Letter',
            ],
            'margins_twips' => [
                'top' => 1440,
                'right' => 1440,
                'bottom' => 1440,
                'left' => 1440,
            ],
            'approved_fonts' => ['Times New Roman'],
            'approved_font_sizes_half_points' => [24],
            'line_spacing_twips' => 480,
            'line_spacing_rule' => 'auto',
            'body_first_line_indent_twips' => 720,
            'body_alignment' => 'left',
            'reference_hanging_indent_twips' => 720,
            'title_max_words' => 120,
            'abstract_min_words' => 150,
            'abstract_max_words' => 250,
            'abstract_requires_separate_page' => false,
            'abstract_must_exclude_author_names' => false,
            'author_details_on_title_page' => true,
            'author_details_on_separate_cover_page' => false,
            'blinded_review_copy' => false,
            'page_numbers' => true,
            'heading_levels' => [1, 2, 3, 4, 5],
            'enforce_heading_level_sequence' => true,
            'require_abstract' => false,
            'reference_style' => 'apa7',
        ];
    }

    /**
     * The OJMAS overrides, taken from the journal's published author guidelines
     * (resources/views/guidelines.blade.php):
     *
     *  - "Typed manuscripts should be prepared in double spacing on A4 paper."
     *  - "Keep the title concise and within 18 words where possible."
     *  - "Place author names and affiliation details on a separate cover page."
     *  - "Present the abstract on a separate sheet without author names and keep
     *     it within 150 words."
     *  - "Use the latest APA referencing style consistently."
     *  - "all author information has been separated from the blinded review copy"
     *
     * @return array<string, mixed>
     */
    public static function ojmasRequirements(): array
    {
        // Order matters: PHP's + is a union that keeps the LEFT operand's key,
        // so the journal overrides must come first or the APA baseline would
        // silently win.
        return [

            'paper' => [
                'width_twips' => 11906,   // A4
                'height_twips' => 16838,
                'label' => 'A4',
            ],
            'title_max_words' => 18,
            'abstract_max_words' => 150,
            'abstract_min_words' => 0,
            'abstract_requires_separate_page' => true,
            'abstract_must_exclude_author_names' => true,
            'author_details_on_separate_cover_page' => true,
            'require_abstract' => true,
            'blinded_review_copy' => true,

        ] + self::apa7ProfessionalRequirements();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function builtInProfiles(): array
    {
        return [
            StyleProfile::APA7_PROFESSIONAL => [
                'name' => 'APA 7 Professional Paper',
                'description' => 'The baseline APA 7 style for a professional manuscript: '
                    .'US Letter, 1 inch margins, 12 pt Times New Roman, double spacing.',
                'requirements' => self::apa7ProfessionalRequirements(),
            ],
            StyleProfile::OJMAS => [
                'name' => 'OJMAS Submission Profile',
                'description' => 'APA 7 professional conventions plus the OJMAS author guidelines: '
                    .'A4 paper, 18-word title ceiling, author details on a separate cover page, '
                    .'and a separate anonymous abstract sheet of at most 150 words.',
                'requirements' => self::ojmasRequirements(),
            ],
        ];
    }

    public function resolve(?string $slug = null): StyleProfile
    {
        $slug ??= (string) config('manuscripts.formatting.default_profile', StyleProfile::OJMAS);
        $slug = trim($slug) !== '' ? trim($slug) : StyleProfile::OJMAS;

        $saved = $this->loadFromDatabase($slug);

        if ($saved !== null) {
            return $saved;
        }

        return $this->builtIn($slug);
    }

    public function builtIn(string $slug): StyleProfile
    {
        $builtIn = self::builtInProfiles();
        $definition = $builtIn[$slug] ?? $builtIn[StyleProfile::OJMAS];

        return new StyleProfile(
            slug: $slug,
            name: $definition['name'],
            version: self::BUILT_IN_VERSION,
            requirements: $definition['requirements'],
            description: $definition['description'],
        );
    }

    /** @return array<int, string> */
    public function availableSlugs(): array
    {
        $slugs = array_keys(self::builtInProfiles());

        try {
            $saved = JournalStyleProfile::query()->pluck('slug')->all();
        } catch (\Throwable) {
            return $slugs;
        }

        return array_values(array_unique(array_merge($slugs, $saved)));
    }

    private function loadFromDatabase(string $slug): ?StyleProfile
    {
        try {
            $record = JournalStyleProfile::query()->where('slug', $slug)->first();
        } catch (\Throwable) {
            // A database outage must not make the formatting engine unusable.
            return null;
        }

        if ($record === null) {
            return null;
        }

        // The model casts rules_json to an array, but a row written by a raw query or
        // an older migration may still hold the JSON string.
        $stored = $record->rules_json;
        $requirements = is_array($stored) ? $stored : json_decode((string) $stored, true);

        if (! is_array($requirements)) {
            return null;
        }

        $fallback = $this->builtIn($slug);

        return new StyleProfile(
            slug: $record->slug,
            name: (string) $record->name,
            version: (string) $record->version,
            requirements: $requirements + $fallback->requirements,
            description: (string) $record->description,
            savedProfileId: (int) $record->id,
        );
    }
}