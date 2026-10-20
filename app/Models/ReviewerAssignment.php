<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReviewerAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'reviewer_id',
        'due_date',
        'status',
        'blind_code',
        'round_id',
        'invited_at',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'invited_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function invitations()
    {
        return $this->hasMany(ReviewerInvitation::class, 'submission_id', 'submission_id')
            ->whereColumn('reviewer_invitations.reviewer_id', 'reviewer_assignments.reviewer_id');
    }

    public function round()
    {
        return $this->belongsTo(RevisionRound::class);
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->due_date->isPast()
            && ! in_array($this->status, ['completed', 'declined'], true);
    }

    /**
     * The submitted review, if there is one.
     *
     * Drafts and withdrawn reviews are deliberately excluded so they cannot
     * influence a decision.
     */
    public function submittedReview()
    {
        return $this->hasOne(Review::class)
            ->where('status', Review::STATUS_SUBMITTED);
    }

    public function submission()
    {
        return $this->belongsTo(\App\Models\Submission::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(\App\Models\User::class, 'reviewer_id');
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }
}
