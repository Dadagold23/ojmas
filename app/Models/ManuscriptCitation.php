<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An in-text citation extracted from a parsed manuscript.
 *
 * Every column holds text the author actually wrote. Nothing here is generated,
 * completed or guessed.
 */
class ManuscriptCitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'manuscript_document_id',
        'raw_text',
        'author_token',
        'year',
        'locator',
        'paragraph_index',
        'match_key',
    ];

    protected function casts(): array
    {
        return [
            'paragraph_index' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(ManuscriptDocument::class, 'manuscript_document_id');
    }
}