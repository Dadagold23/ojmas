<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One round of author revision.
 *
 * The round is what gives the author a concrete deadline and what an editor
 * later compares against when deciding whether a revision was completed.
 */
class RevisionRound extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_SUPERSEDED = 'superseded';

    protected $fillable = [
        'submission_id',
        'round_number',
        'status',
        'opened_by',
        'opened_at',
        'due_at',
        'closed_at',
        'decision_id',
        'author_response',
    ];

    protected function casts(): array
    {
        return [
            'round_number' => 'integer',
            'opened_at' => 'datetime',
            'due_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(EditorialDecision::class);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_at !== null && $this->due_at->isPast();
    }

    public function daysRemaining(): ?int
    {
        if (! $this->isOpen() || $this->due_at === null) {
            return null;
        }

        return (int) now()->diffInDays($this->due_at, false);
    }
}