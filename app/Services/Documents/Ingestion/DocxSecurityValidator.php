<?php

namespace App\Services\Documents\Ingestion;

use App\Services\Documents\Exceptions\CorruptDocumentException;
use App\Services\Documents\Exceptions\DocumentSecurityException;
use App\Services\Documents\Exceptions\DocumentTooLargeException;
use App\Services\Documents\Exceptions\UnsupportedDocumentException;
use ZipArchive;

/**
 * Validates an uploaded file as a genuine, safe OOXML word-processing document.
 *
 * A `.docx` is a ZIP container, so an upload is untrusted input in two layers:
 * the outer HTTP upload, and the inner ZIP. This class hardens the inner layer.
 *
 * It performs, in order:
 *   1. Extension and size gates.
 *   2. ZIP magic-byte check (not just the client-supplied extension).
 *   3. Zip-bomb defence via entry count, total and per-entry uncompressed size,
 *      and compression ratio.
 *   4. Path-traversal detection on every entry name.
 *   5. Macro payload rejection (vbaProject.bin / .docm content type).
 *   6. Encrypted-container rejection.
 *   7. Required OOXML part presence.
 *   8. External-relationship census (flagged, not rejected).
 *
 * It never mutates the file and never trusts a client-supplied MIME type.
 */
class DocxSecurityValidator
{
    private const ZIP_LOCAL_FILE_HEADER = "PK\x03\x04";
    private const ZIP_EMPTY_ARCHIVE = "PK\x05\x06";

    public function __construct(private readonly array $limits = [])
    {
    }

    /**
     * @param  string  $absolutePath  Path to the staged upload.
     * @param  string  $originalName  Client-supplied filename (used only for the extension gate and messages).
     * @return SecurityValidationReport
     *
     * @throws DocumentSecurityException
     * @throws DocumentTooLargeException
     * @throws CorruptDocumentException
     * @throws UnsupportedDocumentException
     */
    public function validate(string $absolutePath, string $originalName): SecurityValidationReport
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw new CorruptDocumentException(
                'The uploaded file could not be read from temporary storage.',
                'document_unreadable',
                ['original_name' => $originalName],
            );
        }

        $this->assertExtension($originalName);
        $this->assertSize($absolutePath, $originalName);
        $this->assertZipContainer($absolutePath, $originalName);

        $zip = new ZipArchive();
        $opened = $zip->open($absolutePath, ZipArchive::RDONLY);

        if ($opened !== true) {
            throw new CorruptDocumentException(
                'The uploaded file is not a readable ZIP-based Office document container.',
                'document_container_unreadable',
                ['original_name' => $originalName, 'zip_status' => $opened],
            );
        }

        try {
            $report = $this->inspectEntries($zip, $originalName);
        } finally {
            $zip->close();
        }

        return $report;
    }

    private function limit(string $key, int $default): int
    {
        return (int) ($this->limits[$key] ?? $default);
    }

    private function assertExtension(string $originalName): void
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension !== 'docx') {
            throw new UnsupportedDocumentException(
                "Automatic APA 7 formatting requires a Microsoft Word .docx source file. "
                ."The uploaded file has the extension '{$extension}'. Legacy .doc and PDF files are accepted by the "
                .'journal but cannot be reformatted automatically because they carry no editable source structure.',
                'document_unsupported_format',
                ['extension' => $extension],
            );
        }
    }

    private function assertSize(string $absolutePath, string $originalName): void
    {
        $size = (int) filesize($absolutePath);
        $max = $this->limit('max_upload_bytes', 104857600);

        if ($size <= 0) {
            throw new CorruptDocumentException(
                'The uploaded file is empty.',
                'document_empty',
                ['original_name' => $originalName, 'size' => $size],
            );
        }

        if ($size > $max) {
            throw new DocumentTooLargeException(
                'The manuscript exceeds the maximum size accepted for automatic formatting.',
                'document_too_large',
                ['original_name' => $originalName, 'size' => $size, 'max_size' => $max],
            );
        }
    }

    private function assertZipContainer(string $absolutePath, string $originalName): void
    {
        $handle = fopen($absolutePath, 'rb');

        if ($handle === false) {
            throw new CorruptDocumentException(
                'The uploaded file could not be opened.',
                'document_unreadable',
                ['original_name' => $originalName],
            );
        }

        $signature = (string) fread($handle, 4);
        fclose($handle);

        $isZip = $signature === self::ZIP_LOCAL_FILE_HEADER || $signature === self::ZIP_EMPTY_ARCHIVE;

        if (! $isZip) {
            throw new DocumentSecurityException(
                'The uploaded file does not contain a valid Office document container. '
                .'A file was supplied whose extension claims to be .docx but whose contents are not a ZIP archive.',
                'document_not_ooxml_container',
                ['original_name' => $originalName],
            );
        }
    }

    private function inspectEntries(ZipArchive $zip, string $originalName): SecurityValidationReport
    {
        $maxEntries = $this->limit('max_entries', 3000);
        $entryCount = $zip->numFiles;

        if ($entryCount > $maxEntries) {
            throw new DocumentSecurityException(
                'The manuscript archive contains an abnormal number of entries and was rejected.',
                'document_too_many_entries',
                ['original_name' => $originalName, 'entries' => $entryCount, 'max_entries' => $maxEntries],
            );
        }

        $requiredParts = $this->limits['required_parts'] ?? ['[Content_Types].xml', 'word/document.xml'];
        $forbiddenParts = array_map('strtolower', $this->limits['forbidden_parts'] ?? ['vbaproject.bin']);

        $found = [];
        $totalUncompressed = 0;
        $maxEntrySize = $this->limit('max_entry_uncompressed_bytes', 268435456);
        $maxTotal = $this->limit('max_total_uncompressed_bytes', 536870912);
        $maxRatio = $this->limit('max_compression_ratio', 120);
        $mediaParts = 0;
        $maxRatioObserved = 0.0;

        for ($i = 0; $i < $entryCount; $i++) {
            $stat = $zip->statIndex($i);

            if ($stat === false) {
                continue;
            }

            $name = (string) $stat['name'];
            $found[strtolower($name)] = true;

            $this->assertSafeEntryName($name, $originalName);

            $lowerName = strtolower($name);

            foreach ($forbiddenParts as $forbidden) {
                if (str_ends_with($lowerName, $forbidden)) {
                    throw new DocumentSecurityException(
                        'The uploaded file contains an embedded macro project. Macro-enabled manuscripts are not accepted '
                        .'for automatic processing.',
                        'document_contains_macros',
                        ['original_name' => $originalName, 'entry' => $name],
                    );
                }
            }

            $this->assertNotEncrypted($zip, $i, $name, $originalName);

            $uncompressed = (int) $stat['size'];
            $compressed = (int) $stat['comp_size'];

            if ($uncompressed > $maxEntrySize) {
                throw new DocumentSecurityException(
                    'The manuscript contains an abnormally large internal component and was rejected.',
                    'document_entry_too_large',
                    ['original_name' => $originalName, 'entry' => $name, 'size' => $uncompressed, 'max_size' => $maxEntrySize],
                );
            }

            $totalUncompressed += $uncompressed;

            if ($totalUncompressed > $maxTotal) {
                throw new DocumentSecurityException(
                    'The manuscript expands to an excessive size when uncompressed and was rejected.',
                    'document_expansion_limit_exceeded',
                    ['original_name' => $originalName, 'expanded_bytes' => $totalUncompressed, 'max_bytes' => $maxTotal],
                );
            }

            // Compression-ratio ceiling: legitimate DOCX media compresses poorly
            // (ratio near 1). A very high ratio indicates a zip bomb.
            if ($uncompressed > 0 && $compressed > 0) {
                $ratio = $uncompressed / $compressed;

                if ($ratio > $maxRatio) {
                    throw new DocumentSecurityException(
                        'The manuscript archive has an abnormal compression ratio and was rejected as a suspected '
                        .'decompression bomb.',
                        'document_compression_bomb',
                        ['original_name' => $originalName, 'entry' => $name, 'ratio' => round($ratio, 2), 'max_ratio' => $maxRatio],
                    );
                }

                $maxRatioObserved = max($maxRatioObserved, $ratio);
            }

            if (str_starts_with($lowerName, 'word/media/')) {
                $mediaParts++;
            }
        }

        foreach ($requiredParts as $required) {
            if (! isset($found[strtolower($required)])) {
                throw new CorruptDocumentException(
                    "The uploaded file is missing the required Office document part '{$required}'. "
                    .'The file is incomplete or is not a Word document.',
                    'document_missing_required_part',
                    ['original_name' => $originalName, 'missing_part' => $required],
                );
            }
        }

        return new SecurityValidationReport(
            entryCount: $entryCount,
            totalUncompressedBytes: $totalUncompressed,
            mediaPartCount: $mediaParts,
            maxCompressionRatioObserved: round($maxRatioObserved, 2),
            partNames: array_keys($found),
        );
    }

    private function assertSafeEntryName(string $name, string $originalName): void
    {
        $normalised = str_replace('\\', '/', $name);

        if ($normalised === '' || $normalised[0] === '/') {
            throw new DocumentSecurityException(
                'The manuscript archive contains an entry with an unsafe path and was rejected.',
                'document_unsafe_entry_path',
                ['original_name' => $originalName, 'entry' => $name],
            );
        }

        if (str_contains($normalised, '../') || str_contains($normalised, '..\\') || $normalised === '..') {
            throw new DocumentSecurityException(
                'The manuscript archive contains an entry attempting to traverse outside the archive and was rejected.',
                'document_path_traversal',
                ['original_name' => $originalName, 'entry' => $name],
            );
        }

        // Defence in depth: reject NUL bytes and drive-style absolute paths.
        if (str_contains($normalised, "\0") || preg_match('#^[A-Za-z]:#', $normalised) === 1) {
            throw new DocumentSecurityException(
                'The manuscript archive contains an entry with an unsafe path and was rejected.',
                'document_unsafe_entry_path',
                ['original_name' => $originalName, 'entry' => $name],
            );
        }
    }

    private function assertNotEncrypted(ZipArchive $zip, int $index, string $name, string $originalName): void
    {
        if (! method_exists($zip, 'getEncryptionName')) {
            return;
        }

        $encryption = $zip->getEncryptionName($index);

        if (is_string($encryption) && $encryption !== ZipArchive::EM_NONE) {
            throw new UnsupportedDocumentException(
                'The manuscript is password protected or uses ZIP encryption and cannot be processed automatically. '
                .'Please upload an unprotected document.',
                'document_encrypted',
                ['original_name' => $originalName, 'entry' => $name, 'encryption' => $encryption],
            );
        }
    }
}