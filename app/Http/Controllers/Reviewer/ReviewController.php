<?php

namespace App\Http\Controllers\Reviewer;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitReviewRequest;
use App\Models\ReviewerAssignment;
use App\Models\SubmissionStatusHistory;
use App\Notifications\ReviewCompletedNotification;

class ReviewController extends Controller
{
    public function dashboard()
    {
        $assignments = ReviewerAssignment::with('submission')
            ->where('reviewer_id', auth()->id())
            ->latest()
            ->take(5)
            ->get();

        $stats = [
            'total' => ReviewerAssignment::where('reviewer_id', auth()->id())->count(),
            'pending' => ReviewerAssignment::where('reviewer_id', auth()->id())->where('status', 'assigned')->count(),
            'completed' => ReviewerAssignment::where('reviewer_id', auth()->id())->where('status', 'completed')->count(),
        ];

        return view('reviewer.dashboard', compact('assignments', 'stats'));
    }

    public function assignments()
    {
        $assignments = ReviewerAssignment::with(['submission', 'review'])
            ->where('reviewer_id', auth()->id())
            ->latest()
            ->paginate(10);

        return view('reviewer.assignments.index', compact('assignments'));
    }

    public function show(ReviewerAssignment $assignment)
    {
        abort_unless($assignment->reviewer_id === auth()->id(), 403);
        abort_unless($assignment->submission->hasPaidVettingFee(), 422, 'Vetting fee must be confirmed before reviews can proceed.');

        $assignment->load(['submission.author', 'submission.files', 'review']);

        return view('reviewer.assignments.show', compact('assignment'));
    }

    public function submit(SubmitReviewRequest $request, ReviewerAssignment $assignment)
    {
        abort_unless($assignment->reviewer_id === auth()->id(), 403);
        abort_unless($assignment->submission->hasPaidVettingFee(), 422, 'Vetting fee must be confirmed before reviews can proceed.');

        if ($assignment->review) {
            return back()->with('error', 'Review already submitted.');
        }

        $review = $assignment->review()->create($request->validated());
        $assignment->update(['status' => 'completed']);

        SubmissionStatusHistory::create([
            'submission_id' => $assignment->submission_id,
            'changed_by' => auth()->id(),
            'old_status' => $assignment->submission->status,
            'new_status' => 'under_review',
            'note' => 'Reviewer submitted review with recommendation: ' . $review->recommendation,
        ]);

        $editors = \App\Models\User::role(['Editor', 'Managing Editor', 'Super Admin'])->get();
        foreach ($editors as $editor) {
            $editor->notify(new ReviewCompletedNotification($assignment));
        }

        return redirect()->route('reviewer.assignments')->with('success', 'Review submitted successfully.');
    }
}
