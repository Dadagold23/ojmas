<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The browser-editable working document.
 *
 * `revision` is an optimistic-concurrency token, not a lock. A save that
 * carries a stale revision is rejected and reported as a conflict, so two
 * people editing at once can never silently overwrite each other. This is
 * sequential editing with conflict detection — NOT real-time collaboration.
 */
class ManuscriptWorkspaceDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'manuscript_document_id',
        'submission_id',
        'content_json',
        'plain_text',
        'revision',
        'word_count',
        'block_count',
        'last_saved_at',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'content_json' => 'array',
            'revision' => 'integer',
            'word_count' => 'integer',
            'block_count' => 'integer',
            'last_saved_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(ManuscriptDocument::class, 'manuscript_document_id');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(ManuscriptSnapshot::class)->orderByDesc('revision');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ManuscriptComment::class);
    }

    public function trackedChanges(): HasMany
    {
        return $this->hasMany(ManuscriptTrackedChange::class);
    }

    public function openComments(): HasMany
    {
        return $this->comments()->where('status', 'open');
    }

    public function pendingChanges(): HasMany
    {
        return $this->trackedChanges()->where('status', 'pending');
    }

    public function content(): array
    {
        return is_array($this->content_json) ? $this->content_json : ['type' => 'doc', 'content' => []];
    }

    /** @return array<int, ManuscriptSnapshot> */
    public function recentSnapshots(int $limit = 20): array
    {
        return $this->snapshots()->with('author')->limit($limit)->get()->all();
    }
}