<?php

namespace Tests\Feature\Documents\Ingestion;

use App\Services\Documents\Exceptions\CorruptDocumentException;
use App\Services\Documents\Exceptions\DocumentSecurityException;
use App\Services\Documents\Exceptions\DocumentTooLargeException;
use App\Services\Documents\Exceptions\UnsupportedDocumentException;
use App\Services\Documents\Ingestion\DocxSecurityValidator;
use Tests\Fixtures\DocxFixtureFactory;
use Tests\TestCase;

class DocxSecurityValidatorTest extends TestCase
{
    private DocxFixtureFactory $fixtures;

    private function validator(array $overrides = []): DocxSecurityValidator
    {
        return new DocxSecurityValidator(array_merge([
            'max_upload_bytes' => 104857600,
            'max_entries' => 3000,
            'max_entry_uncompressed_bytes' => 268435456,
            'max_total_uncompressed_bytes' => 536870912,
            'max_compression_ratio' => 120,
            'required_parts' => ['[Content_Types].xml', 'word/document.xml'],
            'forbidden_parts' => ['vbaProject.bin'],
        ], $overrides));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtures = new DocxFixtureFactory();
    }

    public function test_it_accepts_a_genuine_docx_and_reports_package_facts(): void
    {
        $path = $this->fixtures->compliant('valid-1.docx');

        $report = $this->validator()->validate($path, basename($path));

        $this->assertGreaterThan(0, $report->entryCount);
        $this->assertContains('word/document.xml', $report->partNames);
        $this->assertFalse($report->hasMacros());
        $this->assertIsArray($report->toArray());
    }

    public function test_it_rejects_a_file_that_is_not_a_zip_container(): void
    {
        $path = $this->fixtures->notADocument('plain.docx');

        $this->expectException(DocumentSecurityException::class);

        try {
            $this->validator()->validate($path, 'plain.docx');
        } catch (DocumentSecurityException $e) {
            $this->assertSame('document_not_ooxml_container', $e->errorCode());
            throw $e;
        }
    }

    public function test_it_rejects_a_zip_without_the_required_ooxml_parts(): void
    {
        $path = $this->fixtures->fakeDocx('fake-1.docx');

        $this->expectException(CorruptDocumentException::class);

        try {
            $this->validator()->validate($path, 'fake.docx');
        } catch (CorruptDocumentException $e) {
            $this->assertSame('document_missing_required_part', $e->errorCode());
            throw $e;
        }
    }

    public function test_it_rejects_a_document_containing_a_macro_project(): void
    {
        $path = $this->fixtures->withMacros('macro-1.docx');

        $this->expectException(DocumentSecurityException::class);

        try {
            $this->validator()->validate($path, 'macro.docx');
        } catch (DocumentSecurityException $e) {
            $this->assertSame('document_contains_macros', $e->errorCode());
            throw $e;
        }
    }

    public function test_it_rejects_an_archive_entry_containing_path_traversal(): void
    {
        $path = $this->fixtures->withPathTraversal('traversal-1.docx');

        $this->expectException(DocumentSecurityException::class);

        try {
            $this->validator()->validate($path, 'traversal.docx');
        } catch (DocumentSecurityException $e) {
            $this->assertSame('document_path_traversal', $e->errorCode());
            throw $e;
        }
    }

    public function test_it_rejects_a_decompression_bomb(): void
    {
        $path = $this->fixtures->compressionBomb('bomb-1.docx');

        $this->expectException(DocumentSecurityException::class);

        try {
            $this->validator()->validate($path, 'bomb.docx');
        } catch (DocumentSecurityException $e) {
            $this->assertContains($e->errorCode(), [
                'document_compression_bomb',
                'document_entry_too_large',
                'document_expansion_limit_exceeded',
            ]);
            throw $e;
        }
    }

    public function test_it_rejects_an_empty_file(): void
    {
        $path = $this->fixtures->emptyFile('empty-1.docx');

        $this->expectException(CorruptDocumentException::class);

        try {
            $this->validator()->validate($path, 'empty.docx');
        } catch (CorruptDocumentException $e) {
            $this->assertSame('document_empty', $e->errorCode());
            throw $e;
        }
    }

    public function test_it_rejects_a_corrupt_container(): void
    {
        $path = $this->fixtures->corrupt('corrupt-1.docx');

        $this->expectException(CorruptDocumentException::class);

        $this->validator()->validate($path, 'corrupt.docx');
    }

    public function test_it_rejects_a_file_over_the_size_ceiling(): void
    {
        $path = $this->fixtures->compliant('too-large.docx');

        $this->expectException(DocumentTooLargeException::class);

        $this->validator(['max_upload_bytes' => 1024])->validate($path, 'too-large.docx');
    }

    public function test_it_rejects_an_archive_with_too_many_entries(): void
    {
        $path = $this->fixtures->compliant('many-entries.docx');

        $this->expectException(DocumentSecurityException::class);

        try {
            $this->validator(['max_entries' => 2])->validate($path, 'many-entries.docx');
        } catch (DocumentSecurityException $e) {
            $this->assertSame('document_too_many_entries', $e->errorCode());
            throw $e;
        }
    }

    public function test_it_rejects_a_non_docx_extension_with_an_unsupported_error(): void
    {
        $path = $this->fixtures->compliant('as-pdf.pdf');

        $this->expectException(UnsupportedDocumentException::class);

        try {
            $this->validator()->validate($path, 'as-pdf.pdf');
        } catch (UnsupportedDocumentException $e) {
            $this->assertSame('document_unsupported_format', $e->errorCode());

            // Unsupported is a warning, not a hard error: the journal still
            // accepts the manuscript, it just cannot be reformatted.
            $this->assertSame('warning', $e->severity());
            throw $e;
        }
    }

    public function test_it_reports_missing_source_files_rather_than_crashing(): void
    {
        $this->expectException(CorruptDocumentException::class);

        $this->validator()->validate(
            sys_get_temp_dir().'/definitely-not-here-'.uniqid().'.docx',
            'missing.docx',
        );
    }

    public function test_validation_never_modifies_the_source_file(): void
    {
        $path = $this->fixtures->compliant('immutable.docx');

        $before = hash_file('sha256', $path);
        $sizeBefore = filesize($path);

        $this->validator()->validate($path, 'immutable.docx');

        $this->assertSame($before, hash_file('sha256', $path), 'The validator must never write to the upload.');
        $this->assertSame($sizeBefore, filesize($path));
    }
}