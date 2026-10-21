<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Addendum Module 2 — Advertisements & Announcements.
 *
 * Three new tables only; nothing existing is altered. The placement registry
 * is seeded here because the public templates reference these keys and the
 * system must be complete on deploy. Rows are never deleted: campaigns are
 * archived, placements deactivated.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('ad_placements', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('label', 120);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('ad_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('placement_id')->constrained('ad_placements')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            // image | video | audio | banner | call_for_papers |
            // issue_announcement | publication_date | event | sponsored | news
            $table->string('type', 40)->default('banner');
            $table->string('advertiser_name', 160)->nullable();
            $table->string('media_path')->nullable();
            $table->string('media_type', 20)->nullable(); // image | video | audio
            $table->string('external_media_url', 500)->nullable();
            $table->string('destination_url', 500)->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            // draft | pending | scheduled | published | paused | expired | archived
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('priority')->default(0);
            $table->string('audience', 40)->default('public');
            $table->boolean('notify_push')->default(false);
            $table->string('label', 40)->default('Advertisement');
            $table->string('sponsor_fee_note', 255)->nullable();
            $table->text('internal_notes')->nullable();
            $table->unsignedBigInteger('push_campaign_id')->nullable();
            $table->timestamps();

            $table->index(['status', 'placement_id']);
            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create('ad_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('ad_campaigns')->cascadeOnDelete();
            // impression | click
            $table->string('event', 20);
            $table->string('fingerprint', 64);
            $table->date('occurred_on');
            $table->dateTime('occurred_at');
            $table->timestamps();

            // One impression/click per visitor fingerprint per campaign per day:
            // honest counting without surveillance-grade tracking.
            $table->unique(['campaign_id', 'event', 'fingerprint', 'occurred_on'], 'ad_events_dedupe');
        });

        // The placements the public templates reference. Deactivating a
        // placement hides its slot everywhere; nothing is ever deleted.
        $now = now();
        $placements = [
            ['homepage_banner', 'Homepage banner', 'Wide slot on the public homepage.', 10],
            ['archive_banner', 'Archive page banner', 'Slot above the public archive listing.', 20],
            ['issue_promo', 'Issue page promotional area', 'Slot on public issue pages.', 30],
            ['article_banner', 'Article page banner', 'Slot on public article pages.', 40],
            ['announcement_strip', 'Announcement strip', 'Sitewide slim strip for official announcements.', 50],
            ['calls_for_papers', 'Calls for Papers page', 'Slot on the dedicated Calls for Papers page.', 60],
            ['sponsored_section', 'Sponsored-content section', 'Sponsored content section.', 70],
        ];

        foreach ($placements as [$key, $label, $description, $sort]) {
            Schema::getConnection()->table('ad_placements')->insert([
                'key' => $key,
                'label' => $label,
                'description' => $description,
                'sort' => $sort,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_events');
        Schema::dropIfExists('ad_campaigns');
        Schema::dropIfExists('ad_placements');
    }
};
