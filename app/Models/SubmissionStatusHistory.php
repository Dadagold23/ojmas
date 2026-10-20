<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An append-only record of a submission status or stage change.
 *
 * Nothing in the interface edits or removes these rows.
 */
class SubmissionStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'changed_by',
        'old_status',
        'new_status',
        'note',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}