<?php

namespace App\Services\Workspace;

use App\Models\ManuscriptComment;
use App\Models\ManuscriptWorkspaceDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Contextual comment threads.
 *
 * Visibility is enforced here, not in the view. A request from an author can
 * never read or write a `reviewer_confidential` thread, and a reviewer can
 * never create an `author_visible` thread that bypasses the editor's control.
 */
class WorkspaceCommentService
{
    /**
     * Which visibilities a role may read and write.
     *
     * @var array<string, array{read: array<int, string>, write: array<int, string>}>
     */
    private const MATRIX = [
        'author' => [
            'read' => [ManuscriptComment::VISIBILITY_AUTHOR],
            'write' => [ManuscriptComment::VISIBILITY_AUTHOR],
        ],
        'editor' => [
            'read' => [
                ManuscriptComment::VISIBILITY_AUTHOR,
                ManuscriptComment::VISIBILITY_EDITORIAL,
                ManuscriptComment::VISIBILITY_REVIEWER_CONFIDENTIAL,
            ],
            'write' => [
                ManuscriptComment::VISIBILITY_AUTHOR,
                ManuscriptComment::VISIBILITY_EDITORIAL,
                ManuscriptComment::VISIBILITY_REVIEWER_CONFIDENTIAL,
            ],
        ],
        'reviewer' => [
            'read' => [ManuscriptComment::VISIBILITY_EDITORIAL, ManuscriptComment::VISIBILITY_REVIEWER_CONFIDENTIAL],
            'write' => [ManuscriptComment::VISIBILITY_EDITORIAL, ManuscriptComment::VISIBILITY_REVIEWER_CONFIDENTIAL],
        ],
        'admin' => [
            'read' => [
                ManuscriptComment::VISIBILITY_AUTHOR,
                ManuscriptComment::VISIBILITY_EDITORIAL,
                ManuscriptComment::VISIBILITY_REVIEWER_CONFIDENTIAL,
            ],
            'write' => [ManuscriptComment::VISIBILITY_EDITORIAL],
        ],
    ];

    /** @return array<string, array{read: array<int, string>, write: array<int, string>}> */
    public function matrix(): array
    {
        return self::MATRIX;
    }

    public function capabilitiesFor(User $user): string
    {
        if ($user->hasAnyRole(['Super Admin', 'Admin', 'Managing Editor', 'Managing Admin'])) {
            return 'admin';
        }

        if ($user->hasAnyRole(['Editor'])) {
            return 'editor';
        }

        if ($user->hasAnyRole(['Reviewer'])) {
            return 'reviewer';
        }

        return 'author';
    }

    /** @return array<int, string> */
    public function readableVisibilities(User $user): array
    {
        return self::MATRIX[$this->capabilitiesFor($user)]['read'];
    }

    /** @return array<int, string> */
    public function writableVisibilities(User $user): array
    {
        return self::MATRIX[$this->capabilitiesFor($user)]['write'];
    }

    /**
     * The visibility a comment gets when the author does not choose one.
     *
     * An author has no choice here: their only writable visibility is
     * author-visible. An editor or reviewer defaults to an editorial thread,
     * and a Super Admin — who may not create author-visible threads directly —
     * defaults to editorial too.
     */
    public function defaultVisibilityFor(User $user): string
    {
        $writable = $this->writableVisibilities($user);

        return in_array(ManuscriptComment::VISIBILITY_AUTHOR, $writable, true)
            ? ManuscriptComment::VISIBILITY_AUTHOR
            : ManuscriptComment::VISIBILITY_EDITORIAL;
    }

    public function canRead(User $user, ManuscriptComment $comment): bool
    {
        return in_array($comment->visibility, $this->readableVisibilities($user), true);
    }

    public function canWrite(User $user, ManuscriptComment $comment): bool
    {
        return in_array($comment->visibility, $this->writableVisibilities($user), true);
    }

    /**
     * All threads visible to the given user, as a nested tree.
     *
     * @return array<int, array<string, mixed>>
     */
    public function threadsFor(ManuscriptWorkspaceDocument $workspace, User $user): array
    {
        $visibilities = $this->readableVisibilities($user);

        $comments = ManuscriptComment::with(['author', 'resolver', 'replies.author', 'replies.resolver'])
            ->where('manuscript_workspace_document_id', $workspace->id)
            ->whereIn('visibility', $visibilities)
            ->orderBy('created_at')
            ->get();

        $byParent = [];

        foreach ($comments as $comment) {
            $byParent[$comment->parent_id ?? 0][] = $comment;
        }

        $build = function (int $parentId) use (&$build, $byParent): array {
            $out = [];

            foreach ($byParent[$parentId] ?? [] as $comment) {
                $payload = $comment->toWorkspaceArray();
                $payload['replies'] = $build((int) $comment->id);
                $out[] = $payload;
            }

            return $out;
        };

        return $build(0);
    }

    /**
     * Creates a thread anchored to a document range.
     */
    public function create(
        ManuscriptWorkspaceDocument $workspace,
        User $author,
        string $body,
        ?int $anchorFrom = null,
        ?int $anchorTo = null,
        ?string $quotedText = null,
        string $visibility = ManuscriptComment::VISIBILITY_EDITORIAL,
        ?int $parentId = null,
    ): ?ManuscriptComment {
        $body = trim($body);

        if ($body === '') {
            return null;
        }

        if ($parentId !== null) {
            $parent = ManuscriptComment::whereKey($parentId)->first();

            if ($parent === null
                || (int) $parent->manuscript_workspace_document_id !== (int) $workspace->id
                || ! $this->canRead($author, $parent)) {
                return null;
            }

            // A reply always inherits its thread's visibility. It is validated
            // against the parent rather than the request, so a reply can neither
            // downgrade a confidential thread nor smuggle confidential content
            // into an author-visible one.
            $visibility = $parent->visibility;
        } elseif (! in_array($visibility, $this->writableVisibilities($author), true)) {
            return null;
        }

        return ManuscriptComment::create([
            'manuscript_workspace_document_id' => $workspace->id,
            'manuscript_document_id' => $workspace->manuscript_document_id,
            'parent_id' => $parentId,
            'anchor_from' => $anchorFrom,
            'anchor_to' => $anchorTo,
            'quoted_text' => $quotedText !== null ? mb_substr($quotedText, 0, 1000) : null,
            'anchor_signature' => $this->signature($quotedText),
            'anchor_valid' => true,
            'revision_at_creation' => (int) $workspace->revision,
            'body' => $body,
            'visibility' => $visibility,
            'status' => ManuscriptComment::STATUS_OPEN,
            'author_id' => $author->id,
        ]);
    }

    public function resolve(ManuscriptWorkspaceDocument $workspace, ManuscriptComment $comment, User $user): bool
    {
        if ((int) $comment->manuscript_workspace_document_id !== (int) $workspace->id) {
            return false;
        }

        if (! $this->canWrite($user, $comment)) {
            return false;
        }

        $comment->update([
            'status' => ManuscriptComment::STATUS_RESOLVED,
            'resolved_by' => $user->id,
            'resolved_at' => now(),
        ]);

        return true;
    }

    public function reopen(ManuscriptWorkspaceDocument $workspace, ManuscriptComment $comment, User $user): bool
    {
        if ((int) $comment->manuscript_workspace_document_id !== (int) $workspace->id) {
            return false;
        }

        if (! $this->canWrite($user, $comment)) {
            return false;
        }

        $comment->update([
            'status' => ManuscriptComment::STATUS_REOPENED,
            'resolved_by' => null,
            'resolved_at' => null,
        ]);

        return true;
    }

    /**
     * Re-checks every anchor against the current document.
     *
     * A thread whose quoted text is no longer present is marked invalid but is
     * kept: an editorial conversation must survive an edit that removes the
     * text it was discussing.
     *
     * @return int Number of anchors invalidated.
     */
    public function refreshAnchors(ManuscriptWorkspaceDocument $workspace): int
    {
        $plain = mb_strtolower((string) $workspace->plain_text);
        $invalidated = 0;

        return DB::transaction(function () use ($workspace, $plain, &$invalidated) {
            ManuscriptComment::where('manuscript_workspace_document_id', $workspace->id)
                ->whereNotNull('anchor_signature')
                ->where('anchor_valid', true)
                ->each(function (ManuscriptComment $comment) use ($plain, &$invalidated) {
                    $signature = (string) $comment->anchor_signature;

                    if ($signature === '' || str_contains($plain, $signature)) {
                        return;
                    }

                    $comment->update(['anchor_valid' => false]);
                    $invalidated++;
                });

            return $invalidated;
        });
    }

    /**
     * A stable fingerprint of the quoted text, used to detect that an anchor
     * has drifted away from what it originally pointed at.
     */
    private function signature(?string $quotedText): ?string
    {
        if ($quotedText === null) {
            return null;
        }

        $normalised = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $quotedText) ?? ''));

        if ($normalised === '') {
            return null;
        }

        return mb_substr($normalised, 0, 120);
    }
}