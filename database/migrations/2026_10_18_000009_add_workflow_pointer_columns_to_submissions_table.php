<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive workflow pointers on submissions.
 *
 * The existing `status` ENUM is deliberately NOT altered so that every current
 * query, view and filter keeps working. `current_stage` carries the richer
 * Module 4 stage and is kept in sync by the workflow service.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            if (!Schema::hasColumn('submissions', 'current_version_id')) {
                $table->foreignId('current_version_id')->nullable()->after('assigned_editor_id')
                    ->constrained('manuscript_versions')->nullOnDelete();
            }
            if (!Schema::hasColumn('submissions', 'current_stage')) {
                $table->string('current_stage', 80)->nullable()->after('current_version_id');
            }
            if (!Schema::hasColumn('submissions', 'revision_round')) {
                $table->unsignedInteger('revision_round')->default(0)->after('current_stage');
            }
            if (!Schema::hasColumn('submissions', 'decision_due_at')) {
                $table->timestamp('decision_due_at')->nullable()->after('revision_round');
            }
            if (!Schema::hasColumn('submissions', 'anonymous_code')) {
                $table->string('anonymous_code', 32)->nullable()->after('decision_due_at');
            }
        });
    }

    public function down(): void
    {
        $columns = array_values(array_filter(
            ['anonymous_code', 'decision_due_at', 'revision_round', 'current_stage', 'current_version_id'],
            fn (string $column) => Schema::hasColumn('submissions', $column)
        ));

        if ($columns !== []) {
            Schema::table('submissions', function (Blueprint $table) use ($columns) {
                if (in_array('current_version_id', $columns, true)) {
                    $table->dropForeign(['current_version_id']);
                }
                $table->dropColumn($columns);
            });
        }
    }
};