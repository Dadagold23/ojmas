<?php

namespace App\Services\Documents;

use App\Models\ArticleFile;
use App\Models\JournalStyleProfile;
use App\Models\ManuscriptDocument;
use App\Models\ManuscriptValidation;
use App\Models\ManuscriptValidationIssue;
use App\Models\ManuscriptVersion;
use App\Models\Submission;
use App\Services\Documents\Exceptions\DocumentProcessingException;
use App\Services\Documents\Formatting\DocxRenderer;
use App\Services\Documents\Formatting\FormattingEngine;
use App\Services\Documents\Formatting\FormattingResult;
use App\Services\Documents\Formatting\StyleProfile;
use App\Services\Documents\Formatting\StyleProfileRepository;
use App\Services\Documents\Ingestion\DocxSecurityValidator;
use App\Services\Documents\Parsing\DocxStructureParser;
use App\Services\Documents\Parsing\OoxmlPackage;
use App\Services\Documents\Validation\CitationExtractor;
use App\Services\Documents\Validation\ReferenceParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The manuscript ingestion and APA 7 formatting pipeline.
 *
 * The pipeline is deliberately ordered so that no destructive step can happen
 * before every reversible one has succeeded:
 *
 *   1. Validate the upload (security, container, size).
 *   2. Copy the original onto the private disk and record its checksum.
 *   3. Parse the original into a neutral manuscript tree.
 *   4. Evaluate the versioned rule set against the parsed tree.
 *   5. Render a **new** DOCX carrying the corrections.
 *   6. Persist the report.
 *
 * The author's original file is never opened for writing and never replaced.
 */
class ManuscriptProcessingService
{
    public function __construct(
        private readonly DocxSecurityValidator $securityValidator,
        private readonly DocxStructureParser $parser,
        private readonly FormattingEngine $engine,
        private readonly StyleProfileRepository $profiles,
        private readonly CitationExtractor $citationExtractor = new CitationExtractor(),
        private readonly ReferenceParser $referenceParser = new ReferenceParser(),
    ) {
    }

    private function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk(config('manuscripts.disk', 'private'));
    }

    /**
     * Captures an author upload as an immutable manuscript version.
     *
     * @return array{version: ManuscriptVersion, security: \App\Services\Documents\Ingestion\SecurityValidationReport}
     *
     * @throws DocumentProcessingException
     */
    public function ingest(Submission $submission, UploadedFile $file, ?int $userId = null): array
    {
        $temporaryPath = $file->getRealPath();

        if ($temporaryPath === false) {
            throw new \App\Services\Documents\Exceptions\CorruptDocumentException(
                'The uploaded file could not be read.',
                'document_unreadable',
            );
        }

        $security = $this->securityValidator->validate($temporaryPath, $file->getClientOriginalName());
        $sha256 = hash_file('sha256', $temporaryPath);
        $size = (int) filesize($temporaryPath);

        $version = DB::transaction(function () use ($submission, $file, $sha256, $size, $userId) {
            $number = (int) ManuscriptVersion::where('submission_id', $submission->id)->max('version_number') + 1;

            $storedPath = $this->disk()->putFileAs(
                config('manuscripts.paths.originals', 'submissions/manuscript'),
                $file,
                $number.'-'.Str::random(40).'.docx',
            );

            if ($storedPath === false) {
                throw new \App\Services\Documents\Exceptions\CorruptDocumentException(
                    'The manuscript could not be stored on the private disk.',
                    'document_storage_failed',
                );
            }

            $fileRecord = $submission->files()->create([
                'file_type' => 'manuscript',
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $storedPath,
                'mime_type' => $file->getMimeType(),
                'file_size' => $size,
                'sha256' => $sha256,
                'uploaded_by' => $userId,
                'version_label' => 'v'.$number,
            ]);

            $version = ManuscriptVersion::create([
                'submission_id' => $submission->id,
                'version_number' => $number,
                'label' => 'Version '.$number,
                'source_file_id' => $fileRecord->id,
                'storage_path' => $storedPath,
                'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'file_size' => $size,
                'sha256' => $sha256,
                'format' => 'original',
                'origin' => 'author_upload',
                'created_by' => $userId,
            ]);

            if ($submission->current_version_id === null) {
                $submission->forceFill(['current_version_id' => $version->id])->save();
            }

            return $version;
        });

        return ['version' => $version->fresh(), 'security' => $security];
    }

    /**
     * Parses a stored version into the neutral manuscript tree.
     *
     * @throws DocumentProcessingException
     */
    public function parse(ManuscriptVersion $version): ManuscriptDocument
    {
        $document = ManuscriptDocument::updateOrCreate(
            ['manuscript_version_id' => $version->id],
            [
                'format' => 'docx',
                'storage_path' => $version->storage_path,
                'sha256' => $version->sha256,
                'status' => ManuscriptDocument::STATUS_PARSING,
            ],
        );

        $path = $version->absolutePath();

        if ($path === null) {
            $document->update([
                'status' => ManuscriptDocument::STATUS_FAILED,
                'error_message' => 'The stored manuscript file could not be found on the private disk.',
            ]);

            throw new \App\Services\Documents\Exceptions\CorruptDocumentException(
                'The stored manuscript file could not be found.',
                'document_missing_on_disk',
            );
        }

        try {
            $security = $this->securityValidator->validate($path, $version->storage_path);
            $manuscript = $this->parser->parse($path, $security);
        } catch (DocumentProcessingException $e) {
            $document->update([
                'status' => ManuscriptDocument::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        } catch (\Throwable $e) {
            $document->update([
                'status' => ManuscriptDocument::STATUS_FAILED,
                'error_message' => 'An unexpected error occurred while reading the document: '.$e->getMessage(),
            ]);

            Log::error('Manuscript parsing failed', [
                'manuscript_version_id' => $version->id,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }

        $document->update([
            'status' => ManuscriptDocument::STATUS_PARSED,
            'word_count' => $manuscript->wordCount,
            'paragraph_count' => $manuscript->paragraphCount,
            'structure_json' => $manuscript->toArray(),
        ]);

        return $document->fresh();
    }

    /**
     * Runs the full pipeline for a stored version: parse, format, render, report.
     *
     * @param  bool  $persist  Whether to write the report to the database.
     *
     * @throws DocumentProcessingException
     */
    public function process(
        ManuscriptVersion $version,
        ?string $profileSlug = null,
        bool $persist = true,
    ): ManuscriptProcessingResult {
        $document = $this->parse($version);
        $profile = $this->profiles->resolve($profileSlug);

        $parsed = \App\Services\Documents\Ast\ParsedManuscript::fromArray($document->structure_json ?? []);
        $result = $this->engine->format($parsed, $profile);

        $formattedPath = $this->renderFormattedDocument($version, $result);

        $validation = $persist
            ? $this->persistValidation($version, $document, $result, $formattedPath)
            : null;

        $this->persistExtractedCitationsAndReferences($document, $parsed);

        return new ManuscriptProcessingResult(
            version: $version->fresh(),
            document: $document->fresh(),
            validation: $validation,
            result: $result,
            profile: $profile,
            formattedPath: $formattedPath,
        );
    }

    /**
     * Renders the corrected manuscript to a NEW file. The original is untouched.
     */
    private function renderFormattedDocument(ManuscriptVersion $version, FormattingResult $result): string
    {
        $sourcePath = $version->absolutePath();

        if ($sourcePath === null) {
            throw new \App\Services\Documents\Exceptions\CorruptDocumentException(
                'The stored manuscript file could not be found.',
                'document_missing_on_disk',
            );
        }

        $nextNumber = (int) ManuscriptVersion::where('submission_id', $version->submission_id)->max('version_number') + 1;

        $target = sprintf(
            '%s/%d-%s.docx',
            trim((string) config('manuscripts.paths.formatted', 'manuscripts/formatted'), '/'),
            $version->id,
            Str::random(32),
        );

        $absolute = $this->disk()->path($target);
        $directory = dirname($absolute);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Unable to create the formatted manuscript directory.');
        }

        (new DocxRenderer($result->profile))->render(
            $result->formatted,
            $absolute,
            OoxmlPackage::open($sourcePath),
        );

        return $target;
    }

    private function persistValidation(
        ManuscriptVersion $version,
        ManuscriptDocument $document,
        FormattingResult $result,
        string $formattedPath,
    ): ManuscriptValidation {
        $payload = $result->toArray();

        // The formatted artefact is recorded alongside the report so the
        // download route can find exactly the file this validation describes.
        $payload['formatted_document'] = [
            'source_version_id' => $version->id,
            'storage_path' => $formattedPath,
            'source_sha256' => $version->sha256,
            'generated_at' => now()->toIso8601String(),
        ];

        return DB::transaction(function () use ($version, $document, $result, $payload) {
            $validation = ManuscriptValidation::create([
                'manuscript_document_id' => $document->id,
                'submission_id' => $version->submission_id,
                'profile' => $result->profile->slug,
                'profile_version' => $result->profile->version,
                'status' => ManuscriptValidation::STATUS_COMPLETED,
                'auto_fixed_count' => $result->autoFixedCount(),
                'author_action_count' => $result->authorActionCount(),
                'unverifiable_count' => $this->countUnverifiable($result),
                'unsupported_count' => $result->unsupportedCount(),
                'warning_count' => count($result->warnings()),
                'error_count' => count($result->errors()),
                'report_json' => $payload,
            ]);

            $rows = [];

            foreach ($result->findings as $finding) {
                $rows[] = [
                    'manuscript_validation_id' => $validation->id,
                    'severity' => $finding->severity,
                    'category' => $finding->category,
                    'code' => $finding->code,
                    'rule_id' => $finding->ruleId,
                    'rule_version' => $finding->ruleVersion,
                    'location_json' => json_encode($finding->location),
                    'message' => $finding->message,
                    'suggestion' => $finding->suggestion,
                    'was_auto_fixed' => $finding->wasAutoFixed,
                    'requires_author_action' => $finding->requiresAuthorAction,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            foreach (array_chunk($rows, 200) as $chunk) {
                ManuscriptValidationIssue::insert($chunk);
            }

            return $validation;
        });
    }

    private function persistExtractedCitationsAndReferences(
        ManuscriptDocument $document,
        \App\Services\Documents\Ast\ParsedManuscript $manuscript,
    ): void {
        $bodyTexts = [];

        foreach ($manuscript->blocks as $block) {
            if (in_array($block->type, [\App\Services\Documents\Ast\NodeType::REFERENCE_ENTRY], true)) {
                continue;
            }

            $text = trim((string) $block->text);

            if ($text !== '') {
                $bodyTexts[] = $text;
            }
        }

        $citations = $this->citationExtractor->extract($bodyTexts);
        $references = $this->referenceParser->parse(
            array_map(fn ($node) => (string) $node->text, $manuscript->referenceEntries()),
        );

        $document->citations()->delete();
        $document->references()->delete();

        foreach ($citations as $citation) {
            $document->citations()->create([
                'raw_text' => $citation->rawText,
                'author_token' => $citation->authorToken,
                'year' => $citation->year,
                'locator' => $citation->locator,
                'paragraph_index' => $citation->paragraphIndex,
                'match_key' => $citation->matchKey(),
            ]);
        }

        $byKey = [];

        foreach ($references as $reference) {
            $record = $document->references()->create([
                'ordinal' => $reference->ordinal,
                'raw_text' => $reference->rawText,
                'author_raw' => $reference->authorRaw,
                'year' => $reference->year,
                'title_raw' => $reference->titleRaw,
                'source_raw' => $reference->sourceRaw,
                'doi_raw' => $reference->doiRaw,
                'url_raw' => $reference->urlRaw,
                'pages_raw' => $reference->pagesRaw,
                'parsed_json' => $reference->parsed,
                'match_key' => $reference->matchKey(),
                'verification_status' => $reference->verificationStatus,
            ]);

            $duplicateKey = $reference->duplicateKey();

            if (isset($byKey[$duplicateKey])) {
                $record->update(['duplicate_of_id' => $byKey[$duplicateKey]]);
            } else {
                $byKey[$duplicateKey] = $record->id;
            }
        }
    }

    /**
     * References the engine could not confirm against any authority. Every
     * reference is unverified by default; this only counts them.
     */
    private function countUnverifiable(FormattingResult $result): int
    {
        return count(array_filter(
            $result->findings,
            fn ($finding) => in_array($finding->code, [
                'reference.incomplete',
                'reference.malformed_doi',
                'reference.malformed_url',
                'citation.no_matching_reference',
                'reference.never_cited',
            ], true),
        ));
    }

    /**
     * Ensures the two built-in style profiles exist so a Super Admin can edit
     * and duplicate them from the administration screens.
     */
    public function seedStyleProfiles(): int
    {
        $created = 0;

        foreach (StyleProfileRepository::builtInProfiles() as $slug => $definition) {
            if (JournalStyleProfile::where('slug', $slug)->exists()) {
                continue;
            }

            JournalStyleProfile::create([
                'slug' => $slug,
                'name' => $definition['name'],
                'description' => $definition['description'],
                'version' => StyleProfileRepository::BUILT_IN_VERSION,
                'is_active' => $slug === (string) config('manuscripts.formatting.default_profile'),
                'rules_json' => $definition['requirements'],
            ]);

            $created++;
        }

        return $created;
    }
}