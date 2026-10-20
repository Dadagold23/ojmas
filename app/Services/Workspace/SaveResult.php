<?php

namespace App\Services\Workspace;

use App\Models\ManuscriptSnapshot;
use App\Models\ManuscriptWorkspaceDocument;

/**
 * The outcome of a save attempt.
 *
 * A save either succeeds, is rejected as a conflict, or is rejected as invalid.
 * The three cases must never be confused: silently accepting a stale write is
 * the one unacceptable outcome.
 */
final readonly class SaveResult
{
    public const OUTCOME_SAVED = 'saved';
    public const OUTCOME_CONFLICT = 'conflict';
    public const OUTCOME_INVALID = 'invalid';

    private function __construct(
        public string $outcome,
        public ManuscriptWorkspaceDocument $document,
        public ?int $acceptedRevision = null,
        public ?int $submittedRevision = null,
        public ?ManuscriptSnapshot $snapshot = null,
        public ?string $message = null,
        public ?array $serverContent = null,
        public ?array $rejectedContent = null,
    ) {
    }

    public static function saved(
        ManuscriptWorkspaceDocument $document,
        ManuscriptWorkspaceDocument $previous,
        ?ManuscriptSnapshot $snapshot = null,
    ): self {
        return new self(
            self::OUTCOME_SAVED,
            $document,
            $document->revision,
            $previous->revision,
            $snapshot,
            'Saved.',
        );
    }

    /**
     * A conflict carries BOTH versions so the browser can show the user what
     * happened and let them choose. Neither side is discarded: the stored
     * document keeps the first editor's work, and the rejected edit is handed
     * back intact in `rejected_content`.
     */
    public static function conflict(
        ManuscriptWorkspaceDocument $current,
        int $submittedRevision,
        array $rejectedContent,
    ): self {
        return new self(
            self::OUTCOME_CONFLICT,
            $current,
            $current->revision,
            $submittedRevision,
            null,
            'Your changes were not saved because this manuscript was edited by someone else while you were working. '
            .'Your version has been preserved so you can compare the two and merge them.',
            $current->content(),
            $rejectedContent,
        );
    }

    public static function invalid(ManuscriptWorkspaceDocument $current, string $message): self
    {
        return new self(self::OUTCOME_INVALID, $current, null, null, null, $message);
    }

    public function isSaved(): bool
    {
        return $this->outcome === self::OUTCOME_SAVED;
    }

    public function isConflict(): bool
    {
        return $this->outcome === self::OUTCOME_CONFLICT;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'outcome' => $this->outcome,
            'saved' => $this->isSaved(),
            'conflict' => $this->isConflict(),
            'revision' => $this->document->revision,
            'accepted_revision' => $this->acceptedRevision,
            'submitted_revision' => $this->submittedRevision,
            'snapshot_id' => $this->snapshot?->id,
            'message' => $this->message,
            'server_content' => $this->serverContent,
            'rejected_content' => $this->rejectedContent,
            'saved_at' => $this->isSaved() ? $this->document->last_saved_at?->toIso8601String() : null,
        ], fn ($value) => $value !== null);
    }
}