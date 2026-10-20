<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reference-list entry extracted from a parsed manuscript.
 *
 * `verification_status` is explicit and defaults to "unverified": the engine
 * never presents a reference as externally confirmed unless a metadata service
 * that was actually queried returned it, and even then the raw entry is left
 * untouched.
 */
class ManuscriptReference extends Model
{
    use HasFactory;

    public const VERIFICATION_UNVERIFIED = 'unverified';
    public const VERIFICATION_EXTERNAL = 'verified_external';
    public const VERIFICATION_SUGGESTION = 'unverified_suggestion';

    protected $fillable = [
        'manuscript_document_id',
        'ordinal',
        'raw_text',
        'author_raw',
        'year',
        'title_raw',
        'source_raw',
        'doi_raw',
        'url_raw',
        'pages_raw',
        'parsed_json',
        'match_key',
        'duplicate_of_id',
        'verification_status',
    ];

    protected function casts(): array
    {
        return [
            'ordinal' => 'integer',
            'parsed_json' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(ManuscriptDocument::class, 'manuscript_document_id');
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_id');
    }

    public function isVerifiedExternally(): bool
    {
        return $this->verification_status === self::VERIFICATION_EXTERNAL;
    }
}