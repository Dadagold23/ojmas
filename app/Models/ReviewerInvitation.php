<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How a reviewer was invited to a manuscript, and whether they responded.
 */
class ReviewerInvitation extends Model
{
    use HasFactory;

    public const RESPONSE_PENDING = 'pending';
    public const RESPONSE_ACCEPTED = 'accepted';
    public const RESPONSE_DECLINED = 'declined';

    protected $fillable = [
        'submission_id', 'reviewer_id', 'round_id', 'invited_by', 'invited_at',
        'due_at', 'response', 'responded_at', 'response_note',
        'reminder_count', 'last_reminded_at', 'expired_at',
    ];

    protected function casts(): array
    {
        return [
            'invited_at' => 'datetime',
            'due_at' => 'datetime',
            'responded_at' => 'datetime',
            'last_reminded_at' => 'datetime',
            'expired_at' => 'datetime',
            'reminder_count' => 'integer',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(RevisionRound::class);
    }

    public function isPending(): bool
    {
        return $this->response === self::RESPONSE_PENDING;
    }

    public function isOverdue(): bool
    {
        return $this->isPending()
            && $this->due_at !== null
            && $this->due_at->isPast();
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        return [
            'id' => $this->id,
            'reviewer' => $this->reviewer?->name,
            'response' => $this->response,
            'invited_at' => $this->invited_at?->toIso8601String(),
            'due_at' => $this->due_at?->toIso8601String(),
            'responded_at' => $this->responded_at?->toIso8601String(),
            'reminder_count' => (int) $this->reminder_count,
            'overdue' => $this->isOverdue(),
        ];
    }
}