<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Point-in-time snapshots of the browser-editable document.
 *
 * A snapshot is taken on every meaningful change: the first save after an
 * import, the first save after a long idle period, a restore, and an explicit
 * "save version" by the user. This is what makes version comparison and
 * restoration possible without storing every keystroke.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('manuscript_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manuscript_workspace_document_id')->constrained('manuscript_workspace_documents')->cascadeOnDelete();
            $table->unsignedBigInteger('revision');
            $table->string('label', 190)->nullable();
            $table->longText('content_json');
            $table->longText('plain_text')->nullable();
            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedInteger('block_count')->default(0);
            $table->char('sha256', 64);
            $table->enum('created_from', [
                'import',
                'autosave',
                'manual',
                'restore',
                'accepted_changes',
            ])->default('autosave');
            $table->foreignId('restored_from_id')->nullable()->constrained('manuscript_snapshots')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['manuscript_workspace_document_id', 'revision'],
                'manuscript_snapshots_document_revision_unique',
            );
            $table->index('created_at', 'manuscript_snapshots_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manuscript_snapshots');
    }
};