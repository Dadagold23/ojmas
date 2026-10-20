<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The browser-editable working document for a manuscript.
 *
 * This is a SEPARATE record from manuscript_documents (which holds the parsed
 * OOXML tree) and from manuscript_versions (which holds immutable files). The
 * workspace document is what the browser editor reads and writes, and it
 * carries the optimistic-concurrency token.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('manuscript_workspace_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manuscript_document_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();

            // The Tiptap/ProseMirror document, stored as JSON.
            $table->longText('content_json')->nullable();

            // A server-rendered projection, kept so previews and plain-text
            // search never have to re-parse ProseMirror on read.
            $table->longText('plain_text')->nullable();

            // Optimistic concurrency token. Every accepted save increments it;
            // a save carrying a stale value is rejected as a conflict rather
            // than silently overwriting a newer edit.
            $table->unsignedBigInteger('revision')->default(1);

            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedInteger('block_count')->default(0);
            $table->timestamp('last_saved_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('submission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manuscript_workspace_documents');
    }
};