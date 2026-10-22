<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushNotification extends Model
{
    public const STATUSES = [
        'draft' => 'Draft',
        'scheduled' => 'Scheduled',
        'sending' => 'Sending',
        'sent' => 'Sent',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
    ];

    public const CATEGORIES = [
        'new_volume' => 'New volume published',
        'new_issue' => 'New issue published',
        'new_article' => 'New article published',
        'upcoming_issue' => 'Upcoming issue announcement',
        'call_for_papers' => 'Call for Papers',
        'deadline_reminder' => 'Submission deadline reminder',
        'conference_announcement' => 'Conference or workshop announcement',
        'sponsored_announcement' => 'Sponsored announcement',
        'advertisement_campaign' => 'Advertisement campaign',
        'journal_notice' => 'Important journal notice',
    ];

    public const AUDIENCES = [
        'all' => 'All consenting subscribers',
        'editorial' => 'Editorial updates consent',
        'promotions' => 'Promotions and advertising consent',
        'authors' => 'Authenticated: authors',
        'reviewers' => 'Authenticated: reviewers',
        'editors' => 'Authenticated: editorial staff',
    ];

    protected $fillable = [
        'created_by', 'title', 'message', 'category', 'icon_path',
        'action_url', 'audience', 'status', 'scheduled_at', 'expires_at',
        'sent_at', 'ad_campaign_id', 'dispatch_attempts', 'last_dispatch_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
            'last_dispatch_at' => 'datetime',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function campaign()
    {
        return $this->belongsTo(\App\Models\AdCampaign::class, 'ad_campaign_id');
    }

    public function deliveries()
    {
        return $this->hasMany(\App\Models\PushDelivery::class, 'notification_id');
    }
}
