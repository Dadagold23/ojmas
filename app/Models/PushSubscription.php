<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushSubscription extends Model
{
    /** Consent flags only; promotions are never default-on. */
    protected $fillable = [
        'user_id', 'endpoint_hash', 'endpoint', 'p256dh', 'auth',
        'wants_editorial', 'wants_promotions', 'is_active', 'failure_count',
        'last_error', 'last_success_at', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
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
