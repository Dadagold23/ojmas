<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One formatting and validation run against a parsed manuscript.
 *
 * A validation is a record of what the engine found and what it corrected. It
 * is never a decision: no manuscript is accepted or rejected on the basis of a
 * formatting result alone.
 */
class ManuscriptValidation extends Model
{
    use HasFactory;

    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'manuscript_document_id',
        'submission_id',
        'profile',
        'profile_version',
        'status',
        'auto_fixed_count',
        'author_action_count',
        'unverifiable_count',
        'unsupported_count',
        'warning_count',
        'error_count',
        'report_json',
        'error_message',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'report_json' => 'array',
            'auto_fixed_count' => 'integer',
            'author_action_count' => 'integer',
            'unverifiable_count' => 'integer',
            'unsupported_count' => 'integer',
            'warning_count' => 'integer',
            'error_count' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(ManuscriptDocument::class, 'manuscript_document_id');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(ManuscriptValidationIssue::class);
    }

    public function errors(): HasMany
    {
        return $this->issues()->where('severity', 'error');
    }

    public function warnings(): HasMany
    {
        return $this->issues()->where('severity', 'warning');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * A validation blocks editorial progression while it still has unresolved
     * errors. Warnings never block: a bibliography inconsistency should not stop
     * an editor reading a manuscript.
     */
    public function blocksProgression(): bool
    {
        return $this->isCompleted() && ! $this->isApproved() && $this->error_count > 0;
    }
}