<?php

namespace App\Http\Controllers\Author;

use App\Http\Controllers\Controller;
use App\Models\EditorialDecision;
use App\Models\Submission;
use App\Services\Documents\ManuscriptAccessLogger;
use App\Services\Workflow\EditorialWorkflowService;
use Illuminate\Http\Request;

/**
 * The author's revision response.
 *
 * §3.1: the author must be able to respond to a revision request, see the
 * deadline, and compare what they submitted with what they have revised.
 *
 * What the author must NOT be able to do — alter reviewer reports, editorial
 * decisions, confidential notes or the audit trail — is enforced by policy. This
 * controller has no code path that writes to any of them.
 */
class RevisionController extends Controller
{
    public function __construct(
        private readonly EditorialWorkflowService $workflow,
        private readonly ManuscriptAccessLogger $accessLog,
    ) {
    }

    public function show(Request $request, Submission $submission)
    {
        abort_unless((int) $submission->author_id === (int) $request->user()->id, 403);

        $submission->load([
            'files',
            'editorialDecisions.editor',
            'revisionRounds.opener',
            'manuscriptVersions.document',
        ]);

        $round = $submission->currentRevisionRound();

        $this->accessLog->view($request->user(), $submission);

        return view('author.revisions.show', [
            'submission' => $submission,
            'round' => $round,
            // Only decisions addressed to the author are shown. A decision is
            // an editorial act communicated to the author; it is not editable.
            'decisions' => EditorialDecision::where('submission_id', $submission->id)
                ->with('editor')
                ->latest()
                ->get(),
            'versions' => $submission->manuscriptVersions()->with('document')->get(),
            'canRespond' => $request->user()->can('respondToRevision', $submission),
        ]);
    }

    /**
     * Records the author's written response to a revision request.
     */
    public function respond(Request $request, Submission $submission)
    {
        $this->authorize('respondToRevision', $submission);

        $validated = $request->validate([
            'response' => ['required', 'string', 'max:10000'],
        ]);

        $round = $submission->currentRevisionRound();

        if ($round === null) {
            return back()->with('error', 'There is no open revision request for this manuscript.');
        }

        $round->update([
            'author_response' => $validated['response'],
            'status' => \App\Models\RevisionRound::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        $this->workflow->log($submission, $request->user(), 'author_revision_response', null, null, [
            'round' => $round->round_number,
        ]);

        \App\Models\SubmissionStatusHistory::create([
            'submission_id' => $submission->id,
            'changed_by' => $request->user()->id,
            'old_status' => $submission->status,
            'new_status' => $submission->status,
            'note' => 'Author responded to revision round '.$round->round_number.'.',
        ]);

        return back()->with('success', 'Thank you. Your response has been sent to the editor.');
    }

    /**
     * Side-by-side comparison of the submitted and revised versions.
     */
    public function compare(Request $request, Submission $submission)
    {
        abort_unless((int) $submission->author_id === (int) $request->user()->id, 403);

        $submission->load('manuscriptVersions.document');

        return view('author.revisions.compare', [
            'submission' => $submission,
            'versions' => $submission->manuscriptVersions()->with('document')->get(),
        ]);
    }
}