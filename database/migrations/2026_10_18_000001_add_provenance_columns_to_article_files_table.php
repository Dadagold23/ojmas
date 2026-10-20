<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds provenance and integrity columns to the existing article_files table.
 *
 * Every column is nullable or has a default so existing rows are untouched.
 * Fully reversible.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('article_files', function (Blueprint $table) {
            if (!Schema::hasColumn('article_files', 'sha256')) {
                $table->char('sha256', 64)->nullable()->after('file_size');
            }
            if (!Schema::hasColumn('article_files', 'is_anonymised')) {
                $table->boolean('is_anonymised')->default(false)->after('sha256');
            }
            if (!Schema::hasColumn('article_files', 'version_label')) {
                $table->string('version_label', 120)->nullable()->after('is_anonymised');
            }
            if (!Schema::hasColumn('article_files', 'uploaded_by')) {
                $table->foreignId('uploaded_by')->nullable()->after('version_label')
                    ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        $columns = array_values(array_filter(
            ['uploaded_by', 'version_label', 'is_anonymised', 'sha256'],
            fn (string $column) => Schema::hasColumn('article_files', $column)
        ));

        if ($columns !== []) {
            Schema::table('article_files', function (Blueprint $table) use ($columns) {
                if (in_array('uploaded_by', $columns, true)) {
                    $table->dropForeign(['uploaded_by']);
                }
                $table->dropColumn($columns);
            });
        }
    }
};