<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The author's signed approval of their proofs — or their request for
 * corrections. Part of what the publication readiness gate requires.
 */
class ManuscriptProofApproval extends Model
{
    public const STATUS_APPROVED = 'approved';
    public const STATUS_CORRECTIONS = 'corrections_requested';

    protected $fillable = [
        'submission_id',
        'revision_round_id',
        'author_id',
        'status',
        'notes',
        'approved_at',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function revisionRound(): BelongsTo
    {
        return $this->belongsTo(RevisionRound::class, 'revision_round_id');
    }
}
