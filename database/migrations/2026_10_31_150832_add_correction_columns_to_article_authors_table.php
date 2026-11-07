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
        Schema::table('article_authors', function (Blueprint $table) {
            $table->foreignId('edited_by')->nullable()->after('country');
            $table->timestamp('edited_at')->nullable()->after('edited_by');
            $table->text('correction_reason')->nullable()->after('edited_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('article_authors', function (Blueprint $table) {
            $table->dropColumn(['edited_by', 'edited_at', 'correction_reason']);
        });
    }
};
