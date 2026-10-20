<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single finding from a validation run.
 *
 * Severity is stored rather than inferred, so the report the author reads and
 * the stored record can never drift apart.
 */
class ManuscriptValidationIssue extends Model
{
    use HasFactory;

    public const SEVERITY_ERROR = 'error';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_INFO = 'info';

    protected $fillable = [
        'manuscript_validation_id',
        'severity',
        'category',
        'code',
        'rule_id',
        'rule_version',
        'location_json',
        'message',
        'suggestion',
        'was_auto_fixed',
        'requires_author_action',
    ];

    protected function casts(): array
    {
        return [
            'location_json' => 'array',
            'was_auto_fixed' => 'boolean',
            'requires_author_action' => 'boolean',
        ];
    }

    public function validation(): BelongsTo
    {
        return $this->belongsTo(ManuscriptValidation::class, 'manuscript_validation_id');
    }

    public function isError(): bool
    {
        return $this->severity === self::SEVERITY_ERROR;
    }

    public function isWarning(): bool
    {
        return $this->severity === self::SEVERITY_WARNING;
    }
}