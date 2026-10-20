<?php

namespace App\Http\Controllers\Reviewer;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitReviewRequest;
use App\Models\ArticleFile;
use App\Models\ReviewerAssignment;
use App\Models\Submission;
use App\Models\SubmissionStatusHistory;
use App\Notifications\ReviewCompletedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AssignmentController extends Controller
{
    public function index()
    {
        $assignments = ReviewerAssignment::with(['submission.files'])
            ->where('reviewer_id', auth()->id())
            ->latest()
            ->paginate(15);

        return view('reviewer.assignments.index', compact('assignments'));
    }

    public function show(ReviewerAssignment $assignment)
    {
        abort_unless($assignment->reviewer_id === auth()->id() || auth()->user()->hasRole('Super Admin'), 403);
        abort_unless($assignment->submission && $assignment->submission->hasPaidVettingFee(), 422, 'Vetting fee must be confirmed before reviewer access can continue.');

        $assignment->load(['submission.files', 'submission.author']);

        return view('reviewer.assignments.show', compact('assignment'));
    }

    public function storeReview(SubmitReviewRequest $request, ReviewerAssignment $assignment)
    {
        abort_unless($assignment->reviewer_id === auth()->id(), 403);

        if ($assignment->review) {
            return back()->with('error', 'You have already submitted a review for this manuscript.');
        }

        $review = $assignment->review()->create($request->validated());

        $assignment->update(['status' => 'completed', 'responded_at' => now()]);

        SubmissionStatusHistory::create([
            'submission_id' => $assignment->submission_id,
            'changed_by' => auth()->id(),
            'old_status' => $assignment->submission?->status,
            'new_status' => $assignment->submission?->status ?? 'under_review',
            'note' => 'Reviewer submitted review with recommendation: '.$review->recommendation,
        ]);

        $editors = \App\Models\User::role(['Editor', 'Managing Editor', 'Managing Admin', 'Super Admin'])->get();

        foreach ($editors as $editor) {
            $editor->notify(new ReviewCompletedNotification($assignment));
        }

        return redirect()
            ->route('reviewer.assignments.index')
            ->with('success', 'Review submitted successfully.');
    }

    public function downloadFile(Submission $submission, ArticleFile $file)
    {
        $isAssignedReviewer = ReviewerAssignment::query()
            ->where('submission_id', $submission->id)
            ->where('reviewer_id', auth()->id())
            ->exists();

        abort_unless($isAssignedReviewer || auth()->user()->hasRole('Super Admin'), 403);
        abort_unless($file->submission_id === $submission->id, 404);
        abort_unless($submission->hasPaidVettingFee(), 422, 'Vetting fee must be confirmed before reviewer downloads can continue.');
        $resolvedPath = $file->resolvedPrivatePath();
        abort_unless($resolvedPath, 404);

        return Storage::disk('private')->download(
            $resolvedPath,
            $file->original_name
        );
    }
}
