<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrNull;

/**
 * The parsed representation of one manuscript version.
 *
 * `structure_json` holds the neutral manuscript tree, which makes the parse
 * result inspectable and lets the workspace and the comparison view in later
 * modules work from the same data the formatting engine used.
 */
class ManuscriptDocument extends Model
{
    use HasFactory;

    public const STATUS_QUEUED = 'queued';
    public const STATUS_PARSING = 'parsing';
    public const STATUS_PARSED = 'parsed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'manuscript_version_id',
        'format',
        'storage_path',
        'sha256',
        'rule_set_version',
        'word_count',
        'paragraph_count',
        'structure_json',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'word_count' => 'integer',
            'paragraph_count' => 'integer',
            'structure_json' => 'array',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ManuscriptVersion::class, 'manuscript_version_id');
    }

    public function validations(): HasMany
    {
        return $this->hasMany(ManuscriptValidation::class);
    }

    public function latestValidation(): HasOneOrNull
    {
        return $this->hasOne(ManuscriptValidation::class)->latestOfMany('id');
    }

    public function citations(): HasMany
    {
        return $this->hasMany(ManuscriptCitation::class);
    }

    public function references(): HasMany
    {
        return $this->hasMany(ManuscriptReference::class);
    }

    public function hasParsedSuccessfully(): bool
    {
        return $this->status === self::STATUS_PARSED;
    }
}