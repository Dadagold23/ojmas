<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('issues', 'pdf_path')) {
            Schema::table('issues', function (Blueprint $table) {
                $table->string('pdf_path')->nullable()->after('description');
            });
        }

        $missing = array_values(array_filter(
            ['type', 'recipient_email', 'status'],
            fn (string $column) => !Schema::hasColumn('correspondence_messages', $column)
        ));

        if ($missing !== []) {
            Schema::table('correspondence_messages', function (Blueprint $table) use ($missing) {
                foreach ($missing as $column) {
                    $table->string($column)->nullable()->after('message');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('issues', 'pdf_path')) {
            Schema::table('issues', function (Blueprint $table) {
                $table->dropColumn('pdf_path');
            });
        }

        $present = array_values(array_filter(
            ['type', 'recipient_email', 'status'],
            fn (string $column) => Schema::hasColumn('correspondence_messages', $column)
        ));

        if ($present !== []) {
            Schema::table('correspondence_messages', function (Blueprint $table) use ($present) {
                $table->dropColumn($present);
            });
        }
    }
};