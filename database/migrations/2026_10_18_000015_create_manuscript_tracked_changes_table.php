<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracked insertions and deletions recorded in the browser workspace.
 *
 * The editor (Tiptap) does not provide native track changes, so this table is
 * the system of record: the workspace renders pending changes as Tiptap marks
 * and the review actions resolve them here. This is the tested alternative the
 * module brief requires when the chosen editor lacks a native implementation.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('manuscript_tracked_changes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('manuscript_workspace_document_id');
            $table->string('change_uid', 64);
            $table->enum('type', ['insertion', 'deletion']);
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();

            $table->text('text_snapshot')->nullable();
            $table->json('body_json')->nullable();
            $table->unsignedInteger('anchor_from')->nullable();
            $table->unsignedInteger('anchor_to')->nullable();

            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique('change_uid', 'manuscript_tracked_changes_uid_unique');
            $table->index(
                ['manuscript_workspace_document_id', 'status'],
                'manuscript_tracked_changes_doc_status_index',
            );

            // Named explicitly: Laravel's generated name for this column
            // exceeds MySQL's 64-character identifier limit.
            $table->foreign('manuscript_workspace_document_id', 'manuscript_workspace_documents', 'id')
                ->cascadeOnDelete()
                ->name('manuscript_tracked_changes_doc_fk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manuscript_tracked_changes');
    }
};