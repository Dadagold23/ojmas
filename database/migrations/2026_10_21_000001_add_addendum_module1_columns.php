<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Addendum Module 1 — Enhanced journal archive, issue ToC and attribution.
 *
 * Every change is additive: new nullable columns only. No existing column is
 * renamed, re-typed or dropped, and no enum value list is modified.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            // The journal's approved editorial sequence inside an issue; null
            // means "no explicit order has been established yet" and the issue
            // falls back to a deterministic published-then-id order.
            $table->unsignedInteger('issue_order')->nullable()->after('pages');
            $table->string('licence', 100)->nullable()->after('pages');
            $table->string('copyright_note', 255)->nullable();

            $table->index(['issue_id', 'issue_order']);
        });

        Schema::table('article_authors', function (Blueprint $table) {
            $table->string('orcid', 39)->nullable()->after('institution');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('orcid', 39)->nullable();
        });

        Schema::table('issues', function (Blueprint $table) {
            $table->string('cover_image_path')->nullable()->after('description');
        });

        Schema::table('volumes', function (Blueprint $table) {
            $table->string('cover_image_path')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex(['issue_id', 'issue_order']);
            $table->dropColumn(['issue_order', 'licence', 'copyright_note']);
        });

        Schema::table('article_authors', function (Blueprint $table) {
            $table->dropColumn('orcid');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('orcid');
        });

        Schema::table('issues', function (Blueprint $table) {
            $table->dropColumn('cover_image_path');
        });

        Schema::table('volumes', function (Blueprint $table) {
            $table->dropColumn('cover_image_path');
        });
    }
};
