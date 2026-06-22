<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->foreignId('assigned_editor_id')
                  ->nullable()
                  ->after('is_corresponding_author')
                  ->constrained('users')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\User::class, 'assigned_editor_id');
            $table->dropColumn('assigned_editor_id');
        });
    }
};
