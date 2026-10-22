<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushDelivery extends Model
{
    public const PENDING = 'pending';

    public const SENT = 'sent';

    public const FAILED = 'failed';

    protected $fillable = [
        'notification_id', 'subscription_id', 'status', 'attempts',
        'last_error', 'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'delivered_at' => 'datetime',
        ];
    }

    public function notification()
    {
        return $this->belongsTo(\App\Models\PushNotification::class, 'notification_id');
    }

    public function subscription()
    {
        return $this->belongsTo(\App\Models\PushSubscription::class, 'subscription_id');
    }
}
