<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_no',
        'author_id',
        'title',
        'slug',
        'abstract',
        'keywords',
        'subject_area',
        'status',
        'is_corresponding_author',
        'assigned_editor_id',
        'current_version_id',
        'current_stage',
        'revision_round',
        'decision_due_at',
        'anonymous_code',
    ];

    protected function casts(): array
    {
        return [
            'decision_due_at' => 'datetime',
        ];
    }

    public function assignedEditor()
    {
        return $this->belongsTo(User::class, 'assigned_editor_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function coAuthors()
    {
        return $this->hasMany(ArticleAuthor::class);
    }

    public function files()
    {
        return $this->hasMany(ArticleFile::class);
    }

    public function histories()
    {
        return $this->hasMany(SubmissionStatusHistory::class);
    }
    public function reviewerAssignments()
    {
        return $this->hasMany(\App\Models\ReviewerAssignment::class);
    }

    public function editorialDecisions()
    {
        return $this->hasMany(EditorialDecision::class);
    }
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
    public function article()
    {
        return $this->hasOne(\App\Models\Article::class);
    }

    public function correspondenceMessages()
    {
        return $this->hasMany(CorrespondenceMessage::class);
    }

    public function articleAuthors()
    {
        return $this->hasMany(\App\Models\ArticleAuthor::class);
    }

    /**
     * Immutable manuscript snapshots, oldest first.
     */
    public function manuscriptVersions()
    {
        return $this->hasMany(ManuscriptVersion::class)->orderBy('version_number');
    }

    public function manuscriptValidations()
    {
        return $this->hasMany(ManuscriptValidation::class)->latest();
    }

    public function manuscriptAccessLogs()
    {
        return $this->hasMany(ManuscriptAccessLog::class);
    }

    public function currentVersion()
    {
        return $this->belongsTo(ManuscriptVersion::class, 'current_version_id');
    }

    // ── Editorial workflow (Module 3) ──────────────────────────────────

    public function revisionRounds()
    {
        return $this->hasMany(RevisionRound::class);
    }

    public function currentRevisionRound()
    {
        return $this->revisionRounds()
            ->where('round_number', (int) ($this->revision_round ?: 0))
            ->first()
            // The counter can lag behind (it is bookkeeping); an open round is
            // still an open revision request and the author must be able to
            // answer it.
            ?? $this->revisionRounds()
                ->where('status', RevisionRound::STATUS_OPEN)
                ->latest('id')
                ->first();
    }

    public function reviewerInvitations()
    {
        return $this->hasMany(ReviewerInvitation::class);
    }

    public function workflowEvents()
    {
        return $this->hasMany(ManuscriptWorkflowEvent::class)->latest();
    }

    public function isRevisionWindowOpen(): bool
    {
        if ($this->status !== 'revision_requested') {
            return false;
        }

        return $this->decision_due_at === null || $this->decision_due_at->isFuture();
    }

    public function isOverdue(): bool
    {
        return $this->decision_due_at !== null
            && $this->decision_due_at->isPast()
            && ! in_array($this->status, ['published', 'rejected'], true);
    }

    public function currentStageName(): string
    {
        if (blank($this->current_stage)) {
            return 'Not yet staged';
        }

        return $this->currentStage?->name ?? $this->current_stage;
    }

    public function currentStage()
    {
        return EditorialStage::where('slug', $this->current_stage)->first();
    }

    /** @return array<int, ReviewerAssignment> */
    public function submittedReviews(): array
    {
        return Review::whereHas('assignment', function ($query) {
            $query->where('submission_id', $this->getKey());
        })
            ->where('status', 'submitted')
            ->with(['assignment.reviewer'])
            ->latest('submitted_at')
            ->get()
            ->all();
    }

    public function hasPaidPurpose(string $purpose): bool
    {
        $loadedPayments = $this->relationLoaded('payments') ? $this->payments : null;

        if ($loadedPayments) {
            return $loadedPayments
                ->where('purpose', $purpose)
                ->where('status', 'paid')
                ->isNotEmpty();
        }

        return $this->payments()
            ->where('purpose', $purpose)
            ->where('status', 'paid')
            ->exists();
    }

    public function hasPaidVettingFee(): bool
    {
        if (str_starts_with($this->submission_no, 'PUB-')) {
            return true;
        }
        return $this->hasPaidPurpose('vetting_fee');
    }

    public function hasPaidPublicationFee(): bool
    {
        if (str_starts_with($this->submission_no, 'PUB-')) {
            return true;
        }
        return $this->hasPaidPurpose('publication_fee');
    }

    public function canBeViewedBy(User $user): bool
    {
        if ($this->isOwnedBy($user)) {
            return true;
        }

        if ($user->hasAnyRole(['Admin', 'Managing Editor', 'Managing Admin', 'Editor', 'Super Admin'])) {
            return true;
        }

        // A reviewer may see a submission only while they hold an assignment on
        // it. Blanket reviewer access was removed in Module 1 (audit finding B5).
        if ($this->hasReviewerAssignmentBy($user)) {
            return true;
        }

        return $this->isLinkedToUserEmail($user->email);
    }

    public function hasReviewerAssignmentBy(User $user): bool
    {
        return ReviewerAssignment::query()
            ->where('submission_id', $this->getKey())
            ->where('reviewer_id', $user->getKey())
            ->exists();
    }

    public function canInitiatePaymentBy(User $user): bool
    {
        return $this->isOwnedBy($user)
            || $this->isLinkedToUserEmail($user->email)
            || $this->hasPaymentFromUser($user);
    }

    public function isOwnedBy(User $user): bool
    {
        return (int) $this->author_id === (int) $user->getKey();
    }

    /**
     * True when the given account appears among the co-authors.
     *
     * Public because the manuscript policy uses it to decide whether a co-author
     * may read a submission's document workspace.
     */
    public function isLinkedToUserEmail(?string $email): bool
    {
        $normalizedEmail = strtolower(trim((string) $email));

        if ($normalizedEmail === '') {
            return false;
        }

        $loadedCoAuthors = $this->relationLoaded('coAuthors') ? $this->coAuthors : null;

        if ($loadedCoAuthors) {
            return $loadedCoAuthors->contains(function ($coAuthor) use ($normalizedEmail) {
                return strtolower((string) $coAuthor->email) === $normalizedEmail;
            });
        }

        return $this->coAuthors()
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->exists();
    }

    private function hasPaymentFromUser(User $user): bool
    {
        $loadedPayments = $this->relationLoaded('payments') ? $this->payments : null;

        if ($loadedPayments) {
            return $loadedPayments->contains(function ($payment) use ($user) {
                return (int) $payment->user_id === (int) $user->getKey();
            });
        }

        return $this->payments()
            ->where('user_id', $user->getKey())
            ->exists();
    }

}
