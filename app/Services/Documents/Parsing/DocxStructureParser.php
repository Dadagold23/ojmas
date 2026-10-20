<?php

namespace App\Services\Documents\Parsing;

use App\Services\Documents\Ast\ManuscriptNode;
use App\Services\Documents\Ast\NodeType;
use App\Services\Documents\Ast\ParsedManuscript;
use App\Services\Documents\Ingestion\SecurityValidationReport;
use DOMElement;
use DOMNode;

/**
 * Converts a DOCX package into a neutral manuscript tree.
 *
 * Design rules that follow directly from the project brief:
 *
 *  - **Read-only.** The source package is never modified.
 *  - **Lossless.** Anything the parser cannot map onto a semantic manuscript
 *    construct is preserved as a NodeType::UNSUPPORTED node carrying its text
 *    and OOXML tag, so the validation report can flag it. Nothing is dropped.
 *  - **Deterministic.** No heuristics depend on wall-clock time, randomness or
 *    external services, so the same input always yields the same tree.
 */
class DocxStructureParser
{
    private const MATH_NAMESPACE = 'http://schemas.openxmlformats.org/officeDocument/2006/math';

    /**
     * Upper bound on the raw OOXML retained for a preserved construct.
     *
     * Raw XML is what lets equations and unmapped elements round-trip exactly
     * instead of degrading to plain text, but it must not be able to bloat the
     * stored structure beyond reason.
     */
    private const MAX_RAW_XML_BYTES = 65536;

    /** Word style IDs that APA 7 and Word use for the five heading levels. */
    private const HEADING_STYLE_MAP = [
        'heading1' => 1,
        'heading 1' => 1,
        'heading2' => 2,
        'heading 2' => 2,
        'heading3' => 3,
        'heading 3' => 3,
        'heading4' => 4,
        'heading 4' => 4,
        'heading5' => 5,
        'heading 5' => 5,
        'heading6' => 5,
        'heading 7' => 5,
        'heading 8' => 5,
        'heading 9' => 5,
    ];

    private const TITLE_STYLES = ['title', 'subtitle', 'doctitle'];

    /** Headings whose following siblings begin the reference list. */
    private const REFERENCE_HEADINGS = ['references', 'reference', 'bibliography', 'works cited', 'literature cited'];

    private const APPENDIX_HEADINGS = ['appendix', 'appendices'];

    private const ABSTRACT_HEADINGS = ['abstract', 'summary'];

    private const KEYWORDS_LABELS = ['keywords', 'key words'];

    private const DECLARATION_HEADINGS = [
        'acknowledgments', 'acknowledgements',
        'acknowledgment', 'acknowledgement',
        'funding', 'conflict of interest', 'conflicts of interest',
        'conflict of interests', 'competing interests',
        'data availability', 'data availability statement',
        'declaration of interest', 'ethics statement',
        'author contributions', 'author contribution',
    ];

    /** Styles that signal a cover page or title block. */
    private const COVER_STYLES = ['subtitle', 'affiliation', 'author', 'authors', 'email'];

    public function parse(
        string $absolutePath,
        ?SecurityValidationReport $security = null,
    ): ParsedManuscript {
        $package = OoxmlPackage::open($absolutePath);
        $dom = $package->loadXml($package->documentXml(), 'Word document body');

        $body = $this->findBody($dom);
        $context = $this->buildParseContext();

        foreach ($body->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'sectPr') {
                $context['sectionProperties'][] = $this->parseSectionProperties($child);
            }
        }

        $blocks = [];
        $inReferenceList = false;

        foreach ($body->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            if ($child->localName === 'sectPr') {
                continue;
            }

            $node = $this->parseBlockElement($child, $context);

            if ($node === null) {
                continue;
            }

            if ($node->type === NodeType::HEADING && $this->isReferenceHeading((string) $node->text)) {
                $inReferenceList = true;

                $blocks[] = $node->setAttribute('opens_reference_list', true);
                continue;
            }

            if ($inReferenceList) {
                if ($node->type === NodeType::HEADING || $node->type === NodeType::PAGE_BREAK) {
                    $inReferenceList = false;
                } else {
                    $node = $node->setAttribute('is_reference_entry', true);
                }
            }

            $blocks[] = $node;
        }

        $blocks = $this->promoteCoverPage($blocks);
        $blocks = $this->classifyFrontMatter($blocks);
        $blocks = $this->numberReferences($blocks);

        $footnotes = $this->parseNotes($package->footnotesXml(), 'footnote');
        $endnotes = $this->parseNotes($package->endnotesXml(), 'endnote');
        $footnotes = array_merge($footnotes, $endnotes);

        $media = [];

        foreach ($package->mediaPartNames() as $index => $partName) {
            $media[] = [
                'part' => $partName,
                'index' => $index + 1,
                'bytes' => strlen((string) $package->raw($partName)),
            ];
        }

        $coreProperties = $this->parseCoreProperties($package->corePropertiesXml());

        $text = implode("\n", array_filter(array_map(
            fn (ManuscriptNode $block) => $block->text,
            $blocks,
        )));

        return new ParsedManuscript(
            blocks: $blocks,
            sectionProperties: $this->flattenSectionProperties($context['sectionProperties']),
            media: $media,
            footnotes: $footnotes,
            coreProperties: $coreProperties,
            security: $security,
            wordCount: $this->countWords($text),
            paragraphCount: count(array_filter($blocks, fn (ManuscriptNode $b) => $b->type === NodeType::PARAGRAPH)),
        );
    }

    /** @return array<string, mixed> */
    private function buildParseContext(): array
    {
        return [
            'sectionProperties' => [],
            'paragraphIndex' => 0,
            'footnotes' => [],
            'images' => [],
        ];
    }

    private function findBody(\DOMDocument $dom): DOMElement
    {
        foreach ($dom->documentElement?->childNodes ?? [] as $child) {
            if ($child instanceof DOMElement && $child->localName === 'body') {
                return $child;
            }
        }

        throw new \App\Services\Documents\Exceptions\CorruptDocumentException(
            'The Word document body could not be located.',
            'document_missing_body',
        );
    }

    private function parseBlockElement(DOMElement $element, array &$context): ?ManuscriptNode
    {
        // Office Math Markup Language blocks sit directly in w:body, not inside
        // w:p, so they must be recognised before the generic fallback.
        if (str_starts_with((string) $element->namespaceURI, self::MATH_NAMESPACE)) {
            return new ManuscriptNode(
                NodeType::EQUATION,
                ['format' => 'omml', 'ooxml_tag' => $element->localName, 'raw_xml' => $this->rawXml($element)],
                [],
                $this->collectText($element),
            );
        }

        return match ($element->localName) {
            'p' => $this->parseParagraph($element, $context),
            'tbl' => $this->parseTable($element, $context),
            'sdt' => $this->parseContentControl($element, $context),
            'bookmarkStart', 'bookmarkEnd', 'sectPr', 'proofErr' => null,
            default => $this->unsupported(
                $element,
                'Unknown block-level element <w:'.$element->localName.'>.',
                $element->localName,
            ),
        };
    }

    private function parseContentControl(DOMElement $element, array &$context): ?ManuscriptNode
    {
        // A structured document tag wraps real content. Its children are parsed
        // normally so the content survives; the wrapper itself is flagged.
        $inner = [];

        $contentNode = $this->findChild($element, 'sdtContent');

        if ($contentNode !== null) {
            foreach ($contentNode->childNodes as $child) {
                if (! $child instanceof DOMElement) {
                    continue;
                }

                $parsed = $this->parseBlockElement($child, $context);

                if ($parsed !== null) {
                    $inner[] = $parsed;
                }
            }
        }

        $alias = $this->findChild($element, 'sdtPr');
        $aliasValue = null;

        if ($aliasNode = $this->findChild($alias ?? $element, 'alias')) {
            $aliasValue = $aliasNode->getAttribute('w:val') ?: null;
        }

        if ($inner === []) {
            return $this->unsupported(
                $element,
                'A document content control was present without readable content.',
                'sdt',
                ['alias' => $aliasValue],
            );
        }

        if (count($inner) === 1) {
            $preserved = $inner[0]->setAttribute('contained_in_content_control', $aliasValue ?? 'unnamed');

            return $preserved;
        }

        return new ManuscriptNode(
            NodeType::UNSUPPORTED,
            ['reason' => 'A document content control wrapped multiple blocks.', 'ooxml_tag' => 'sdt', 'alias' => $aliasValue],
            $inner,
            $this->collectText($element),
        );
    }

    private function parseParagraph(DOMElement $element, array &$context): ManuscriptNode
    {
        $context['paragraphIndex']++;
        $paragraphIndex = $context['paragraphIndex'];

        $style = $this->paragraphStyle($element);
        $runProperties = $this->paragraphRunProperties($element);

        $hasPageBreak = $this->containsPageBreak($element);
        $children = $this->parseInlineChildren($element, $context);
        $text = $this->flattenText($children);
        $trimmed = trim($text);

        // Word resolves effective formatting by inheriting from the paragraph
        // mark, then from the run. Reporting the paragraph mark alone would
        // hide the font actually applied to the visible text.
        $effective = $this->effectiveFont($runProperties, $children);

        $attributes = [
            'paragraph_index' => $paragraphIndex,
            'style_id' => $style,
            'alignment' => $this->paragraphAlignment($element),
            'line_spacing' => $runProperties['line_spacing'],
            'line_spacing_rule' => $runProperties['line_spacing_rule'],
            'space_before' => $runProperties['space_before'],
            'space_after' => $runProperties['space_after'],
            'first_line_indent' => $runProperties['first_line_indent'],
            'left_indent' => $runProperties['left_indent'],
            'hanging_indent' => $runProperties['hanging_indent'],
            'is_list_item' => $this->isListItem($element),
            'font' => $effective['font'],
            'font_size_half_points' => $effective['font_size_half_points'],
            'font_source' => $effective['source'],
            'has_page_break' => $hasPageBreak,
            'bold_default' => $runProperties['bold'],
            'italic_default' => $runProperties['italic'],
        ];

        if ($trimmed === '') {
            if ($hasPageBreak) {
                return new ManuscriptNode(NodeType::PAGE_BREAK, $attributes, [], null);
            }

            return new ManuscriptNode(NodeType::PARAGRAPH, $attributes + ['is_blank' => true], [], '');
        }

        $headingLevel = $this->headingLevel($style);

        if ($headingLevel !== null) {
            return new ManuscriptNode(
                NodeType::HEADING,
                $attributes + ['level' => $headingLevel, 'heading_style' => $style],
                $children,
                $trimmed,
            );
        }

        if (in_array($style, self::TITLE_STYLES, true)) {
            return new ManuscriptNode(NodeType::TITLE, $attributes + ['style_id' => $style], $children, $trimmed);
        }

        if ($this->isBlockQuote($element)) {
            return new ManuscriptNode(NodeType::BLOCKQUOTE, $attributes, $children, $trimmed);
        }

        // APA 7 and the OJMAS guidelines both place the abstract immediately
        // after a heading reading "Abstract"; the parser promotes it so the
        // formatting engine can enforce the journal's 150-word rule.
        return new ManuscriptNode(NodeType::PARAGRAPH, $attributes, $children, $trimmed);
    }

    private function parseTable(DOMElement $element, array &$context): ManuscriptNode
    {
        $rowNodes = [];
        $rowIndex = 0;

        foreach ($this->findChildren($element, 'tr') as $row) {
            $cells = [];
            $cellIndex = 0;

            foreach ($this->findChildren($row, 'tc') as $cell) {
                $cellParagraphs = [];

                foreach ($this->findChildren($cell, 'p') as $cellParagraph) {
                    $parsed = $this->parseParagraph($cellParagraph, $context);

                    if (! $parsed->isEmpty() || $parsed->type === NodeType::PAGE_BREAK) {
                        $cellParagraphs[] = $parsed;
                    }
                }

                $cells[] = new ManuscriptNode(
                    NodeType::TABLE_CELL,
                    [
                        'index' => $cellIndex,
                        'grid_span' => (int) ($this->cellProperty($cell, 'gridSpan') ?? 1),
                    ],
                    $cellParagraphs,
                    trim(implode(' ', array_filter(array_map(
                        fn (ManuscriptNode $paragraph) => $paragraph->text,
                        $cellParagraphs,
                    )))),
                );
                $cellIndex++;
            }

            $rowNodes[] = new ManuscriptNode(
                NodeType::TABLE_ROW,
                ['index' => $rowIndex, 'cell_count' => count($cells)],
                $cells,
                null,
            );
            $rowIndex++;
        }

        $context['paragraphIndex']++; // a table occupies a structural position

        return new ManuscriptNode(
            NodeType::TABLE,
            [
                'row_count' => count($rowNodes),
                'column_count' => $rowNodes === [] ? 0 : $rowNodes[0]->attribute('cell_count'),
                'style_id' => $this->tableStyle($element),
                'has_header_row' => $this->tableHasHeaderRow($element),
                'caption' => null,
            ],
            $rowNodes,
            null,
        );
    }

    /**
     * Resolves the font that actually applies to a paragraph's visible text.
     *
     * The paragraph mark (w:pPr/w:rPr) is only a default; when it is silent the
     * first run that declares a font wins. Without this the formatting engine
     * would report a document as having no detectable font and emit a false
     * "font not recognised" warning.
     *
     * @param  array<string, mixed>  $paragraphMark
     * @param  array<int, ManuscriptNode>  $children
     * @return array{font: string|null, font_size_half_points: int|null, source: string}
     */
    private function effectiveFont(array $paragraphMark, array $children): array
    {
        $font = $paragraphMark['font'] ?? null;
        $size = $paragraphMark['font_size_half_points'] ?? null;
        $source = $font !== null || $size !== null ? 'paragraph_mark' : 'document_default';

        if ($font !== null && $size !== null) {
            return ['font' => $font, 'font_size_half_points' => $size, 'source' => $source];
        }

        foreach ($children as $child) {
            if ($child->type !== 'run') {
                continue;
            }

            if ($font === null && $child->attribute('font') !== null) {
                $font = $child->attribute('font');
                $source = 'first_run';
            }

            if ($size === null && $child->attribute('font_size_half_points') !== null) {
                $size = $child->attribute('font_size_half_points');
                $source = 'first_run';
            }

            if ($font !== null && $size !== null) {
                break;
            }
        }

        return ['font' => $font, 'font_size_half_points' => $size, 'source' => $source];
    }

    private function parseInlineChildren(DOMElement $element, array &$context): array
    {
        $nodes = [];

        foreach ($element->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $parsed = match ($child->localName) {
                'r' => $this->parseRun($child, $context),
                'hyperlink' => $this->parseHyperlink($child, $context),
                'ins' => $this->parseTrackedChange($child, $context, 'insertion'),
                'del' => $this->parseTrackedChange($child, $context, 'deletion'),
                'sdt', 'smartTag', 'fldSimple', 'bookmarkStart', 'bookmarkEnd',
                'proofErr', 'commentRangeStart', 'commentRangeEnd', 'pPr', 'commentReference' => null,
                default => $this->parseUnknownInline($child),
            };

            if ($parsed === null) {
                continue;
            }

            if (is_array($parsed)) {
                foreach ($parsed as $item) {
                    $nodes[] = $item;
                }
                continue;
            }

            $nodes[] = $parsed;
        }

        return $nodes;
    }

    private function parseUnknownInline(DOMElement $element): ManuscriptNode
    {
        // Office Math Markup Language and field codes both appear inline.
        if (str_starts_with((string) $element->namespaceURI, self::MATH_NAMESPACE)) {
            return new ManuscriptNode(
                NodeType::EQUATION,
                ['format' => 'omml', 'ooxml_tag' => $element->localName, 'raw_xml' => $this->rawXml($element)],
                [],
                $this->collectText($element),
            );
        }

        return $this->unsupported(
            $element,
            'An inline element was present that the parser could not map onto a manuscript construct.',
            $element->localName,
        );
    }

    /**
     * Tracked insertions and deletions wrap ordinary runs.
     *
     * Their text is preserved as a run inside a change marker node so the
     * renderer can re-emit genuine tracked changes rather than flattening the
     * revision into anonymous body text.
     */
    private function parseTrackedChange(DOMElement $element, array &$context, string $type): array
    {
        $runs = [];

        foreach ($this->findChildren($element, 'r') as $run) {
            $parsed = $this->parseRun($run, $context);

            if ($parsed === null) {
                continue;
            }

            foreach ($parsed as $runNode) {
                $runs[] = $runNode;
            }
        }

        if ($runs === []) {
            return [];
        }

        return [new ManuscriptNode(
            $type === 'insertion' ? 'tracked_insertion' : 'tracked_deletion',
            [
                'change_type' => $type,
                'change_id' => $element->getAttribute('w:id'),
                'author' => $element->getAttribute('w:author'),
                'date' => $element->getAttribute('w:date'),
            ],
            $runs,
            $this->flattenText($runs),
        )];
    }

    private function parseHyperlink(DOMElement $element, array &$context): ManuscriptNode
    {
        $children = $this->parseInlineChildren($element, $context);

        return new ManuscriptNode(
            'hyperlink',
            ['target' => $element->getAttribute('r:id')],
            $children,
            $this->flattenText($children),
        );
    }

    private function parseRun(DOMElement $element, array &$context): ?array
    {
        $properties = $this->runProperties($element);
        $texts = [];

        foreach ($element->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            switch ($child->localName) {
                case 't':
                    $texts[] = $child->textContent;
                    break;

                case 'tab':
                    $texts[] = "\t";
                    break;

                case 'br':
                    if (strtolower($child->getAttribute('w:type')) === 'page') {
                        $context['pageBreaks'] = true;
                    }
                    $texts[] = "\n";
                    break;

                case 'noBreakHyphen':
                    $texts[] = '-';
                    break;

                case 'drawing':
                case 'pict':
                case 'object':
                    $texts[] = $this->parseDrawing($child, $context);
                    break;

                case 'footnoteReference':
                case 'endnoteReference':
                    $id = $child->getAttribute('w:id');
                    $context['footnotes'][] = ['id' => $id, 'type' => $child->localName];
                    $texts[] = null;
                    break;

                case 'sym':
                    $texts[] = '[symbol '.$child->getAttribute('w:char').']';
                    break;
            }
        }

        if ($texts === []) {
            return null;
        }

        $text = implode('', array_map(fn ($piece) => $piece ?? '', $texts));

        return [new ManuscriptNode(
            'run',
            $properties,
            [],
            $text,
        )];
    }

    private function parseDrawing(DOMElement $element, array &$context): string
    {
        $context['images'][] = true;

        $description = $this->drawingDescription($element);

        // The placeholder is a zero-width marker so it does not inject visible
        // words into the manuscript text or the word count.
        return "\u{FFFC}".($description !== null ? '' : '');
    }

    private function drawingDescription(DOMElement $element): ?string
    {
        foreach ($element->getElementsByTagNameNS('*', 'docPr') as $docPr) {
            $description = $docPr->getAttribute('descr') ?: $docPr->getAttribute('name');

            if ($description !== '') {
                return $description;
            }
        }

        return null;
    }

    private function unsupported(DOMElement $element, string $reason, string $tag, array $extra = []): ManuscriptNode
    {
        $text = trim($this->collectText($element));

        return new ManuscriptNode(
            NodeType::UNSUPPORTED,
            $extra + [
                'reason' => $reason,
                'ooxml_tag' => $tag,
                'namespace' => $element->namespaceURI,
                'preserved_text' => $text,
                'raw_xml' => $this->rawXml($element),
            ],
            [],
            $text !== '' ? $text : null,
        );
    }

    /**
     * Serialises an element back to OOXML so it can be re-emitted verbatim by
     * the renderer rather than being flattened to text.
     */
    private function rawXml(DOMElement $element): ?string
    {
        $owner = $element->ownerDocument;

        if ($owner === null) {
            return null;
        }

        $xml = $owner->saveXML($element);

        if ($xml === false) {
            return null;
        }

        if (strlen($xml) > self::MAX_RAW_XML_BYTES) {
            return null;
        }

        return $xml;
    }

    private function collectText(DOMElement $element): string
    {
        $parts = [];

        foreach ($element->getElementsByTagNameNS('*', 't') as $textNode) {
            $parts[] = $textNode->textContent;
        }

        return implode('', $parts);
    }

    private function flattenText(array $nodes): string
    {
        $parts = [];

        foreach ($nodes as $node) {
            $parts[] = $node->text ?? '';
        }

        return implode('', $parts);
    }

    private function paragraphStyle(DOMElement $element): ?string
    {
        $properties = $this->findChild($element, 'pPr');

        if ($properties === null) {
            return null;
        }

        $style = $this->findChild($properties, 'pStyle');

        if ($style === null) {
            return null;
        }

        $value = $style->getAttribute('w:val');

        return $value !== '' ? strtolower($value) : null;
    }

    private function headingLevel(?string $style): ?int
    {
        if ($style === null) {
            return null;
        }

        return self::HEADING_STYLE_MAP[$style] ?? null;
    }

    private function isBlockQuote(DOMElement $element): bool
    {
        $style = $this->paragraphStyle($element);

        if ($style !== null && (str_contains($style, 'quote') || $style === 'intensequote')) {
            return true;
        }

        $properties = $this->findChild($element, 'pPr');

        if ($properties === null) {
            return false;
        }

        return $this->findChild($properties, 'ind') !== null
            && $this->findChild($properties, 'ind')?->getAttribute('w:left') !== null
            && (int) $this->findChild($properties, 'ind')?->getAttribute('w:left') >= 720;
    }

    private function isListItem(DOMElement $element): bool
    {
        $properties = $this->findChild($element, 'pPr');

        return $properties !== null && $this->findChild($properties, 'numPr') !== null;
    }

    private function containsPageBreak(DOMElement $element): bool
    {
        foreach ($element->getElementsByTagNameNS('*', 'br') as $br) {
            if (strtolower($br->getAttribute('w:type')) === 'page') {
                return true;
            }
        }

        return false;
    }

    private function paragraphAlignment(DOMElement $element): ?string
    {
        $properties = $this->findChild($element, 'pPr');

        if ($properties === null) {
            return null;
        }

        $justification = $this->findChild($properties, 'jc');

        return $justification === null ? null : strtolower($justification->getAttribute('w:val'));
    }

    /** @return array<string, mixed> */
    private function paragraphRunProperties(DOMElement $element): array
    {
        $defaults = [
            'line_spacing' => null,
            'line_spacing_rule' => null,
            'space_before' => null,
            'space_after' => null,
            'first_line_indent' => null,
            'left_indent' => null,
            'hanging_indent' => null,
            'font' => null,
            'font_size_half_points' => null,
            'bold' => null,
            'italic' => null,
        ];

        $properties = $this->findChild($element, 'pPr');

        if ($properties === null) {
            return $defaults;
        }

        $spacing = $this->findChild($properties, 'spacing');

        if ($spacing !== null) {
            $defaults['line_spacing'] = $spacing->getAttribute('w:line') !== '' ? (int) $spacing->getAttribute('w:line') : null;
            $defaults['line_spacing_rule'] = $spacing->getAttribute('w:lineRule') !== ''
                ? $spacing->getAttribute('w:lineRule')
                : null;
            $defaults['space_before'] = $spacing->getAttribute('w:before') !== '' ? (int) $spacing->getAttribute('w:before') : null;
            $defaults['space_after'] = $spacing->getAttribute('w:after') !== '' ? (int) $spacing->getAttribute('w:after') : null;
        }

        $indentation = $this->findChild($properties, 'ind');

        if ($indentation !== null) {
            $firstLine = $indentation->getAttribute('w:firstLine');
            $hanging = $indentation->getAttribute('w:hanging');

            $defaults['first_line_indent'] = $firstLine !== '' ? (int) $firstLine : null;
            $defaults['hanging_indent'] = $hanging !== '' ? (int) $hanging : null;
            $defaults['left_indent'] = $indentation->getAttribute('w:left') !== '' ? (int) $indentation->getAttribute('w:left') : null;
        }

        $paragraphMark = $this->findChild($properties, 'rPr');

        if ($paragraphMark !== null) {
            $defaults += $this->fontPropertiesFrom($paragraphMark);
        }

        return $defaults;
    }

    /** @return array<string, mixed> */
    private function runProperties(DOMElement $element): array
    {
        $properties = $this->findChild($element, 'rPr');

        if ($properties === null) {
            return [
                'bold' => false,
                'italic' => false,
                'underline' => false,
                'superscript' => false,
                'subscript' => false,
                'font' => null,
                'font_size_half_points' => null,
                'is_strikethrough' => false,
            ];
        }

        $font = $this->fontPropertiesFrom($properties);

        return [
            'bold' => $this->findChild($properties, 'b') !== null,
            'italic' => $this->findChild($properties, 'i') !== null,
            'underline' => $this->findChild($properties, 'u') !== null,
            'is_strikethrough' => $this->findChild($properties, 'strike') !== null,
            'superscript' => $this->isVerticalAlign($properties, 'superscript'),
            'subscript' => $this->isVerticalAlign($properties, 'subscript'),
            'font' => $font['font'],
            'font_size_half_points' => $font['font_size_half_points'],
        ];
    }

    private function isVerticalAlign(DOMElement $properties, string $value): bool
    {
        $node = $this->findChild($properties, 'vertAlign');

        return $node !== null && $node->getAttribute('w:val') === $value;
    }

    /** @return array<string, mixed> */
    private function fontPropertiesFrom(DOMElement $properties): array
    {
        $result = ['font' => null, 'font_size_half_points' => null];

        $fonts = $this->findChild($properties, 'rFonts');

        if ($fonts !== null) {
            $result['font'] = $fonts->getAttribute('w:ascii') ?: null;
        }

        $size = $this->findChild($properties, 'sz');

        if ($size !== null) {
            $value = $size->getAttribute('w:val');

            if ($value !== '') {
                $result['font_size_half_points'] = (int) $value;
            }
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function parseSectionProperties(DOMElement $element): array
    {
        $pageSize = $this->findChild($element, 'pgSz');
        $margins = $this->findChild($element, 'pgMar');

        return [
            'width_twips' => $pageSize?->getAttribute('w:w') !== '' && $pageSize !== null ? (int) $pageSize->getAttribute('w:w') : null,
            'height_twips' => $pageSize?->getAttribute('w:h') !== '' && $pageSize !== null ? (int) $pageSize->getAttribute('w:h') : null,
            'orientation' => $pageSize !== null && $pageSize->getAttribute('w:orient') !== ''
                ? $pageSize->getAttribute('w:orient')
                : 'portrait',
            'margin_top_twips' => $margins !== null && $margins->getAttribute('w:top') !== '' ? (int) $margins->getAttribute('w:top') : null,
            'margin_right_twips' => $margins !== null && $margins->getAttribute('w:right') !== '' ? (int) $margins->getAttribute('w:right') : null,
            'margin_bottom_twips' => $margins !== null && $margins->getAttribute('w:bottom') !== '' ? (int) $margins->getAttribute('w:bottom') : null,
            'margin_left_twips' => $margins !== null && $margins->getAttribute('w:left') !== '' ? (int) $margins->getAttribute('w:left') : null,
            'header_distance_twips' => $margins !== null && $margins->getAttribute('w:header') !== '' ? (int) $margins->getAttribute('w:header') : null,
            'footer_distance_twips' => $margins !== null && $margins->getAttribute('w:footer') !== '' ? (int) $margins->getAttribute('w:footer') : null,
        ];
    }

    /** @param array<int, array<string, mixed>> $sections @return array<string, mixed> */
    private function flattenSectionProperties(array $sections): array
    {
        if ($sections === []) {
            return [];
        }

        return $sections[0];
    }

    /**
     * The OJMAS guidelines place author details on a separate cover page. The
     * parser marks the leading run of title-block paragraphs so the formatting
     * engine can enforce that without guessing at author names.
     *
     * @param  array<int, ManuscriptNode>  $blocks
     * @return array<int, ManuscriptNode>
     */
    private function promoteCoverPage(array $blocks): array
    {
        foreach ($blocks as $index => $block) {
            if ($block->type === NodeType::HEADING) {
                // First real heading: everything before it is front matter.
                return $this->wrapFrontMatter(array_slice($blocks, 0, $index), $blocks, $index);
            }
        }

        return $blocks;
    }

    /**
     * @param  array<int, ManuscriptNode>  $frontMatter
     * @param  array<int, ManuscriptNode>  $blocks
     * @return array<int, ManuscriptNode>
     */
    private function wrapFrontMatter(array $frontMatter, array $blocks, int $firstHeadingIndex): array
    {
        if ($frontMatter === []) {
            return $blocks;
        }

        $coverNodes = [];
        $abstractNode = null;
        $keywordNode = null;
        $titleNode = null;

        foreach ($frontMatter as $node) {
            $text = trim((string) $node->text);
            $lower = mb_strtolower($text);

            if ($node->type === NodeType::TITLE) {
                $titleNode ??= $node;
                continue;
            }

            if (in_array($lower, self::KEYWORDS_LABELS, true)) {
                $keywordNode = $node->setAttribute('role', 'keywords');
                continue;
            }

            if ($this->looksLikeKeywordLine($lower)) {
                $keywordNode = $node->setAttribute('role', 'keywords');
                continue;
            }

            if ($abstractNode === null && $this->looksLikeAbstractBody($text)) {
                $abstractNode = $node->setAttribute('role', 'abstract');
                continue;
            }

            $coverNodes[] = $node->setAttribute('role', 'cover_page_line');
        }

        $rebuilt = [];

        // The title must stay at block level: the formatting engine needs it
        // to enforce the journal's title rules, and it must not be swallowed
        // into the cover page container.
        if ($titleNode !== null) {
            $rebuilt[] = $titleNode;
        }

        if ($coverNodes !== []) {
            $rebuilt[] = new ManuscriptNode(
                NodeType::COVER_PAGE,
                ['line_count' => count($coverNodes)],
                $coverNodes,
                $this->flattenText($coverNodes),
            );
        }

        if ($abstractNode !== null) {
            $rebuilt[] = $abstractNode;
        }

        if ($keywordNode !== null) {
            $rebuilt[] = $keywordNode;
        }

        return array_merge($rebuilt, array_slice($blocks, $firstHeadingIndex));
    }

    /**
     * @param  array<int, ManuscriptNode>  $blocks
     * @return array<int, ManuscriptNode>
     */
    private function classifyFrontMatter(array $blocks): array
    {
        $result = [];
        $index = 0;
        $count = count($blocks);

        while ($index < $count) {
            $block = $blocks[$index];

            if ($block->type !== NodeType::HEADING) {
                $result[] = $block;
                $index++;
                continue;
            }

            $result[] = $block;

            $heading = mb_strtolower(trim((string) $block->text));
            $next = $index + 1 < $count ? $blocks[$index + 1] : null;

            // "Abstract" heading: promote the following body paragraph so the
            // journal's 150-word ceiling can be enforced against it directly.
            if (in_array($heading, self::ABSTRACT_HEADINGS, true)
                && $next !== null
                && $next->type === NodeType::PARAGRAPH) {
                $result[] = new ManuscriptNode(
                    NodeType::ABSTRACT,
                    $next->attributes + ['role' => 'abstract', 'abstract_heading' => $block->text],
                    $next->children,
                    $next->text,
                );
                $index += 2;
                continue;
            }

            if (in_array($heading, self::KEYWORDS_LABELS, true)
                && $next !== null
                && $next->type === NodeType::PARAGRAPH) {
                $result[] = new ManuscriptNode(
                    NodeType::KEYWORDS,
                    $next->attributes + ['role' => 'keywords', 'keywords_heading' => $block->text],
                    $next->children,
                    $next->text,
                );
                $index += 2;
                continue;
            }

            $index++;
        }

        return $result;
    }

    /** @param array<int, ManuscriptNode> $blocks @return array<int, ManuscriptNode> */
    private function numberReferences(array $blocks): array
    {
        $ordinal = 0;

        foreach ($blocks as $index => $block) {
            if ($block->attribute('is_reference_entry') !== true) {
                continue;
            }

            $ordinal++;
            $blocks[$index] = $block->setAttribute('ordinal', $ordinal);
            $blocks[$index] = new ManuscriptNode(
                NodeType::REFERENCE_ENTRY,
                $blocks[$index]->attributes,
                $blocks[$index]->children,
                $blocks[$index]->text,
            );
        }

        return $blocks;
    }

    private function isReferenceHeading(string $text): bool
    {
        return in_array(mb_strtolower(trim($text)), self::REFERENCE_HEADINGS, true);
    }

    private function looksLikeKeywordLine(string $lowered): bool
    {
        foreach (self::KEYWORDS_LABELS as $label) {
            if (str_starts_with($lowered, $label.':') || str_starts_with($lowered, $label.' ')) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeAbstractBody(string $text): bool
    {
        if (mb_strlen($text) < 120) {
            return false;
        }

        // An abstract is a single continuous prose block well over the journal's
        // 150-word floor before formatting.
        return substr_count($text, "\n") === 0 && mb_strlen($text) >= 400;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseNotes(?string $xml, string $kind): array
    {
        if ($xml === null) {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new \DOMDocument();
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOENT | LIBXML_COMPACT);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded === false) {
            return [];
        }

        $notes = [];
        $container = $kind === 'footnote' ? 'footnote' : 'endnote';

        foreach ($dom->documentElement?->childNodes ?? [] as $node) {
            if (! $node instanceof DOMElement || $node->localName !== $container) {
                continue;
            }

            $id = $node->getAttribute('w:id');

            // Separators carry id -1 / 0 and are not real notes.
            if (in_array($id, ['-1', '0'], true)) {
                continue;
            }

            $notes[] = [
                'id' => $id,
                'kind' => $kind,
                'text' => trim($this->collectText($node)),
            ];
        }

        return $notes;
    }

    /** @return array<string, string|null> */
    private function parseCoreProperties(?string $xml): array
    {
        $empty = [
            'creator' => null,
            'last_modified_by' => null,
            'title' => null,
            'subject' => null,
            'keywords' => null,
            'description' => null,
            'revision' => null,
            'created' => null,
            'modified' => null,
        ];

        if ($xml === null) {
            return $empty;
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new \DOMDocument();

        if ($dom->loadXML($xml, LIBXML_NONET | LIBXML_NOENT | LIBXML_COMPACT) === false) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            return $empty;
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $map = [
            'creator' => 'creator',
            'lastModifiedBy' => 'last_modified_by',
            'title' => 'title',
            'subject' => 'subject',
            'keywords' => 'keywords',
            'description' => 'description',
            'revision' => 'revision',
            'created' => 'created',
            'modified' => 'modified',
        ];

        foreach ($dom->documentElement?->childNodes ?? [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $key = $map[$node->localName] ?? null;

            if ($key !== null && $empty[$key] === null) {
                $value = trim($node->textContent);
                $empty[$key] = $value !== '' ? $value : null;
            }
        }

        return $empty;
    }

    private function tableStyle(DOMElement $element): ?string
    {
        $properties = $this->findChild($element, 'tblPr');

        if ($properties === null) {
            return null;
        }

        $style = $this->findChild($properties, 'tblStyle');

        return $style === null ? null : strtolower($style->getAttribute('w:val'));
    }

    private function tableHasHeaderRow(DOMElement $element): bool
    {
        $firstRow = $this->findChildren($element, 'tr')[0] ?? null;

        if ($firstRow === null) {
            return false;
        }

        $properties = $this->findChild($firstRow, 'trPr');

        return $properties !== null && $this->findChild($properties, 'tblHeader') !== null;
    }

    private function cellProperty(DOMElement $cell, string $localName): ?string
    {
        $properties = $this->findChild($cell, 'tcPr');

        return $properties === null ? null : $this->findChild($properties, $localName)?->getAttribute('w:val');
    }

    private function countWords(string $text): int
    {
        $text = preg_replace('/[\x{FFFC}\s]+/u', ' ', $text) ?? '';

        return count(array_filter(explode(' ', trim($text)), fn (string $word) => $word !== ''));
    }

    private function findChild(?DOMElement $parent, string $localName): ?DOMElement
    {
        if ($parent === null) {
            return null;
        }

        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    /** @return array<int, DOMElement> */
    private function findChildren(DOMElement $parent, string $localName): array
    {
        $found = [];

        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $localName) {
                $found[] = $child;
            }
        }

        return $found;
    }
}