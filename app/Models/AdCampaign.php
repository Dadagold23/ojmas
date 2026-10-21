<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdCampaign extends Model
{
    public const STATUSES = [
        'draft' => 'Draft',
        'pending' => 'Pending approval',
        'scheduled' => 'Scheduled',
        'published' => 'Published',
        'paused' => 'Paused',
        'expired' => 'Expired',
        'archived' => 'Archived',
    ];

    public const TYPES = [
        'image' => 'Image advertisement',
        'video' => 'Video advertisement',
        'audio' => 'Audio / jingle promotion',
        'banner' => 'Banner advertisement',
        'call_for_papers' => 'Call for Papers',
        'issue_announcement' => 'Upcoming volume/issue announcement',
        'publication_date' => 'Publication-date announcement',
        'event' => 'Conference, workshop or academic event',
        'sponsored' => 'Sponsored content announcement',
        'news' => 'General journal news',
    ];

    public const AUDIENCES = [
        'public' => 'Everyone',
        'authors' => 'Authors',
        'reviewers' => 'Reviewers',
        'editors' => 'Editorial staff',
    ];

    protected $fillable = [
        'placement_id', 'created_by', 'title', 'description', 'type',
        'advertiser_name', 'media_path', 'media_type', 'external_media_url',
        'destination_url', 'starts_at', 'ends_at', 'status', 'priority',
        'audience', 'notify_push', 'label', 'sponsor_fee_note',
        'internal_notes', 'push_campaign_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'notify_push' => 'boolean',
        ];
    }

    public function placement()
    {
        return $this->belongsTo(\App\Models\AdPlacement::class, 'placement_id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function events()
    {
        return $this->hasMany(\App\Models\AdEvent::class, 'campaign_id');
    }

    /**
     * Whether the campaign's publication window is open right now. Expiry is
     * window-based at serve time: an expired campaign stops being shown
     * without any manual step or scheduler dependency.
     */
    public function windowIsOpen(): bool
    {
        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at !== null && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Whether the campaign may appear on the public site: an approved status
     * plus an open window. `scheduled` campaigns are already approved — the
     * window decides when they appear, so scheduling never depends on an
     * unverified cron worker.
     */
    public function isPubliclyVisible(): bool
    {
        if (! in_array($this->status, ['published', 'scheduled'], true)) {
            return false;
        }

        return $this->windowIsOpen();
    }
}
