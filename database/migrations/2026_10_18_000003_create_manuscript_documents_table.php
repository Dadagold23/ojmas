<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('manuscript_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manuscript_version_id')->constrained()->cascadeOnDelete();
            $table->enum('format', ['docx'])->default('docx');
            $table->string('storage_path', 255);
            $table->char('sha256', 64);
            $table->string('rule_set_version', 40)->nullable();
            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedInteger('paragraph_count')->default(0);
            $table->longText('structure_json')->nullable();
            $table->enum('status', ['queued', 'parsing', 'parsed', 'failed'])->default('queued');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manuscript_documents');
    }
};