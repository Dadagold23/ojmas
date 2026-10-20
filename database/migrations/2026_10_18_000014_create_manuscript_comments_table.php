<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contextual comments anchored to a range of the working document.
 *
 * The anchor is a ProseMirror position pair. It degrades gracefully: if the
 * anchored text is later deleted, `anchor_valid` becomes false and the thread
 * is kept rather than discarded, so an editorial conversation is never lost
 * because of an edit.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('manuscript_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manuscript_workspace_document_id')->constrained('manuscript_workspace_documents')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('manuscript_comments')->cascadeOnDelete();
            $table->foreignId('manuscript_document_id')->nullable()->constrained()->cascadeOnDelete();

            $table->unsignedInteger('anchor_from')->nullable();
            $table->unsignedInteger('anchor_to')->nullable();
            $table->text('quoted_text')->nullable();
            $table->text('anchor_signature')->nullable();
            $table->boolean('anchor_valid')->default(true);
            $table->unsignedInteger('revision_at_creation')->nullable();

            $table->longText('body');
            $table->enum('visibility', [
                'author_visible',
                'editorial',
                'reviewer_confidential',
            ])->default('editorial');
            $table->enum('status', ['open', 'resolved', 'reopened'])->default('open');

            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(
                ['manuscript_workspace_document_id', 'status'],
                'manuscript_comments_document_status_index',
            );
            $table->index('parent_id', 'manuscript_comments_parent_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manuscript_comments');
    }
};