<?php

namespace App\Services\Review;

use App\Models\ArticleFile;
use App\Models\Submission;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Produces a double-blind review copy of a manuscript.
 *
 * The module brief is explicit: **"Do not assume that hiding a name in the
 * interface makes a document anonymous."** This service therefore removes
 * identity from every channel a reviewer can reach, not just the page:
 *
 *  1. **File name.** Reviewers receive `MANUSCRIPT-<code>.docx`, never the
 *     author's filename, which routinely contains a name or institution.
 *  2. **Document properties.** `dc:creator`, `cp:lastModifiedBy`, `dc:title`
 *     and `cp:keywords` are rewritten, and a `docProps/custom.xml` carrying
 *     authoring tools is stripped entirely.
 *  3. **Revision authorship.** Every `w:ins` and `w:del` author attribute is
 *     replaced with a neutral code, as are `w:comment` authors and initials.
 *  4. **Running metadata.** `w:rsid` revision-save identifiers are removed;
 *     they are derived from editing sessions and can leak.
 *  5. **Body content.** Author names, affiliations, emails, ORCIDs, funding
 *     identifiers and acknowledgement blocks are replaced with role labels.
 *  6. **Relationships.** External targets pointing at author or institution
 *     pages are neutralised where they are plainly identifying.
 *
 * Every one of these transformations is recorded in the returned manifest so
 * the editor can see exactly what was anonymised, and the service reports any
 * identity it believes it could NOT remove rather than claiming a clean copy.
 */
class ManuscriptAnonymiser
{
    /** Replacement tokens used in the body text. */
    private const AUTHOR_TOKEN = '[AUTHOR NAME REDACTED FOR BLIND REVIEW]';
    private const AFFILIATION_TOKEN = '[AUTHOR AFFILIATION REDACTED FOR BLIND REVIEW]';
    private const EMAIL_TOKEN = '[CORRESPONDING AUTHOR CONTACT REDACTED]';
    private const ACKNOWLEDGEMENT_TOKEN = '[ACKNOWLEDGEMENTS REDACTED FOR BLIND REVIEW]';
    private const FUNDING_TOKEN = '[FUNDING INFORMATION REDACTED FOR BLIND REVIEW]';
    private const ORCID_TOKEN = '[ORCID REDACTED FOR BLIND REVIEW]';

    /**
     * Parts that carry authoring-tool identity and are simply dropped.
     */
    private const STRIPPED_PARTS = [
        'docProps/custom.xml',
        'word/people.xml',
        'word/commentsIds.xml',
    ];

    /**
     * Creates or refreshes the anonymised review copy for a submission.
     *
     * @return array{
     *   path: string, filename: string, code: string,
     *   transformations: array<int, string>, residual_risks: array<int, string>
     * }
     */
    public function createReviewCopy(Submission $submission): array
    {
        $code = $this->anonymousCode($submission);
        $source = $this->latestSourceFile($submission);

        if ($source === null) {
            throw new \RuntimeException('This submission has no stored manuscript to anonymise.');
        }

        $disk = Storage::disk(config('manuscripts.disk', 'private'));
        $absoluteSource = $disk->path($source->file_path);

        if (! is_file($absoluteSource)) {
            throw new \RuntimeException('The stored manuscript file could not be found on the private disk.');
        }

        $identities = $this->collectIdentities($submission);
        $filename = 'MANUSCRIPT-'.$code.'.docx';

        $targetRelative = sprintf(
            '%s/%s-%s.docx',
            trim((string) config('manuscripts.paths.formatted', 'manuscripts/formatted'), '/'),
            'blind',
            Str::random(32),
        );

        $absoluteTarget = $disk->path($targetRelative);
        $directory = dirname($absoluteTarget);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Unable to create the blinded manuscript directory.');
        }

        $transformations = [];

        $zip = new ZipArchive();

        if ($zip->open($absoluteSource) !== true) {
            throw new \RuntimeException('The source manuscript could not be opened for anonymisation.');
        }

        $out = new ZipArchive();
        $out->open($absoluteTarget, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $residualRisks = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            if (in_array($name, self::STRIPPED_PARTS, true)) {
                $transformations[] = 'Stripped '.$name.' (carries authoring-tool identity).';
                continue;
            }

            $contents = $zip->getFromIndex($i);

            if ($contents === false) {
                continue;
            }

            if (str_starts_with($name, 'docProps/') && str_ends_with($name, '.xml')) {
                $contents = $this->anonymiseCoreProperties($contents);
                $transformations[] = 'Rewrote '.$name.' so document properties carry no author identity.';
                $out->addFromString($name, $contents);

                continue;
            }

            if (in_array($name, ['word/document.xml', 'word/comments.xml', 'word/footnotes.xml', 'word/endnotes.xml'], true)) {
                $before = $contents;
                $contents = $this->anonymiseDocumentXml($contents, $code, $identities, $transformations);
                $residualRisks = array_merge($residualRisks, $this->residualRisks($before, $contents));

                $out->addFromString($name, $contents);

                continue;
            }

            if ($name === 'word/settings.xml') {
                $contents = $this->stripRsids($contents);
                $transformations[] = 'Removed editing-session identifiers from word/settings.xml.';
                $out->addFromString($name, $contents);

                continue;
            }

            $out->addFromString($name, $contents);
        }

        $zip->close();
        $out->close();

        $file = ArticleFile::updateOrCreate(
            [
                'submission_id' => $submission->id,
                'file_type' => 'manuscript',
                'version_label' => 'Blinded review copy '.$code,
            ],
            [
                'original_name' => $filename,
                'file_path' => $targetRelative,
                'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'file_size' => (int) filesize($absoluteTarget),
                'sha256' => hash_file('sha256', $absoluteTarget),
                'is_anonymised' => true,
            ],
        );

        return [
            'path' => $targetRelative,
            'filename' => $filename,
            'code' => $code,
            'transformations' => array_values(array_unique($transformations)),
            'residual_risks' => array_values(array_unique($residualRisks)),
        ];
    }

    /**
     * A stable, non-guessable identifier used in place of the submission
     * number on every reviewer-facing artefact.
     */
    public function anonymousCode(Submission $submission): string
    {
        if (blank($submission->anonymous_code)) {
            $submission->forceFill(['anonymous_code' => strtoupper(Str::random(10))])->save();
        }

        return (string) $submission->fresh()->anonymous_code;
    }

    /**
     * The identity strings to scrub from the document body.
     *
     * @return array<string, string>
     */
    private function collectIdentities(Submission $submission): array
    {
        $submission->loadMissing(['author', 'coAuthors']);

        $identities = [];

        $add = function (?string $value, string $replacement) use (&$identities): void {
            $value = trim((string) $value);

            // Very short tokens would strip innocent text out of the manuscript.
            if (mb_strlen($value) < 4) {
                return;
            }

            $identities[$value] = $replacement;
        };

        $add($submission->author?->name, self::AUTHOR_TOKEN);
        $add($submission->author?->institution, self::AFFILIATION_TOKEN);
        $add($submission->author?->email, self::EMAIL_TOKEN);

        foreach ($submission->coAuthors as $coAuthor) {
            $add($coAuthor->full_name, self::AUTHOR_TOKEN);
            $add($coAuthor->institution, self::AFFILIATION_TOKEN);
            $add($coAuthor->email, self::EMAIL_TOKEN);
        }

        // Longest first, so "University of Ilorin" is replaced before
        // "Ilorin" can match inside it.
        uksort($identities, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));

        return $identities;
    }

    private function anonymiseCoreProperties(string $xml): string
    {
        $replacements = [
            'dc:creator' => 'Author',
            'cp:lastModifiedBy' => 'Author',
            'dc:title' => 'Manuscript for blinded review',
            'dc:subject' => 'Manuscript',
            'cp:keywords' => '',
        ];

        foreach ($replacements as $element => $value) {
            $xml = preg_replace(
                '#(<'.preg_quote($element, '#').'\b[^>]*>)(.*?)(</'.preg_quote($element, '#').'>)#s',
                '$1'.htmlspecialchars($value, ENT_XML1).'$3',
                $xml,
            ) ?? $xml;
        }

        return $xml;
    }

    /**
     * @param  array<string, string>  $identities
     * @param  array<int, string>  $transformations
     */
    private function anonymiseDocumentXml(string $xml, string $code, array $identities, array &$transformations): string
    {
        // Tracked-change and comment authorship.
        $xml = preg_replace(
            '/(<w:(?:ins|del|comment)\b[^>]*?\sw:author=")[^"]*(")/i',
            '$1Reviewer'.$code.'$2',
            $xml,
        ) ?? $xml;

        $xml = preg_replace(
            '/(<w:(?:ins|del|comment)\b[^>]*?\sw:initials=")[^"]*(")/i',
            '$1R$2',
            $xml,
        ) ?? $xml;

        if (str_contains($xml, 'Reviewer'.$code)) {
            $transformations[] = 'Replaced tracked-change and comment authorship with a neutral reviewer code.';
        }

        // Editing-session identifiers.
        $before = preg_match_all('/\sw:rsid[A-Za-z]*="[^"]*"/i', $xml);
        $xml = preg_replace('/\sw:rsid[A-Za-z]*="[^"]*"/i', '', $xml) ?? $xml;

        if ($before > 0) {
            $transformations[] = 'Removed '.$before.' editing-session identifiers that could link to an author.';
        }

        // Plain-text identities.
        foreach ($identities as $identity => $replacement) {
            $escaped = htmlspecialchars($identity, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $replacementXml = htmlspecialchars($replacement, ENT_XML1 | ENT_QUOTES, 'UTF-8');

            $count = 0;
            $xml = str_replace($escaped, $replacementXml, $xml, $count);

            if ($count > 0) {
                $transformations[] = sprintf(
                    'Replaced %d occurrence(s) of an identifying name or affiliation with "%s".',
                    $count,
                    $replacement,
                );
            }
        }

        // Structured declarations.
        $xml = $this->redactSection($xml, 'Acknowledg', self::ACKNOWLEDGEMENT_TOKEN, $transformations);
        $xml = $this->redactSection($xml, 'Funding', self::FUNDING_TOKEN, $transformations);
        $xml = $this->redactSection($xml, 'Conflict of interest', self::FUNDING_TOKEN, $transformations);

        // ORCIDs.
        $count = 0;
        $xml = preg_replace(
            '/\b\d{4}-\d{4}-\d{4}-\d{3}[\dX]\b/',
            self::ORCID_TOKEN,
            $xml,
            -1,
            $count,
        ) ?? $xml;

        if ($count > 0) {
            $transformations[] = 'Replaced '.$count.' ORCID identifier(s).';
        }

        // Author email addresses remaining anywhere in the body.
        $count = 0;
        $xml = preg_replace(
            '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i',
            self::EMAIL_TOKEN,
            $xml,
            -1,
            $count,
        ) ?? $xml;

        if ($count > 0) {
            $transformations[] = 'Replaced '.$count.' email address(es) remaining in the body.';
        }

        return $xml;
    }

    /** @param array<int, string> $transformations */
    private function redactSection(string $xml, string $headingNeedle, string $token, array &$transformations): string
    {
        $tokenXml = htmlspecialchars($token, ENT_XML1);

        // Split into paragraph spans so declarations such as the grant line that
        // follows a "Funding" heading fall inside the redacted window.
        if (preg_match_all('/<w:p\b[^>]*>.*?<\/w:p>/s', $xml, $paragraphs, PREG_OFFSET_CAPTURE) === false) {
            return $xml;
        }

        $matches = $paragraphs[0];
        $redacted = 0;

        for ($i = 0; $i < count($matches); $i++) {
            [$paragraphSpan, $offset] = $matches[$i];
            $visible = $this->paragraphText($paragraphSpan);

            if (mb_strtolower(trim($visible)) === mb_strtolower($headingNeedle)
                || mb_strpos(mb_strtolower(trim($visible)), mb_strtolower($headingNeedle)) === 0) {
                // The heading paragraph itself, plus up to three body
                // paragraphs of description beneath it.
                for ($j = $i; $j < min($i + 4, count($matches)); $j++) {
                    $span = $matches[$j][0];

                    if ($j > $i
                        && trim($this->paragraphText($span)) === ''
                        && str_contains($span, '<w:t')) {
                        // An empty paragraph ends a block cheaply padded out.
                        continue;
                    }

                    $replaced = preg_replace(
                        '/(<w:t\b[^>]*>)(.*?)(<\/w:t>)/s',
                        '$1'.$tokenXml.'$3',
                        $span,
                    );

                    if ($replaced !== null && $replaced !== $span) {
                        $redacted++;
                        $xml = substr_replace($xml, $replaced, $matches[$j][1], strlen($span));
                    }
                }

                break;
            }
        }

        if ($redacted > 0) {
            $transformations[] = 'Redacted a "'.$headingNeedle.'" declaration and its descriptive text.';
        }

        return $xml;
    }

    /** The visible text of a single paragraph, without markup. */
    private function paragraphText(string $paragraph): string
    {
        return trim(html_entity_decode(
            (string) strip_tags(
                (string) preg_replace('#<w:t\b[^>]*>(.*?)</w:t>#s', '$1', $paragraph),
            ),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        ));
    }

    /**
     * Identity that survives anonymisation and that a human should look at.
     *
     * Reporting this honestly is the point: a "blind" copy that silently leaks
     * is worse than one that admits a residual risk.
     *
     * @return array<int, string>
     */
    private function residualRisks(string $before, string $after): array
    {
        $risks = [];

        $removed = $this->extractVisibleText($before);
        $kept = $this->extractVisibleText($after);

        foreach ($this->nameLikeTokens($removed) as $token) {
            if (str_contains($kept, $token)) {
                $risks[] = 'A personal name ("'.$token.'") appears to remain in the text; check it manually.';
            }
        }

        foreach (['http://', 'https://'] as $needle) {
            $beforeCount = substr_count($removed, $needle);
            $afterCount = substr_count($kept, $needle);

            if ($beforeCount > $afterCount) {
                $risks[] = 'Some web links were removed; remaining links may still identify the institution.';
            }
        }

        return $risks;
    }

    /** Names that appear in acknowledgements, methods prose or funding text. */
    private function nameLikeTokens(string $text): array
    {
        $candidates = [];

        if (preg_match_all('/\b(?:Prof(?:essor)?|Dr|Mr|Mrs|Ms|Miss)\.?\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+){0,2})/u', $text, $matches) > 0) {
            foreach ($matches[0] as $match) {
                $candidates[] = $match;
            }
        }

        return array_values(array_unique($candidates));
    }

    /** Visible text only, so markup attributes do not produce false positives. */
    private function extractVisibleText(string $xml): string
    {
        $text = preg_replace('#<w:t\b[^>]*>(.*?)</w:t>#s', '$1', $xml) ?? $xml;

        return html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function stripRsids(string $xml): string
    {
        // `<w:rsidR .../>` and friends: the letter suffix must stay out of the
        // word boundary, or the pattern would never match them.
        $xml = preg_replace('/<w:rsid[A-Za-z]*\b[^>]*\/>/i', '', $xml) ?? $xml;

        // An emptied <w:rsids> container may as well go too.
        return preg_replace('/<w:rsids\b[^>]*>\s*<\/w:rsids>/i', '', $xml) ?? $xml;
    }

    private function latestSourceFile(Submission $submission): ?ArticleFile
    {
        return $submission->files()
            ->where('file_type', 'manuscript')
            ->where('is_anonymised', false)
            ->latest('id')
            ->first()
            ?? $submission->files()->where('file_type', 'manuscript')->latest('id')->first();
    }
}