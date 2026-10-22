<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushSubscription extends Model
{
    /**
     * Consent flags only; promotions are never default-on.
     */
    protected $fillable = [
        'user_id', 'endpoint_hash', 'endpoint', 'p256dh', 'auth',
        'wants_editorial', 'wants_promotions', 'is_active', 'failure_count',
        'last_error', 'last_success_at', 'user_agent',
    ];

    /**
     * The endpoint URL and the encryption keys are secrets belonging to one
     * browser and one journal: they are encrypted at rest (attribute
     * encryption, app-key) and are never serialised into arrays or JSON.
     */
    protected $hidden = [
        'endpoint', 'p256dh', 'auth',
    ];

    protected function casts(): array
    {
        return [
            'endpoint' => 'encrypted',
            'p256dh' => 'encrypted',
            'auth' => 'encrypted',
            'is_active' => 'boolean',
            'wants_editorial' => 'boolean',
            'wants_promotions' => 'boolean',
            'last_success_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function deliveries()
    {
        return $this->hasMany(\App\Models\PushDelivery::class, 'subscription_id');
    }

    /** A subscription valid for delivering right now. */
    public function isDeliverable(): bool
    {
        return $this->is_active
            && ! blank($this->endpoint)
            && ! blank($this->p256dh)
            && ! blank($this->auth);
    }
}
