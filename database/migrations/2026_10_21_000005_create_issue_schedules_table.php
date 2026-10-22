<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Addendum Module 5 — Automated Issue and Announcement Scheduling.
 *
 * One new table only; nothing existing is altered. A schedule rows plan a
 * future volume/issue announcement: the dates the journal intends, the
 * optional banner, and the optional push announcement. Scheduling an
 * announcement NEVER publishes an issue or its articles — the linked issue
 * stays in its editors' hands; only the announcement surfaces (an ad
 * campaign window and/or a scheduled push notification) are prepared.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('issue_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('volume_id')->nullable()->constrained('volumes')->nullOnDelete();
            // The issue under announcement. One schedule per issue; the
            // slug-free link is what stops the system ever duplicating one.
            $table->foreignId('issue_id')->nullable()->constrained('issues')->nullOnDelete();
            $table->string('title', 200);
            $table->string('message', 300)->nullable();
            // issue_announcement | call_for_papers
            $table->string('announcement_type', 40)->default('issue_announcement');
            // draft | scheduled | paused | cancelled
            $table->string('status', 20)->default('draft');
            $table->date('planned_publication_date')->nullable();
            $table->date('cfp_opens_at')->nullable();
            $table->date('submission_deadline')->nullable();
            $table->dateTime('announce_at')->nullable();
            $table->dateTime('announcement_ends_at')->nullable();
            $table->string('landing_url', 500)->nullable();
            $table->string('banner_path')->nullable();
            $table->boolean('notify_push')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('linked_campaign_id')->nullable()->constrained('ad_campaigns')->nullOnDelete();
            $table->foreignId('linked_notification_id')->nullable()->constrained('push_notifications')->nullOnDelete();
            $table->timestamps();

            // Announcements cannot be double-scheduled for one issue.
            $table->unique('issue_id');
            $table->index(['status', 'announce_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_schedules');
    }
};
