<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repairs three truths that the legacy endpoints left unrecorded.
 *
 *  1. Reviewer reports are written as `submitted`. The old reviewer
 *     controllers inserted rows without a status, so they kept the table
 *     default ("pending") and the readiness gate counted no reports.
 *  2. `current_stage` follows an already-recorded decision. Decisions made
 *     through the legacy editorial endpoint only changed the status, leaving
 *     the workflow pointer null and the copyediting/proof stages unreachable.
 *  3. Copyediting follows acceptance. The catalogue previously only allowed
 *     it after a revision, so an accepted manuscript had nowhere to go.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::table('reviews')
            ->where('status', 'pending')
            ->whereNotNull('recommendation')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('reviewer_assignments')
                    ->whereColumn('reviewer_assignments.id', 'reviews.reviewer_assignment_id')
                    ->where('reviewer_assignments.status', 'completed');
            })
            ->update([
                'status' => 'submitted',
                'submitted_at' => DB::raw(
                    'COALESCE(submitted_at, '
                    .'(SELECT responded_at FROM reviewer_assignments '
                    .'WHERE reviewer_assignments.id = reviews.reviewer_assignment_id), updated_at)'
                ),
            ]);

        foreach ([
            'accepted' => 'accepted',
            'rejected' => 'rejected',
            'revision_requested' => 'author_revision',
            'published' => 'published',
        ] as $status => $stage) {
            DB::table('submissions')
                ->where('status', $status)
                ->whereNull('current_stage')
                ->update(['current_stage' => $stage]);
        }

        $hasCopyeditingTransition = DB::table('editorial_stage_transitions')
            ->where('from_stage_slug', 'accepted')
            ->where('to_stage_slug', 'copyediting')
            ->exists();

        if (! $hasCopyeditingTransition) {
            DB::table('editorial_stage_transitions')->insert([
                'from_stage_slug' => 'accepted',
                'to_stage_slug' => 'copyediting',
                'label' => 'Publication fee paid, begin copyediting',
                'required_permissions' => json_encode(['manuscript.edit']),
                'requires_deadline' => false,
                'requires_note' => false,
                'notifies_author' => false,
                'opens_revision_window' => false,
                'closes_revision_window' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Data repairs are not reverted: restoring the false "no submitted
        // reports" state would only reintroduce the defect.
    }
};
