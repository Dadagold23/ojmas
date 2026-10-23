<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One extracted embedded image of a manuscript version.
 *
 * The original uploaded package is never modified; every row points at a
 * COPY on the private disk. Rows are created once per (version, checksum) —
 * repeated saves or workspace re-opens never duplicate extraction. Rows are
 * never deleted, so restored older versions keep displaying their figures.
 */
class ManuscriptMedia extends Model
{
    protected $table = 'manuscript_media';

    protected $fillable = [
        'submission_id',
        'manuscript_version_id',
        'part_name',
        'storage_path',
        'mime_type',
        'size_bytes',
        'sha256',
        'ordinal',
        'width_px',
        'height_px',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'ordinal' => 'integer',
            'width_px' => 'integer',
            'height_px' => 'integer',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ManuscriptVersion::class, 'manuscript_version_id');
    }
}
