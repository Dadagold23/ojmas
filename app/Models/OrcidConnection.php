<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One verified ORCID identity per OJMAS account.
 *
 * Tokens are encrypted at rest through the encrypted casts and are never
 * rendered, logged or placed in audit records.
 */
class OrcidConnection extends Model
{
    use HasFactory;

    public const STATUS_CONNECTED = 'connected';
    public const STATUS_DISCONNECTED = 'disconnected';

    protected $fillable = [
        'user_id', 'orcid_id', 'orcid_name', 'access_token', 'refresh_token',
        'token_expires_at', 'scope', 'status', 'authenticated_at', 'revoked_at',
    ];

    protected $hidden = [
        'access_token', 'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'authenticated_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED
            && $this->orcid_id !== null
            && $this->authenticated_at !== null;
    }
}
