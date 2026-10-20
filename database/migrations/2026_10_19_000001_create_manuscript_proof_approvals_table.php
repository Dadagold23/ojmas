<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The author's signed approval of their proofs.
 *
 * Publication must not follow merely from an editor finishing copyediting
 * (Module 4 brief is explicit). This table records the author's approval —
 * or requested corrections — which the publication readiness gate treats as
 * one of the required editorial approvals.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('manuscript_proof_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('revision_round_id')->nullable()
                ->constrained('revision_rounds')->nullOnDelete();

            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();

            // approved | corrections_requested
            $table->enum('status', ['approved', 'corrections_requested'])->default('approved');
            $table->text('notes')->nullable();

            $table->timestamp('approved_at')->nullable();
            $table->string('ip_address', 45)->nullable();

            $table->timestamps();

            $table->index(
                ['submission_id', 'status'],
                'manuscript_proof_approvals_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manuscript_proof_approvals');
    }
};
