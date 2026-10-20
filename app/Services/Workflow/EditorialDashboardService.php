<?php

namespace App\Services\Workflow;

use App\Models\EditorialStage;
use App\Models\ReviewerAssignment;
use App\Models\ReviewerInvitation;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The operational view a Managing Editor needs (§3.4).
 *
 * Read-only: it counts and lists. Every action it points at is authorised
 * separately, so a dashboard can never become a way to change a manuscript.
 */
class EditorialDashboardService
{
    /**
     * Headline counts across the pipeline.
     *
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        return [
            'awaiting_decision' => $this->awaitingDecision()->count(),
            'awaiting_assignment' => $this->awaitingAssignment()->count(),
            'in_revision' => Submission::where('current_stage', 'author_revision')
                ->orWhere('status', 'revision_requested')
                ->count(),
            'in_peer_review' => Submission::where('status', 'under_review')->count(),
            'awaiting_payment' => Submission::where('status', 'pending_payment')->count(),
            'accepted_awaiting_production' => Submission::where('status', 'accepted')->count(),
            'published' => Submission::where('status', 'published')->count(),
            'overdue' => $this->overdue()->count(),
            'pending_reviewer_responses' => ReviewerInvitation::where('response', 'pending')->count(),
            'overdue_reviewer_responses' => ReviewerInvitation::where('response', 'pending')
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->count(),
        ];
    }

    /**
     * Manuscripts sitting in peer review with their reports in.
     *
     * @return \Illuminate\Support\Collection<int, Submission>
     */
    public function awaitingDecision()
    {
        return Submission::query()
            ->where('current_stage', 'editorial_decision')
            ->orWhere(function ($query) {
                $query->where('status', 'under_review')
                    ->whereHas('reviewerAssignments.review', fn ($q) => $q->where('status', 'submitted'));
            })
            ->with(['author', 'assignedEditor', 'reviewerAssignments.review'])
            ->latest('updated_at')
            ->get();
    }

    /** @return \Illuminate\Support\Collection<int, Submission> */
    public function awaitingAssignment()
    {
        return Submission::query()
            ->where(function ($query) {
                $query->where('current_stage', 'reviewer_assignment')
                    ->orWhere(function ($inner) {
                        $inner->where('status', 'submitted')
                            ->whereDoesntHave('reviewerAssignments');
                    });
            })
            ->with(['author', 'assignedEditor'])
            ->latest('updated_at')
            ->get();
    }

    /**
     * Manuscripts past their stage deadline.
     *
     * @return \Illuminate\Support\Collection<int, Submission>
     */
    public function overdue()
    {
        return Submission::query()
            ->whereNotNull('decision_due_at')
            ->where('decision_due_at', '<', now())
            ->whereNotIn('status', ['published', 'rejected'])
            ->with(['author', 'assignedEditor'])
            ->orderBy('decision_due_at')
            ->get();
    }

    /** @return \Illuminate\Support\Collection<int, Submission> */
    public function delayedByStage()
    {
        return Submission::query()
            ->whereNotNull('current_stage')
            ->whereNotIn('status', ['published', 'rejected'])
            ->with(['author', 'assignedEditor'])
            ->get()
            ->sortByDesc(fn (Submission $submission) => (int) $submission->decision_due_at?->diffInDays(now()) * -1)
            ->take(25)
            ->values();
    }

    /**
     * Reviewer invitation tracking.
     *
     * @return array{pending: \Illuminate\Support\Collection<int, ReviewerInvitation>, accepted: \Illuminate\Support\Collection<int, ReviewerInvitation>, declined: \Illuminate\Support\Collection<int, ReviewerInvitation>, overdue: \Illuminate\Support\Collection<int, ReviewerInvitation>}
     */
    public function reviewerInvitations(): array
    {
        $base = fn () => ReviewerInvitation::with(['reviewer', 'inviter', 'submission'])
            ->latest('invited_at')
            ->get();

        $all = $base();

        return [
            'pending' => $all->where('response', 'pending')->values(),
            'accepted' => $all->where('response', 'accepted')->values(),
            'declined' => $all->where('response', 'declined')->values(),
            'overdue' => $all->filter(fn (ReviewerInvitation $i) => $i->isOverdue())->values(),
        ];
    }

    /**
     * Assignment counts per stage, for a workflow report.
     *
     * @return array<string, int>
     */
    public function stageDistribution(): array
    {
        $distribution = EditorialStage::where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('name', 'slug')
            ->all();

        foreach (array_keys($distribution) as $slug) {
            $distribution[$slug] = 0;
        }

        Submission::query()
            ->selectRaw('current_stage, COUNT(*) as aggregate')
            ->whereNotNull('current_stage')
            ->groupBy('current_stage')
            ->get()
            ->each(function ($row) use (&$distribution) {
                $distribution[$row->current_stage] = (int) $row->aggregate;
            });

        // Manuscripts that have never entered a stage still need a home.
        $unstaged = Submission::whereNull('current_stage')
            ->whereNotIn('status', ['published', 'rejected'])
            ->count();

        if ($unstaged > 0) {
            $distribution['(not yet staged)'] = $unstaged;
        }

        return $distribution;
    }

    /**
     * Reviewer workload, so invitations go to people with capacity.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function reviewerWorkload()
    {
        return User::role('Reviewer')
            ->withCount(['reviewerAssignments as active_assignments' => fn ($q) => $q->where('status', 'assigned')])
            ->withCount(['reviewerAssignments as completed_assignments' => fn ($q) => $q->where('status', 'completed')])
            ->orderBy('active_assignments')
            ->get()
            ->map(fn (User $reviewer) => (object) [
                'reviewer' => $reviewer,
                'active' => (int) $reviewer->active_assignments,
                'completed' => (int) $reviewer->completed_assignments,
            ]);
    }

    /**
     * A CSV-ready workflow report.
     *
     * @return array<int, array<string, mixed>>
     */
    public function workflowReport(): array
    {
        return Submission::query()
            ->with(['author', 'assignedEditor'])
            ->whereIn('status', ['submitted', 'screening', 'under_review', 'revision_requested', 'accepted'])
            ->orderBy('updated_at')
            ->get()
            ->map(fn (Submission $submission) => [
                'submission_no' => $submission->submission_no,
                'title' => $submission->title,
                'stage' => $submission->current_stage,
                'status' => $submission->status,
                'author' => $submission->author?->name,
                'editor' => $submission->assignedEditor?->name,
                'reviewers' => $submission->reviewerAssignments()->count(),
                'reports' => $submission->reviewerAssignments()->whereHas('review', fn ($q) => $q->where('status', 'submitted'))->count(),
                'revision_round' => (int) $submission->revision_round,
                'due_at' => $submission->decision_due_at?->toDateString(),
                'days_open' => $submission->created_at?->diffInDays(now()),
            ])
            ->all();
    }
}