<?php

namespace App\Services\Workspace;

use App\Models\ManuscriptComment;
use App\Models\ManuscriptWorkspaceDocument;
use App\Services\Documents\Formatting\StyleProfile;
use App\Services\Documents\Formatting\StyleProfileRepository;

/**
 * Exports a workspace document to DOCX.
 *
 * The exporter writes real OOXML. Table rows that the browser editor flattened
 * into pipe-delimited paragraphs are reassembled into a grid; pending tracked
 * changes are written as genuine `w:ins` and `w:del` elements with their real
 * authorship, so Word shows them as track changes rather than as loose text.
 * Comments are written as a visible appendix, because OOXML comment anchoring
 * is not reproduced faithfully and silently dropping the discussion would be
 * worse than presenting it as text.
 */
class WorkspaceDocxExporter
{
    public function __construct(
        private readonly StyleProfileRepository $profiles = new StyleProfileRepository(),
    ) {
    }

    /**
     * Renders the workspace document to $destinationPath.
     *
     * @return array{path: string, summary: array<string, mixed>}
     */
    public function export(
        ManuscriptWorkspaceDocument $workspace,
        string $destinationPath,
        ?StyleProfile $profile = null,
    ): array {
        $profile ??= $this->profiles->resolve();

        $body = $this->buildBody($workspace, $workspace->content());
        $body .= $this->renderCommentAppendix($workspace);
        $body .= $this->renderSectionProperties($profile);

        $documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
            .' xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math">'
            .'<w:body>'.$body.'</w:body></w:document>';

        $directory = dirname($destinationPath);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Unable to create the export directory.');
        }

        $zip = new \ZipArchive();

        if ($zip->open($destinationPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to create the export package.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->packageRelationshipsXml());
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->addFromString('word/_rels/document.xml.rels', $this->documentRelationshipsXml());
        $zip->addFromString('word/styles.xml', $this->stylesXml($profile));
        $zip->addFromString('docProps/core.xml', $this->corePropertiesXml($profile));
        $zip->addFromString('docProps/app.xml', $this->appPropertiesXml());
        $zip->close();

        return [
            'path' => $destinationPath,
            'summary' => [
                'blocks' => (int) $workspace->block_count,
                'words' => (int) $workspace->word_count,
                'pending_changes' => $workspace->pendingChanges()->count(),
                'comments' => $workspace->comments()->count(),
            ],
        ];
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .'<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            .'</Types>';
    }

    private function packageRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rIdDoc" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'<Relationship Id="rIdCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'<Relationship Id="rIdApp" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            .'</Relationships>';
    }

    private function documentRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function stylesXml(StyleProfile $profile): string
    {
        $font = $profile->approvedFonts()[0] ?? 'Times New Roman';
        $sizes = array_map('intval', (array) $profile->requirement('approved_font_sizes_half_points', [24]));
        $size = $sizes === [] ? 24 : $sizes[0];

        $headings = '';

        for ($level = 1; $level <= 6; $level++) {
            $headings .= '<w:style w:type="paragraph" w:styleId="Heading'.$level.'">'
                .'<w:name w:val="heading '.$level.'"/>'
                .'<w:pPr><w:outlineLvl w:val="'.($level - 1).'"/></w:pPr>'
                .'<w:rPr><w:b/></w:rPr></w:style>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:docDefaults><w:rPrDefault><w:rPr>'
            .'<w:rFonts w:ascii="'.$this->escape($font).'" w:hAnsi="'.$this->escape($font).'"/>'
            .'<w:sz w:val="'.$size.'"/><w:szCs w:val="'.$size.'"/>'
            .'</w:rPr></w:rPrDefault></w:docDefaults>'
            .'<w:style w:type="paragraph" w:styleId="Normal"><w:name w:val="Normal"/></w:style>'
            .$headings
            .'<w:style w:type="table" w:styleId="TableGrid"><w:name w:val="Table Grid"/></w:style>'
            .'</w:styles>';
    }

    private function corePropertiesXml(StyleProfile $profile): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"'
            .' xmlns:dc="http://purl.org/dc/elements/1.1/">'
            .'<dc:title>Workspace export</dc:title>'
            .'<dc:creator>OJMAS Journal</dc:creator>'
            .'<cp:lastModifiedBy>OJMAS Journal</cp:lastModifiedBy>'
            .'<dc:subject>'.$this->escape($profile->name).'</dc:subject>'
            .'</cp:coreProperties>';
    }

    private function appPropertiesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">'
            .'<Application>OJMAS Manuscript Workspace</Application>'
            .'</Properties>';
    }

    /**
     * Builds the document body, grouping consecutive table rows back into a grid.
     */
    private function buildBody(ManuscriptWorkspaceDocument $workspace, array $content): string
    {
        $xml = '';
        $tableBuffer = [];

        $flushTable = function () use (&$tableBuffer, &$xml): void {
            if ($tableBuffer === []) {
                return;
            }

            $xml .= '<w:tbl><w:tblPr><w:tblStyle w:val="TableGrid"/><w:tblW w:w="0" w:type="auto"/></w:tblPr>';

            foreach ($tableBuffer as $index => $rowText) {
                $xml .= '<w:tr>';

                if ($index === 0) {
                    $xml .= '<w:trPr><w:tblHeader/></w:trPr>';
                }

                foreach (explode('|', (string) $rowText) as $cell) {
                    $xml .= '<w:tc><w:tcPr><w:tcW w:w="0" w:type="auto"/></w:tcPr>'
                        .$this->paragraph($this->escape(trim($cell)))
                        .'</w:tc>';
                }

                $xml .= '</w:tr>';
            }

            $xml .= '</w:tbl>';
            $tableBuffer = [];
        };

        foreach ((array) ($content['content'] ?? []) as $block) {
            if (($block['attrs']['submissionBlockType'] ?? null) === 'table_row') {
                $tableBuffer[] = $this->plainOf($block);
                continue;
            }

            $flushTable();
            $xml .= $this->renderBlock($workspace, $block);
        }

        $flushTable();

        return $xml;
    }

    private function renderBlock(ManuscriptWorkspaceDocument $workspace, array $block): string
    {
        $type = (string) ($block['type'] ?? 'paragraph');
        $attrs = (array) ($block['attrs'] ?? []);

        $spacing = $this->spacingProperty($attrs);
        $alignment = $this->alignmentProperty($attrs, 'left');

        if ($type === 'heading') {
            $level = (int) ($attrs['level'] ?? 1);
            $xml = '<w:p><w:pPr><w:pStyle w:val="Heading'.max(1, min(6, $level)).'"/>'
                .$spacing.'<w:ind w:firstLine="0"/>'.$alignment.'</w:pPr>'
                .$this->inline($block)
                .'</w:p>';

            return $xml;
        }

        if ($type === 'blockquote') {
            return '<w:p><w:pPr>'.$spacing
                .'<w:ind w:left="720" w:firstLine="0"/>'.$alignment.'</w:pPr>'
                .$this->inline($block)
                .'</w:p>';
        }

        if ($type === 'bulletList' || $type === 'orderedList') {
            $xml = '<w:p><w:pPr>'.$spacing
                .'<w:ind w:left="720" w:hanging="360"/>'.$alignment.'</w:pPr>'
                .'<w:r><w:t xml:space="preserve">• </w:t></w:r>'
                .$this->inline($block)
                .'</w:p>';

            return $xml;
        }

        return '<w:p><w:pPr>'.$spacing.'<w:ind w:firstLine="720"/>'.$alignment.'</w:pPr>'
            .$this->inline($block)
            .'</w:p>';
    }

    /**
     * Line spacing into OOXML.
     *
     * Default is **double** (w:line="480", line-rule auto) — APA 7 requires
     * double spacing throughout, so the editor's untouched paragraphs comply
     * without anyone having to pick a setting. A `lineSpacing` attribute set
     * in the workspace is an explicit editorial decision and overrides it.
     */
    private function spacingProperty(array $attrs): string
    {
        $value = $attrs['lineSpacing'] ?? null;

        if (is_numeric($value) && (float) $value > 0) {
            $twips = (string) max(120, (int) round(((float) $value) * 240));

            return '<w:spacing w:line="'.$twips.'" w:lineRule="auto"/>';
        }

        return '<w:spacing w:line="480" w:lineRule="auto"/>';
    }

    /** Explicit alignment, or the APA flush-left default. */
    private function alignmentProperty(array $attrs, string $default = 'left'): string
    {
        $map = [
            'left' => 'left',
            'start' => 'left',
            'center' => 'center',
            'right' => 'right',
            'end' => 'right',
            'justify' => 'both',
        ];

        $value = (string) ($attrs['textAlign'] ?? $default);

        return '<w:jc w:val="'.$this->escape($map[$value] ?? 'both').'"/>';
    }

    /**
     * Renders inline content, turning tracked-change marks into real OOXML
     * tracked changes with their recorded authorship.
     */
    private function inline(array $node): string
    {
        if (($node['type'] ?? null) === 'text') {
            $text = (string) ($node['text'] ?? '');
            $marks = (array) ($node['marks'] ?? []);

            if ($text === '') {
                return '';
            }

            return $this->applyMarks($this->escape($text), $marks);
        }

        $xml = '';

        foreach ((array) ($node['content'] ?? []) as $child) {
            $xml .= $this->inline($child);
        }

        return $xml;
    }

    private function applyMarks(string $escaped, array $marks): string
    {
        $runProperties = '';
        $wrapper = null;
        $wrapperAttributes = '';

        foreach ($marks as $mark) {
            $type = (string) ($mark['attrs']['type'] ?? $mark['type'] ?? '');

            switch ($type) {
                case 'bold':
                    $runProperties .= '<w:b/>';
                    break;
                case 'italic':
                    $runProperties .= '<w:i/>';
                    break;
                case 'underline':
                    $runProperties .= '<w:u w:val="single"/>';
                    break;
                case 'strike':
                    $runProperties .= '<w:strike/>';
                    break;
                case 'textStyle':
                    // Office-suite typography chosen in the workspace: font
                    // family and size are carried into the run properties so
                    // Word shows exactly what the editor showed.
                    $style = (array) ($mark['attrs'] ?? []);

                    if (! empty($style['fontFamily'])) {
                        $family = $this->escape((string) $style['fontFamily']);
                        $runProperties .= '<w:rFonts w:ascii="'.$family.'" w:hAnsi="'.$family.'"/>';
                    }

                    if (! empty($style['fontSize'])) {
                        $points = $this->pointsFrom((string) $style['fontSize']);

                        if ($points !== null) {
                            $half = (string) max(2, (int) round($points * 2));
                            $runProperties .= '<w:sz w:val="'.$half.'"/><w:szCs w:val="'.$half.'"/>';
                        }
                    }

                    if (! empty($style['color'])) {
                        $runProperties .= '<w:color w:val="'.$this->escape(ltrim((string) $style['color'], '#')).'"/>';
                    }
                    break;
                case 'trackedInsertion':
                    $wrapper = 'w:ins';
                    $wrapperAttributes = sprintf(
                        ' w:id="%s" w:author="%s" w:date="%s"',
                        $this->escape((string) ($mark['attrs']['changeUid'] ?? '0')),
                        $this->escape((string) ($mark['attrs']['author'] ?? 'Unknown')),
                        $this->escape((string) ($mark['attrs']['date'] ?? now()->toIso8601String())),
                    );
                    break;
                case 'trackedDeletion':
                    $wrapper = 'w:del';
                    $wrapperAttributes = sprintf(
                        ' w:id="%s" w:author="%s" w:date="%s"',
                        $this->escape((string) ($mark['attrs']['changeUid'] ?? '0')),
                        $this->escape((string) ($mark['attrs']['author'] ?? 'Unknown')),
                        $this->escape((string) ($mark['attrs']['date'] ?? now()->toIso8601String())),
                    );
                    $runProperties .= '<w:strike/>';
                    break;
            }
        }

        $run = '<w:r>'
            .($runProperties === '' ? '' : '<w:rPr>'.$runProperties.'</w:rPr>')
            .'<w:t xml:space="preserve">'.$escaped.'</w:t>'
            .'</w:r>';

        return $wrapper === null ? $run : '<'.$wrapper.$wrapperAttributes.'>'.$run.'</'.$wrapper.'>';
    }

    /**
     * Comments are appended as a clearly-labelled discussion section.
     *
     * Reproducing OOXML comment anchors exactly is not something we can claim
     * to do reliably, and dropping the discussion would be worse than presenting
     * it as text the reader can see.
     */
    private function renderCommentAppendix(ManuscriptWorkspaceDocument $workspace): string
    {
        $comments = ManuscriptComment::with(['author', 'replies.author'])
            ->where('manuscript_workspace_document_id', $workspace->id)
            ->whereNull('parent_id')
            ->orderBy('created_at')
            ->get();

        if ($comments->isEmpty()) {
            return '';
        }

        $xml = '<w:p><w:pPr><w:pStyle w:val="Heading1"/><w:spacing w:before="480"/></w:pPr>'
            .'<w:r><w:t>Editorial Discussion</w:t></w:r></w:p>';

        foreach ($comments as $comment) {
            $xml .= $this->paragraph(
                '['.ucfirst($comment->status).'] '.$comment->author?->name.': '.$comment->body
            );

            if ($comment->quoted_text) {
                $xml .= $this->paragraph('  On: "'.$comment->quoted_text.'"');
            }

            foreach ($comment->replies as $reply) {
                $xml .= $this->paragraph('  ↳ '.$reply->author?->name.': '.$reply->body);
            }
        }

        return $xml;
    }

    private function renderSectionProperties(StyleProfile $profile): string
    {
        $paper = (array) $profile->requirement('paper', ['width_twips' => 11906, 'height_twips' => 16838]);
        $margins = (array) $profile->requirement('margins_twips', ['top' => 1440, 'right' => 1440, 'bottom' => 1440, 'left' => 1440]);

        return '<w:sectPr>'
            .'<w:pgSz w:w="'.(int) $paper['width_twips'].'" w:h="'.(int) $paper['height_twips'].'"/>'
            .'<w:pgMar w:top="'.(int) $margins['top'].'" w:right="'.(int) $margins['right'].'"'
            .' w:bottom="'.(int) $margins['bottom'].'" w:left="'.(int) $margins['left'].'"'
            .' w:header="720" w:footer="720" w:gutter="0"/>'
            .'</w:sectPr>';
    }

    private function paragraph(string $text): string
    {
        return '<w:p><w:pPr><w:spacing w:line="360" w:lineRule="auto"/></w:pPr>'
            .'<w:r><w:t xml:space="preserve">'.$this->escape($text).'</w:t></w:r></w:p>';
    }

    private function plainOf(array $node): string
    {
        if (($node['type'] ?? null) === 'text') {
            return (string) ($node['text'] ?? '');
        }

        $parts = [];

        foreach ((array) ($node['content'] ?? []) as $child) {
            $parts[] = $this->plainOf($child);
        }

        return implode('', $parts);
    }

    /** '12pt' → 12.0, '14 PT' → 14.0, '16px' → 12.0. Returns null for any unit the editor cannot emit. */
    private function pointsFrom(string $value): ?float
    {
        if (preg_match('/^\s*(\d+(?:\.\d+)?)\s*(pt|px)?\s*$/i', $value, $matches) !== 1) {
            return null;
        }

        $points = (float) $matches[1];

        if (strtolower((string) ($matches[2] ?? 'pt')) === 'px') {
            $points *= 0.75; // CSS px ≈ 3/4 pt at the mandated 96 dpi.
        }

        return $points;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}