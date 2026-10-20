<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A point-in-time snapshot of the working document.
 *
 * Snapshots are the unit of version comparison and restoration. They are not
 * created on every keystroke; the service creates them on import, on the first
 * save after an idle period, on restore, and when a user explicitly saves a
 * version.
 */
class ManuscriptSnapshot extends Model
{
    use HasFactory;

    public const FROM_IMPORT = 'import';
    public const FROM_AUTOSAVE = 'autosave';
    public const FROM_MANUAL = 'manual';
    public const FROM_RESTORE = 'restore';
    public const FROM_ACCEPTED_CHANGES = 'accepted_changes';

    protected $fillable = [
        'manuscript_workspace_document_id',
        'revision',
        'label',
        'content_json',
        'plain_text',
        'word_count',
        'block_count',
        'sha256',
        'created_from',
        'restored_from_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'content_json' => 'array',
            'revision' => 'integer',
            'word_count' => 'integer',
            'block_count' => 'integer',
        ];
    }

    public function workspaceDocument(): BelongsTo
    {
        return $this->belongsTo(ManuscriptWorkspaceDocument::class, 'manuscript_workspace_document_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function restoredFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'restored_from_id');
    }

    /** @return array<string, mixed> */
    public function content(): array
    {
        return is_array($this->content_json) ? $this->content_json : ['type' => 'doc', 'content' => []];
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        return [
            'id' => $this->id,
            'revision' => $this->revision,
            'label' => $this->label,
            'created_from' => $this->created_from,
            'word_count' => $this->word_count,
            'block_count' => $this->block_count,
            'author' => $this->author?->name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}