<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArticleAuthor extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_id','user_id','full_name','email','institution',
        'orcid','orcid_verified_at','country','is_corresponding','author_order'
    ];

    protected function casts(): array
    {
        return [
            'orcid_verified_at' => 'datetime',
            'is_corresponding' => 'boolean',
        ];
    }

    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orcidClaims()
    {
        return $this->hasMany(OrcidCoauthorClaim::class);
    }

    public function hasVerifiedOrcid(): bool
    {
        return $this->orcid_verified_at !== null && filled($this->orcid);
    }

    /** Latest usable claim link, if one is still outstanding. */
    public function activeOrcidClaim(): ?OrcidCoauthorClaim
    {
        return $this->orcidClaims()
            ->whereNull('completed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();
    }
}
