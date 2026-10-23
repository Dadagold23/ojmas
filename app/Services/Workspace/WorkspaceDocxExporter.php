<?php

namespace App\Services\Workspace;

use App\Models\ManuscriptComment;
use App\Models\ManuscriptMedia;
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
     * Media registry of the export in flight: editor media id → package
     * reference (relationship id + the part the bytes are written to). Set by
     * export(), never relied on across calls.
     *
     * @var array<int, array{rid: string, part: string, media: ManuscriptMedia}>
     */
    private array $imageRefs = [];

    private int $drawingId = 0;

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

        $content = $workspace->content();

        // Every figure the editor holds gets genuine WordprocessingML
        // relationships and real media parts in the package: an image is
        // exported as a real `<w:drawing>` Word can render, never as a
        // placeholder or flattened text.
        $this->imageRefs = $this->collectImageRefs($content);
        $this->drawingId = 0;

        $body = $this->buildBody($workspace, $content);
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

        // The extracted media copies become real package parts. Missing
        // copies were already refused in collectImageRefs, so every
        // referenced part exists here.
        $disk = \Illuminate\Support\Facades\Storage::disk(config('manuscripts.disk', 'private'));

        foreach ($this->imageRefs as $ref) {
            $zip->addFromString($ref['part'], $disk->get($ref['media']->storage_path));
        }

        $zip->close();

        return [
            'path' => $destinationPath,
            'summary' => [
                'blocks' => (int) $workspace->block_count,
                'words' => (int) $workspace->word_count,
                'images' => count($this->imageRefs),
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
            // Image defaults so embedded figures are legal package parts.
            .'<Default Extension="png" ContentType="image/png"/>'
            .'<Default Extension="jpeg" ContentType="image/jpeg"/>'
            .'<Default Extension="jpg" ContentType="image/jpeg"/>'
            .'<Default Extension="gif" ContentType="image/gif"/>'
            .'<Default Extension="webp" ContentType="image/webp"/>'
            .'<Default Extension="bmp" ContentType="image/bmp"/>'
            .'<Default Extension="tiff" ContentType="image/tiff"/>'
            .'<Default Extension="tif" ContentType="image/tiff"/>'
            .'<Default Extension="emf" ContentType="image/x-emf"/>'
            .'<Default Extension="wmf" ContentType="image/x-wmf"/>'
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
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        // One relationship per embedded figure; Word resolves the `<a:blip
        // r:embed>` in the drawing against exactly these ids.
        foreach ($this->imageRefs as $ref) {
            $xml .= '<Relationship Id="'.$ref['rid']
                .'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image'
                .'" Target="'.str_replace('word/', '', $ref['part']).'"/>';
        }

        return $xml.'</Relationships>';
    }

    /**
     * Registers every figure the content references as an exportable media
     * part: sequential relationship ids and `word/media/imageNN.ext` part
     * names. A row whose extracted copy is missing (storage tampered with)
     * is refused here, BEFORE any XML is written, so no dangling relationship
     * can ever end up in the package.
     *
     * @param  array<string, mixed>  $content
     * @return array<int, array{rid: string, part: string, media: ManuscriptMedia}>
     */
    private function collectImageRefs(array $content): array
    {
        $ids = [];

        foreach ($this->walkForImages($content) as $id) {
            $ids[(int) $id] = true;
        }

        if ($ids === []) {
            return [];
        }

        $rows = ManuscriptMedia::whereIn('id', array_keys($ids))->get()->keyBy('id');
        $disk = \Illuminate\Support\Facades\Storage::disk(config('manuscripts.disk', 'private'));

        $refs = [];
        $index = 0;

        foreach ($rows as $row) {
            if (! $disk->exists($row->storage_path)) {
                continue;
            }

            $index++;
            $extension = strtolower(pathinfo((string) $row->part_name, PATHINFO_EXTENSION));
            $extension = $extension !== '' ? $extension : 'bin';

            $refs[(int) $row->id] = [
                'rid' => 'rIdImg'.$index,
                'part' => sprintf('word/media/image%02d.%s', $index, $extension),
                'media' => $row,
            ];
        }

        return $refs;
    }

    /**
     * Every media id referenced by a `submissionImage` node anywhere in the
     * document (body and inside table cells).
     *
     * @param  array<string, mixed>  $node
     * @return array<int, int>
     */
    private function walkForImages(array $node): array
    {
        $ids = [];

        if (($node['type'] ?? null) === 'submissionImage' && is_numeric($node['attrs']['mediaId'] ?? null)) {
            $ids[] = (int) $node['attrs']['mediaId'];
        }

        foreach ((array) ($node['content'] ?? []) as $child) {
            if (is_array($child)) {
                foreach ($this->walkForImages($child) as $id) {
                    $ids[] = $id;
                }
            }
        }

        return $ids;
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
            if (($block['type'] ?? null) === 'submissionImage') {
                $flushTable();
                $xml .= $this->renderImageParagraph($block);
                continue;
            }

            if (($block['type'] ?? null) === 'table') {
                $flushTable();
                $xml .= $this->renderTable($block);
                continue;
            }

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

    /**
     * A figure block becomes a centred paragraph holding a REAL Wordprocessing
     * drawing (`<w:drawing>` → `wp:inline` → `a:blip r:embed`), bound to the
     * media part exported beside the document. No placeholder, no flattened
     * text — this is the same structure Word itself writes for an inline
     * picture.
     */
    private function renderImageParagraph(array $block): string
    {
        return '<w:p><w:pPr><w:spacing w:before="120" w:after="120"/><w:ind w:left="0" w:firstLine="0"/>'
            .'<w:jc w:val="center"/></w:pPr>'.$this->imageRun($block).'</w:p>';
    }

    /**
     * The drawing run for a `submissionImage` node.
     *
     * When no extracted media could be registered for the node (its row is
     * gone, or its copy is missing from storage), the figure is exported as a
     * clearly-labelled italic placeholder — the honest alternative, because
     * inventing an empty drawing frame Word cannot render would be worse.
     */
    private function imageRun(array $block): string
    {
        $attrs = (array) ($block['attrs'] ?? []);
        $ref = is_numeric($attrs['mediaId'] ?? null)
            ? ($this->imageRefs[(int) $attrs['mediaId']] ?? null)
            : null;

        if ($ref === null) {
            $caption = $attrs['alt'] ?? $attrs['mediaPart'] ?? 'figure';

            return '<w:r><w:rPr><w:i/></w:rPr><w:t xml:space="preserve">'
                .$this->escape('[figure unavailable: '.$caption.']')
                .'</w:t></w:r>';
        }

        $extent = $this->imageExtent($attrs, $ref['media']);
        $cx = (int) $extent['cx'];
        $cy = (int) $extent['cy'];
        $this->drawingId++;
        $drawingId = $this->drawingId;
        $name = (string) ($ref['media']->part_name ?: 'media/image'.str_pad((string) $drawingId, 2, '0', STR_PAD_LEFT).'.png');
        $alt = trim((string) ($attrs['alt'] ?? $ref['media']->description ?? ''));

        $W_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $R_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

        return '<w:r><w:drawing>'
            .'<wp:inline xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"'
            .' distT="0" distB="0" distL="0" distR="0">'
            .'<wp:extent cx="'.$cx.'" cy="'.$cy.'"/>'
            .'<wp:effectExtent l="0" t="0" r="0" b="0"/>'
            .'<wp:docPr id="'.$drawingId.'" name="Figure '.$drawingId.'"'
            .($alt !== '' ? ' descr="'.$this->escape($alt).'"' : '').'/>'
            .'<wp:cNvGraphicFramePr><a:graphicFrameLocks'
            .' xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"'
            .' noChangeAspect="1"/></wp:cNvGraphicFramePr>'
            .'<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
            .'<a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            .'<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            .'<pic:nvPicPr><pic:cNvPr id="'.$drawingId.'" name="'.$this->escape($name).'"/><pic:cNvPicPr/></pic:nvPicPr>'
            .'<pic:blipFill><a:blip xmlns:r="'.$R_NS.'" r:embed="'.$ref['rid'].'"/>'
            .'<a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            .'<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="'.$cx.'" cy="'.$cy.'"/></a:xfrm>'
            .'<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
            .'</pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r>';
    }

    /**
     * Display extent in EMUs (1 px = 9525 EMU): the size the editor shows
     * falls back to the probed pixel size of the extracted copy, and finally
     * to a 100×100 px square rather than a zero-size frame.
     *
     * @return array{cx: int, cy: int}
     */
    private function imageExtent(array $attrs, ManuscriptMedia $media): array
    {
        $emu = 9525;
        $width = is_numeric($attrs['width'] ?? null) && (int) $attrs['width'] > 0
            ? (int) $attrs['width'] : $media->width_px;
        $height = is_numeric($attrs['height'] ?? null) && (int) $attrs['height'] > 0
            ? (int) $attrs['height'] : $media->height_px;

        if (is_string($media->mime_type) && in_array($media->mime_type, ['image/x-emf', 'image/x-wmf', 'image/tiff'], true)) {
            $width ??= 200;
            $height ??= 200;
        }

        return [
            'cx' => max(1, (int) round(((int) ($width ?? 100)) * $emu)),
            'cy' => max(1, (int) round(((int) ($height ?? 100)) * $emu)),
        ];
    }

    /**
     * Renders a real Tiptap `table` node as a Word `w:tbl` grid.
     *
     * Column spans become `w:gridSpan`. Row spans become a proper vertical
     * merge: the origin cell writes `w:vMerge="restart"` and every covered
     * column position in the following rows gets the `w:vMerge` continuation
     * cell Word requires — nothing is faked and nothing is lost.
     *
     * The pipe-delimited `table_row` paragraphs written by older imports keep
     * their own legacy path above; this only reads genuine table nodes.
     */
    private function renderTable(array $table): string
    {
        $rows = array_values(array_filter(
            (array) ($table['content'] ?? []),
            fn ($row) => is_array($row) && (($row['type'] ?? null) === 'tableRow'),
        ));

        if ($rows === []) {
            return '';
        }

        $xml = '<w:tbl><w:tblPr><w:tblStyle w:val="TableGrid"/><w:tblW w:w="0" w:type="auto"/></w:tblPr>';

        /** @var array<int, int> $continuations column position → merge rows still owed */
        $continuations = [];

        foreach (array_values($rows) as $rowIndex => $row) {
            $cells = array_values(array_filter(
                (array) ($row['content'] ?? []),
                fn ($cell) => is_array($cell) && in_array($cell['type'] ?? null, ['tableCell', 'tableHeader'], true),
            ));

            $xml .= '<w:tr>';

            $isHeaderRow = $rowIndex === 0
                && count(array_filter($cells, fn ($cell) => ($cell['type'] ?? null) === 'tableHeader')) > 0;

            if ($isHeaderRow) {
                $xml .= '<w:trPr><w:tblHeader/></w:trPr>';
            }

            $column = 0;
            $index = 0;

            while (true) {
                if (($continuations[$column] ?? 0) > 0) {
                    $xml .= '<w:tc><w:tcPr><w:tcW w:w="0" w:type="auto"/><w:vMerge/></w:tcPr><w:p/></w:tc>';
                    $continuations[$column]--;

                    if ($continuations[$column] === 0) {
                        unset($continuations[$column]);
                    }

                    $column++;
                    continue;
                }

                if ($index >= count($cells)) {
                    break;
                }

                $cell = $cells[$index];
                $colspan = max(1, (int) ($cell['attrs']['colspan'] ?? 1));
                $rowspan = max(1, (int) ($cell['attrs']['rowspan'] ?? 1));

                if ($rowspan > 1) {
                    for ($span = 0; $span < $colspan; $span++) {
                        $continuations[$column + $span] = ($continuations[$column + $span] ?? 0) + $rowspan - 1;
                    }
                }

                $xml .= '<w:tc><w:tcPr><w:tcW w:w="0" w:type="auto"/>'
                    .($rowspan > 1 ? '<w:vMerge w:val="restart"/>' : '')
                    .($colspan > 1 ? '<w:gridSpan w:val="'.$colspan.'"/>' : '')
                    .'</w:tcPr>'
                    .$this->cellContent($cell)
                    .'</w:tc>';

                $column += $colspan;
                $index++;
            }

            $xml .= '</w:tr>';
        }

        return $xml.'</w:tbl>';
    }

    /**
     * Cell paragraphs keep the choice the editor made (alignment, bold/italic
     * marks) but drop the APA body geometry — no 0.5 in first-line indents
     * inside a grid — and use single line spacing, which is what Word's own
     * table cells do.
     */
    private function cellContent(array $cell): string
    {
        $xml = '';

        foreach ((array) ($cell['content'] ?? []) as $child) {
            if (! is_array($child)) {
                continue;
            }

            // A figure inside the cell stays inside the cell: it becomes its
            // own centred paragraph there, exactly as Word would lay it out.
            if (($child['type'] ?? null) === 'submissionImage') {
                $xml .= '<w:p><w:pPr><w:spacing w:before="0" w:after="0"/><w:jc w:val="center"/></w:pPr>'
                    .$this->imageRun($child)
                    .'</w:p>';

                continue;
            }

            $attrs = (array) ($child['attrs'] ?? []);
            $alignment = (string) ($child['type'] ?? '') === 'paragraph'
                ? $this->alignmentProperty($attrs, 'left')
                : '';

            // Non-paragraph block content inside a cell (a list, a heading)
            // still renders, as its own paragraph; only fidelity of style is
            // simplified, never the text.
            $xml .= '<w:p><w:pPr><w:spacing w:before="0" w:after="0" w:line="240" w:lineRule="auto"/>'
                .$alignment.'</w:pPr>'
                .$this->inline($child)
                .'</w:p>';
        }

        return $xml !== '' ? $xml : '<w:p/>';
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
                .$spacing.$this->indentationProperty($attrs, 0).$alignment.'</w:pPr>'
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

        // Body paragraph: the original upload's indentation is honoured; an
        // original paragraph with none set gets the APA 0.5 in first line.
        return '<w:p><w:pPr>'.$spacing.$this->indentationProperty($attrs, 720).$alignment.'</w:pPr>'
            .$this->inline($block)
            .'</w:p>';
    }

    /**
     * Line and paragraph spacing into OOXML.
     *
     * APA 7 defaults are always applied: double spacing (w:line="480",
     * line-rule auto) and **0 pt before, 0 pt after every paragraph** — so the
     * exported manuscript never carries accidental gaps, exactly as Word's
     * "Before: 0 pt, After: 0 pt" setting behaves. Values set in the
     * workspace (lineSpacing, spaceBefore, spaceAfter — pt for before/after)
     * are explicit editorial decisions and override the defaults.
     */
    private function spacingProperty(array $attrs): string
    {
        $value = $attrs['lineSpacing'] ?? null;

        if (is_numeric($value) && (float) $value > 0) {
            $line = (string) max(120, (int) round(((float) $value) * 240));
        } else {
            $line = '480'; // APA 7: double spacing throughout.
        }

        $before = is_numeric($attrs['spaceBefore'] ?? null)
            ? (string) max(0, (int) round(((float) $attrs['spaceBefore']) * 20))
            : '0';
        $after = is_numeric($attrs['spaceAfter'] ?? null)
            ? (string) max(0, (int) round(((float) $attrs['spaceAfter']) * 20))
            : '0';

        return '<w:spacing w:before="'.$before.'" w:after="'.$after.'" w:line="'.$line.'" w:lineRule="auto"/>';
    }

    /**
     * Indentation into OOXML.
     *
     * Whatever the author's original upload carried in (first line, left,
     * hanging — in twips) is honoured; paragraphs the original left unset get
     * the APA 7 default of a 0.5 in first-line indent, unless a `$default`
     * override says otherwise (headings are flush; the blockquote and list
     * branches set their own).
     */
    private function indentationProperty(array $attrs, ?int $defaultFirstLine = null): string
    {
        $parts = [];

        $left = is_numeric($attrs['leftIndent'] ?? null)
            ? max(0, (int) round((float) $attrs['leftIndent']))
            : 0;

        if ($left > 0) {
            $parts[] = 'w:left="'.$left.'"';
        }

        $hanging = is_numeric($attrs['hangingIndent'] ?? null)
            ? max(0, (int) round((float) $attrs['hangingIndent']))
            : null;

        if ($hanging !== null && $hanging > 0) {
            $parts[] = 'w:hanging="'.$hanging.'"';
        } else {
            $firstLine = is_numeric($attrs['firstLineIndent'] ?? null)
                ? (int) round((float) $attrs['firstLineIndent'])
                : $defaultFirstLine;

            if ($firstLine !== null) {
                $parts[] = 'w:firstLine="'.max(0, $firstLine).'"';
            }
        }

        return $parts === [] ? '' : '<w:ind '.implode(' ', $parts).'/>';
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
        if (($node['type'] ?? null) === 'hardBreak') {
            return '<w:br/>';
        }

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
                case 'superscript':
                    $runProperties .= '<w:vertAlign w:val="superscript"/>';
                    break;
                case 'subscript':
                    $runProperties .= '<w:vertAlign w:val="subscript"/>';
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