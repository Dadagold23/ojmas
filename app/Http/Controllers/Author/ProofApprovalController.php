<?php

namespace App\Http\Controllers\Author;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Models\ManuscriptProofApproval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The author's proofreading and approval step (§4.11).
 *
 * An editor finishing copyediting is not enough for publication (§4 must not
 * allow publishing "simply because an editor has finished editing it"): the
 * approval recorded here is what the publication readiness gate requires.
 */
class ProofApprovalController extends Controller
{
    public function show(Request $request, Submission $submission)
    {
        $this->authorize('approveProofs', $submission);

        $submission->loadMissing(['author', 'files', 'manuscriptVersions']);

        // The two gate checks, computed from the submission's actual stage
        // position. They are displayed honestly as True/False and enforced
        // again when the author submits an approval.
        $proofChecks = app(\App\Services\Workflow\EditorialWorkflowService::class)
            ->proofGateChecks($submission);

        return view('author.proofs.show', [
            'submission' => $submission,
            'approvals' => ManuscriptProofApproval::where('submission_id', $submission->id)
                ->latest('id')
                ->get(),
            'proofChecks' => $proofChecks,
            'proofGateSatisfied' => $proofChecks['peer_review_concluded']
                && $proofChecks['copyediting_completed'],
        ]);
    }

    public function store(Request $request, Submission $submission)
    {
        $this->authorize('approveProofs', $submission);

        // Server-side gate, independent of what the form allowed to be
        // visible: an approval can only ever be recorded once peer review
        // has concluded and copyediting is complete.
        $proofChecks = app(\App\Services\Workflow\EditorialWorkflowService::class)
            ->proofGateChecks($submission);

        if (! $proofChecks['peer_review_concluded'] || ! $proofChecks['copyediting_completed']) {
            return back()->withErrors([
                'proofs' => 'The proof-approval gate is not satisfied yet'
                    .' (peer review concluded: '.($proofChecks['peer_review_concluded'] ? 'True' : 'False')
                    .', copyediting completed: '.($proofChecks['copyediting_completed'] ? 'True' : 'False').').'
                    .' No approval was recorded.',
            ]);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:'.ManuscriptProofApproval::STATUS_APPROVED.','.ManuscriptProofApproval::STATUS_CORRECTIONS],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $approval = DB::transaction(function () use ($submission, $request, $validated) {
            $approval = ManuscriptProofApproval::create([
                'submission_id' => $submission->id,
                'revision_round_id' => $submission->currentRevisionRound()?->id,
                'author_id' => $request->user()->id,
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
                'approved_at' => $validated['status'] === ManuscriptProofApproval::STATUS_APPROVED ? now() : null,
                'ip_address' => $request->ip(),
            ]);

            app(\App\Services\Workflow\EditorialWorkflowService::class)->log(
                $submission,
                $request->user(),
                $validated['status'] === ManuscriptProofApproval::STATUS_APPROVED ? 'proofs_approved' : 'proof_corrections_requested',
                null,
                null,
                ['notes' => $validated['notes'] ?? null],
            );

            return $approval;
        });

        // The editorial side learns of it through the standard queue.
        $submission->assignedEditor?->notify(new \App\Notifications\ProofApprovalNotification($approval));

        return $validated['status'] === ManuscriptProofApproval::STATUS_APPROVED
            ? redirect()
                ->route('author.revisions.show', $submission)
                ->with('success', 'Thank you — your proof approval is on record and the article can proceed to production.')
            : redirect()
                ->route('author.proofs.show', $submission)
                ->with('success', 'Your correction request has been recorded. The editorial team will re-issue the proofs.');
    }
}
