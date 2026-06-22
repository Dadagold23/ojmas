<?php

namespace App\Http\Controllers\Reviewer;

use App\Http\Controllers\Controller;
use App\Models\ArticleFile;
use App\Models\Review;
use App\Models\ReviewerAssignment;
use App\Models\Submission;
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

    public function storeReview(Request $request, ReviewerAssignment $assignment)
    {
        abort_unless($assignment->reviewer_id === auth()->id() || auth()->user()->hasRole('Super Admin'), 403);

        $validated = $request->validate([
            'recommendation' => ['required', 'string'],
            'comments'       => ['required', 'string'],
        ]);

        Review::create([
            'reviewer_assignment_id' => $assignment->id,
            'submission_id'          => $assignment->submission_id ?? null,
            'reviewer_id'            => auth()->id(),
            'recommendation'         => $validated['recommendation'],
            'comments'               => $validated['comments'],
            'submitted_at'           => now(),
        ]);

        $assignment->update(['status' => 'completed']);

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
