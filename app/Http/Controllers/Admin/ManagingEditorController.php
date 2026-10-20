<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Review\ReviewerAssignmentService;
use App\Services\Workflow\EditorialDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The Managing Editor's operational dashboard (§3.4).
 *
 * Every action listed here is authorised separately on its own route. The
 * dashboard itself only reads, so it can never become a way to change a
 * manuscript.
 */
class ManagingEditorController extends Controller
{
    public function __construct(
        private readonly EditorialDashboardService $dashboard,
        private readonly ReviewerAssignmentService $assignments,
    ) {
    }

    public function index(Request $request)
    {
        $this->authorize('manageWorkflow', new \App\Models\Submission);

        return view('admin.workflow.dashboard', [
            'overview' => $this->dashboard->overview(),
            'awaitingDecision' => $this->dashboard->awaitingDecision(),
            'awaitingAssignment' => $this->dashboard->awaitingAssignment(),
            'overdue' => $this->dashboard->overdue(),
            'distribution' => $this->dashboard->stageDistribution(),
            'invitations' => $this->dashboard->reviewerInvitations(),
        ]);
    }

    public function reviewerTracking(Request $request)
    {
        $this->authorize('manageWorkflow', new \App\Models\Submission);

        $invitations = $this->dashboard->reviewerInvitations();

        return view('admin.workflow.reviewers', [
            'pending' => $invitations['pending'],
            'accepted' => $invitations['accepted'],
            'declined' => $invitations['declined'],
            'overdue' => $invitations['overdue'],
            'workload' => $this->dashboard->reviewerWorkload(),
        ]);
    }

    /**
     * A reminder is recorded here; the notification itself is queued so a slow
     * mail server cannot block the dashboard.
     */
    public function remind(Request $request, \App\Models\ReviewerInvitation $invitation)
    {
        $this->authorize('manageWorkflow', $invitation->submission);

        $this->assignments->recordReminder($invitation, $request->user());

        // §4.31: reminders go out through the existing notification
        // architecture, queued.
        if ($invitation->reviewer !== null) {
            $invitation->reviewer->notify(new \App\Notifications\ReviewerReminderNotification($invitation->fresh()));
        }

        return back()->with('success', 'A reminder has been recorded and sent for this reviewer.');
    }

    /**
     * The workflow report, as CSV.
     */
    public function report(Request $request)
    {
        $this->authorize('manageWorkflow', new \App\Models\Submission);

        $rows = $this->dashboard->workflowReport();

        if ($rows === []) {
            return back()->with('warning', 'There is nothing in the workflow to report yet.');
        }

        $filename = 'ojmas-workflow-report-'.now()->format('Y-m-d').'.csv';
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, array_keys($rows[0]));

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}