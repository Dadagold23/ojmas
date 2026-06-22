<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("
            ALTER TABLE submissions
            MODIFY status ENUM(
                'draft',
                'pending_payment',
                'submitted',
                'screening',
                'under_review',
                'revision_requested',
                'accepted',
                'rejected',
                'published'
            ) NOT NULL DEFAULT 'submitted'
        ");
    }

    public function down(): void
    {
        DB::statement("
            UPDATE submissions
            SET status = 'submitted'
            WHERE status = 'pending_payment'
        ");

        DB::statement("
            ALTER TABLE submissions
            MODIFY status ENUM(
                'draft',
                'submitted',
                'screening',
                'under_review',
                'revision_requested',
                'accepted',
                'rejected',
                'published'
            ) NOT NULL DEFAULT 'submitted'
        ");
    }
};
