<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArticleFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_id','file_type','original_name',
        'file_path','mime_type','file_size',
        // Provenance and integrity columns added by the manuscript pipeline.
        'sha256', 'is_anonymised', 'version_label', 'uploaded_by',
    ];

    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }

    /**
     * The immutable manuscript version captured from this file, if any.
     */
    public function manuscriptVersion()
    {
        return $this->hasOne(ManuscriptVersion::class, 'source_file_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isAnonymised(): bool
    {
        return (bool) $this->is_anonymised;
    }

    public function resolvedPrivatePath(): ?string
    {
        $disk = Storage::disk('private');
        $rawPath = trim((string) $this->file_path);
        $basename = basename(str_replace('\\', '/', $rawPath));

        if ($rawPath === '') {
            return null;
        }

        $standardSubmissionDirectories = [
            'submissions/manuscript/',
            'submissions/cover_letter/',
            'submissions/supplementary/',
        ];

        $normalizedCandidates = collect([
            $rawPath,
            ltrim(str_replace('\\', '/', $rawPath), '/'),
            Str::after(str_replace('\\', '/', $rawPath), 'storage/app/private/'),
            Str::after(str_replace('\\', '/', $rawPath), 'app/private/'),
            'submissions/' . $this->file_type . '/' . $basename,
        ])->filter(fn ($path) => trim((string) $path) !== '')
          ->map(fn ($path) => ltrim((string) $path, '/'))
          ->merge(
              collect($standardSubmissionDirectories)
                  ->map(fn ($directory) => $directory . $basename)
          )
          ->unique()
          ->values();

        foreach ($normalizedCandidates as $candidate) {
            if ($disk->exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
