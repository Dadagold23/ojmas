<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A tracked insertion or deletion recorded in the workspace.
 *
 * Tiptap has no native track changes, so this is the system of record. Pending
 * changes are rendered in the editor as inline marks; accepting an insertion
 * unmarks it, rejecting an insertion deletes it, accepting a deletion removes
 * it, and rejecting a deletion restores it.
 */
class ManuscriptTrackedChange extends Model
{
    use HasFactory;

    public const TYPE_INSERTION = 'insertion';
    public const TYPE_DELETION = 'deletion';

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'manuscript_workspace_document_id',
        'change_uid',
        'type',
        'author_id',
        'text_snapshot',
        'body_json',
        'anchor_from',
        'anchor_to',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'body_json' => 'array',
            'anchor_from' => 'integer',
            'anchor_to' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function workspaceDocument(): BelongsTo
    {
        return $this->belongsTo(ManuscriptWorkspaceDocument::class, 'manuscript_workspace_document_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isInsertion(): bool
    {
        return $this->type === self::TYPE_INSERTION;
    }

    /**
     * What resolving this change does to the document text.
     *
     *   insertion + accept  -> keep the text
     *   insertion + reject  -> remove the text
     *   deletion  + accept  -> remove the text
     *   deletion  + reject  -> restore the text
     */
    public function resolutionRemovesText(): bool
    {
        return ($this->type === self::TYPE_INSERTION && $this->status === self::STATUS_REJECTED)
            || ($this->type === self::TYPE_DELETION && $this->status === self::STATUS_ACCEPTED);
    }

    /** @return array<string, mixed> */
    public function toWorkspaceArray(): array
    {
        return [
            'id' => $this->id,
            'uid' => $this->change_uid,
            'type' => $this->type,
            'status' => $this->status,
            'text' => $this->text_snapshot,
            'author' => $this->author?->name,
            'author_id' => $this->author_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
        ];
    }
}