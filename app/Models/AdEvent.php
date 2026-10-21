<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdEvent extends Model
{
    public const IMPRESSION = 'impression';

    public const CLICK = 'click';

    protected $fillable = [
        'campaign_id', 'event', 'fingerprint', 'occurred_on', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            // Plain Y-m-d storage so the deduplication lookup matches the
            // stored value byte-for-byte on every driver.
            'occurred_on' => 'date:Y-m-d',
            'occurred_at' => 'datetime',
        ];
    }

    public function campaign()
    {
        return $this->belongsTo(\App\Models\AdCampaign::class, 'campaign_id');
    }
}
