<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A single-use invitation that lets a co-author authenticate their own ORCID
 * without using the submitting author's session.
 */
class OrcidCoauthorClaim extends Model
{
    use HasFactory;

    public const LIFETIME_DAYS = 14;

    protected $fillable = [
        'article_author_id', 'token_hash', 'token', 'email', 'expires_at',
        'completed_at', 'created_by',
    ];

    protected $hidden = [
        'token', 'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function articleAuthor(): BelongsTo
    {
        return $this->belongsTo(ArticleAuthor::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function issueFor(ArticleAuthor $author, ?User $issuer = null): self
    {
        $token = Str::random(64);

        return self::create([
            'article_author_id' => $author->getKey(),
            'token_hash' => self::hashToken($token),
            'token' => $token,
            'email' => $author->email,
            'expires_at' => now()->addDays(self::LIFETIME_DAYS),
            'created_by' => $issuer?->getKey(),
        ]);
    }

    public static function findValidByToken(string $token): ?self
    {
        if ($token === '') {
            return null;
        }

        return self::query()
            ->where('token_hash', self::hashToken($token))
            ->whereNull('completed_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    public function isUsable(): bool
    {
        return $this->completed_at === null && $this->expires_at->isFuture();
    }
}
