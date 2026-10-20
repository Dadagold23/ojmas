<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('manuscript_validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manuscript_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->enum('profile', ['apa7_professional', 'ojmas'])->default('ojmas');
            $table->string('profile_version', 40);
            $table->enum('status', ['queued', 'running', 'completed', 'failed'])->default('queued');
            $table->unsignedInteger('auto_fixed_count')->default(0);
            $table->unsignedInteger('author_action_count')->default(0);
            $table->unsignedInteger('unverifiable_count')->default(0);
            $table->unsignedInteger('unsupported_count')->default(0);
            $table->unsignedInteger('warning_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->longText('report_json')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['submission_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manuscript_validations');
    }
};