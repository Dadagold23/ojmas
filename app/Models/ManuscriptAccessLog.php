<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An immutable record that a named user opened, downloaded or exported a given
 * manuscript.
 *
 * The application already had an `audit_logs` table that nothing ever wrote to,
 * so sensitive document access was untraceable. This closes that gap for the
 * manuscript pipeline specifically.
 */
class ManuscriptAccessLog extends Model
{
    use HasFactory;

    public const ACTION_VIEW = 'view';
    public const ACTION_DOWNLOAD = 'download';
    public const ACTION_EDIT = 'edit';
    public const ACTION_EXPORT = 'export';
    public const ACTION_PRINT = 'print';
    public const ACTION_ANONYMISED_VIEW = 'anonymised_view';

    protected $fillable = [
        'user_id',
        'submission_id',
        'manuscript_document_id',
        'action',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(ManuscriptDocument::class, 'manuscript_document_id');
    }
}