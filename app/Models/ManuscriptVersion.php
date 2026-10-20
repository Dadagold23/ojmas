<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * An immutable point-in-time snapshot of a manuscript file.
 *
 * A submission accumulates versions rather than overwriting its file, so the
 * original submission and every later revision remain individually retrievable
 * and auditable.
 */
class ManuscriptVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'version_number',
        'label',
        'source_file_id',
        'storage_path',
        'mime_type',
        'file_size',
        'sha256',
        'format',
        'origin',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'file_size' => 'integer',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function sourceFile(): BelongsTo
    {
        return $this->belongsTo(ArticleFile::class, 'source_file_id');
    }

    public function document(): HasOne
    {
        return $this->hasOne(ManuscriptDocument::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ManuscriptDocument::class);
    }

    public function validations(): HasMany
    {
        return $this->hasMany(ManuscriptValidation::class, 'manuscript_version_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOriginal(): bool
    {
        return $this->format === 'original';
    }

    public function absolutePath(): ?string
    {
        $disk = \Illuminate\Support\Facades\Storage::disk(config('manuscripts.disk', 'private'));

        return $disk->exists($this->storage_path) ? $disk->path($this->storage_path) : null;
    }
}