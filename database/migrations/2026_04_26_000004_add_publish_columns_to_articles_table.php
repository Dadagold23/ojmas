<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('articles', 'author_id')) {
            Schema::table('articles', function (Blueprint $table) {
                $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete()->after('submission_id');
            });
        }

        if (!Schema::hasColumn('articles', 'subject_area')) {
            Schema::table('articles', function (Blueprint $table) {
                $table->string('subject_area')->nullable()->after('keywords');
            });
        }

        if (!Schema::hasColumn('articles', 'full_text')) {
            Schema::table('articles', function (Blueprint $table) {
                $table->longText('full_text')->nullable()->after('subject_area');
            });
        }
    }

    public function down(): void
    {
        $drop = array_values(array_filter(
            ['author_id', 'subject_area', 'full_text'],
            fn (string $column) => Schema::hasColumn('articles', $column)
        ));

        if ($drop !== []) {
            Schema::table('articles', function (Blueprint $table) use ($drop) {
                if (in_array('author_id', $drop, true)) {
                    $table->dropForeign(['author_id']);
                }
                $table->dropColumn($drop);
            });
        }
    }
};