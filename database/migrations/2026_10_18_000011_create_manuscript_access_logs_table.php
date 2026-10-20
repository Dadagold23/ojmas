<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable record of every sensitive document access.
 *
 * The `audit_logs` table already exists in this application but nothing in the
 * codebase ever writes to it, so document access is currently untraceable.
 * This table closes that gap.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('manuscript_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submission_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('manuscript_document_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('action', [
                'view',
                'download',
                'edit',
                'export',
                'print',
                'anonymised_view',
            ]);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->index(['submission_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manuscript_access_logs');
    }
};