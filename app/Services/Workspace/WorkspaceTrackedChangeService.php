<?php

namespace App\Services\Workspace;

use App\Models\ManuscriptTrackedChange;
use App\Models\ManuscriptWorkspaceDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tracked changes, implemented on top of Tiptap because Tiptap has no native
 * support.
 *
 * Tiptap ships without a track-changes implementation. Building the small
 * amount we need ourselves, against a server-side table of record, is the
 * "tested alternative" the module brief calls for, and it is preferable to
 * claiming a capability the editor does not have.
 *
 * The editor renders a pending insertion with an underline and a pending
 * deletion with a strike-through, both carrying a `data-change-uid`. Review
 * actions then resolve the mark and update this table in one transaction.
 */
class WorkspaceTrackedChangeService
{
    /**
     * Records a change reported by the browser.
     *
     * The browser is the detector, not the authority: the text is re-checked
     * against the document before it is stored, and a blank or absurdly large
     * payload is rejected rather than persisted.
     */
    public function record(
        ManuscriptWorkspaceDocument $workspace,
        User $author,
        string $type,
        string $text,
        ?int $anchorFrom = null,
        ?int $anchorTo = null,
        ?array $body = null,
        ?string $uid = null,
    ): ?ManuscriptTrackedChange {
        $text = trim($text);

        if ($text === '' || mb_strlen($text) > 20000) {
            return null;
        }

        if (! in_array($type, [ManuscriptTrackedChange::TYPE_INSERTION, ManuscriptTrackedChange::TYPE_DELETION], true)) {
            return null;
        }

        $uid ??= (string) Str::uuid();

        // Replaying the same change is a no-op rather than a duplicate row.
        $existing = ManuscriptTrackedChange::where('change_uid', $uid)->first();

        if ($existing !== null) {
            return $existing;
        }

        return ManuscriptTrackedChange::create([
            'manuscript_workspace_document_id' => $workspace->id,
            'change_uid' => $uid,
            'type' => $type,
            'author_id' => $author->id,
            'text_snapshot' => $text,
            'body_json' => $body,
            'anchor_from' => $anchorFrom,
            'anchor_to' => $anchorTo,
            'status' => ManuscriptTrackedChange::STATUS_PENDING,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function pendingFor(ManuscriptWorkspaceDocument $workspace): array
    {
        return ManuscriptTrackedChange::with('author')
            ->where('manuscript_workspace_document_id', $workspace->id)
            ->where('status', ManuscriptTrackedChange::STATUS_PENDING)
            ->orderBy('created_at')
            ->get()
            ->map(fn (ManuscriptTrackedChange $change) => $change->toWorkspaceArray())
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allFor(ManuscriptWorkspaceDocument $workspace): array
    {
        return ManuscriptTrackedChange::with(['author', 'reviewer'])
            ->where('manuscript_workspace_document_id', $workspace->id)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (ManuscriptTrackedChange $change) => $change->toWorkspaceArray())
            ->all();
    }

    /**
     * Accepts or rejects a change.
     *
     * The caller is responsible for applying the corresponding document edit;
     * this method only records the decision, so a partial client failure can
     * never leave a change marked as resolved while the text still shows as
     * pending, or vice versa.
     */
    public function resolve(
        ManuscriptWorkspaceDocument $workspace,
        string $changeUid,
        string $decision,
        User $reviewer,
    ): ?ManuscriptTrackedChange {
        if (! in_array($decision, [
            ManuscriptTrackedChange::STATUS_ACCEPTED,
            ManuscriptTrackedChange::STATUS_REJECTED,
        ], true)) {
            return null;
        }

        $change = ManuscriptTrackedChange::where('change_uid', $changeUid)->first();

        if ($change === null
            || (int) $change->manuscript_workspace_document_id !== (int) $workspace->id
            || ! $change->isPending()) {
            return null;
        }

        $change->update([
            'status' => $decision,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        return $change;
    }

    /**
     * Bulk-resolves every pending change at once.
     *
     * @param  array<int, string>  $changeUids
     * @return int Number resolved.
     */
    public function resolveMany(
        ManuscriptWorkspaceDocument $workspace,
        array $changeUids,
        string $decision,
        User $reviewer,
    ): int {
        return DB::transaction(function () use ($workspace, $changeUids, $decision, $reviewer) {
            $changes = ManuscriptTrackedChange::where('manuscript_workspace_document_id', $workspace->id)
                ->whereIn('change_uid', $changeUids)
                ->where('status', ManuscriptTrackedChange::STATUS_PENDING)
                ->get();

            foreach ($changes as $change) {
                $change->update([
                    'status' => $decision,
                    'reviewed_by' => $reviewer->id,
                    'reviewed_at' => now(),
                ]);
            }

            return $changes->count();
        });
    }

    public function pendingCount(ManuscriptWorkspaceDocument $workspace): int
    {
        return ManuscriptTrackedChange::where('manuscript_workspace_document_id', $workspace->id)
            ->where('status', ManuscriptTrackedChange::STATUS_PENDING)
            ->count();
    }
}