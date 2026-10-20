<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A contextual comment thread anchored to a range of the working document.
 *
 * Visibility is enforced server-side. `reviewer_confidential` comments are
 * never returned to an author-facing request, and authors can never read or
 * write them.
 */
class ManuscriptComment extends Model
{
    use HasFactory;

    public const VISIBILITY_AUTHOR = 'author_visible';
    public const VISIBILITY_EDITORIAL = 'editorial';
    public const VISIBILITY_REVIEWER_CONFIDENTIAL = 'reviewer_confidential';

    public const STATUS_OPEN = 'open';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_REOPENED = 'reopened';

    protected $fillable = [
        'manuscript_workspace_document_id',
        'parent_id',
        'manuscript_document_id',
        'anchor_from',
        'anchor_to',
        'quoted_text',
        'anchor_signature',
        'anchor_valid',
        'revision_at_creation',
        'body',
        'visibility',
        'status',
        'author_id',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'anchor_valid' => 'boolean',
            'anchor_from' => 'integer',
            'anchor_to' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }

    public function workspaceDocument(): BelongsTo
    {
        return $this->belongsTo(ManuscriptWorkspaceDocument::class, 'manuscript_workspace_document_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('created_at');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function isAuthorVisible(): bool
    {
        return $this->visibility === self::VISIBILITY_AUTHOR;
    }

    public function isReviewerConfidential(): bool
    {
        return $this->visibility === self::VISIBILITY_REVIEWER_CONFIDENTIAL;
    }

    /**
     * Serialisation for the workspace payload.
     *
     * Reviewer-confidential comments must never reach an author-facing
     * response, so this is only ever called after the visibility filter.
     *
     * @return array<string, mixed>
     */
    public function toWorkspaceArray(): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'anchor_from' => $this->anchor_from,
            'anchor_to' => $this->anchor_to,
            'quoted_text' => $this->quoted_text,
            'anchor_valid' => $this->anchor_valid,
            'body' => $this->body,
            'visibility' => $this->visibility,
            'status' => $this->status,
            'author' => $this->author?->name,
            'author_id' => $this->author_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'resolved_by' => $this->resolver?->name,
            'replies' => $this->relationLoaded('replies')
                ? $this->replies->map(fn (self $reply) => $reply->toWorkspaceArray())->all()
                : [],
        ];
    }
}