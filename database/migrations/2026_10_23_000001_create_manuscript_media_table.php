<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corrective Module 3 — manuscript media extraction.
 *
 * Every embedded image of an uploaded manuscript is copied once into the
 * private storage area and recorded here, so the workspace editor can show it
 * and the DOCX writers can embed genuine WordprocessingML drawings again.
 *
 * The migration is strictly additive: one new table, nothing altered,
 * nothing dropped. The original uploaded package is never modified — media
 * are COPIES. Reruns are safe.
 *
 * Rollback: php artisan migrate:rollback removes only this table and touches
 * no other schema. Take a database backup (mysqldump) before deploying; the
 * extracted media files remain on disk under manuscripts/media/ until an
 * operator deletes them deliberately.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('manuscript_media', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('submission_id')->index();
            $table->unsignedBigInteger('manuscript_version_id')->index();
            $table->string('part_name', 255); // the OOXML part it came from, e.g. word/media/image1.png
            $table->string('storage_path', 500); // private-disk relative path of the extracted copy
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->string('sha256', 64);
            $table->unsignedSmallInteger('ordinal');
            $table->unsignedInteger('width_px')->nullable();
            $table->unsignedInteger('height_px')->nullable();
            $table->string('description', 500)->nullable();
            $table->timestamps();

            // Extraction is idempotent: an identical part of the same version
            // is one row, never duplicated by repeated saves or re-opens.
            $table->unique(['manuscript_version_id', 'part_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manuscript_media');
    }
};
