<?php

namespace Tests\Feature\Editorial;

use App\Models\ArticleAuthor;
use App\Models\ArticleFile;
use App\Models\Submission;
use App\Models\User;
use App\Services\Review\ManuscriptAnonymiser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * §3.6, blinded review: "Do not assume that hiding a name in the interface
 * makes a document anonymous."
 *
 * These tests exercise the document itself: DOCX properties, tracked-change
 * authorship, body text and session identifiers are all channels by which a
 * reviewer could identify an author. The anonymiser must close each of them,
 * and must say so honestly when it cannot.
 */
class ManuscriptAnonymiserTest extends TestCase
{
    use RefreshDatabase;

    private ManuscriptAnonymiser $anonymiser;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');

        $this->anonymiser = app(ManuscriptAnonymiser::class);
    }

    /**
     * A deliberately leaky manuscript: the author's name sits in the file
     * properties, the tracked changes, the acknowledgements and the body; the
     * rsid trail points at her editing sessions; docProps/custom.xml carries
     * authoring-tool identity.
     */
    private function leakyDocxPath(): string
    {
        $path = Storage::disk('private')->path('submissions/manuscript/leaky.docx');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        $namespaces = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"';

        $run = fn (string $text): string => '<w:r><w:t>'.$text.'</w:t></w:r>';
        $para = fn (string $text): string => '<w:p>'.$run($text).'</w:p>';

        $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document '.$namespaces.'><w:body>'
            .'<w:p w:rsidR="00AB12CD" w:rsidRDefault="00AB12CD" w:rsidP="00AB12CD">'
            .'<w:ins w:id="1" w:author="Adebayo Okonkwo" w:date="2026-02-01T10:00:00Z">'
            .'<w:r><w:t>An introduction drafted by Adebayo Okonkwo.</w:t></w:r></w:ins></w:p>'
            .$para('Author 1: Adebayo Okonkwo; Author 2: Chinwe Adeyemi.')
            .$para('Affiliations: University of Ilorin; University of Ilorin Teaching Hospital.')
            .$para('Correspondence: chinwe.adeyemi@unilorin.edu.ng')
            .$para('ORCID: 0000-0002-1825-0097')
            .'<w:p><w:r><w:t>Acknowledgements</w:t></w:r></w:p>'
            .$para('The authors thank Prof. Bolanle Ogunlesi for her comments on drafting.')
            .'<w:p><w:r><w:t>Funding</w:t></w:r></w:p>'
            .$para('Grant TETFund/2026/0042 was received by Adebayo Okonkwo.')
            .'</w:body></w:document>';

        $settings = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:settings '.$namespaces.'>'
            .'<w:rsids><w:rsid w:val="00AB12CD"/><w:rsidR w:val="00EF45GH"/></w:rsids>'
            .'<w:defaultTabStop w:val="720"/>'
            .'</w:settings>';

        $core = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            .'xmlns:dc="http://purl.org/dc/elements/1.1/">'
            .'<dc:creator>Adebayo Okonkwo</dc:creator>'
            .'<cp:lastModifiedBy>Adebayo Okonkwo</cp:lastModifiedBy>'
            .'<dc:title>Instructional Scaffolding and Second Language Mastery</dc:title>'
            .'</cp:coreProperties>';

        $custom = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/custom-properties" '
            .'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            .'<vt:lpwstr>Written on Adebayo\u2019s laptop</vt:lpwstr>'
            .'</Properties>';

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'<Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .'<Override PartName="/docProps/custom.xml" ContentType="application/vnd.openxmlformats-officedocument.custom-properties+xml"/>'
            .'</Types>';

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('word/document.xml', $document);
        $zip->addFromString('word/settings.xml', $settings);
        $zip->addFromString('docProps/core.xml', $core);
        $zip->addFromString('docProps/custom.xml', $custom);
        $zip->close();

        return $path;
    }

    /**
     * A submission whose stored manuscript is the leaky fixture above, under a
     * filename that itself would unmask the author.
     */
    private function submissionWithIdentityLeaks(): Submission
    {
        $author = User::factory()->create([
            'name' => 'Adebayo Okonkwo',
            'email' => 'adebayo.okonkwo@unilorin.edu.ng',
            'institution' => 'University of Ilorin',
        ]);

        $submission = Submission::factory()->underReview()->create(['author_id' => $author->id]);

        ArticleAuthor::create([
            'submission_id' => $submission->id,
            'full_name' => 'Chinwe Adeyemi',
            'email' => 'chinwe.adeyemi@unilorin.edu.ng',
            'institution' => 'University of Ilorin Teaching Hospital',
            'is_corresponding' => false,
            'author_order' => 2,
        ]);

        $source = $this->leakyDocxPath();

        $submission->files()->create([
            'file_type' => 'manuscript',
            'original_name' => 'Adebayo Okonkwo journal paper final FINAL (2).docx',
            'file_path' => 'submissions/manuscript/leaky.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_size' => filesize($source),
            'sha256' => hash_file('sha256', $source),
            'uploaded_by' => $author->id,
        ]);

        return $submission;
    }

    public function test_the_blinded_copy_closes_every_identity_channel(): void
    {
        $submission = $this->submissionWithIdentityLeaks();

        $result = $this->anonymiser->createReviewCopy($submission);

        // 1. The filename carries no identity.
        $this->assertStringStartsWith('MANUSCRIPT-', $result['filename']);
        $this->assertStringNotContainsString('Adebayo', $result['filename']);
        $this->assertStringNotContainsString('Okonkwo', $result['filename']);

        $stored = ArticleFile::where('submission_id', $submission->id)
            ->where('file_type', 'manuscript')
            ->where('is_anonymised', true)
            ->first();

        $this->assertNotNull($stored, 'The blinded copy must be recorded.');

        $zip = new ZipArchive();
        $this->assertTrue($zip->open(Storage::disk('private')->path($stored->file_path)) === true);

        $document = (string) $zip->getFromName('word/document.xml');
        $core = (string) $zip->getFromName('docProps/core.xml');

        // 2. Document properties.
        $this->assertStringNotContainsString('Adebayo Okonkwo', $core);
        $this->assertStringNotContainsString('Adebayo Okonkwo', $document);

        // 3. Body text: names, affiliations, emails, ORCID, funding.
        $this->assertStringNotContainsString('Chinwe Adeyemi', $document);
        $this->assertStringNotContainsString('University of Ilorin', $document);
        $this->assertStringNotContainsString('adebayo.okonkwo@unilorin.edu.ng', $document);
        $this->assertStringNotContainsString('chinwe.adeyemi@unilorin.edu.ng', $document);
        $this->assertStringNotContainsString('0000-0002-1825-0097', $document);
        $this->assertStringNotContainsString('Prof. Bolanle Ogunlesi', $document);
        $this->assertStringNotContainsString('TETFund/2026/0042', $document);

        // Role labels must have replaced them rather than deletion by stealth.
        $this->assertStringContainsString('[AUTHOR NAME REDACTED FOR BLIND REVIEW]', $document);
        $this->assertStringContainsString('[AUTHOR AFFILIATION REDACTED FOR BLIND REVIEW]', $document);
        $this->assertStringContainsString('[CORRESPONDING AUTHOR CONTACT REDACTED]', $document);

        // 4. Tracked-change authorship is neutralised.
        $this->assertStringNotContainsString('w:author="Adebayo Okonkwo"', $document);
        $this->assertStringContainsString('w:author="Reviewer', $document);

        // 5. Editing-session identifiers.
        $this->assertStringNotContainsString('00AB12CD', $document);
        $this->assertStringNotContainsString('w:rsid', $document);
        $this->assertStringNotContainsString('w:rsid', (string) $zip->getFromName('word/settings.xml'));

        // 6. Authoring-tool parts are stripped outright.
        $this->assertFalse(
            $zip->locateName('docProps/custom.xml'),
            'docProps/custom.xml carries authoring-tool identity and must be stripped.',
        );

        $zip->close();
    }

    public function test_the_original_file_is_untouched(): void
    {
        $submission = $this->submissionWithIdentityLeaks();
        $before = sha1_file(Storage::disk('private')->path('submissions/manuscript/leaky.docx'));

        $this->anonymiser->createReviewCopy($submission);

        $this->assertSame(
            $before,
            sha1_file(Storage::disk('private')->path('submissions/manuscript/leaky.docx')),
            "Anonymisation must never modify the author's original.",
        );
    }

    public function test_a_second_run_reuses_the_same_anonymous_code(): void
    {
        $submission = $this->submissionWithIdentityLeaks();

        $first = $this->anonymiser->createReviewCopy($submission);
        $second = $this->anonymiser->createReviewCopy($submission);

        $this->assertSame($first['code'], $second['code']);
        $this->assertDatabaseHas('submissions', [
            'id' => $submission->id,
            'anonymous_code' => $first['code'],
        ]);
    }

    public function test_the_manifest_reports_what_was_removed(): void
    {
        $submission = $this->submissionWithIdentityLeaks();

        $result = $this->anonymiser->createReviewCopy($submission);

        $this->assertNotEmpty($result['transformations'], 'The manifest must name what was removed.');

        foreach ($result['transformations'] as $transformation) {
            $this->assertIsString($transformation);
        }
    }

    public function test_a_submission_without_a_manuscript_reports_clearly(): void
    {
        $submission = Submission::factory()->underReview()->create();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no stored manuscript');

        $this->anonymiser->createReviewCopy($submission);
    }
}
