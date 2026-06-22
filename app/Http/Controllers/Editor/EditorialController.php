<?php

namespace App\Http\Controllers\Editor;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignReviewerRequest;
use App\Http\Requests\EditorialDecisionRequest;
use App\Models\ArticleFile;
use App\Models\ReviewerAssignment;
use App\Models\Submission;
use App\Models\SubmissionStatusHistory;
use App\Models\User;
use App\Notifications\ReviewerAssignedNotification;
use App\Notifications\RevisionRequestedNotification;
use App\Notifications\SubmissionAcceptedNotification;
use App\Notifications\SubmissionRejectedNotification;
use App\Models\Issue;
use App\Notifications\ArticlePublishedNotification;
use App\Services\Publishing\PublicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;


class EditorialController extends Controller
{
    public function dashboard()
    {
        $stats = [
            'total' => Submission::where('status', '!=', 'pending_payment')->count(),
            'submitted' => Submission::where('status', 'submitted')->count(),
            'under_review' => Submission::where('status', 'under_review')->count(),
            'accepted' => Submission::where('status', 'accepted')->count(),
        ];

        $submissions = Submission::with(['author', 'files'])
            ->where('status', '!=', 'pending_payment')
            ->latest()
            ->take(8)
            ->get();

        return view('editor.dashboard', compact('stats', 'submissions'));
    }

    public function index()
    {
        $submissions = Submission::with(['author', 'files', 'reviewerAssignments.reviewer'])
            ->where('status', '!=', 'pending_payment')
            ->latest()
            ->paginate(12);

        return view('editor.submissions.index', compact('submissions'));
    }

    public function show(Submission $submission)
    {
        $reviewers = User::role('Reviewer')->orderBy('name')->get();

        $submission->load([
            'author',
            'coAuthors',
            'files',
            'payments',
            'assignedEditor',
            'reviewerAssignments.reviewer',
            'reviewerAssignments.review',
            'editorialDecisions',
            'histories',
        ]);

        return view('editor.submissions.show', compact('submission', 'reviewers'));
    }

    public function assignReviewer(AssignReviewerRequest $request, Submission $submission)
    {
        abort_unless($submission->hasPaidVettingFee(), 422, 'Vetting fee must be paid before reviewer assignment can begin.');
        abort_unless(
            in_array($submission->status, ['submitted', 'revision_requested'], true),
            422,
            'Only paid submissions that are ready for editorial handling can be assigned to reviewers.'
        );

        $validated = $request->validated();

        $exists = ReviewerAssignment::where('submission_id', $submission->id)
            ->where('reviewer_id', $validated['reviewer_id'])
            ->exists();

        if ($exists) {
            return back()->with('error', 'Reviewer already assigned to this manuscript.');
        }

        $assignment = ReviewerAssignment::create([
            'submission_id' => $submission->id,
            'reviewer_id' => $validated['reviewer_id'],
            'due_date' => $validated['due_date'] ?? null,
            'status' => 'assigned',
        ]);

        $oldStatus = $submission->status;
        $submission->update(['status' => 'under_review']);

        SubmissionStatusHistory::create([
            'submission_id' => $submission->id,
            'changed_by' => auth()->id(),
            'old_status' => $oldStatus,
            'new_status' => 'under_review',
            'note' => 'Reviewer assigned by editor.',
        ]);

        $assignment->reviewer->notify(new ReviewerAssignedNotification($assignment));

        return back()->with('success', 'Reviewer assigned successfully.');
    }

    public function decision(EditorialDecisionRequest $request, Submission $submission)
    {
        abort_unless($submission->hasPaidVettingFee(), 422, 'Vetting fee must be paid before editorial decisions can be recorded.');

        $validated = $request->validated();
        $oldStatus = $submission->status;

        $decision = $submission->editorialDecisions()->create([
            'editor_id' => auth()->id(),
            'decision' => $validated['decision'],
            'message' => $validated['message'],
        ]);

        $submission->update(['status' => $validated['decision']]);

        SubmissionStatusHistory::create([
            'submission_id' => $submission->id,
            'changed_by' => auth()->id(),
            'old_status' => $oldStatus,
            'new_status' => $validated['decision'],
            'note' => $validated['message'],
        ]);

        if ($validated['decision'] === 'accepted') {
            $submission->author->notify(new SubmissionAcceptedNotification($submission));
        } elseif ($validated['decision'] === 'rejected') {
            $submission->author->notify(new SubmissionRejectedNotification($submission, $decision->message));
        } else {
            $submission->author->notify(new RevisionRequestedNotification($submission, $decision->message));
        }

        return back()->with('success', 'Editorial decision recorded successfully.');
    }
public function publish(Request $request, Submission $submission, PublicationService $publicationService)
{
    abort_unless($submission->hasPaidPublicationFee(), 422, 'Publication fee must be paid before an article can be published.');

    $validated = $request->validate([
        'issue_id' => ['required', 'exists:issues,id'],
        'published_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
        'note' => ['nullable', 'string'],
    ]);

    abort_unless(in_array($submission->status, ['accepted', 'screening']), 422, 'Only accepted or screening submissions can be published.');

    $pdfPath = null;
    if ($request->hasFile('published_pdf')) {
        $pdfPath = $request->file('published_pdf')->store('published/articles', 'public');
    }

    $article = $publicationService->publish(
        $submission,
        (int) $validated['issue_id'],
        $pdfPath,
        $validated['note'] ?? null
    );

    $submission->author->notify(new ArticlePublishedNotification($article));

    foreach ($submission->coAuthors as $coAuthor) {
        if ($coAuthor->email) {
            \Illuminate\Support\Facades\Notification::route('mail', $coAuthor->email)
                ->notify(new ArticlePublishedNotification($article));
        }
    }

    return back()->with('success', 'Article published successfully.');
}

    public function downloadFile(Submission $submission, ArticleFile $file)
    {
        $isAdminRoute = request()->routeIs('admin.*');

        if ((int) $file->submission_id !== (int) $submission->id && !$isAdminRoute) {
            abort(404);
        }

        $resolvedPath = ((int) $file->submission_id === (int) $submission->id)
            ? $file->resolvedPrivatePath()
            : null;

        if (!$resolvedPath && $isAdminRoute) {
            $fallbackFile = $submission->files()
                ->get()
                ->sortBy(function (ArticleFile $candidate) {
                    return $candidate->file_type === 'manuscript' ? 0 : 1;
                })
                ->first(function (ArticleFile $candidate) {
                    return $candidate->resolvedPrivatePath() !== null;
                });

            if ($fallbackFile) {
                $file = $fallbackFile;
                $resolvedPath = $fallbackFile->resolvedPrivatePath();
            }
        }

        abort_unless($resolvedPath, 404);

        return Storage::disk('private')->download(
            $resolvedPath,
            $file->original_name
        );
    }

}
