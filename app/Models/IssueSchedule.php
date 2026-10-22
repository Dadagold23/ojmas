<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A planned issue/volume announcement (Addendum Module 5).
 *
 * A schedule is a plan, not a publisher: preparing or executing an
 * announcement must never set an issue's or an article's status. The status
 * machine below concerns only the schedule's own announcement surfaces.
 */
class IssueSchedule extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_SCHEDULED => 'Scheduled',
        self::STATUS_PAUSED => 'Paused',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    public const ANNOUNCEMENT_TYPES = [
        'issue_announcement' => 'Upcoming volume/issue announcement',
        'call_for_papers' => 'Call for Papers',
    ];

    /** The push category each announcement type reports to Module 3. */
    public const PUSH_CATEGORY_BY_TYPE = [
        'issue_announcement' => 'upcoming_issue',
        'call_for_papers' => 'call_for_papers',
    ];

    protected $fillable = [
        'volume_id', 'issue_id', 'title', 'message', 'announcement_type',
        'status', 'planned_publication_date', 'cfp_opens_at',
        'submission_deadline', 'announce_at', 'announcement_ends_at',
        'landing_url', 'banner_path', 'notify_push', 'created_by',
        'linked_campaign_id', 'linked_notification_id',
    ];

    protected function casts(): array
    {
        return [
            'planned_publication_date' => 'date',
            'cfp_opens_at' => 'date',
            'submission_deadline' => 'date',
            'announce_at' => 'datetime',
            'announcement_ends_at' => 'datetime',
            'notify_push' => 'boolean',
        ];
    }

    public function volume()
    {
        return $this->belongsTo(\App\Models\Volume::class, 'volume_id');
    }

    public function issue()
    {
        return $this->belongsTo(\App\Models\Issue::class, 'issue_id');
    }

    public function campaign()
    {
        return $this->belongsTo(\App\Models\AdCampaign::class, 'linked_campaign_id');
    }

    public function notification()
    {
        return $this->belongsTo(\App\Models\PushNotification::class, 'linked_notification_id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function pushCategory(): string
    {
        return self::PUSH_CATEGORY_BY_TYPE[$this->announcement_type] ?? 'journal_notice';
    }
}
