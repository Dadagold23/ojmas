<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An append-only record of a workflow action.
 *
 * Nothing in the interface edits or deletes these rows. They are the evidence
 * behind "who moved this manuscript, when, and why".
 */
class ManuscriptWorkflowEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_id', 'from_stage', 'to_stage', 'actor_id',
        'event', 'reason', 'metadata', 'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}