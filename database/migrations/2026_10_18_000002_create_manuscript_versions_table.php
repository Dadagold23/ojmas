<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('manuscript_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('label', 120)->nullable();
            $table->foreignId('source_file_id')->nullable()->constrained('article_files')->nullOnDelete();
            $table->string('storage_path', 255);
            $table->string('mime_type', 191)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->char('sha256', 64);
            $table->enum('format', ['original', 'formatted', 'editorial'])->default('original');
            $table->enum('origin', [
                'author_upload',
                'apa7_format',
                'editorial_edit',
                'admin_recovery',
            ])->default('author_upload');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['submission_id', 'version_number'], 'manuscript_versions_submission_version_unique');
            $table->index('sha256');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manuscript_versions');
    }
};