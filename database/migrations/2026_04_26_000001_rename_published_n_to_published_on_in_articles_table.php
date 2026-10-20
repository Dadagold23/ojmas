<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('articles', 'published_n') && !Schema::hasColumn('articles', 'published_on')) {
            Schema::table('articles', function (Blueprint $table) {
                $table->renameColumn('published_n', 'published_on');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('articles', 'published_on') && !Schema::hasColumn('articles', 'published_n')) {
            Schema::table('articles', function (Blueprint $table) {
                $table->renameColumn('published_on', 'published_n');
            });
        }
    }
};
