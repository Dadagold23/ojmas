<?php

namespace App\Services\Publishing;

use App\Models\ManuscriptProofApproval;
use App\Models\Review;
use App\Models\Submission;

/**
 * The gate between "the editor has finished editing" and "the article is
 * published".
 *
 * The Module 4 brief is explicit: publication must follow the required
 * editorial approvals, and a manuscript may not be published simply because
 * editing is done. The readiness answer is constructive — callers get every
 * unmet requirement spelled out, not a bare refusal.
 */
class PublicationReadinessService
{
    /**
     * Whether every required editorial approval is present.
     *
     * @return array{ready: bool, requirements: array<int, array{name: string, met: bool, detail: string}>}
     */
    public function assess(Submission $submission): array
    {
        $submission->loadMissing(['payments', 'editorialDecisions', 'reviewerAssignments.review']);

        $requirements = [];

        // 1. The author must have cleared the vetting obligation, as the
        //    journal already requires everywhere else.
        $requirements[] = [
            'name' => 'Vetting fee settled',
            'met' => $submission->hasPaidVettingFee(),
            'detail' => $submission->hasPaidVettingFee()
                ? 'Confirmed by the existing payment records.'
                : 'No confirmed vetting payment is on record.',
        ];

        // 2. An explicit editorial decision must exist — acceptance can never
        //    be inferred from reviewer silence or from copyediting progress.
        $accepted = $submission->editorialDecisions->firstWhere('decision', 'accepted')
            ?? ($submission->status === 'accepted' ? 'implicit-in-status' : null);

        $requirements[] = [
            'name' => 'Editorial decision recorded',
            'met' => $accepted !== null,
            'detail' => $accepted !== null
                ? 'An acceptance decision (or accepted status) is on record.'
                : 'No acceptance decision has been recorded.',
        ];

        // 3. Reviewer reports submitted — withdrawn and draft reports are
        //    excluded, consistent with the decision workflow.
        $submittedReports = Review::whereHas(
            'assignment',
            fn ($q) => $q->where('submission_id', $submission->id),
        )->where('status', Review::STATUS_SUBMITTED)->count();

        $requirements[] = [
            'name' => 'Peer review concluded',
            'met' => $submittedReports > 0,
            'detail' => $submittedReports === 0
                ? 'No submitted reviewer reports exist; the manuscript was never meaningfully reviewed.'
                : $submittedReports.' submitted reviewer report(s) on record.',
        ];

        // 4. An active revision round must not remain open: if an author is
        //    still expected to answer, publication is premature.
        $openRound = $submission->revisionRounds()->where('status', 'open')->first();

        $requirements[] = [
            'name' => 'Revision rounds closed',
            'met' => $openRound === null,
            'detail' => $openRound === null
                ? 'No revision round awaits the author.'
                : "Revision round {$openRound->round_number} is still open.",
        ];

        // 5. Copyediting happened (or the journal chose not to run that stage
        //    for this manuscript).
        $copyedited = $this->stagePassed($submission, 'copyediting');

        $requirements[] = [
            'name' => 'Copyediting completed',
            'met' => $copyedited,
            'detail' => $copyedited
                ? 'The manuscript has passed through copyediting.'
                : 'The manuscript has not yet been through the copyediting stage.',
        ];

        // 6. The author approved the proofs — the check the brief emphasises.
        $approval = ManuscriptProofApproval::where('submission_id', $submission->id)
            ->where('status', ManuscriptProofApproval::STATUS_APPROVED)
            ->exists();

        $requirements[] = [
            'name' => 'Author proof approval',
            'met' => $approval,
            'detail' => $approval
                ? 'The author approved the proofs.'
                : 'The author has not approved the proofs yet.',
        ];

        return [
            'ready' => collect($requirements)->every(fn ($r) => $r['met']),
            'requirements' => $requirements,
        ];
    }

    /**
     * Whether the submission's own record shows it passed through a stage —
     * either by sitting on (or past) that stage now, or by a workflow event
     * recording an outbound transition from it.
     */
    private function stagePassed(Submission $submission, string $slug): bool
    {
        // A submission deep in the lifecycle also satisfies earlier stages;
        // ordering comes from the stage table.
        $stage = \App\Models\EditorialStage::where('slug', $slug)->first();

        if ($stage === null) {
            return false;
        }

        if ($submission->current_stage === $slug) {
            // Still in copyediting: not passed until it moves on.
            return \App\Models\ManuscriptWorkflowEvent::where('submission_id', $submission->id)
                ->where('event', 'stage_transition')
                ->where('from_stage', $slug)
                ->exists();
        }

        $passedStages = false;

        if ($submission->current_stage !== null) {
            $current = \App\Models\EditorialStage::where('slug', $submission->current_stage)->first();

            if ($current !== null) {
                $passedStages = $current->sort_order > $stage->sort_order;
            }
        }

        return $passedStages
            || \App\Models\ManuscriptWorkflowEvent::where('submission_id', $submission->id)
                ->where('event', 'stage_transition')
                ->where('from_stage', $slug)
                ->exists();
    }
}
