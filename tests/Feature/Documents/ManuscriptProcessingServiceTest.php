<?php

namespace Tests\Feature\Documents;

use App\Models\ArticleFile;
use App\Models\ManuscriptDocument;
use App\Models\ManuscriptValidation;
use App\Models\ManuscriptValidationIssue;
use App\Models\ManuscriptVersion;
use App\Models\Submission;
use App\Models\User;
use App\Services\Documents\Exceptions\DocumentProcessingException;
use App\Services\Documents\Formatting\StyleProfileRepository;
use App\Services\Documents\ManuscriptProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\DocxFixtureFactory;
use Tests\TestCase;

/**
 * End-to-end coverage of the ingestion and formatting pipeline.
 *
 * These are the tests that prove the module's acceptance criteria: the
 * original is preserved byte-for-byte, the formatted output is a real
 * openable DOCX, and nothing about the manuscript's status changes as a
 * result of formatting.
 */
class ManuscriptProcessingServiceTest extends TestCase
{
    use RefreshDatabase;

    private DocxFixtureFactory $fixtures;

    private ManuscriptProcessingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');

        $this->fixtures = new DocxFixtureFactory();
        $this->service = app(ManuscriptProcessingService::class);
    }

    private function upload(string $fixturePath, string $name = 'manuscript.docx'): UploadedFile
    {
        return new UploadedFile($fixturePath, $name, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);
    }

    private function submission(): Submission
    {
        $author = User::factory()->create();

        return Submission::factory()->create(['author_id' => $author->id]);
    }

    // ─────────── ingestion ───────────

    public function test_it_ingests_a_docx_as_an_immutable_version(): void
    {
        $submission = $this->submission();
        $author = $submission->author;
        $path = $this->fixtures->compliant('ingest-compliant.docx');

        $result = $this->service->ingest($submission, $this->upload($path), $author->id);

        $version = $result['version'];

        $this->assertInstanceOf(ManuscriptVersion::class, $version);
        $this->assertSame(1, $version->version_number);
        $this->assertSame('original', $version->format);
        $this->assertSame(hash_file('sha256', $path), $version->sha256);
        $this->assertFalse($result['security']->hasMacros());

        Storage::disk('private')->assertExists($version->storage_path);

        $file = ArticleFile::where('submission_id', $submission->id)->first();
        $this->assertNotNull($file);
        $this->assertSame($version->sha256, $file->sha256);
        $this->assertSame($author->id, $file->uploaded_by);
    }

    public function test_ingestion_numbers_versions_sequentially_and_never_overwrites(): void
    {
        $submission = $this->submission();

        $first = $this->service->ingest($submission, $this->upload($this->fixtures->compliant('v1.docx')));
        $second = $this->service->ingest($submission, $this->upload($this->fixtures->nonCompliant('v2.docx')));

        $this->assertSame(1, $first['version']->version_number);
        $this->assertSame(2, $second['version']->version_number);
        $this->assertNotSame($first['version']->storage_path, $second['version']->storage_path);

        Storage::disk('private')->assertExists($first['version']->storage_path);
        Storage::disk('private')->assertExists($second['version']->storage_path);
    }

    public function test_the_original_is_never_overwritten_by_processing(): void
    {
        $submission = $this->submission();
        $sourcePath = $this->fixtures->nonCompliant('immutable-source.docx');

        $version = $this->service->ingest($submission, $this->upload($sourcePath))['version'];
        $originalChecksum = $version->sha256;

        $this->service->process($version);

        $version->refresh();

        $this->assertSame($originalChecksum, $version->sha256, 'The stored original must be untouched.');
        $this->assertSame('original', $version->format);

        $storedBytes = Storage::disk('private')->get($version->storage_path);

        $this->assertSame(
            hash_file('sha256', $sourcePath),
            hash('sha256', $storedBytes),
            'The stored original must be byte-identical to what the author uploaded.',
        );
    }

    public function test_it_rejects_a_hostile_upload_without_creating_a_version(): void
    {
        $submission = $this->submission();

        $this->expectException(DocumentProcessingException::class);

        try {
            $this->service->ingest($submission, $this->upload($this->fixtures->withMacros('ingest-macro.docx')));
        } finally {
            $this->assertSame(0, ManuscriptVersion::where('submission_id', $submission->id)->count());
            $this->assertSame(0, ArticleFile::where('submission_id', $submission->id)->count());
        }
    }

    public function test_it_rejects_a_plain_text_file_named_docx(): void
    {
        $submission = $this->submission();

        $this->expectException(DocumentProcessingException::class);

        try {
            $this->service->ingest($submission, $this->upload($this->fixtures->notADocument('ingest-plain.docx')));
        } finally {
            $this->assertSame(0, ManuscriptVersion::where('submission_id', $submission->id)->count());
        }
    }

    // ─────────── parsing ───────────

    public function test_it_parses_a_version_into_a_stored_structure(): void
    {
        $submission = $this->submission();
        $version = $this->service->ingest($submission, $this->upload($this->fixtures->compliant('parse-e2e.docx')))['version'];

        $document = $this->service->parse($version);

        $this->assertSame(ManuscriptDocument::STATUS_PARSED, $document->status);
        $this->assertGreaterThan(100, $document->word_count);
        $this->assertNotEmpty($document->structure_json['blocks']);
        $this->assertSame(11906, $document->structure_json['section_properties']['width_twips']);
    }

    public function test_a_failed_parse_is_recorded_rather_than_thrown_away(): void
    {
        $submission = $this->submission();

        // A version whose file has gone missing from the private disk.
        $version = ManuscriptVersion::create([
            'submission_id' => $submission->id,
            'version_number' => 1,
            'storage_path' => 'submissions/manuscript/missing-file.docx',
            'sha256' => hash('sha256', 'missing'),
            'format' => 'original',
            'origin' => 'author_upload',
        ]);

        try {
            $this->service->parse($version);
            $this->fail('A missing file must raise a processing exception.');
        } catch (DocumentProcessingException) {
            $document = ManuscriptDocument::where('manuscript_version_id', $version->id)->first();

            $this->assertNotNull($document);
            $this->assertSame(ManuscriptDocument::STATUS_FAILED, $document->status);
            $this->assertNotNull($document->error_message);
        }
    }

    // ─────────── full pipeline ───────────

    public function test_the_full_pipeline_produces_a_formatted_document_and_a_report(): void
    {
        $submission = $this->submission();
        $version = $this->service->ingest($submission, $this->upload($this->fixtures->nonCompliant('pipeline.docx')))['version'];

        $result = $this->service->process($version);

        $this->assertInstanceOf(ManuscriptValidation::class, $result->validation);
        $this->assertSame(ManuscriptValidation::STATUS_COMPLETED, $result->validation->status);
        $this->assertSame('ojmas', $result->validation->profile);

        // The corrected document exists and is a real DOCX.
        Storage::disk('private')->assertExists($result->formattedPath);
        $this->assertGreaterThan(0, $result->validation->auto_fixed_count);

        $issues = $result->validation->issues;
        $this->assertGreaterThan(0, $issues->count());

        // Errors and warnings must be distinguishable in storage.
        $this->assertGreaterThan(0, $result->validation->errors()->count());
        $this->assertGreaterThan(
            0,
            ManuscriptValidationIssue::where('severity', 'error')->where('was_auto_fixed', false)->count(),
        );
    }

    public function test_it_persists_extracted_citations_and_references(): void
    {
        $submission = $this->submission();
        $version = $this->service->ingest($submission, $this->upload($this->fixtures->nonCompliant('citations-e2e.docx')))['version'];

        $result = $this->service->process($version);
        $document = $result->document;

        $this->assertGreaterThan(0, $document->citations()->count());
        $this->assertSame(7, $document->references()->count());

        $document->citations()->get()->each(function ($citation): void {
            $this->assertNotEmpty($citation->raw_text);
        });

        $document->references()->get()->each(function ($reference): void {
            $this->assertSame(
                'unverified',
                $reference->verification_status,
                'No reference may be presented as externally verified by default.',
            );
        });

        // The duplicated entries must be linked.
        $duplicates = $document->references()->whereNotNull('duplicate_of_id')->get();
        $this->assertGreaterThan(0, $duplicates->count());
    }

    public function test_the_formatted_document_is_downloadable_and_openable(): void
    {
        $submission = $this->submission();
        $version = $this->service->ingest($submission, $this->upload($this->fixtures->compliant('downloadable.docx')))['version'];

        $result = $this->service->process($version);
        $absolute = Storage::disk('private')->path($result->formattedPath);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($absolute, \ZipArchive::RDONLY) === true);
        $this->assertNotFalse($zip->locateName('word/document.xml'));
        $zip->close();
    }

    // ─────────── status neutrality ───────────

    public function test_processing_never_changes_the_submission_status(): void
    {
        $submission = $this->submission();
        $statusBefore = $submission->status;

        $version = $this->service->ingest($submission, $this->upload($this->fixtures->nonCompliant('status-neutral.docx')))['version'];
        $result = $this->service->process($version);

        $submission->refresh();

        $this->assertSame($statusBefore, $submission->status);
        $this->assertSame('submitted', $submission->status);
        $this->assertGreaterThan(0, $result->validation->error_count, 'Errors exist yet the status is untouched.');
    }

    public function test_an_unapproved_validation_blocks_progression(): void
    {
        $submission = $this->submission();
        $version = $this->service->ingest($submission, $this->upload($this->fixtures->nonCompliant('blocks.docx')))['version'];

        $result = $this->service->process($version);

        $this->assertTrue($result->validation->blocksProgression());
        $this->assertFalse($result->validation->isApproved());

        $result->validation->update(['approved_by' => $submission->author_id, 'approved_at' => now()]);

        $this->assertFalse($result->validation->fresh()->blocksProgression());
    }

    public function test_warnings_alone_never_block_progression(): void
    {
        $submission = $this->submission();
        $version = $this->service->ingest($submission, $this->upload($this->fixtures->compliant('warnings-only.docx')))['version'];

        $result = $this->service->process($version);

        // A manuscript that is bibliographically inconsistent produces warnings
        // and must still be readable by an editor.
        $bibliographicErrors = $result->validation->issues()
            ->where('category', 'citation')
            ->where('severity', 'error')
            ->count();

        $this->assertSame(0, $bibliographicErrors, 'Bibliographic problems are warnings, never errors.');

        $clean = new ManuscriptValidation([
            'manuscript_validation_id' => $result->validation->id,
            'submission_id' => $submission->id,
            'status' => ManuscriptValidation::STATUS_COMPLETED,
            'error_count' => 0,
            'warning_count' => 5,
        ]);

        $this->assertFalse($clean->blocksProgression(), 'Warnings must not gate editorial progression.');
    }

    // ─────────── repeatability ───────────

    public function test_processing_the_same_version_twice_is_stable(): void
    {
        $submission = $this->submission();
        $version = $this->service->ingest($submission, $this->upload($this->fixtures->nonCompliant('repeat.docx')))['version'];

        $first = $this->service->process($version);
        $second = $this->service->process($version);

        $this->assertSame(
            $first->result->toArray()['summary'],
            $second->result->toArray()['summary'],
            'Repeated processing must be deterministic.',
        );

        // Each run produces its own report rather than overwriting the first.
        $this->assertGreaterThanOrEqual(2, ManuscriptValidation::where('submission_id', $submission->id)->count());
    }

    // ─────────── style profiles ───────────

    public function test_it_seeds_the_built_in_style_profiles(): void
    {
        $created = $this->service->seedStyleProfiles();

        $this->assertSame(2, $created);
        $this->assertDatabaseHas('journal_style_profiles', ['slug' => 'ojmas']);
        $this->assertDatabaseHas('journal_style_profiles', ['slug' => 'apa7_professional']);

        // Seeding again must be idempotent.
        $this->assertSame(0, $this->service->seedStyleProfiles());
    }

    public function test_a_stored_profile_overrides_the_built_in_requirement(): void
    {
        $this->service->seedStyleProfiles();

        \App\Models\JournalStyleProfile::where('slug', 'ojmas')->update([
            'rules_json' => ['title_max_words' => 12],
        ]);

        $profile = (new StyleProfileRepository())->resolve('ojmas');

        $this->assertSame(12, $profile->requirement('title_max_words'), 'The stored value wins.');
        $this->assertSame(150, $profile->requirement('abstract_max_words'), 'Inherited requirements survive.');
    }
}