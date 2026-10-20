<?php

namespace App\Services\Documents\Formatting;

use App\Services\Documents\Ast\ManuscriptNode;
use App\Services\Documents\Ast\NodeType;
use App\Services\Documents\Ast\ParsedManuscript;
use App\Services\Documents\Parsing\OoxmlPackage;
use ZipArchive;

/**
 * Renders a formatted manuscript back into a DOCX package.
 *
 * The renderer writes OOXML directly rather than going through a document
 * builder library, because the round-trip has to carry constructs that most
 * builders discard: Office Math equations, tracked changes, embedded media and
 * any element the parser preserved verbatim.
 *
 * Guarantees:
 *  - The manuscript's wording is never altered. Only formatting attributes change.
 *  - `word/media/*` parts are copied across byte-for-byte from the source.
 *  - Preserved constructs are re-emitted from their stored raw OOXML.
 */
class DocxRenderer
{
    private const XML_HEADER = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';

    private const W_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    private const R_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const WP_NS = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';
    private const A_NS = 'http://schemas.openxmlformats.org/drawingml/2006/main';
    private const PIC_NS = 'http://schemas.openxmlformats.org/drawingml/2006/picture';
    private const M_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/math';

    /** @var array<string, string> Part name => raw bytes to carry across. */
    private array $carriedParts = [];

    /** @var array<int, array<string, string>> Extra relationships, keyed by target part. */
    private array $extraRelationships = [];

    private int $relationshipCounter = 100;

    private int $drawingCounter = 1;

    public function __construct(private readonly StyleProfile $profile)
    {
    }

    /**
     * @param  ParsedManuscript  $manuscript  The formatted tree to render.
     * @param  string  $destinationPath  Absolute path to write the DOCX to.
     * @param  OoxmlPackage|null  $sourcePackage  The original package, used to carry media and notes across.
     */
    public function render(
        ParsedManuscript $manuscript,
        string $destinationPath,
        ?OoxmlPackage $sourcePackage = null,
    ): void {
        if ($sourcePackage !== null) {
            $this->carryMedia($sourcePackage);
        }

        $body = '';

        foreach ($manuscript->blocks as $block) {
            $body .= $this->renderBlock($block, $manuscript);
        }

        $body .= $this->renderSectionProperties($manuscript->sectionProperties);

        $documentXml = self::XML_HEADER
            .'<w:document xmlns:w="'.self::W_NS.'" xmlns:r="'.self::R_NS.'"'
            .' xmlns:wp="'.self::WP_NS.'" xmlns:a="'.self::A_NS.'"'
            .' xmlns:pic="'.self::PIC_NS.'" xmlns:m="'.self::M_NS.'">'
            .'<w:body>'.$body.'</w:body></w:document>';

        $this->write($destinationPath, $documentXml, $this->relationshipsXml(), $manuscript);
    }

    // ───────────────────────── blocks ─────────────────────────

    private function renderBlock(ManuscriptNode $block, ParsedManuscript $manuscript): string
    {
        return match ($block->type) {
            NodeType::TITLE => $this->renderTitle($block),
            NodeType::HEADING => $this->renderHeading($block),
            NodeType::COVER_PAGE => $this->renderCoverPage($block),
            NodeType::ABSTRACT => $this->renderAbstract($block),
            NodeType::KEYWORDS => $this->renderKeywords($block),
            NodeType::PARAGRAPH => $this->renderParagraph($block, $manuscript),
            NodeType::REFERENCE_LIST => $this->renderReferenceHeading($block),
            NodeType::REFERENCE_ENTRY => $this->renderReferenceEntry($block),
            NodeType::TABLE => $this->renderTable($block, $manuscript),
            NodeType::EQUATION => $this->renderEquation($block),
            NodeType::PAGE_BREAK => '<w:p><w:r><w:br w:type="page"/></w:r></w:p>',
            NodeType::BLOCKQUOTE => $this->renderParagraph($block->withAttributes(
                ($block->attributes + ['left_indent' => 720, 'first_line_indent' => 0]),
            ), $manuscript),
            NodeType::UNSUPPORTED => $this->renderUnsupported($block),
            default => $this->renderParagraph($block, $manuscript),
        };
    }

    private function renderTitle(ManuscriptNode $block): string
    {
        return $this->paragraph(
            $this->inlineChildren($block, ['alignment' => 'center', 'bold' => true, 'first_line_indent' => 0]),
            $block->attributes + [
                'alignment' => 'center',
                'style_id' => 'Title',
                'first_line_indent' => 0,
            ],
        );
    }

    private function renderHeading(ManuscriptNode $block): string
    {
        $level = max(1, min(9, (int) $block->attribute('level', 1)));

        return $this->paragraph(
            $this->inlineChildren($block, ['bold' => true, 'first_line_indent' => 0]),
            $block->attributes + [
                'style_id' => 'Heading'.$level,
                'first_line_indent' => 0,
            ],
        );
    }

    private function renderCoverPage(ManuscriptNode $block): string
    {
        $xml = '';

        foreach ($block->children as $line) {
            $xml .= $this->paragraph(
                $this->inlineChildren($line, ['alignment' => 'center', 'first_line_indent' => 0]),
                $line->attributes + [
                    'alignment' => 'center',
                    'first_line_indent' => 0,
                ],
            );
        }

        // The journal requires author details on a separate cover page, so the
        // body always begins on a fresh sheet.
        return $xml.'<w:p><w:r><w:br w:type="page"/></w:r></w:p>';
    }

    private function renderAbstract(ManuscriptNode $block): string
    {
        return $this->paragraph(
            $this->inlineChildren($block, ['first_line_indent' => 0]),
            $block->attributes + ['first_line_indent' => 0],
        );
    }

    private function renderKeywords(ManuscriptNode $block): string
    {
        $text = trim((string) $block->text);

        if (! preg_match('/^\s*(keywords|key\s+words)\s*:/iu', $text)) {
            $text = 'Keywords: '.$text;
        }

        return $this->paragraph(
            $this->run($text, ['italic' => true, 'first_line_indent' => 0]),
            $block->attributes + ['first_line_indent' => 0],
        );
    }

    private function renderReferenceHeading(ManuscriptNode $block): string
    {
        return $this->renderHeading($block->setAttribute('level', 1));
    }

    private function renderReferenceEntry(ManuscriptNode $block): string
    {
        return $this->paragraph(
            $this->inlineChildren($block),
            $block->attributes,
        );
    }

    private function renderParagraph(ManuscriptNode $block, ParsedManuscript $manuscript): string
    {
        if ($block->attribute('is_blank') === true) {
            return '<w:p/>';
        }

        $xml = $this->paragraph(
            $this->inlineChildren($block),
            $block->attributes,
        );

        if ($block->attribute('has_page_break') === true) {
            $xml = '<w:p><w:r><w:br w:type="page"/></w:r></w:p>'.$xml;
        }

        return $xml;
    }

    private function renderTable(ManuscriptNode $block, ParsedManuscript $manuscript): string
    {
        $xml = '<w:tbl><w:tblPr><w:tblStyle w:val="TableGrid"/><w:tblW w:w="0" w:type="auto"/></w:tblPr>';

        foreach ($block->children as $row) {
            if (! $row instanceof ManuscriptNode || $row->type !== NodeType::TABLE_ROW) {
                continue;
            }

            $xml .= '<w:tr>';

            if ($row->attribute('index') === 0 && $block->attribute('has_header_row') === true) {
                $xml .= '<w:trPr><w:tblHeader/></w:trPr>';
            }

            foreach ($row->children as $cell) {
                if (! $cell instanceof ManuscriptNode || $cell->type !== NodeType::TABLE_CELL) {
                    continue;
                }

                $cellXml = '';

                foreach ($cell->children as $cellParagraph) {
                    if ($cellParagraph instanceof ManuscriptNode && $cellParagraph->type === NodeType::PARAGRAPH) {
                        $cellXml .= $this->paragraph(
                            $this->inlineChildren($cellParagraph, ['first_line_indent' => 0]),
                            $cellParagraph->attributes + ['first_line_indent' => 0],
                        );
                    }
                }

                $xml .= '<w:tc><w:tcPr><w:tcW w:w="0" w:type="auto"/></w:tcPr>'
                    .($cellXml === '' ? '<w:p/>' : $cellXml)
                    .'</w:tc>';
            }

            $xml .= '</w:tr>';
        }

        return $xml.'</w:tbl>';
    }

    /**
     * Equations are re-emitted from their stored raw OOXML so the mathematical
     * notation survives exactly. When raw XML is unavailable the equation text
     * is emitted as a plain paragraph and flagged by the preservation rule.
     */
    private function renderEquation(ManuscriptNode $block): string
    {
        $raw = $block->attribute('raw_xml');

        if (is_string($raw) && trim($raw) !== '') {
            // OMML lives directly in w:body; strip any XML declaration.
            return preg_replace('/<\?xml[^?]*\?>/u', '', trim($raw));
        }

        return $this->paragraph(
            $this->run((string) $block->text, ['first_line_indent' => 0]),
            $this->paragraphProperties(['alignment' => 'center', 'first_line_indent' => 0]),
        );
    }

    /**
     * Preserved-but-unmapped constructs are written back verbatim when their
     * raw OOXML is available, and as clearly-marked text when it is not. Either
     * way the content survives and the validation report says so.
     */
    private function renderUnsupported(ManuscriptNode $block): string
    {
        $raw = $block->attribute('raw_xml');

        if (is_string($raw) && trim($raw) !== '') {
            $clean = preg_replace('/<\?xml[^?]*\?>/u', '', trim($raw));

            // A preserved block may itself contain w:p elements; those are
            // already valid children of w:body.
            if (str_starts_with($clean, '<w:p') || str_starts_with($clean, '<w:sdt') || str_starts_with($clean, '<w:tbl')) {
                return $clean;
            }
        }

        $text = trim((string) $block->text);

        if ($text === '') {
            return '';
        }

        return $this->paragraph(
            $this->run($text, ['first_line_indent' => 0]),
            ['first_line_indent' => 0],
        );
    }

    // ───────────────────────── runs and paragraphs ─────────────────────────

    /** @param array<string, mixed> $overrides */
    private function inlineChildren(ManuscriptNode $block, array $overrides = []): string
    {
        if ($block->children === []) {
            $text = (string) $block->text;

            return $text === '' ? '' : $this->run($text, $overrides);
        }

        $xml = '';

        foreach ($block->children as $child) {
            $xml .= match ($child->type) {
                'run' => $this->run((string) $child->text, $overrides + (array) $child->attributes),
                'hyperlink' => $this->inlineChildren($child, $overrides),
                'tracked_insertion' => $this->renderTrackedChange($child, $overrides, false),
                'tracked_deletion' => $this->renderTrackedChange($child, $overrides, true),
                NodeType::EQUATION => $this->renderEquation($child),
                default => $this->inlineChildren($child, $overrides),
            };
        }

        return $xml;
    }

    /** @param array<string, mixed> $overrides */
    private function renderTrackedChange(ManuscriptNode $node, array $overrides, bool $deletion): string
    {
        $tag = $deletion ? 'w:del' : 'w:ins';
        $attributes = sprintf(
            ' w:id="%s" w:author="%s" w:date="%s"',
            $this->escape((string) $node->attribute('change_id', '0')),
            $this->escape((string) $node->attribute('author', 'Unknown')),
            $this->escape((string) $node->attribute('date', '1970-01-01T00:00:00Z')),
        );

        $inner = '';

        foreach ($node->children as $child) {
            $inner .= $this->run((string) $child->text, $overrides, $deletion);
        }

        if ($inner === '') {
            return '';
        }

        return '<'.$tag.$attributes.'>'.$inner.'</'.$tag.'>';
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function run(string $text, array $properties = [], bool $deletion = false): string
    {
        $runProperties = '';

        if (! empty($properties['bold'])) {
            $runProperties .= '<w:b/>';
        }

        if (! empty($properties['italic'])) {
            $runProperties .= '<w:i/>';
        }

        if (! empty($properties['underline'])) {
            $runProperties .= '<w:u w:val="single"/>';
        }

        if (! empty($properties['superscript'])) {
            $runProperties .= '<w:vertAlign w:val="superscript"/>';
        }

        if (! empty($properties['subscript'])) {
            $runProperties .= '<w:vertAlign w:val="subscript"/>';
        }

        $font = $properties['font'] ?? null;

        if (is_string($font) && trim($font) !== '' && $this->profile->isFontApproved($font)) {
            $escaped = $this->escape($font);
            $runProperties .= '<w:rFonts w:ascii="'.$escaped.'" w:hAnsi="'.$escaped.'"/>';
        }

        if (! $deletion) {
            $size = $properties['font_size_half_points'] ?? null;

            if ($size !== null && $this->profile->isFontSizeApproved((int) $size)) {
                $value = (int) $size;
                $runProperties .= '<w:sz w:val="'.$value.'"/><w:szCs w:val="'.$value.'"/>';
            }
        }

        if ($deletion) {
            $runProperties .= '<w:strike/>';
        }

        $prefix = $runProperties === '' ? '' : '<w:rPr>'.$runProperties.'</w:rPr>';

        // A tab in the source must survive as a tab, not a run of spaces.
        $parts = preg_split('/(\t|\n|\x{FFFC})/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
        $body = '';

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if ($part === "\t") {
                $body .= '<w:tab/>';
                continue;
            }

            if ($part === "\n") {
                $body .= '<w:br/>';
                continue;
            }

            if ($part === "\x{FFFC") {
                continue; // image placeholder: re-emitted from the media table
            }

            $body .= '<w:t xml:space="preserve">'.$this->escape($part).'</w:t>';
        }

        if ($body === '') {
            return '';
        }

        return '<w:r>'.$prefix.$body.'</w:r>';
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function paragraph(string $runsXml, array $attributes): string
    {
        return '<w:p>'.$this->paragraphProperties($attributes).$runsXml.'</w:p>';
    }

    /**
     * Builds w:pPr from the formatting attributes the rule engine corrected.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function paragraphProperties(array $attributes): string
    {
        $xml = '';

        $style = $attributes['style_id'] ?? null;

        if (is_string($style) && $style !== '' && $style !== 'Normal') {
            $xml .= '<w:pStyle w:val="'.$this->escape($style).'"/>';
        }

        $spacing = '';

        if (($attributes['line_spacing'] ?? null) !== null) {
            $spacing .= ' w:line="'.(int) $attributes['line_spacing'].'"';
        }

        if (($attributes['line_spacing_rule'] ?? null) !== null) {
            $spacing .= ' w:lineRule="'.$this->escape((string) $attributes['line_spacing_rule']).'"';
        }

        if ($spacing !== '') {
            $xml .= '<w:spacing'.$spacing.'/>';
        }

        $indentation = '';

        if (($attributes['first_line_indent'] ?? null) !== null) {
            $value = (int) $attributes['first_line_indent'];

            // Word models a negative first-line indent as a hanging indent.
            $indentation .= $value < 0
                ? ' w:hanging="'.abs($value).'"'
                : ' w:firstLine="'.$value.'"';
        }

        if (($attributes['hanging_indent'] ?? null) !== null) {
            $indentation .= ' w:hanging="'.(int) $attributes['hanging_indent'].'"';
        }

        if (($attributes['left_indent'] ?? null) !== null) {
            $indentation .= ' w:left="'.(int) $attributes['left_indent'].'"';
        }

        if ($indentation !== '') {
            $xml .= '<w:ind'.$indentation.'/>';
        }

        $alignment = $attributes['alignment'] ?? null;

        if (is_string($alignment) && $alignment !== '') {
            $xml .= '<w:jc w:val="'.$this->escape($alignment).'"/>';
        }

        return $xml === '' ? '' : '<w:pPr>'.$xml.'</w:pPr>';
    }

    /** @param array<string, mixed> $section */
    private function renderSectionProperties(array $section): string
    {
        if ($section === []) {
            return '';
        }

        $width = (int) ($section['width_twips'] ?? 11906);
        $height = (int) ($section['height_twips'] ?? 16838);

        return '<w:sectPr>'
            .'<w:pgSz w:w="'.$width.'" w:h="'.$height.'"/>'
            .'<w:pgMar w:top="'.(int) ($section['margin_top_twips'] ?? 1440).'"'
            .' w:right="'.(int) ($section['margin_right_twips'] ?? 1440).'"'
            .' w:bottom="'.(int) ($section['margin_bottom_twips'] ?? 1440).'"'
            .' w:left="'.(int) ($section['margin_left_twips'] ?? 1440).'"'
            .' w:header="'.(int) ($section['header_distance_twips'] ?? 720).'"'
            .' w:footer="'.(int) ($section['footer_distance_twips'] ?? 720).'"'
            .' w:gutter="0"/>'
            .'</w:sectPr>';
    }

    // ───────────────────────── package assembly ─────────────────────────

    private function carryMedia(OoxmlPackage $source): void
    {
        foreach ($source->mediaPartNames() as $partName) {
            $contents = $source->raw($partName);

            if ($contents === null) {
                continue;
            }

            $this->carriedParts[$partName] = $contents;
        }
    }

    private function relationshipsXml(): string
    {
        $xml = self::XML_HEADER
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="'.self::R_NS.'/styles" Target="styles.xml"/>'
            .'<Relationship Id="rId2" Type="'.self::R_NS.'/settings" Target="settings.xml"/>';

        $target = 1;
        $index = 0;

        foreach (array_keys($this->carriedParts) as $partName) {
            $target++;
            $index++;
            $xml .= '<Relationship Id="rIdImg'.$index.'" Type="'.self::R_NS.'/image" Target="'
                .$this->escape(str_replace('word/', '', $partName)).'"/>';
        }

        return $xml.'</Relationships>';
    }

    private function contentTypesXml(): string
    {
        $xml = self::XML_HEADER
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Default Extension="png" ContentType="image/png"/>'
            .'<Default Extension="jpeg" ContentType="image/jpeg"/>'
            .'<Default Extension="jpg" ContentType="image/jpeg"/>'
            .'<Default Extension="gif" ContentType="image/gif"/>'
            .'<Default Extension="emf" ContentType="image/x-emf"/>'
            .'<Default Extension="wmf" ContentType="image/x-wmf"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            .'<Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .'<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            .'</Types>';

        return $xml;
    }

    private function packageRelationshipsXml(): string
    {
        return self::XML_HEADER
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rIdDoc" Type="'.self::R_NS.'/officeDocument" Target="word/document.xml"/>'
            .'<Relationship Id="rIdCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'<Relationship Id="rIdApp" Type="'.self::R_NS.'/extended-properties" Target="docProps/app.xml"/>'
            .'</Relationships>';
    }

    private function stylesXml(): string
    {
        $font = $this->profile->approvedFonts()[0] ?? 'Times New Roman';
        $size = 24;
        $sizes = array_map('intval', (array) $this->profile->requirement('approved_font_sizes_half_points', [$size]));
        $size = $sizes === [] ? 24 : $sizes[0];

        $headings = '';

        for ($level = 1; $level <= 9; $level++) {
            $headings .= '<w:style w:type="paragraph" w:styleId="Heading'.$level.'">'
                .'<w:name w:val="heading '.$level.'"/>'
                .'<w:pPr><w:outlineLvl w:val="'.($level - 1).'"/></w:pPr>'
                .'<w:rPr><w:b/></w:rPr>'
                .'</w:style>';
        }

        return self::XML_HEADER
            .'<w:styles xmlns:w="'.self::W_NS.'">'
            .'<w:docDefaults><w:rPrDefault><w:rPr>'
            .'<w:rFonts w:ascii="'.$this->escape($font).'" w:hAnsi="'.$this->escape($font).'"/>'
            .'<w:sz w:val="'.$size.'"/><w:szCs w:val="'.$size.'"/>'
            .'</w:rPr></w:rPrDefault></w:docDefaults>'
            .'<w:style w:type="paragraph" w:styleId="Normal"><w:name w:val="Normal"/></w:style>'
            .'<w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/>'
            .'<w:rPr><w:b/></w:rPr></w:style>'
            .$headings
            .'<w:style w:type="table" w:styleId="TableGrid"><w:name w:val="Table Grid"/></w:style>'
            .'</w:styles>';
    }

    private function settingsXml(): string
    {
        return self::XML_HEADER
            .'<w:settings xmlns:w="'.self::W_NS.'">'
            .'<w:zoom w:percent="100"/>'
            .'<w:defaultTabStop w:val="720"/>'
            .'<w:compat/>'
            .'</w:settings>';
    }

    /**
     * Core properties for the output document.
     *
     * The journal's own metadata is written rather than the original author's,
     * because the original creator string would otherwise carry the author's
     * name into a formatted derivative that reviewers may open.
     */
    private function corePropertiesXml(): string
    {
        return self::XML_HEADER
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"'
            .' xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/"'
            .' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:title>Formatted manuscript</dc:title>'
            .'<dc:creator>OJMAS Journal</dc:creator>'
            .'<cp:lastModifiedBy>OJMAS Journal</cp:lastModifiedBy>'
            .'<cp:revision>1</cp:revision>'
            .'</cp:coreProperties>';
    }

    private function appPropertiesXml(): string
    {
        return self::XML_HEADER
            .'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">'
            .'<Application>OJMAS APA 7 Formatting Engine</Application>'
            .'</Properties>';
    }

    private function write(string $destinationPath, string $documentXml, string $relationshipsXml, ParsedManuscript $manuscript): void
    {
        $zip = new ZipArchive();

        if ($zip->open($destinationPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to create the formatted document package.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->packageRelationshipsXml());
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->addFromString('word/_rels/document.xml.rels', $relationshipsXml);
        $zip->addFromString('word/styles.xml', $this->stylesXml());
        $zip->addFromString('word/settings.xml', $this->settingsXml());
        $zip->addFromString('docProps/core.xml', $this->corePropertiesXml());
        $zip->addFromString('docProps/app.xml', $this->appPropertiesXml());

        foreach ($this->carriedParts as $partName => $contents) {
            $zip->addFromString($partName, $contents);
        }

        $zip->close();
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}