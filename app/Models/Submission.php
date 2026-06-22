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
    ];

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
        return $this->hasPaidPurpose('vetting_fee');
    }

    public function hasPaidPublicationFee(): bool
    {
        return $this->hasPaidPurpose('publication_fee');
    }

    public function canBeViewedBy(User $user): bool
    {
        return $this->isOwnedBy($user)
            || $user->hasAnyRole(['Admin', 'Editor', 'Managing Editor', 'Super Admin', 'Reviewer'])
            || $this->isLinkedToUserEmail($user->email)
            || $this->hasPaymentFromUser($user);
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

    private function isLinkedToUserEmail(?string $email): bool
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
