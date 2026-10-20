<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The workflow activity log and the retention policy register.
 *
 * `submission_status_histories` already exists and records status changes, but
 * it does not record *which stage* was entered, by which transition, or why.
 * This log is append-only and never editable through the interface.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('manuscript_workflow_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->string('from_stage', 80)->nullable();
            $table->string('to_stage', 80)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('event', 80);
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['submission_id', 'created_at'], 'manuscript_workflow_events_submission_index');
            $table->index(['event', 'created_at'], 'manuscript_workflow_events_event_index');
        });

        Schema::create('document_retention_policies', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name');
            $table->text('description')->nullable();

            $table->unsignedInteger('retain_original_days')->nullable();
            $table->unsignedInteger('retain_formatted_days')->nullable();
            $table->unsignedInteger('retain_workspace_days')->nullable();
            $table->unsignedInteger('retain_review_days')->nullable();
            $table->unsignedInteger('retain_audit_days')->default(2555); // ~7 years

            $table->boolean('delete_formatted_on_publication')->default(false);
            $table->boolean('anonymise_after_review')->default(true);
            $table->boolean('is_active')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('is_active', 'document_retention_policies_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_retention_policies');
        Schema::dropIfExists('manuscript_workflow_events');
    }
};