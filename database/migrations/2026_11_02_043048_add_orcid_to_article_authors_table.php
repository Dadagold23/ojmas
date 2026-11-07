<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('article_authors', 'orcid')) {
            return;
        }

        Schema::table('article_authors', function (Blueprint $table) {
            $table->string('orcid')->nullable()->after('country');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('article_authors', 'orcid')) {
            return;
        }

        Schema::table('article_authors', function (Blueprint $table) {
            $table->dropColumn('orcid');
        });
    }
};
