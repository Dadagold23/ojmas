<?php

namespace Tests\Fixtures;

use ZipArchive;

/**
 * Builds representative DOCX fixtures by emitting OOXML directly.
 *
 * Generating the XML by hand (rather than through a library) gives the tests
 * exact control over page geometry, fonts, spacing and indentation — which is
 * precisely what the APA 7 rule set is asserted against. Every manuscript
 * produced here is synthetic.
 */
class DocxFixtureFactory
{
    /** A 1x1 transparent PNG, inlined because ext-gd is unavailable. */
    public const PIXEL_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?? sys_get_temp_dir().'/ojmas-docx-fixtures';

        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0777, true);
        }
    }

    public function path(string $name): string
    {
        return $this->directory.'/'.$name;
    }

    // ───────────────────────── manuscript fixtures ─────────────────────────

    /** A manuscript that already satisfies the APA 7 rule set. */
    public function compliant(string $name = 'compliant.docx'): string
    {
        $body = '';

        $body .= $this->paragraph('Effects of Instructional Scaffolding on Secondary Students\' Achievement', [
            'style' => 'Title',
            'jc' => 'center',
        ]);

        $body .= $this->paragraph('Amina Bello', ['jc' => 'center', 'bold' => true]);
        $body .= $this->paragraph('Department of Educational Sciences, University of Ilorin, Nigeria', ['jc' => 'center']);
        $body .= $this->paragraph('Corresponding author: a.bello@example.edu', ['jc' => 'center']);

        $body .= $this->paragraph('Abstract', ['style' => 'Heading1']);
        $body .= $this->paragraph(
            'This study examined the effect of instructional scaffolding on the academic achievement of '
            .'secondary school students in a Nigerian urban context. A quasi-experimental design was adopted in '
            .'which 128 students were assigned to scaffolded and conventional instruction groups. Achievement '
            .'data were collected with a validated locally adapted instrument, and results indicate that '
            .'scaffolded instruction produced statistically significant gains relative to conventional '
            .'instruction, with the largest differences observed in higher-order cognitive outcomes.',
            ['firstLine' => 0],
        );

        $body .= $this->paragraph('Keywords: instructional scaffolding, secondary education, achievement', ['firstLine' => 0]);

        $body .= $this->heading('Introduction', 1);
        $body .= $this->paragraph(
            'Instructional scaffolding has been widely promoted as a means of supporting learners who have not yet '
            .'developed the cognitive structures required for independent task performance (Belland et al., 2020). '
            .'Despite the popularity of the approach, evidence from Sub-Saharan African classrooms remains sparse.',
        );

        $body .= $this->heading('Method', 1);
        $body .= $this->heading('Design and Participants', 2);
        $body .= $this->paragraph('A quasi-experimental design was used. Participants were 128 senior secondary students.');

        $body .= $this->heading('Results', 1);
        $body .= $this->paragraph('Descriptive and inferential statistics are reported in Table 1.');
        $body .= $this->table(
            [['Group', 'M', 'SD'], ['Scaffolded', '72.4', '8.1'], ['Control', '64.9', '9.7']],
            true,
        );

        $body .= $this->heading('Discussion', 1);
        $body .= $this->paragraph('The findings corroborate earlier work (Vygotsky, 1978; Wood et al., 1976).');

        $body .= $this->heading('References', 1);

        foreach ([
            'Belland, B. R., Walker, A. E., Kim, S., & Lefler, M. (2020). Synthesizing results from empirical '
            .'research on computer-based scaffolding in K-12 education: A meta-analysis. Review of Educational '
            .'Research, 90(4), 611-651. https://doi.org/10.3102/0034654320914221',
            'Vygotsky, L. S. (1978). Mind in society: The development of higher psychological processes. Harvard University Press.',
            'Wood, D., Bruner, J. S., & Ross, G. (1976). The role of tutoring in problem solving. Journal of Child '
            .'Psychology and Psychiatry, 17(2), 89-100. https://doi.org/10.1111/j.1469-7610.1976.tb00381.x',
        ] as $reference) {
            $body .= $this->paragraph($reference, ['hanging' => 720, 'left' => 720, 'line' => 480, 'rule' => 'auto']);
        }

        return $this->write($name, $body, $this->a4Section(1440));
    }

    /** A manuscript whose body paragraphs deliberately carry indent + spacing choices. */
    public function indented(string $name = 'indented.docx'): string
    {
        $body = '';

        $body .= $this->paragraph('Effects of Instructional Scaffolding on Secondary Students\' Achievement', [
            'style' => 'Title',
            'jc' => 'center',
        ]);
        $body .= $this->paragraph('Amina Bello', ['jc' => 'center', 'bold' => true]);

        $body .= $this->heading('Introduction', 1);
        $body .= $this->paragraph(
            'This body paragraph carries the author\'s own 0.5 inch first-line indent, a 0.25 inch left indent '
            .'and explicit 6 pt spacing before it, so the workspace can be verified to accept the upload as '
            .'it was formatted rather than rejecting or rewriting the paragraph geometry.',
            ['firstLine' => 720, 'left' => 360, 'before' => 120, 'after' => 240],
        );
        $body .= $this->paragraph(
            'This body paragraph is deliberately flush with the margin: the author set an explicit zero '
            .'first-line indent on it, which the workspace must also preserve as a decision rather than '
            .'treat it as an unset default.',
            ['firstLine' => 0],
        );
        $body .= $this->paragraph(
            'This body paragraph carries no indentation attributes at all, so APA 7 must apply its own '
            .'0.5 inch first-line indent default when the workspace exports it.',
        );

        $body .= $this->heading('Results', 1);
        $body .= $this->paragraph('Descriptive statistics are reported in the body text without a table.');

        $body .= $this->heading('References', 1);
        $body .= $this->paragraph(
            'Belland, B. R., Walker, A. E., Kim, S., & Lefler, M. (2020). Synthesizing results from empirical '
            .'research on computer-based scaffolding in K-12 education: A meta-analysis. Review of '
            .'Educational Research, 90(4), 611-651. https://doi.org/10.3102/0034654320914221',
            ['hanging' => 720, 'left' => 720, 'line' => 480, 'rule' => 'auto'],
        );

        return $this->write($name, $body, $this->a4Section(1440));
    }

    /** A manuscript with APA violations for the rule engine to correct. */
    public function nonCompliant(string $name = 'non-compliant.docx'): string
    {
        $body = '';

        $body .= $this->paragraph(
            'A Study Whose Title Is Deliberately Far Longer Than The Eighteen Word Ceiling That The Journal '
            .'Imposes On Submissions So That The Title Length Rule Can Be Exercised',
            ['style' => 'Title'],
        );

        $body .= $this->paragraph('Author Name Here');
        $body .= $this->paragraph('Some Institution');

        $body .= $this->paragraph('Abstract', ['style' => 'Heading1']);
        $body .= $this->paragraph(
            'This abstract is deliberately written far beyond the one hundred and fifty word ceiling that the '
            .'journal imposes, in order to exercise the abstract length validation rule during automated testing '
            .'of the formatting engine and its associated reporting mechanisms for authors and editors alike. '
            .'It continues for a considerable length so that the word counter has no difficulty whatsoever in '
            .'detecting that the requirement has been exceeded by a wide margin, which is the whole purpose of '
            .'this particular fixture document in the automated test suite for the manuscript pipeline and its '
            .'associated reporting and preview functionality that authors will be shown before they approve it. '
            .'The study reported here examined one hundred and ninety four secondary school students across three '
            .'urban schools in a single Nigerian metropolitan area, comparing two instructional sequences that '
            .'differed only in the degree of scaffolding provided to learners during independent practice. Data were '
            .'collected across three successive terms using a locally adapted instrument whose reliability had been '
            .'established in an earlier pilot study. Results indicate that scaffolded instruction produced '
            .'statistically significant gains, with the largest differences observed in higher order cognitive '
            .'outcomes rather than in recall of factual material, and these differences persisted at follow up.',
            ['line' => 240, 'rule' => 'auto', 'firstLine' => 720, 'font' => 'Courier New', 'size' => 22, 'jc' => 'both'],
        );

        $body .= $this->paragraph('Keywords: one, two, three', ['firstLine' => 720]);

        $body .= $this->heading('Introduction', 1);

        foreach (range(1, 4) as $index) {
            $body .= $this->paragraph(
                "Body paragraph {$index}. This paragraph cites (Smith, 2020) and another work "
                .'(Jones & Brown, 2019) to exercise citation detection across a multi paragraph body section.',
                ['font' => 'Courier New', 'size' => 22, 'line' => 240, 'rule' => 'auto', 'firstLine' => 720, 'jc' => 'both'],
            );
        }

        $body .= $this->heading('Results', 1);
        $body .= $this->paragraph('Table 1 reports the results.');
        $body .= $this->table([['A', 'B'], ['1', '2']], false);

        $body .= $this->heading('References', 1);

        // Correctly formatted with a hanging indent.
        $body .= $this->paragraph(
            'Smith, J. (2020). A work that is cited in the body. Journal of Examples, 1(1), 1-10.',
            ['hanging' => 720, 'left' => 720],
        );
        // Uncited.
        $body .= $this->paragraph('Brown, K. (2018). An uncited reference entry.', ['hanging' => 720, 'left' => 720]);
        // Incomplete.
        $body .= $this->paragraph('Nobody, N. (2021).', ['hanging' => 720, 'left' => 720]);
        // Malformed DOI.
        $body .= $this->paragraph(
            'Wrong, W. (2017). A broken DOI entry. doi:11.1234/not-a-doi',
            ['hanging' => 720, 'left' => 720],
        );
        // Duplicates: identical author and title, listed twice.
        $body .= $this->paragraph(
            'Duplicate, D. (2015). A study that appears twice in the reference list. Journal of Repeats, 4(1), 20-29.',
            ['hanging' => 720, 'left' => 720],
        );
        $body .= $this->paragraph(
            'Duplicate, D. (2015). A study that appears twice in the reference list. Journal of Repeats, 4(1), 20-29.',
            ['hanging' => 720, 'left' => 720],
        );
        // Year mismatch against the in-text citation.
        $body .= $this->paragraph(
            'Smyth, S. (2016). This entry disagrees with the in-text citation (Smith, 2020).',
            ['hanging' => 720, 'left' => 720],
        );

        // US Letter with 1.5" margins and 11pt Calibri: wrong on every axis.
        return $this->write($name, $body, $this->letterSection(2160));
    }

    /** Tables, figures and embedded images. */
    public function richMedia(string $name = 'rich-media.docx'): string
    {
        $image = base64_decode(self::PIXEL_PNG_BASE64);

        $body = '';
        $body .= $this->paragraph('A Media-Rich Manuscript for Preservation Testing', ['style' => 'Title']);
        $body .= $this->heading('Introduction', 1);
        $body .= $this->paragraph('This manuscript exercises tables, figures and embedded media.');

        $body .= $this->heading('Results', 1);
        $body .= $this->paragraph('Table 1 reports the descriptive statistics.');
        $body .= $this->table([
            ['Variable', 'M', 'SD', 'N'],
            ['Pre-test', '41.2', '7.9', '128'],
            ['Post-test', '58.7', '6.1', '128'],
        ], true);

        $body .= $this->paragraph('Figure 1 shows the distribution.');
        $body .= $this->image('rId100', 900001, 'Figure one caption text');
        $body .= $this->paragraph('Figure 2 shows the comparison.');
        $body .= $this->image('rId101', 900002, '');

        $body .= $this->heading('References', 1);
        $body .= $this->paragraph('Author, A. (2020). A cited work. Publisher.', ['hanging' => 720, 'left' => 720]);

        return $this->write($name, $body, $this->a4Section(1440), [
            'word/media/image1.png' => $image,
            'word/media/image2.png' => $image,
        ], $this->mediaRelationships());
    }

    /** A long reference list for cross-reference testing. */
    public function manyReferences(int $count = 60, string $name = 'many-references.docx'): string
    {
        $body = '';
        $body .= $this->paragraph('A Manuscript With a Long Reference List', ['style' => 'Title']);
        $body .= $this->heading('Introduction', 1);

        for ($i = 1; $i <= $count; $i++) {
            $year = 1990 + ($i % 30);
            $surname = $this->syntheticSurname($i);

            $body .= $this->paragraph(
                "Subsection {$i}. This sentence cites ({$surname}, {$year}).",
            );
        }

        $body .= $this->heading('References', 1);

        for ($i = 1; $i <= $count; $i++) {
            $year = 1990 + ($i % 30);
            $surname = $this->syntheticSurname($i);

            $body .= $this->paragraph(
                "{$surname}, A. ({$year}). Title of reference number {$i}. Journal of Testing, {$i}(2), "
                .($i * 3).'-'.($i * 3 + 9).'.',
                ['hanging' => 720, 'left' => 720],
            );
        }

        return $this->write($name, $body, $this->a4Section(1440));
    }

    /**
     * An alphabetic surname for a given index.
     *
     * Digits are avoided because a real personal name never contains one, and
     * the citation extractor deliberately rejects candidate author strings that
     * contain digits so that "(n = 2020)" is not mistaken for a citation.
     */
    private function syntheticSurname(int $index): string
    {
        $alphabet = range('a', 'z');
        $index--;

        return 'Author'.$alphabet[intdiv($index, 26) % 26].$alphabet[$index % 26];
    }

    /** Abstract presented under a heading, the common case. */
    public function abstractUnderHeading(string $name = 'abstract-under-heading.docx'): string
    {
        $body = '';
        $body .= $this->paragraph('Social Media Use and Academic Performance', ['style' => 'Title']);
        $body .= $this->heading('Abstract', 1);
        $body .= $this->paragraph(
            'This study investigated the relationship between social media usage and academic performance among '
            .'university undergraduates in a Nigerian metropolitan setting. A correlational survey design was '
            .'employed with a sample of 310 respondents drawn through stratified random sampling. Results '
            .'indicated a statistically significant but modest negative relationship between daily social media '
            .'usage hours and semester grade point average, while controlling for gender and year of study.',
            ['firstLine' => 0],
        );
        $body .= $this->paragraph('Keywords: social media, academic performance', ['firstLine' => 0]);
        $body .= $this->heading('Introduction', 1);
        $body .= $this->paragraph('The growth of social media has reshaped student study habits.');
        $body .= $this->heading('References', 1);
        $body .= $this->paragraph('Researcher, R. (2021). A work.', ['hanging' => 720, 'left' => 720]);

        return $this->write($name, $body, $this->a4Section(1440));
    }

    /** Raw OOXML the parser must classify: OMML, content control, tracked insertion. */
    public function withRawOoxml(string $name = 'raw-ooxml.docx'): string
    {
        $equation = '<m:oMathPara xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math">'
            .'<m:oMath><m:r><m:t>E = mc^2</m:t></m:r></m:oMath></m:oMathPara>';

        $contentControl = '<w:sdt><w:sdtPr><w:alias w:val="ReviewerNote"/><w:tag w:val="reviewer-note"/></w:sdtPr>'
            .'<w:sdtContent><w:p><w:r><w:t>A structured content control paragraph.</w:t></w:r></w:p></w:sdtContent></w:sdt>';

        $trackedInsertion = '<w:p><w:ins w:id="9001" w:author="Reviewer One" w:date="2026-01-01T00:00:00Z">'
            .'<w:r><w:t>An inserted sentence recorded as a tracked change.</w:t></w:r></w:ins></w:p>';

        // A block-level element the parser has no semantic mapping for. It must
        // be preserved and reported, never dropped.
        $unknownElement = '<w:customXml w:uri="urn:ojmas:unknown" w:element="EditorialMarker">'
            .'<w:p><w:r><w:t>Text inside an unmapped custom XML block.</w:t></w:r></w:p>'
            .'</w:customXml>';

        $body = '';
        $body .= $this->paragraph('A Manuscript Containing Raw OOXML Constructs', ['style' => 'Title']);
        $body .= $this->heading('Introduction', 1);
        $body .= $this->paragraph('Normal paragraph before the raw constructs.');
        $body .= $contentControl;
        $body .= $equation;
        $body .= $trackedInsertion;
        $body .= $unknownElement;
        $body .= $this->paragraph('Normal paragraph after the raw constructs.');
        $body .= $this->heading('References', 1);
        $body .= $this->paragraph('Writer, W. (2020). A work.', ['hanging' => 720, 'left' => 720]);

        return $this->write($name, $body, $this->a4Section(1440));
    }

    // ───────────────────────── hostile fixtures ─────────────────────────

    /** A valid ZIP that is missing every required OOXML part. */
    public function fakeDocx(string $name = 'fake.docx'): string
    {
        $path = $this->path($name);

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('hello.txt', 'This is not a Word document at all.');
        $zip->addFromString('readme.md', '# not a docx');
        $zip->close();

        return $path;
    }

    /** A DOCX carrying a VBA macro project. */
    public function withMacros(string $name = 'macro.docx'): string
    {
        return $this->withExtraPart($name, 'word/vbaProject.bin', random_bytes(2048));
    }

    /** A DOCX whose archive contains a path-traversal entry name. */
    public function withPathTraversal(string $name = 'traversal.docx'): string
    {
        return $this->withExtraPart($name, '../../../../evil.xml', '<evil/>');
    }

    /** A highly compressible entry that trips the zip-bomb ratio ceiling. */
    public function compressionBomb(string $name = 'bomb.docx'): string
    {
        return $this->withExtraPart($name, 'word/media/huge.bin', str_repeat("\0", 4 * 1024 * 1024));
    }

    /** A truncated ZIP. */
    public function corrupt(string $name = 'corrupt.docx'): string
    {
        $source = $this->compliant('corrupt-base.docx');
        $contents = (string) file_get_contents($source);
        $target = $this->path($name);

        file_put_contents($target, substr($contents, 0, (int) (strlen($contents) * 0.6)));

        return $target;
    }

    /** Plain text wearing a .docx extension. */
    public function notADocument(string $name = 'not-a-document.docx'): string
    {
        $target = $this->path($name);
        file_put_contents($target, "Plain text pretending to be a DOCX.\nNot a ZIP archive.\n");

        return $target;
    }

    /** A zero-byte file. */
    public function emptyFile(string $name = 'empty.docx'): string
    {
        $target = $this->path($name);
        file_put_contents($target, '');

        return $target;
    }

    // ───────────────────────── OOXML builders ─────────────────────────

    /** @param array<string, mixed> $options */
    private function paragraph(string $text, array $options = []): string
    {
        $properties = '';

        if (isset($options['style'])) {
            $properties .= '<w:pStyle w:val="'.$options['style'].'"/>';
        }

        if (isset($options['jc'])) {
            $properties .= '<w:jc w:val="'.$options['jc'].'"/>';
        }

        $spacing = '';

        if (isset($options['line'])) {
            $spacing .= ' w:line="'.$options['line'].'"';
        }
        if (isset($options['rule'])) {
            $spacing .= ' w:lineRule="'.$options['rule'].'"';
        }
        if (isset($options['before'])) {
            $spacing .= ' w:before="'.$options['before'].'"';
        }
        if (isset($options['after'])) {
            $spacing .= ' w:after="'.$options['after'].'"';
        }

        if ($spacing !== '') {
            $properties .= '<w:spacing'.$spacing.'/>';
        }

        $indentation = '';

        if (isset($options['firstLine'])) {
            $indentation .= ' w:firstLine="'.$options['firstLine'].'"';
        }
        if (isset($options['hanging'])) {
            $indentation .= ' w:hanging="'.$options['hanging'].'"';
        }
        if (isset($options['left'])) {
            $indentation .= ' w:left="'.$options['left'].'"';
        }

        if ($indentation !== '') {
            $properties .= '<w:ind'.$indentation.'/>';
        }

        $runProperties = '';

        if (! empty($options['bold'])) {
            $runProperties .= '<w:b/>';
        }
        if (isset($options['font'])) {
            $runProperties .= '<w:rFonts w:ascii="'.$options['font'].'" w:hAnsi="'.$options['font'].'"/>';
        }
        if (isset($options['size'])) {
            $runProperties .= '<w:sz w:val="'.$options['size'].'"/><w:szCs w:val="'.$options['size'].'"/>';
        }

        if ($runProperties !== '') {
            $properties .= '<w:rPr>'.$runProperties.'</w:rPr>';
        }

        $paragraphProperties = $properties === '' ? '' : '<w:pPr>'.$properties.'</w:pPr>';
        $runPrefix = $runProperties === '' ? '' : '<w:rPr>'.$runProperties.'</w:rPr>';

        return '<w:p>'.$paragraphProperties
            .'<w:r>'.$runPrefix.'<w:t xml:space="preserve">'.$this->escape($text).'</w:t></w:r>'
            .'</w:p>';
    }

    private function heading(string $text, int $level): string
    {
        return $this->paragraph($text, ['style' => 'Heading'.$level]);
    }

    /** @param array<int, array<int, string>> $rows */
    private function table(array $rows, bool $headerRow): string
    {
        $xml = '<w:tbl><w:tblPr><w:tblStyle w:val="TableGrid"/><w:tblW w:w="0" w:type="auto"/></w:tblPr>';

        foreach ($rows as $rowIndex => $cells) {
            $xml .= '<w:tr>';

            if ($headerRow && $rowIndex === 0) {
                $xml .= '<w:trPr><w:tblHeader/></w:trPr>';
            }

            foreach ($cells as $cell) {
                $xml .= '<w:tc><w:tcPr><w:tcW w:w="0" w:type="auto"/></w:tcPr>'
                    .$this->paragraph($cell)
                    .'</w:tc>';
            }

            $xml .= '</w:tr>';
        }

        return $xml.'</w:tbl>';
    }

    private function image(string $relationshipId, int $docPrId, string $description): string
    {
        $nameAttribute = $description !== ''
            ? ' descr="'.$this->escape($description).'"'
            : '';

        return '<w:p><w:r><w:drawing>'
            .'<wp:inline xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing">'
            .'<wp:extent cx="571500" cy="571500"/>'
            .'<wp:docPr id="'.$docPrId.'" name="Picture '.$docPrId.'"'.$nameAttribute.'/>'
            .'<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
            .'<a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            .'<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            .'<pic:blipFill><a:blip r:embed="'.$relationshipId.'"/></pic:blipFill>'
            .'<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="571500" cy="571500"/></a:xfrm>'
            .'<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
            .'</pic:pic></a:graphicData></a:graphic></wp:inline>'
            .'</w:drawing></w:r></w:p>';
    }

    /** @param array<string, string> $extraParts */
    private function write(
        string $name,
        string $bodyXml,
        string $sectionXml,
        array $extraParts = [],
        string $extraRelationships = '',
    ): string {
        $path = $this->path($name);

        $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" '
            .'xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" '
            .'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
            .'xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            .'<w:body>'.$bodyXml.$sectionXml.'</w:body></w:document>';

        $relationships = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .$extraRelationships
            .'</Relationships>';

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Default Extension="png" ContentType="image/png"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .'<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            .'</Types>';

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $this->packageRelationships());
        $zip->addFromString('word/document.xml', $document);
        $zip->addFromString('word/_rels/document.xml.rels', $relationships);
        $zip->addFromString('word/styles.xml', $this->styles());
        $zip->addFromString('docProps/core.xml', $this->coreProperties());
        $zip->addFromString('docProps/app.xml', $this->appProperties());

        foreach ($extraParts as $partName => $contents) {
            $zip->addFromString($partName, $contents);
        }

        $zip->close();

        return $path;
    }

    private function withExtraPart(string $name, string $partName, string $contents): string
    {
        $source = $this->compliant('base-'.md5($name).'.docx');
        $target = $this->path($name);

        $zip = new ZipArchive();
        $zip->open($source);
        $entries = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
        }
        $zip->close();

        $out = new ZipArchive();
        $out->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($entries as $entryName => $data) {
            $out->addFromString($entryName, $data);
        }

        $out->addFromString($partName, $contents);
        $out->close();

        return $target;
    }

    private function mediaRelationships(): string
    {
        return '<Relationship Id="rId100" '
            .'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" '
            .'Target="media/image1.png"/>'
            .'<Relationship Id="rId101" '
            .'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" '
            .'Target="media/image2.png"/>';
    }

    private function packageRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rIdDoc" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'<Relationship Id="rIdCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'<Relationship Id="rIdApp" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            .'</Relationships>';
    }

    private function coreProperties(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            .'xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" '
            .'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:creator>Amina Bello</dc:creator>'
            .'<cp:lastModifiedBy>Amina Bello</cp:lastModifiedBy>'
            .'<dc:title>Effects of Instructional Scaffolding</dc:title>'
            .'<dc:subject>Education</dc:subject>'
            .'<cp:revision>3</cp:revision>'
            .'</cp:coreProperties>';
    }

    private function appProperties(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">'
            .'<Application>OJMAS Test Fixture</Application>'
            .'</Properties>';
    }

    private function styles(): string
    {
        $headingStyles = '';

        for ($level = 1; $level <= 5; $level++) {
            $headingStyles .= '<w:style w:type="paragraph" w:styleId="Heading'.$level.'">'
                .'<w:name w:val="heading '.$level.'"/>'
                .'<w:pPr><w:outlineLvl w:val="'.($level - 1).'"/></w:pPr>'
                .'<w:rPr><w:b/></w:rPr>'
                .'</w:style>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:docDefaults><w:rPrDefault><w:rPr>'
            .'<w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman"/>'
            .'<w:sz w:val="24"/><w:szCs w:val="24"/>'
            .'</w:rPr></w:rPrDefault></w:docDefaults>'
            .'<w:style w:type="paragraph" w:styleId="Normal"><w:name w:val="Normal"/></w:style>'
            .'<w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/></w:style>'
            .$headingStyles
            .'<w:style w:type="table" w:styleId="TableGrid"><w:name w:val="Table Grid"/></w:style>'
            .'</w:styles>';
    }

    private function a4Section(int $margin): string
    {
        // A4 = 11906 x 16838 twips.
        return '<w:sectPr>'
            .'<w:pgSz w:w="11906" w:h="16838"/>'
            .'<w:pgMar w:top="'.$margin.'" w:right="'.$margin.'" w:bottom="'.$margin.'" w:left="'.$margin.'" '
            .'w:header="720" w:footer="720" w:gutter="0"/>'
            .'</w:sectPr>';
    }

    private function letterSection(int $margin): string
    {
        // US Letter = 12240 x 15840 twips.
        return '<w:sectPr>'
            .'<w:pgSz w:w="12240" w:h="15840"/>'
            .'<w:pgMar w:top="'.$margin.'" w:right="'.$margin.'" w:bottom="'.$margin.'" w:left="'.$margin.'" '
            .'w:header="720" w:footer="720" w:gutter="0"/>'
            .'<w:pgNumType w:start="1"/>'
            .'</w:sectPr>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}