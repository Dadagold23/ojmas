<?php

namespace App\Services\Workflow;

use App\Models\EditorialStage;
use App\Models\EditorialStageTransition;
use App\Models\ManuscriptWorkflowEvent;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The configurable editorial lifecycle.
 *
 * Two invariants hold here:
 *
 *  1. **`submissions.status` is never rewritten by a stage change.** The legacy
 *     ENUM keeps working for every existing query, view and filter; the richer
 *     `current_stage` is advanced alongside it and `legacy_status` on each
 *     stage records which ENUM value that stage corresponds to.
 *  2. **A transition is refused unless the catalogue says it is allowed and the
 *     actor holds the required permission.** Configuration is not a suggestion.
 */
class EditorialWorkflowService
{
    /**
     * The built-in OJMAS lifecycle, mirroring the process the journal already
     * follows, expressed as data so it can be edited without a deployment.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function defaultStages(): array
    {
        return [
            [
                'slug' => 'initial_submission',
                'name' => 'Initial Submission',
                'description' => 'The manuscript has been submitted and the vetting fee is awaited.',
                'sort_order' => 10,
                'is_initial' => true,
                'requires_payment' => true,
                'default_deadline_days' => null,
                'legacy_status' => 'pending_payment',
                'required_permissions' => [],
                'colour_hint' => 'secondary',
            ],
            [
                'slug' => 'technical_validation',
                'name' => 'Formatting and Technical Validation',
                'description' => 'Automated APA 7 formatting and validation are run on the submitted file.',
                'sort_order' => 20,
                'default_deadline_days' => 3,
                'legacy_status' => 'submitted',
                'required_permissions' => ['manuscript.view'],
                'colour_hint' => 'info',
            ],
            [
                'slug' => 'editorial_screening',
                'name' => 'Editorial Screening',
                'description' => 'An editor confirms scope, ethics and basic completeness.',
                'sort_order' => 30,
                'default_deadline_days' => 7,
                'blinded_at_this_stage' => true,
                'legacy_status' => 'screening',
                'required_permissions' => ['workflow.transition'],
                'colour_hint' => 'info',
            ],
            [
                'slug' => 'reviewer_assignment',
                'name' => 'Reviewer Assignment and Invitation',
                'description' => 'Reviewers are invited and their responses are tracked.',
                'sort_order' => 40,
                'default_deadline_days' => 10,
                'blinded_at_this_stage' => true,
                'legacy_status' => 'under_review',
                'required_permissions' => ['review.assign'],
                'colour_hint' => 'primary',
            ],
            [
                'slug' => 'peer_review',
                'name' => 'Peer Review',
                'description' => 'Reviewers read the anonymised manuscript and submit reports.',
                'sort_order' => 50,
                'default_deadline_days' => 21,
                'blinded_at_this_stage' => true,
                'legacy_status' => 'under_review',
                'required_permissions' => ['review.view.assigned'],
                'colour_hint' => 'primary',
            ],
            [
                'slug' => 'editorial_decision',
                'name' => 'Editorial Decision',
                'description' => 'The editor records a decision from the reviewer recommendations.',
                'sort_order' => 60,
                'default_deadline_days' => 7,
                'legacy_status' => 'under_review',
                'required_permissions' => ['decision.record'],
                'colour_hint' => 'warning',
            ],
            [
                'slug' => 'author_revision',
                'name' => 'Author Revision',
                'description' => 'The author revises the manuscript within the revision window.',
                'sort_order' => 70,
                'default_deadline_days' => 30,
                'requires_author_approval' => true,
                'legacy_status' => 'revision_requested',
                'required_permissions' => ['submission.respond.revision'],
                'colour_hint' => 'warning',
            ],
            [
                'slug' => 'further_review',
                'name' => 'Further Review',
                'description' => 'A revised manuscript returns to reviewers where required.',
                'sort_order' => 80,
                'default_deadline_days' => 21,
                'blinded_at_this_stage' => true,
                'legacy_status' => 'under_review',
                'required_permissions' => ['review.view.assigned'],
                'colour_hint' => 'primary',
            ],
            [
                'slug' => 'accepted',
                'name' => 'Accepted',
                'description' => 'The manuscript is accepted; the publication fee is awaited.',
                'sort_order' => 90,
                'requires_payment' => true,
                'legacy_status' => 'accepted',
                'required_permissions' => ['decision.record'],
                'colour_hint' => 'success',
            ],
            [
                'slug' => 'rejected',
                'name' => 'Rejected',
                'description' => 'The manuscript has been declined.',
                'sort_order' => 95,
                'is_terminal' => true,
                'legacy_status' => 'rejected',
                'required_permissions' => ['decision.record'],
                'colour_hint' => 'danger',
            ],
            [
                'slug' => 'copyediting',
                'name' => 'Copyediting',
                'description' => 'Copyediting against the accepted manuscript.',
                'sort_order' => 100,
                'default_deadline_days' => 14,
                'legacy_status' => 'accepted',
                'required_permissions' => ['manuscript.edit'],
                'colour_hint' => 'info',
            ],
            [
                'slug' => 'proof_approval',
                'name' => 'Proofing and Author Approval',
                'description' => 'Proofs are issued and approved by the author.',
                'sort_order' => 110,
                'default_deadline_days' => 7,
                'legacy_status' => 'accepted',
                'required_permissions' => ['manuscript.edit'],
                'colour_hint' => 'info',
            ],
            [
                'slug' => 'published',
                'name' => 'Published',
                'description' => 'The article is published in an issue.',
                'sort_order' => 120,
                'is_terminal' => true,
                'legacy_status' => 'published',
                'required_permissions' => ['publication.publish'],
                'colour_hint' => 'success',
            ],
        ];
    }

    /**
     * The allowed transitions between the built-in stages.
     *
     * Each transition names the permission needed to *perform it*, which is
     * not the same as the capability needed to work *at* the destination: a
     * manuscript entering author revision is moved by an editor holding
     * `decision.record`, not by the author.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function defaultTransitions(): array
    {
        return [
            ['from' => 'initial_submission', 'to' => 'technical_validation', 'label' => 'Fee received, begin technical validation', 'permission' => 'workflow.transition'],
            ['from' => 'technical_validation', 'to' => 'editorial_screening', 'label' => 'Technical checks passed', 'permission' => 'workflow.transition'],
            ['from' => 'editorial_screening', 'to' => 'reviewer_assignment', 'label' => 'Screening passed, invite reviewers', 'permission' => 'review.assign'],
            ['from' => 'editorial_screening', 'to' => 'rejected', 'label' => 'Decline at screening', 'permission' => 'decision.record'],
            ['from' => 'reviewer_assignment', 'to' => 'peer_review', 'label' => 'Reviewers assigned and responding', 'permission' => 'review.assign'],
            ['from' => 'peer_review', 'to' => 'editorial_decision', 'label' => 'Reports submitted', 'permission' => 'decision.record'],
            ['from' => 'editorial_decision', 'to' => 'author_revision', 'label' => 'Request revision', 'permission' => 'decision.record'],
            ['from' => 'editorial_decision', 'to' => 'further_review', 'label' => 'Send to further review', 'permission' => 'decision.record'],
            ['from' => 'editorial_decision', 'to' => 'accepted', 'label' => 'Accept', 'permission' => 'decision.record'],
            ['from' => 'editorial_decision', 'to' => 'rejected', 'label' => 'Reject', 'permission' => 'decision.record'],
            ['from' => 'author_revision', 'to' => 'further_review', 'label' => 'Revised manuscript returned', 'permission' => 'decision.record'],
            ['from' => 'author_revision', 'to' => 'copyediting', 'label' => 'Revision accepted', 'permission' => 'decision.record'],
            ['from' => 'further_review', 'to' => 'editorial_decision', 'label' => 'Further reports submitted', 'permission' => 'decision.record'],
            ['from' => 'copyediting', 'to' => 'proof_approval', 'label' => 'Copyediting complete', 'permission' => 'manuscript.edit'],
            ['from' => 'proof_approval', 'to' => 'published', 'label' => 'Proofs approved', 'permission' => 'publication.publish'],
        ];
    }

    public function seed(): array
    {
        $stages = 0;
        $transitions = 0;

        foreach (self::defaultStages() as $definition) {
            $stage = EditorialStage::firstOrNew(['slug' => $definition['slug']]);
            $stage->fill($definition + ['is_active' => true]);
            $stage->save();
            $stages++;
        }

        foreach (self::defaultTransitions() as $definition) {
            $transition = EditorialStageTransition::firstOrNew([
                'from_stage_slug' => $definition['from'],
                'to_stage_slug' => $definition['to'],
            ]);
            $transition->fill([
                'label' => $definition['label'],
                'required_permissions' => [(string) ($definition['permission'] ?? 'workflow.transition')],
                'notifies_author' => in_array($definition['to'], ['author_revision', 'accepted', 'rejected'], true),
                'opens_revision_window' => $definition['to'] === 'author_revision',
                'closes_revision_window' => in_array($definition['from'], ['author_revision'], true),
                'requires_note' => in_array($definition['to'], ['rejected', 'author_revision'], true),
            ]);
            $transition->save();
            $transitions++;
        }

        return ['stages' => $stages, 'transitions' => $transitions];
    }

    /** @return array<int, string> */
    private function transitionPermissions(string $toStage): array
    {
        // Kept for callers that still want the destination's own requirement.
        $stage = collect(self::defaultStages())->firstWhere('slug', $toStage);

        return (array) ($stage['required_permissions'] ?? []);
    }

    /** @return array<int, EditorialStage> */
    public function activeStages(): array
    {
        return EditorialStage::where('is_active', true)->orderBy('sort_order')->get()->all();
    }

    public function stage(string $slug): ?EditorialStage
    {
        return EditorialStage::where('slug', $slug)->first();
    }

    public function currentStage(Submission $submission): ?EditorialStage
    {
        return EditorialStage::where('slug', $submission->current_stage)->first()
            ?? $this->initialStage();
    }

    public function initialStage(): ?EditorialStage
    {
        return EditorialStage::where('is_initial', true)->orderBy('sort_order')->first();
    }

    /**
     * Transitions available from the submission's current stage, filtered to
     * those the given actor is actually allowed to perform.
     *
     * @return array<int, EditorialStageTransition>
     */
    public function availableTransitions(Submission $submission, User $actor): array
    {
        $from = $submission->current_stage ?: ($this->initialStage()?->slug);

        if ($from === null) {
            return [];
        }

        return EditorialStageTransition::where('from_stage_slug', $from)
            ->get()
            ->filter(fn (EditorialStageTransition $transition) => $this->actorMayTransition($actor, $transition))
            ->values()
            ->all();
    }

    /**
     * @return array<int, EditorialStageTransition> Everything configured, for
     *                                      the stage-configuration screens.
     */
    public function configuredTransitions(): array
    {
        return EditorialStageTransition::orderBy('id')->get()->all();
    }

    public function actorMayTransition(User $actor, EditorialStageTransition $transition): bool
    {
        foreach ((array) ($transition->required_permissions ?? []) as $permission) {
            if (! $actor->can($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Applies a stage transition.
     *
     * @return array{ok: bool, message: string, submission: Submission}
     */
    public function transition(
        Submission $submission,
        string $toStage,
        User $actor,
        ?string $reason = null,
        bool $notifyAuthor = false,
    ): array {
        $from = $submission->current_stage ?: ($this->initialStage()?->slug);

        if ($toStage === $from) {
            return ['ok' => false, 'message' => 'The manuscript is already at that stage.', 'submission' => $submission];
        }

        $transition = EditorialStageTransition::where('from_stage_slug', $from)
            ->where('to_stage_slug', $toStage)
            ->first();

        if ($transition === null) {
            return [
                'ok' => false,
                'message' => "No transition from \"{$from}\" to \"{$toStage}\" is configured.",
                'submission' => $submission,
            ];
        }

        if (! $this->actorMayTransition($actor, $transition)) {
            return [
                'ok' => false,
                'message' => 'You do not have permission to make that transition.',
                'submission' => $submission,
            ];
        }

        if ($transition->requires_note && trim((string) $reason) === '') {
            return [
                'ok' => false,
                'message' => 'This transition requires a written reason.',
                'submission' => $submission,
            ];
        }

        $stage = $this->stage($toStage);

        if ($stage === null) {
            return ['ok' => false, 'message' => 'That stage is not configured.', 'submission' => $submission];
        }

        $result = DB::transaction(function () use ($submission, $from, $stage, $actor, $reason, $transition) {
            // Capture BEFORE the mutation, so the history rows record an
            // honest from → to pair (Module 4: decision history accuracy).
            $oldStatus = $submission->status;

            $submission->current_stage = $stage->slug;

            // Keep the legacy ENUM in step so existing views and filters behave.
            $legacyStatus = $stage->legacy_status;

            if ($legacyStatus !== null && $submission->status !== $legacyStatus) {
                $submission->status = $legacyStatus;
            }

            if ($stage->default_deadline_days !== null) {
                $submission->decision_due_at = now()->addDays($stage->default_deadline_days);
            }

            $submission->save();

            // A transition that opens the revision window IS an author-revision
            // request: the round and the deadline are created here, exactly as
            // the decision endpoint does, so both paths stay consistent.
            $openedRound = null;

            if ($transition->opens_revision_window) {
                $submission->revision_round = (int) $submission->revision_round + 1;
                $submission->save();

                $openedRound = $submission->revisionRounds()->create([
                    'round_number' => (int) $submission->revision_round,
                    'status' => \App\Models\RevisionRound::STATUS_OPEN,
                    'opened_by' => $actor->id,
                    'opened_at' => now(),
                    'due_at' => $submission->decision_due_at,
                ]);
            }

            // Entering copyediting (or any stage whose arrival ends a revision
            // window) closes any round still awaiting the author.
            if ($transition->closes_revision_window) {
                \App\Models\RevisionRound::where('submission_id', $submission->id)
                    ->where('status', \App\Models\RevisionRound::STATUS_OPEN)
                    ->update(['status' => \App\Models\RevisionRound::STATUS_CLOSED, 'closed_at' => now()]);
            }

            \App\Models\SubmissionStatusHistory::create([
                'submission_id' => $submission->id,
                'changed_by' => $actor->id,
                'old_status' => $oldStatus,
                'new_status' => $submission->status,
                'note' => sprintf(
                    'Stage moved from %s to %s%s',
                    $from ?? 'initial',
                    $stage->name,
                    $reason !== null && trim($reason) !== '' ? ': '.$reason : '',
                ),
            ]);

            $this->log($submission, $actor, 'stage_transition', $from, $stage->slug, array_filter([
                'transition' => $transition->label,
                'reason' => $reason,
                'revision_round' => $openedRound?->round_number,
                'notifies_author' => (bool) $transition->notifies_author,
            ], fn ($value) => $value !== null));

            return $submission->fresh();
        });

        // Author notifications ride the existing notification architecture.
        // They deliberately run after the commit so a mail failure cannot
        // roll the stage change back out from under the record.
        if ($transition->notifies_author && $result->author !== null) {
            match ($result->status) {
                'revision_requested' => $result->author->notify(new \App\Notifications\RevisionRequestedNotification(
                    $result,
                    (string) $reason,
                )),
                'accepted' => $result->author->notify(new \App\Notifications\SubmissionAcceptedNotification($result)),
                'rejected' => $result->author->notify(new \App\Notifications\SubmissionRejectedNotification(
                    $result,
                    (string) $reason,
                )),
                default => null,
            };
        }

        return [
            'ok' => true,
            'message' => "Moved to {$stage->name}.",
            'submission' => $result,
            'stage' => $stage,
            'transition' => $transition,
        ];
    }

    /**
     * Appends to the workflow log. Never updated or deleted.
     */
    public function log(
        Submission $submission,
        ?User $actor,
        string $event,
        ?string $from = null,
        ?string $to = null,
        array $metadata = [],
    ): ManuscriptWorkflowEvent {
        return ManuscriptWorkflowEvent::create([
            'submission_id' => $submission->id,
            'from_stage' => $from,
            'to_stage' => $to,
            'actor_id' => $actor?->id,
            'event' => $event,
            'reason' => $metadata['reason'] ?? null,
            'metadata' => $metadata ?: null,
            'ip_address' => request()?->ip(),
        ]);
    }

    /** @return array<int, ManuscriptWorkflowEvent> */
    public function historyFor(Submission $submission, int $limit = 100): array
    {
        return ManuscriptWorkflowEvent::where('submission_id', $submission->id)
            ->with('actor')
            ->latest()
            ->limit($limit)
            ->get()
            ->all();
    }
}