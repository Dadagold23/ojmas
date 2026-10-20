<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reviewer invitations and reminders.
 *
 * §3.4 requires the Managing Editor to be able to track reviewer invitations
 * and responses. `reviewer_assignments` records who is reviewing; this table
 * records how they were asked, when, and whether they responded.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('reviewer_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('round_id')->nullable()->constrained('revision_rounds')->nullOnDelete();

            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('due_at')->nullable();

            $table->enum('response', ['pending', 'accepted', 'declined'])->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->text('response_note')->nullable();

            $table->unsignedTinyInteger('reminder_count')->default(0);
            $table->timestamp('last_reminded_at')->nullable();
            $table->timestamp('expired_at')->nullable();

            $table->timestamps();

            $table->index(
                ['submission_id', 'reviewer_id', 'round_id'],
                'reviewer_invitations_lookup_index',
            );
            $table->index(['response', 'due_at'], 'reviewer_invitations_response_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviewer_invitations');
    }
};