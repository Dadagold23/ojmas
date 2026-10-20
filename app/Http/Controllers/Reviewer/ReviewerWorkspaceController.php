<?php

namespace App\Http\Controllers\Reviewer;

use App\Http\Controllers\Controller;
use App\Models\ReviewerAssignment;
use App\Models\ReviewerInvitation;
use App\Models\Submission;
use App\Services\Documents\ManuscriptAccessLogger;
use App\Services\Review\ManuscriptAnonymiser;
use App\Services\Review\ReviewService;
use App\Services\Review\ReviewerAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The reviewer workspace.
 *
 * A reviewer sees only the manuscripts they have been assigned, and only the
 * files they are entitled to: the blinded review copy and the supplementary
 * material, never the author's original manuscript.
 */
class ReviewerWorkspaceController extends Controller
{
    public function __construct(
        private readonly ReviewService $reviews,
        private readonly ReviewerAssignmentService $assignments,
        private readonly ManuscriptAnonymiser $anonymiser,
        private readonly ManuscriptAccessLogger $accessLog,
    ) {
    }

    public function dashboard(Request $request)
    {
        $reviewer = $request->user();

        $assignments = ReviewerAssignment::with(['submission', 'review'])
            ->where('reviewer_id', $reviewer->id)
            ->latest()
            ->get();

        $invitations = ReviewerInvitation::with('submission')
            ->where('reviewer_id', $reviewer->id)
            ->where('response', ReviewerInvitation::RESPONSE_PENDING)
            ->get();

        $stats = [
            'invitations' => $invitations->count(),
            'in_progress' => $assignments->where('status', 'assigned')->count(),
            'completed' => $assignments->where('status', 'completed')->count(),
            'overdue' => $assignments->filter(
                fn (ReviewerAssignment $a) => $a->due_date !== null && $a->due_date->isPast() && $a->status !== 'completed',
            )->count(),
        ];

        return view('reviewer.workspace.dashboard', [
            'assignments' => $assignments,
            'invitations' => $invitations,
            'stats' => $stats,
        ]);
    }

    public function invitations(Request $request)
    {
        $invitations = ReviewerInvitation::with(['submission', 'inviter'])
            ->where('reviewer_id', $request->user()->id)
            ->latest('invited_at')
            ->get();

        return view('reviewer.workspace.invitations', ['invitations' => $invitations]);
    }

    public function respond(Request $request, ReviewerInvitation $invitation)
    {
        abort_unless((int) $invitation->reviewer_id === (int) $request->user()->id, 403);

        $validated = $request->validate([
            'response' => ['required', 'string', 'in:accepted,declined'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $assignment = ReviewerAssignment::where('submission_id', $invitation->submission_id)
            ->where('reviewer_id', $request->user()->id)
            ->firstOrFail();

        $this->assignments->respond($assignment, $request->user(), $validated['response'], $validated['note'] ?? null);

        return back()->with('success', 'Thank you — your response has been recorded.');
    }

    /**
     * A single assignment: the blinded manuscript, its files, and the review form.
     */
    public function show(Request $request, ReviewerAssignment $assignment)
    {
        abort_unless((int) $assignment->reviewer_id === (int) $request->user()->id, 403);

        $submission = $assignment->submission;

        abort_if($submission === null, 404);

        $review = $this->reviews->reviewFor($assignment);

        $this->accessLog->view($request->user(), $submission, null);

        return view('reviewer.workspace.show', [
            'assignment' => $assignment->load(['submission.files', 'review']),
            'review' => $review,
            'recommendations' => $this->reviews->recommendationLabels(),
            'isSubmitted' => $review?->status === ReviewService::STATUS_SUBMITTED,
            'isConfidential' => true,
        ]);
    }

    /**
     * The blinded manuscript file.
     *
     * A reviewer is served the anonymised derivative, never the author's
     * original — the original's filename alone can identify the author.
     */
    public function downloadBlinded(Request $request, Submission $submission): SymfonyResponse
    {
        $assignment = ReviewerAssignment::where('submission_id', $submission->id)
            ->where('reviewer_id', $request->user()->id)
            ->firstOrFail();

        $file = $submission->files()
            ->where('file_type', 'manuscript')
            ->where('is_anonymised', true)
            ->latest('id')
            ->first();

        if ($file === null) {
            // No blinded copy exists yet; create one on demand.
            try {
                $this->anonymiser->createReviewCopy($submission);
            } catch (\Throwable $e) {
                report($e);

                return back()->with('error', 'The blinded review copy could not be prepared. Please contact the editorial office.');
            }

            $file = $submission->files()
                ->where('file_type', 'manuscript')
                ->where('is_anonymised', true)
                ->latest('id')
                ->first();
        }

        abort_if($file === null, 404);

        $disk = Storage::disk(config('manuscripts.disk', 'private'));
        $path = $file->resolvedPrivatePath() ?? $file->file_path;

        abort_unless($disk->exists($path), 404);

        $this->accessLog->download($request->user(), $submission, null);

        return $disk->download($path, $file->original_name);
    }

    /**
     * Supplementary material. Blinding does not apply: these are data files,
     * not the manuscript, and withholding them would make review impossible.
     */
    public function downloadSupplementary(Request $request, Submission $submission, string $fileId)
    {
        ReviewerAssignment::where('submission_id', $submission->id)
            ->where('reviewer_id', $request->user()->id)
            ->firstOrFail();

        $file = $submission->files()
            ->where('file_type', 'supplementary')
            ->whereKey($fileId)
            ->firstOrFail();

        $disk = Storage::disk(config('manuscripts.disk', 'private'));
        $path = $file->resolvedPrivatePath() ?? $file->file_path;

        abort_unless($disk->exists($path), 404);

        $this->accessLog->download($request->user(), $submission, null);

        return $disk->download($path, $file->original_name);
    }

    public function saveDraft(Request $request, ReviewerAssignment $assignment)
    {
        abort_unless((int) $assignment->reviewer_id === (int) $request->user()->id, 403);

        $validated = $request->validate([
            'recommendation' => ['nullable', 'string', 'in:accept,minor_revision,major_revision,reject'],
            'comments_for_author' => ['nullable', 'string', 'max:20000'],
            'comments_for_editor' => ['nullable', 'string', 'max:20000'],
        ]);

        $this->reviews->saveDraft($assignment, $request->user(), $validated);

        return back()->with('success', 'Draft saved. Only you can see it until you submit.');
    }

    public function submitReview(Request $request, ReviewerAssignment $assignment)
    {
        abort_unless((int) $assignment->reviewer_id === (int) $request->user()->id, 403);

        $validated = $request->validate([
            'recommendation' => ['required', 'string', 'in:accept,minor_revision,major_revision,reject'],
            'comments_for_author' => ['required', 'string', 'max:20000'],
            'comments_for_editor' => ['nullable', 'string', 'max:20000'],
        ]);

        $review = $this->reviews->submit($assignment, $request->user(), $validated);

        $editors = \App\Models\User::role(['Editor', 'Managing Editor', 'Managing Admin', 'Super Admin'])->get();

        foreach ($editors as $editor) {
            $editor->notify(new \App\Notifications\ReviewCompletedNotification($assignment));
        }

        return back()->with(
            'success',
            'Thank you. Your recommendation of "'.$this->reviews->recommendationLabels()[$review->recommendation].'" has been recorded.',
        );
    }

    /**
     * A submitted review cannot be edited. The reviewer may withdraw it, which
     * is recorded, so that a correction is never an invisible edit.
     */
    public function withdraw(Request $request, ReviewerAssignment $assignment)
    {
        abort_unless((int) $assignment->reviewer_id === (int) $request->user()->id, 403);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->reviews->withdraw($assignment, $request->user(), $validated['reason'] ?? null);

        return back()->with('warning', 'Your review has been withdrawn and may be revised and submitted again.');
    }
}