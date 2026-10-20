<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('volumes', 'status')) {
            Schema::table('volumes', function (Blueprint $table) {
                $table->enum('status', ['draft', 'published'])->default('draft')->after('description');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('volumes', 'status')) {
            Schema::table('volumes', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};