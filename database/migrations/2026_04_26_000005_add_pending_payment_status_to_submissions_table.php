<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * `pending_payment` is written by the author submission flow and read by
     * the payment controllers, dashboards and policies, but was absent from the
     * enum, so every insert failed with "Data truncated for column 'status'".
     */
    private const STATUSES = [
        'draft', 'pending_payment', 'submitted', 'screening',
        'under_review', 'revision_requested', 'accepted',
        'rejected', 'published',
    ];

    private const ORIGINAL = [
        'draft', 'submitted', 'screening', 'under_review',
        'revision_requested', 'accepted', 'rejected', 'published',
    ];

    public function up(): void
    {
        if (!Schema::hasColumn('submissions', 'status')) {
            return;
        }

        Schema::table('submissions', function (Blueprint $table) {
            $table->enum('status', self::STATUSES)->default('submitted')->change();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('submissions', 'status')) {
            return;
        }

        // Anything still sitting in pending_payment would not fit the original
        // enum, so move it to submitted before narrowing.
        DB::table('submissions')->where('status', 'pending_payment')->update(['status' => 'submitted']);

        Schema::table('submissions', function (Blueprint $table) {
            $table->enum('status', self::ORIGINAL)->default('submitted')->change();
        });
    }
};