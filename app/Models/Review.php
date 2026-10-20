<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reviewer's report on a manuscript.
 *
 * Lifecycle: draft → submitted → (optionally) withdrawn.
 *
 * **A submitted review is immutable.** `comments_for_author` and
 * `comments_for_editor` are never written after submission except through
 * `ReviewService`, which refuses to change them. The author-facing text is the
 * scholarly record of what a reviewer said, and an editor editing it would
 * corrupt that record.
 */
class Review extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_WITHDRAWN = 'withdrawn';

    protected $fillable = [
        'reviewer_assignment_id',
        'recommendation',
        'comments_for_author',
        'comments_for_editor',
        'status',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ReviewerAssignment::class, 'reviewer_assignment_id');
    }

    public function reviewer()
    {
        return $this->assignment?->reviewer;
    }

    public function submission()
    {
        return $this->assignment?->submission;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function isWithdrawn(): bool
    {
        return $this->status === self::STATUS_WITHDRAWN;
    }

    /**
     * Whether this review counts towards an editorial decision.
     */
    public function countsTowardsDecision(): bool
    {
        return $this->isSubmitted();
    }

    public function recommendationLabel(): string
    {
        return match ($this->recommendation) {
            'accept' => 'Accept',
            'minor_revision' => 'Minor revision',
            'major_revision' => 'Major revision',
            'reject' => 'Reject',
            default => 'No recommendation yet',
        };
    }
}