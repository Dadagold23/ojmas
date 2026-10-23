<?php

namespace App\Services\Documents\Media;

use App\Models\ManuscriptMedia;
use App\Models\ManuscriptVersion;
use App\Services\Documents\Exceptions\CorruptDocumentException;
use App\Services\Documents\Parsing\OoxmlPackage;
use Illuminate\Support\Facades\Storage;

/**
 * Extracts embedded images out of an uploaded manuscript package.
 *
 * Principles this module is held to:
 *
 *  - **The original is never modified.** The source package is opened
 *    read-only; media parts are COPIED into the private disk.
 *  - **Idempotent.** One row per (version, sha256); repeated saves, re-opens
 *    and version restorations never duplicate extraction. When rows already
 *    exist for a version the service returns immediately instead of reopening
 *    the package.
 *  - **Honest failure.** A package that cannot be read produces no rows and no
 *    invented substitutes; the editor shows a diagnostic placeholder.
 *  - **Size-aware.** Image pixel dimensions are probed with GD when the
 *    bytes are a readable raster format, so both the editor and the DOCX
 *    writers can lay figures out at their true aspect ratio.
 */
class ManuscriptMediaService
{
    /** Extensions accepted for display and export. Anything else is recorded with mime 'unhandled' — never silently dropped from report counts. */
    private const DISPLAYABLE = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'bmp' => 'image/bmp',
        'tiff' => 'image/tiff',
        'tif' => 'image/tiff',
        'emf' => 'image/x-emf',
        'wmf' => 'image/x-wmf',
    ];

    /**
     * Extracts every media part of the version's ORIGINAL package.
     *
     * @return array<string, ManuscriptMedia> keyed by OOXML part name, e.g. 'word/media/image1.png'
     */
    public function extractForVersion(ManuscriptVersion $version): array
    {
        $existing = ManuscriptMedia::where('manuscript_version_id', $version->id)
            ->orderBy('ordinal')
            ->get();

        if ($existing->isNotEmpty()) {
            return $existing->keyBy('part_name')->all();
        }

        $disk = Storage::disk(config('manuscripts.disk', 'private'));
        $sourcePath = trim((string) $version->storage_path);

        if ($sourcePath === '' || ! $disk->exists($sourcePath)) {
            return [];
        }

        try {
            $package = OoxmlPackage::open($disk->path($sourcePath));
        } catch (CorruptDocumentException) {
            return [];
        }

        $rows = [];
        $ordinal = 0;

        foreach ($package->mediaPartNames() as $partName) {
            $bytes = (string) $package->raw($partName);

            if ($bytes === '') {
                continue;
            }

            $checksum = hash('sha256', $bytes);
            $ordinal++;

            $extension = strtolower(pathinfo($partName, PATHINFO_EXTENSION));
            $mime = self::DISPLAYABLE[$extension] ?? 'unhandled/'.($extension !== '' ? $extension : 'bin');

            [$width, $height] = $this->probeDimensions($bytes, $extension);

            $path = sprintf(
                'manuscripts/media/%d/%d/%s',
                (int) $version->submission_id,
                (int) $version->id,
                sprintf('%02d-image.%s', $ordinal, $extension !== '' ? $extension : 'bin'),
            );

            $disk->put($path, $bytes);

            // firstOrCreate keyed on (version, part_name): extraction is
            // idempotent — the same part of the same version is one row, never
            // duplicated by repeated saves or re-opens. Distinct parts with
            // identical bytes stay separate rows: they are separate figures
            // with their own extents and alt text.
            $rows[$partName] = ManuscriptMedia::firstOrCreate(
                [
                    'manuscript_version_id' => $version->id,
                    'part_name' => $partName,
                ],
                [
                    'submission_id' => $version->submission_id,
                    'storage_path' => $path,
                    'mime_type' => $mime,
                    'size_bytes' => strlen($bytes),
                    'sha256' => $checksum,
                    'ordinal' => $ordinal,
                    'width_px' => $width,
                    'height_px' => $height,
                ],
            );
        }

        return $rows;
    }

    /**
     * Rows for a version keyed by part name, without touching the disk.
     *
     * @return array<string, ManuscriptMedia>
     */
    public function rowsForVersion(ManuscriptVersion $version): array
    {
        return ManuscriptMedia::where('manuscript_version_id', $version->id)
            ->orderBy('ordinal')
            ->get()
            ->keyBy('part_name')
            ->all();
    }

    /**
     * True when extraction already ran for the version (any rows exist).
     *
     * Used by open-path flows so workspaces imported before this module
     * gained their rows lazily on the next open, without a package read.
     */
    public function hasAnyForVersion(ManuscriptVersion $version): bool
    {
        return ManuscriptMedia::where('manuscript_version_id', $version->id)->exists();
    }

    /**
     * Pixel dimensions via GD (enabled in the environment) for raster types;
     * vector/metafile formats confidently report null and the writers fall
     * back to the OOXML-declared extent.
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function probeDimensions(string $bytes, string $extension): array
    {
        $raster = in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'], true);

        if (! $raster || ! function_exists('imagecreatefromstring')) {
            return [null, null];
        }

        $probe = @\getimagesizefromstring($bytes);

        if ($probe === false || $probe[0] === null || $probe[1] === null) {
            return [null, null];
        }

        return [(int) $probe[0], (int) $probe[1]];
    }
}
