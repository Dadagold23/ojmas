<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configurable editorial lifecycle.
 *
 * The stage list is data, not code, so the journal can rename or reorder a
 * stage without a deployment. `submissions.status` (the existing 9-value ENUM)
 * is deliberately untouched; `submissions.current_stage` carries these slugs
 * and the workflow service keeps the two in sync for backward compatibility.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('editorial_stages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_initial')->default(false);
            $table->boolean('is_terminal')->default(false);
            $table->boolean('requires_payment')->default(false);
            $table->boolean('requires_author_approval')->default(false);
            $table->boolean('blinded_at_this_stage')->default(false);
            $table->unsignedSmallInteger('default_deadline_days')->nullable();
            $table->json('required_permissions')->nullable();
            $table->string('legacy_status', 40)->nullable();
            $table->string('colour_hint', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order'], 'editorial_stages_active_order_index');
        });

        Schema::create('editorial_stage_transitions', function (Blueprint $table) {
            $table->id();
            $table->string('from_stage_slug', 80);
            $table->string('to_stage_slug', 80);
            $table->string('label');
            $table->json('required_permissions')->nullable();
            $table->boolean('requires_deadline')->default(false);
            $table->boolean('requires_note')->default(false);
            $table->boolean('notifies_author')->default(false);
            $table->boolean('opens_revision_window')->default(false);
            $table->boolean('closes_revision_window')->default(false);
            $table->timestamps();

            $table->unique(
                ['from_stage_slug', 'to_stage_slug'],
                'editorial_stage_transitions_from_to_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_stage_transitions');
        Schema::dropIfExists('editorial_stages');
    }
};