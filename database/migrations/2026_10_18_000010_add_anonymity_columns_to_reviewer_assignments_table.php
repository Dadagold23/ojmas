<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reviewer_assignments', function (Blueprint $table) {
            if (!Schema::hasColumn('reviewer_assignments', 'blind_code')) {
                $table->string('blind_code', 32)->nullable()->after('status');
            }
            if (!Schema::hasColumn('reviewer_assignments', 'round_id')) {
                $table->foreignId('round_id')->nullable()->after('blind_code')
                    ->constrained('revision_rounds')->nullOnDelete();
            }
            if (!Schema::hasColumn('reviewer_assignments', 'invited_at')) {
                $table->timestamp('invited_at')->nullable()->after('round_id');
            }
            if (!Schema::hasColumn('reviewer_assignments', 'responded_at')) {
                $table->timestamp('responded_at')->nullable()->after('invited_at');
            }
        });
    }

    public function down(): void
    {
        $columns = array_values(array_filter(
            ['responded_at', 'invited_at', 'round_id', 'blind_code'],
            fn (string $column) => Schema::hasColumn('reviewer_assignments', $column)
        ));

        if ($columns !== []) {
            Schema::table('reviewer_assignments', function (Blueprint $table) use ($columns) {
                if (in_array('round_id', $columns, true)) {
                    $table->dropForeign(['round_id']);
                }
                $table->dropColumn($columns);
            });
        }
    }
};