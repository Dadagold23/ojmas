<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Addendum Module 3 — Web Push Notification System.
 *
 * New tables only; nothing existing is altered. Subscription rows are
 * kept/oauth; invalid ones are disabled rather than deleted. Category
 * rows follow the brief's §3.2 list and can be enabled/disabled by
 * administrators.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Indexed hash sidesteps every driver's endpoint length limits.
            $table->string('endpoint_hash', 64)->unique();
            $table->text('endpoint');            $table->string('p256dh');
            $table->string('auth');
            // Separate consent flags: editorial updates on by default only
            // when the visitor opts in; promotions are never default-on.
            $table->boolean('wants_editorial')->default(true);
            $table->boolean('wants_promotions')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('failure_count')->default(0);
            $table->text('last_error')->nullable();
            $table->dateTime('last_success_at')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id']);
        });

        Schema::create('push_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 120);
            $table->string('message', 300);
            $table->string('category', 40);
            $table->string('icon_path')->nullable();
            $table->string('action_url', 500)->nullable();
            // all | editorial | promotions | authors | reviewers | editors
            $table->string('audience', 40)->default('all');
            // draft | scheduled | sending | sent | failed | cancelled
            $table->string('status', 20)->default('draft');
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->foreignId('ad_campaign_id')->nullable()->constrained('ad_campaigns')->nullOnDelete();
            $table->unsignedInteger('dispatch_attempts')->default(0);
            $table->dateTime('last_dispatch_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
        });

        Schema::create('push_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('push_notifications')->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained('push_subscriptions')->cascadeOnDelete();
            // pending | sent | failed
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->timestamps();

            // Duplicate-send protection: exactly one delivery row per
            // subscription per notification, whichever way the job re-runs.
            $table->unique(['notification_id', 'subscription_id'], 'push_deliveries_dedupe');
        });

        Schema::create('push_category_settings', function (Blueprint $table) {
            $table->id();
            $table->string('category', 40)->unique();
            $table->string('label', 120);
            $table->boolean('is_eligible')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        $now = now();
        $categories = [
            ['new_volume', 'New volume published', 10],
            ['new_issue', 'New issue published', 20],
            ['new_article', 'New article published', 30],
            ['upcoming_issue', 'Upcoming issue announcement', 40],
            ['call_for_papers', 'Call for Papers', 50],
            ['deadline_reminder', 'Submission deadline reminder', 60],
            ['conference_announcement', 'Conference or workshop announcement', 70],
            ['sponsored_announcement', 'Sponsored announcement', 80],
            ['advertisement_campaign', 'Advertisement campaign', 90],
            ['journal_notice', 'Important journal notice', 100],
        ];

        foreach ($categories as [$category, $label, $sort]) {
            Schema::getConnection()->table('push_category_settings')->insert([
                'category' => $category,
                'label' => $label,
                'is_eligible' => true,
                'sort' => $sort,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('push_deliveries');
        Schema::dropIfExists('push_notifications');
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('push_category_settings');
    }
};
