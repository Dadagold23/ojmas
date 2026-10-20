<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Manuscript Document Processing
    |--------------------------------------------------------------------------
    |
    | Configuration for the manuscript ingestion and APA 7 formatting engine.
    | These values are intentionally explicit rather than environment-driven
    | for anything security-relevant, so that a misconfigured .env cannot
    | weaken upload validation.
    |
    */

    'disk' => env('MANUSCRIPT_DISK', 'private'),

    'paths' => [
        // Original author uploads live in the existing submissions tree and are
        // never overwritten by the formatting engine.
        'originals' => 'submissions/manuscript',
        // Engine output.
        'formatted' => 'manuscripts/formatted',
        'parsed' => 'manuscripts/parsed',
    ],

    'limits' => [
        // Hard ceiling on an accepted DOCX upload (100 MB).
        'max_upload_bytes' => 104857600,

        // Zip-bomb defence: a DOCX is a ZIP container, so the decompressed
        // size and the compression ratio must both be bounded.
        'max_entries' => 3000,
        'max_entry_uncompressed_bytes' => 268435456,   // 256 MB single entry
        'max_total_uncompressed_bytes' => 536870912,   // 512 MB total
        'max_compression_ratio' => 120,

        // Parts that must exist for a file to be a genuine OOXML word document.
        'required_parts' => [
            '[Content_Types].xml',
            'word/document.xml',
        ],

        // Parts that are never acceptable in a manuscript upload.
        'forbidden_parts' => [
            'vbaProject.bin',      // macro-enabled document (.docm) payload
            'word/vbaProject.bin',
        ],

        // Guard against relationship-driven external fetches.
        'max_external_relationships' => 50,
    ],

    'accepted_mime_types' => [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ],

    /*
    |--------------------------------------------------------------------------
    | Formatting
    |--------------------------------------------------------------------------
    */

    'formatting' => [
        // "ojmas" applies APA 7 professional conventions plus the journal's
        // published overrides. "apa7_professional" is the bare APA 7 baseline.
        'default_profile' => env('MANUSCRIPT_STYLE_PROFILE', 'ojmas'),

        // External metadata verification (Crossref et al.) is OFF by default.
        // Prompt rule: never present unverified suggestions as verified facts.
        'external_metadata_lookup' => [
            'enabled' => env('MANUSCRIPT_EXTERNAL_LOOKUP', false),
            'timeout_seconds' => 8,
            'max_lookups_per_document' => 25,
            'mailto' => env('MANUSCRIPT_LOOKUP_MAILTO'),
        ],
    ],

];