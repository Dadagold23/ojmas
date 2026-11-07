<?php

// Journal identity used by citations, sitemap and scholarly metadata.
return [
    'title' => env('JOURNAL_TITLE', 'OJMAS Journal'),
    'issn' => env('JOURNAL_ISSN'),

    // The site release, shown on the public pages and sent to Crossref.
    'version' => env('JOURNAL_VERSION', '2.3.0'),
];
