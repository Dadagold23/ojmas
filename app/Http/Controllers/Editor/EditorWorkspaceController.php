<?php

namespace App\Http\Controllers\Editor;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\ReviewerAssignment;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\ReviewerAssignedNotification;
use App\Notifications\RevisionRequestedNotification;
use App\Notifications\SubmissionAcceptedNotification;
use App\Notifications\SubmissionRejectedNotification;
use App\Services\Documents\ManuscriptAccessLogger;
use App\Services\Review\ManuscriptAnonymiser;
use App\Services\Review\ReviewService;
use App\Services\Review\ReviewerAssignmentService;
use App\Services\Workflow\EditorialWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The editor workspace.
 *
 * An editor can see reviewer recommendations and act on them, but cannot alter
 * a submitted review. Only the `reviews.status` lifecycle is writable, and only
 * by the reviewer who owns the review.
 */
class EditorWorkspaceController extends Controller
{
    public function __construct(
        private readonly ReviewService $reviews,
        private readonly ReviewerAssignmentService $assignments,
        private readonly EditorialWorkflowService $workflow,
        private readonly ManuscriptAnonymiser $anonymiser,
        private readonly ManuscriptAccessLogger $accessLog,
    ) {
    }

    public function dashboard(Request $request)
    {
        $editor = $request->user();

        $assigned = Submission::query()
            ->where('assigned_editor_id', $editor->id)
            ->whereNotIn('status', ['published', 'rejected'])
            ->with(['author', 'reviewerAssignments.review'])
            ->latest('updated_at')
            ->get();

        $stats = [
            'assigned' => $assigned->count(),
            'awaiting_reports' => $assigned->filter(
                fn (Submission $s) => $s->reviewerAssignments->isNotEmpty()
                    && $s->reviewerAssignments->contains(fn (ReviewerAssignment $a) => $a->review === null),
            )->count(),
            'awaiting_decision' => $assigned->filter(
                fn (Submission $s) => $s->reviewerAssignments->contains(
                    fn (ReviewerAssignment $a) => $a->review?->status === ReviewService::STATUS_SUBMITTED,
                ),
            )->count(),
            'in_revision' => $assigned->where('status', 'revision_requested')->count(),
        ];

        return view('editor.workspace.dashboard', [
            'submissions' => $assigned->take(20),
            'stats' => $stats,
        ]);
    }

    public function show(Request $request, Submission $submission)
    {
        $this->authorize('viewAny', $submission);

        $submission->load([
            'author', 'coAuthors', 'files', 'assignedEditor',
            'reviewerAssignments.review', 'reviewerAssignments.invitations',
            'editorialDecisions.editor', 'histories',
            'manuscriptValidations.issues',
        ]);

        $this->accessLog->view($request->user(), $submission, $submission->manuscriptValidations->first()?->document);

        return view('editor.workspace.show', [
            'submission' => $submission,
            'reviews' => $this->reviews->submittedFor($submission),
            'summary' => $this->reviews->recommendationSummary($submission),
            'stage' => $this->workflow->currentStage($submission),
            'transitions' => $this->workflow->availableTransitions($submission, $request->user()),
            'history' => $this->workflow->historyFor($submission, 50),
            'reviewers' => User::role('Reviewer')->orderBy('name')->get(),
            'readiness' => app(\App\Services\Publishing\PublicationReadinessService::class)->assess($submission),
        ]);
    }

    public function assignReviewer(Request $request, Submission $submission)
    {
        $this->authorize('assignReviewer', $submission);

        $validated = $request->validate([
            'reviewer_id' => ['required', 'exists:users,id'],
            'due_date' => ['nullable', 'date', 'after:today'],
            'blinded' => ['nullable', 'boolean'],
        ]);

        $reviewer = User::findOrFail($validated['reviewer_id']);

        try {
            $assignment = $this->assignments->invite(
                $submission,
                $reviewer,
                $request->user(),
                isset($validated['due_date']) ? Carbon::parse($validated['due_date']) : null,
                null,
                (bool) ($validated['blinded'] ?? true),
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $reviewer->notify(new ReviewerAssignedNotification($assignment));

        return back()->with('success', $reviewer->name.' has been invited to review this manuscript.');
    }

    /**
     * The anonymised review copy, with a manifest of what was removed and what
     * risk remains. The editor sees the manifest because they are accountable
     * for the confidentiality of the review.
     */
    public function blindedCopy(Request $request, Submission $submission)
    {
        $this->authorize('viewAny', $submission);

        try {
            $result = $this->anonymiser->createReviewCopy($submission);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'The blinded review copy could not be prepared: '.$e->getMessage());
        }

        return view('editor.workspace.blinded', [
            'submission' => $submission,
            'result' => $result,
        ]);
    }

    /**
     * Records an editorial decision.
     *
     * A revision request also opens a revision window so the author knows when
     * their deadline is.
     */
    public function decide(Request $request, Submission $submission)
    {
        $this->authorize('recordDecision', $submission);

        $validated = $request->validate([
            'decision' => ['required', 'string', 'in:accepted,rejected,revision_requested'],
            'message' => ['required', 'string', 'max:5000'],
            'revision_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $oldStatus = $submission->status;

        $decision = $submission->editorialDecisions()->create([
            'editor_id' => $request->user()->id,
            'decision' => $validated['decision'],
            'message' => $validated['message'],
        ]);

        $submission->status = $validated['decision'];

        if ($validated['decision'] === 'revision_requested') {
            $submission->revision_round = (int) $submission->revision_round + 1;
            $submission->decision_due_at = now()->addDays((int) ($validated['revision_days'] ?? 30));

            $submission->revisionRounds()->create([
                'round_number' => (int) $submission->revision_round,
                'status' => 'open',
                'opened_by' => $request->user()->id,
                'opened_at' => now(),
                'due_at' => $submission->decision_due_at,
                'decision_id' => $decision->id,
            ]);
        }

        $submission->save();

        \App\Models\SubmissionStatusHistory::create([
            'submission_id' => $submission->id,
            'changed_by' => $request->user()->id,
            'old_status' => $oldStatus,
            'new_status' => $submission->status,
            'note' => $validated['message'],
        ]);

        $this->workflow->log($submission, $request->user(), 'decision_recorded', null, null, [
            'decision' => $validated['decision'],
        ]);

        $author = $submission->author;

        match ($validated['decision']) {
            'accepted' => $author->notify(new SubmissionAcceptedNotification($submission)),
            'rejected' => $author->notify(new SubmissionRejectedNotification($submission, $decision->message)),
            default => $author->notify(new RevisionRequestedNotification($submission, $decision->message)),
        };

        return back()->with('success', 'Editorial decision recorded and the author has been notified.');
    }

    /**
     * Moves a manuscript to another configured stage.
     */
    public function transition(Request $request, Submission $submission)
    {
        $this->authorize('manageWorkflow', $submission);

        $validated = $request->validate([
            'to_stage' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = $this->workflow->transition(
            $submission,
            $validated['to_stage'],
            $request->user(),
            $validated['reason'] ?? null,
        );

        if (! $result['ok']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', $result['message']);
    }

    /**
     * The audit history for one manuscript. Append-only.
     */
    public function audit(Request $request, Submission $submission)
    {
        $this->authorize('viewAudit', $submission);

        $submission->load(['author', 'assignedEditor']);

        return view('editor.workspace.audit', [
            'submission' => $submission,
            'history' => $this->workflow->historyFor($submission, 200),
            'statusHistory' => $submission->histories()->with('changedBy')->latest()->get(),
            'accessLogs' => $submission->manuscriptAccessLogs()->with('user')->latest()->limit(100)->get(),
        ]);
    }
}