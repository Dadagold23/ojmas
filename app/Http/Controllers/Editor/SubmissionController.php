<?php

namespace App\Http\Controllers\Editor;

use App\Http\Controllers\Controller;
use App\Models\CorrespondenceMessage;
use App\Models\ReviewerAssignment;
use App\Models\Submission;
use App\Models\SubmissionStatusHistory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Models\Article;
use App\Models\Issue;
use App\Services\Publishing\PublicationService;


class SubmissionController extends Controller
{
    public function index()
    {
        $submissions = Submission::with('author')
            ->latest()
            ->paginate(15);

        return view('auth.editor.submissions.index', compact('submissions'));
    }

    public function show(Submission $submission)
    {
        $submission->load(['author', 'articleAuthors']);

        $reviewers = User::role('Reviewer')->get();

        return view('auth.editor.submissions.show', compact('submission', 'reviewers'));
    }

    public function assignReviewer(Request $request, Submission $submission): RedirectResponse
    {
        abort_unless($submission->hasPaidVettingFee(), 422, 'Vetting fee must be paid before reviewer assignment can begin.');

        $validated = $request->validate([
            'reviewer_id' => ['required', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
        ]);

        $reviewer = User::findOrFail($validated['reviewer_id']);

        ReviewerAssignment::create([
            'submission_id' => $submission->id,
            'reviewer_id' => $reviewer->id,
            'due_date' => $validated['due_date'] ?? now()->addDays(14)->toDateString(),
            'status' => 'pending',
        ]);

        $oldStatus = $submission->status;

        $submission->update([
            'status' => 'under_review',
        ]);

        $this->logStatusHistory($submission->id, $oldStatus, 'under_review', 'Reviewer assigned by editor.');

        $message = "You have been assigned as reviewer for the manuscript titled '{$submission->title}'. Due date: " .
            ($validated['due_date'] ?? now()->addDays(14)->format('Y-m-d')) . ".";

        $this->logCorrespondence(
            $submission->id,
            'reviewer_assignment',
            "Reviewer Assignment - {$submission->submission_no}",
            $message,
            $reviewer->email
        );

        $this->sendBasicMail(
            $reviewer->email,
            "Reviewer Assignment - {$submission->submission_no}",
            $message
        );

        return back()->with('success', 'Reviewer assigned successfully.');
    }

    public function requestRevision(Request $request, Submission $submission): RedirectResponse
    {
        abort_unless($submission->hasPaidVettingFee(), 422, 'Vetting fee must be paid before editorial processing can continue.');

        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:5000'],
        ]);

        $oldStatus = $submission->status;

        $submission->update([
            'status' => 'revision_requested',
        ]);

        $remarks = $validated['remarks'] ?? 'Revision has been requested by the editor.';

        $this->logStatusHistory($submission->id, $oldStatus, 'revision_requested', $remarks);

        $message = "Revision has been requested for your submission titled '{$submission->title}'. " .
            "Editor remarks: {$remarks}";

        $this->logCorrespondence(
            $submission->id,
            'revision_request',
            "Revision Requested - {$submission->submission_no}",
            $message,
            $submission->author->email ?? null
        );

        if (!empty($submission->author?->email)) {
            $this->sendBasicMail(
                $submission->author->email,
                "Revision Requested - {$submission->submission_no}",
                $message
            );
        }

        return back()->with('success', 'Revision request sent successfully.');
    }

    public function accept(Request $request, Submission $submission): RedirectResponse
    {
        abort_unless($submission->hasPaidVettingFee(), 422, 'Vetting fee must be paid before a submission can be accepted.');

        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:5000'],
        ]);

        $oldStatus = $submission->status;

        $submission->update([
            'status' => 'accepted',
        ]);

        $remarks = $validated['remarks'] ?? 'Your submission has been accepted for publication processing.';

        $this->logStatusHistory($submission->id, $oldStatus, 'accepted', $remarks);

        $message = "Congratulations. Your submission titled '{$submission->title}' has been accepted. " .
            "Editor remarks: {$remarks}";

        $this->logCorrespondence(
            $submission->id,
            'acceptance',
            "Submission Accepted - {$submission->submission_no}",
            $message,
            $submission->author->email ?? null
        );

        if (!empty($submission->author?->email)) {
            $this->sendBasicMail(
                $submission->author->email,
                "Submission Accepted - {$submission->submission_no}",
                $message
            );
        }

        return back()->with('success', 'Submission accepted successfully.');
    }

    public function reject(Request $request, Submission $submission): RedirectResponse
    {
        abort_unless($submission->hasPaidVettingFee(), 422, 'Vetting fee must be paid before a submission can be rejected through editorial processing.');

        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:5000'],
        ]);

        $oldStatus = $submission->status;

        $submission->update([
            'status' => 'rejected',
        ]);

        $remarks = $validated['remarks'] ?? 'Your submission was not accepted for publication.';

        $this->logStatusHistory($submission->id, $oldStatus, 'rejected', $remarks);

        $message = "Your submission titled '{$submission->title}' has been rejected. " .
            "Editor remarks: {$remarks}";

        $this->logCorrespondence(
            $submission->id,
            'rejection',
            "Submission Rejected - {$submission->submission_no}",
            $message,
            $submission->author->email ?? null
        );

        if (!empty($submission->author?->email)) {
            $this->sendBasicMail(
                $submission->author->email,
                "Submission Rejected - {$submission->submission_no}",
                $message
            );
        }

        return back()->with('success', 'Submission rejected successfully.');
    }

    protected function logStatusHistory(int $submissionId, ?string $fromStatus, string $toStatus, ?string $remarks = null): void
    {
        if (class_exists(SubmissionStatusHistory::class)) {
            SubmissionStatusHistory::create([
                'submission_id' => $submissionId,
                'old_status' => $fromStatus,
                'new_status' => $toStatus,
                'note' => $remarks,
            ]);
        }
    }

    protected function logCorrespondence(
        int $submissionId,
        string $type,
        string $subject,
        string $message,
        ?string $recipientEmail = null
    ): void {
        if (class_exists(CorrespondenceMessage::class)) {
            CorrespondenceMessage::create([
                'submission_id' => $submissionId,
                'type' => $type,
                'subject' => $subject,
                'message' => $message,
                'recipient_email' => $recipientEmail,
                'status' => 'sent',
            ]);
        }
    }

    protected function sendBasicMail(string $to, string $subject, string $message): void
    {
        try {
            Mail::raw($message, function ($mail) use ($to, $subject) {
                $mail->to($to)->subject($subject);
            });
        } catch (\Throwable $e) {
            // keep workflow running even if mail is not configured
        }
    }

public function publishForm(Submission $submission)
    {
        abort_unless($submission->hasPaidPublicationFee(), 422, 'Publication fee must be paid before publication can proceed.');
        $this->assertPublishable($submission);

        $issues = Issue::with('volume')
            ->orderByDesc('published_on')
            ->latest()
            ->get();

        return view('auth.editor.submissions.publish', compact('submission', 'issues'));
    }

    /**
     * A submission is publishable once it is accepted, and also once it has
     * been moved to `screening`, which is the state Admin\AdminController sets
     * when an editor is assigned for publication. This mirrors
     * EditorialController::publish().
     */
    private const PUBLISHABLE_STATUSES = ['accepted', 'screening', 'published'];

    private function assertPublishable(Submission $submission): void
    {
        abort_unless(
            in_array($submission->status, self::PUBLISHABLE_STATUSES, true),
            422,
            'Only accepted submissions can be published.'
        );
    }

    public function publish(Request $request, Submission $submission): RedirectResponse
    {
        abort_unless($submission->hasPaidPublicationFee(), 422, 'Publication fee must be paid before publication can proceed.');
        $this->assertPublishable($submission);

        $validated = $request->validate([
            'issue_id' => ['required', 'exists:issues,id'],
            'published_on' => ['nullable', 'date'],
            'full_text' => ['nullable', 'string'],
        ]);

        $issue = Issue::findOrFail($validated['issue_id']);
        $publishedAt = $validated['published_on'] ?? now()->toDateString();

        $oldStatus = $submission->status;

        // The Module 4 readiness gate is the single source of truth: publishing
        // happens through the service (which refuses, constructively, until
        // vetting, acceptance, review, revision closure, copyediting and proof
        // approval are all on record) instead of an Article::updateOrCreate
        // call that previously bypassed every approval.
        $article = app(PublicationService::class)->publish(
            $submission,
            $issue->id,
            null,
            "Published via the editor pipeline in {$issue->title}."
        );

        $article->update(array_filter([
            'published_on' => $publishedAt,
            'full_text' => $validated['full_text'] ?? null,
        ], fn ($value) => $value !== null));

        $this->logStatusHistory(
            $submission->id,
            $oldStatus,
            'published',
            "Submission published in {$issue->title}."
        );

        $message = "Your submission titled '{$submission->title}' has been published in '{$issue->title}'.";

        $this->logCorrespondence(
            $submission->id,
            'publication',
            "Submission Published - {$submission->submission_no}",
            $message,
            $submission->author->email ?? null
        );

        if (!empty($submission->author?->email)) {
            $this->sendBasicMail(
                $submission->author->email,
                "Submission Published - {$submission->submission_no}",
                $message
            );
        }

        return redirect()
            ->route('editor.submissions.show', $submission->id)
            ->with('success', 'Submission published successfully.');
    }

}
