<?php

namespace App\Services\Workspace;

use App\Models\ArticleFile;
use App\Models\ManuscriptDocument;
use App\Models\ManuscriptMedia;
use App\Models\ManuscriptSnapshot;
use App\Models\ManuscriptVersion;
use App\Models\ManuscriptWorkspaceDocument;
use App\Models\Submission;
use App\Models\User;
use App\Services\Documents\Ast\ManuscriptNode;
use App\Services\Documents\Ast\NodeType;
use App\Services\Documents\Ast\ParsedManuscript;
use App\Services\Documents\Exceptions\DocumentProcessingException;
use App\Services\Documents\Formatting\DocxRenderer;
use App\Services\Documents\Ingestion\DocxSecurityValidator;
use App\Services\Documents\ManuscriptProcessingService;
use App\Services\Documents\Media\ManuscriptMediaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Owns the browser-editable working document.
 *
 * Concurrency model
 * -----------------
 * This is **sequential editing with optimistic concurrency detection**, not
 * real-time collaboration. Two people may have the document open at once; the
 * second person's save is rejected if the first person's save landed in between.
 * The rejected save is returned to the browser intact so the user can compare
 * and merge rather than losing work.
 *
 * Why not real-time: Tiptap ships without a collaborative backend, and the
 * alternatives either require a paid subscription (Tiptap Collaboration) or a
 * separate CRDT service. Neither has been authorised. The prompt forbids
 * claiming real-time collaboration when only sequential editing exists, so this
 * class is named and documented for what it is.
 */
class WorkspaceDocumentService
{
    /**
     * Node types that become Tiptap block nodes on import.
     */
    private const BLOCK_MAPPING = [
        'title' => 'heading',
        'heading' => 'heading',
        'abstract_heading' => 'heading',
        'paragraph' => 'paragraph',
        'abstract' => 'paragraph',
        'keywords' => 'paragraph',
        'reference_list' => 'heading',
        'reference_entry' => 'paragraph',
        'block_quote' => 'blockquote',
    ];

    public function __construct(
        private readonly DocxSecurityValidator $securityValidator,
        private readonly ManuscriptProcessingService $processing,
        private readonly ManuscriptMediaService $media,
    ) {
    }

    /**
     * Media context of the conversion in flight (set by convert(), never
     * relied on across calls): OOXML part name → extracted media row, and a
     * resolver that turns a row into the policy-gated editor URL.
     *
     * @var array<string, ManuscriptMedia>|null
     */
    private ?array $mediaByPart = null;

    private ?\Closure $mediaUrlResolver = null;

    /**
     * Finds or creates the working document for a parsed manuscript.
     */
    public function forDocument(ManuscriptDocument $document, int $submissionId): ManuscriptWorkspaceDocument
    {
        $existing = ManuscriptWorkspaceDocument::where('manuscript_document_id', $document->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        return ManuscriptWorkspaceDocument::create([
            'manuscript_document_id' => $document->id,
            'submission_id' => $submissionId,
            'content_json' => null,
            'revision' => 1,
        ]);
    }

    /**
     * Finds the workspace document through the version chain, when it exists.
     *
     * Chain: submission → manuscript_version → manuscript_document → workspace.
     * A version that exists but was never parsed is parsed on demand — an
     * interrupted ingestion should not permanently hide a readable manuscript.
     */
    public function resolveForSubmission(Submission $submission): ?ManuscriptWorkspaceDocument
    {
        $version = ManuscriptVersion::where('submission_id', $submission->id)
            ->latest('id')
            ->first();

        if ($version === null) {
            return null;
        }

        $document = ManuscriptDocument::where('manuscript_version_id', $version->id)->first();

        if ($document === null) {
            try {
                $document = $this->processing->parse($version);
            } catch (DocumentProcessingException) {
                return null;
            }
        }

        if (! $document->hasParsedSuccessfully()) {
            return null;
        }

        // Corrective Module 3: workspaces imported before image extraction
        // existed gain their media rows lazily on the next open. The service
        // is idempotent — once rows exist it returns without opening the
        // package, so repeated opens and saves never duplicate extraction.
        $this->media->extractForVersion($document->version);

        return $this->forDocument($document, $submission->id);
    }

    /**
     * Bootstraps the workspace from the ORIGINAL uploaded manuscript.
     *
     * Submissions that exist (legacy rows, plain uploads, out-of-band import
     * flows) have no manuscript_version record, because that record is created
     * by the ingestion pipeline. Without it, `resolveForSubmission`
     * returned null and the browser editor rendered an empty shell — which was
     * reported as "the workspace is not implemented; only download works".
     *
     * This method closes that gap without re-uploading or touching anything:
     * it re-uses the ORIGINAL stored bytes as a manuscript_version
     * (`source_file_id` points at the existing `article_files` record and
     * `storage_path` already exists on the private disk, so no file is copied
     * and no original is modified), parses it through the same security
     * validation and DOCX parser as ingestion, and imports the result into an
     * editable workspace document.
     *
     * Returns null when there is honestly nothing to edit: no manuscript file,
     * a file that fails the same security validation as uploads (not a DOCX,
     * corrupt, oversized, macro-carrying), or a document the parser could not
     * read. The caller renders an explanatory state in that case; nothing is
     * silently discarded.
     */
    public function bootstrapForSubmission(Submission $submission, User $user): ?ManuscriptWorkspaceDocument
    {
        $workspace = $this->resolveForSubmission($submission);

        if ($workspace !== null) {
            return $workspace;
        }

        $file = ArticleFile::where('submission_id', $submission->id)
            ->where('file_type', 'manuscript')
            ->where('is_anonymised', false)
            ->latest('id')
            ->first();

        if ($file === null) {
            return null;
        }

        $disk = Storage::disk(config('manuscripts.disk', 'private'));
        $path = trim((string) $file->file_path);

        if ($path === '' || ! $disk->exists($path)) {
            return null;
        }

        $absolute = $disk->path($path);

        try {
            // The exact same gate every upload is subject to (Module 1):
            // magic bytes, zip ceilings, macro rejection, traversal checks.
            $this->securityValidator->validate($absolute, (string) $file->original_name);

            $sha256 = hash_file('sha256', $absolute);
            $size = (int) filesize($absolute);

            $version = DB::transaction(function () use ($submission, $file, $path, $sha256, $size, $user) {
                $number = (int) ManuscriptVersion::where('submission_id', $submission->id)
                    ->max('version_number') + 1;

                $version = ManuscriptVersion::create([
                    'submission_id' => $submission->id,
                    'version_number' => $number,
                    'label' => $file->version_label ?: 'Version '.$number,
                    'source_file_id' => $file->id,
                    'storage_path' => $path,
                    'mime_type' => $file->mime_type ?: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'file_size' => $size,
                    'sha256' => $sha256,
                    'format' => 'original',
                    // The allowed enum values record where a version came
                    // from. These bytes ARE an author upload, merely now
                    // registered for editing; no fictional provenance.
                    'origin' => 'author_upload',
                    'created_by' => $user->id,
                ]);

                if ($submission->current_version_id === null) {
                    $submission->forceFill(['current_version_id' => $version->id])->save();
                }

                return $version;
            });

            $document = $this->processing->parse($version);

            if (! $document->hasParsedSuccessfully()) {
                return null;
            }

            $workspace = $this->forDocument($document, $submission->id);

            $parsed = ParsedManuscript::fromArray($document->structure_json ?? []);

            return $this->importFromParsed($workspace, $parsed, $user);
        } catch (DocumentProcessingException) {
            // The original failed ingestion-grade validation or parsing.
            // Nothing was created; the caller explains itself.
            return null;
        }
    }

    /**
     * Imports a parsed manuscript into the workspace, creating the first
     * snapshot. Existing content is never silently overwritten.
     */
    public function importFromParsed(
        ManuscriptWorkspaceDocument $workspace,
        ParsedManuscript $manuscript,
        User $author,
    ): ManuscriptWorkspaceDocument {
        // Embedded images are extracted from the ORIGINAL package once (the
        // service is idempotent) and can never be duplicated by later saves.
        $version = $workspace->document?->version;
        $mediaByPart = [];

        if ($version !== null) {
            $mediaByPart = $this->media->extractForVersion($version);
        }

        $submissionId = (int) $workspace->submission_id;

        $content = $this->convert($manuscript, $mediaByPart, function (ManuscriptMedia $row) use ($submissionId): string {
            return route('workspace.media.show', ['submission' => $submissionId, 'media' => $row->id]);
        });

        $workspace->update([
            'content_json' => $content,
            'plain_text' => $this->plainText($content),
            'word_count' => $this->wordCount($content),
            'block_count' => $this->blockCount($content),
            'last_saved_at' => now(),
            'updated_by' => $author->id,
        ]);

        $snapshot = $this->takeSnapshot(
            $workspace,
            ManuscriptSnapshot::FROM_IMPORT,
            $author,
            'Imported from the submitted document',
        );

        return $workspace->fresh();
    }

    /**
     * Persists an edit, enforcing the optimistic-concurrency contract.
     *
     * @param  array  $content  The Tiptap document sent by the browser.
     * @param  int  $baseRevision  The revision the edit was made against.
     */
    public function save(
        ManuscriptWorkspaceDocument $workspace,
        array $content,
        int $baseRevision,
        User $editor,
        bool $force = false,
        ?string $snapshotLabel = null,
    ): SaveResult {
        if (! is_array($content) || ($content['type'] ?? null) !== 'doc') {
            return SaveResult::invalid($workspace, 'The document payload was not a valid editor document.');
        }

        return DB::transaction(function () use ($workspace, $content, $baseRevision, $editor, $force, $snapshotLabel) {
            // A row lock serialises the read-check-write so two simultaneous
            // requests cannot both pass the revision test.
            $current = ManuscriptWorkspaceDocument::whereKey($workspace->getKey())->lockForUpdate()->first();

            if ($current === null) {
                return SaveResult::invalid($workspace, 'The document no longer exists.');
            }

            if (! $force && $baseRevision !== (int) $current->revision) {
                // Nothing is written to the row. The submitter's content is
                // returned intact so the browser can present both versions and
                // let the user decide — an overwrite is never automatic.
                return SaveResult::conflict($current, $baseRevision, $content);
            }

            $current->update([
                'content_json' => $content,
                'plain_text' => $this->plainText($content),
                'word_count' => $this->wordCount($content),
                'block_count' => $this->blockCount($content),
                'revision' => (int) $current->revision + 1,
                'last_saved_at' => now(),
                'updated_by' => $editor->id,
            ]);

            $snapshot = $snapshotLabel !== null
                ? $this->takeSnapshot($current, ManuscriptSnapshot::FROM_MANUAL, $editor, $snapshotLabel)
                : null;

            return SaveResult::saved($current->fresh(), $current, $snapshot);
        });
    }

    /**
     * Records a snapshot at the given revision.
     *
     * One snapshot exists per revision. Saving a named version of content that
     * has not changed since the last snapshot reuses that snapshot rather than
     * failing, so "save a label" is always safe to press.
     */
    public function takeSnapshot(
        ManuscriptWorkspaceDocument $workspace,
        string $createdFrom,
        User $author,
        ?string $label = null,
        ?ManuscriptSnapshot $restoredFrom = null,
    ): ManuscriptSnapshot {
        $content = $workspace->content();
        $json = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $attributes = [
            'label' => $label,
            'content_json' => $content,
            'plain_text' => $this->plainText($content),
            'word_count' => (int) $workspace->word_count,
            'block_count' => (int) $workspace->block_count,
            'sha256' => hash('sha256', (string) $json),
            'created_from' => $createdFrom,
            'restored_from_id' => $restoredFrom?->id,
            'created_by' => $author->id,
        ];

        $existing = ManuscriptSnapshot::where('manuscript_workspace_document_id', $workspace->id)
            ->where('revision', (int) $workspace->revision)
            ->first();

        if ($existing !== null) {
            // Only a genuinely different state deserves a new row; otherwise the
            // existing snapshot is annotated. This also lets a later event
            // (such as a restore) refine how the same revision was reached
            // without duplicating an identical row.
            if ($existing->sha256 !== $attributes['sha256']) {
                $existing->update($attributes);

                return $existing->fresh();
            }

            $updates = [];

            if ($label !== null && $existing->label !== $label) {
                $updates['label'] = $label;
            }

            if ($existing->created_from !== $createdFrom) {
                $updates['created_from'] = $createdFrom;
            }

            if ($restoredFrom !== null && (int) $existing->restored_from_id !== (int) $restoredFrom->id) {
                $updates['restored_from_id'] = $restoredFrom->id;
            }

            if ($updates !== []) {
                $existing->update($updates);
            }

            return $existing;
        }

        return ManuscriptSnapshot::create($attributes + [
            'manuscript_workspace_document_id' => $workspace->id,
            'revision' => (int) $workspace->revision,
        ]);
    }

    /**
     * Restores a previous snapshot as a NEW revision.
     *
     * History is never rewritten: restoring v3 when you are on v12 produces v13
     * whose content equals v3, with v3 recorded as the source.
     */
    public function restore(
        ManuscriptWorkspaceDocument $workspace,
        ManuscriptSnapshot $snapshot,
        User $editor,
    ): SaveResult {
        if ((int) $snapshot->manuscript_workspace_document_id !== (int) $workspace->id) {
            return SaveResult::invalid($workspace, 'That version does not belong to this manuscript.');
        }

        return $this->save(
            $workspace,
            $snapshot->content(),
            (int) $workspace->revision,
            $editor,
            force: true,
            snapshotLabel: 'Restored from revision '.$snapshot->revision,
        );
    }

    // ───────────────────────── conversion ─────────────────────────

    /**
     * Converts a parsed manuscript tree into a Tiptap/ProseMirror document.
     *
     * Inline emphasis survives as ProseMirror marks, so an italicised journal
     * title stays italic rather than being flattened.
     *
     * @return array<string, mixed>
     */
    public function convert(ParsedManuscript $manuscript, array $mediaByPart = [], ?\Closure $mediaUrlResolver = null): array
    {
        // Per-conversion media context. Conversions are sequential within a
        // request; the fields are set here and unset below, never relied on
        // across calls.
        $this->mediaByPart = $mediaByPart;
        $this->mediaUrlResolver = $mediaUrlResolver;

        /** @return array<int, array<string, mixed>> */
        $blocks = [];

        foreach ($manuscript->blocks as $node) {
            $converted = $this->convertBlock($node);

            if ($converted === null) {
                continue;
            }

            // A block may expand into several nodes (a table row per row).
            foreach ($converted as $entry) {
                $blocks[] = $entry;
            }
        }

        $this->mediaByPart = null;
        $this->mediaUrlResolver = null;

        if ($blocks === []) {
            $blocks[] = ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => '']]];
        }

        return ['type' => 'doc', 'content' => $blocks];
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function convertBlock(\App\Services\Documents\Ast\ManuscriptNode $node): ?array
    {
        $type = self::BLOCK_MAPPING[$node->type] ?? null;

        if ($type === null) {
            return $this->convertUnknownBlock($node);
        }

        if ($type === 'heading') {
            $level = (int) $node->attribute('level', 1);

            if ($node->type === 'title') {
                $level = 1;
            }

            $images = [];

            $block = [
                'type' => 'heading',
                'attrs' => array_merge(
                    ['level' => max(1, min(6, $level)), 'submissionBlockType' => $node->type],
                    $this->formattingAttributes($node),
                ),
                'content' => $this->inlineContent($node, $images),
            ];

            return array_merge([$block], $images);
        }

        if ($type === 'blockquote') {
            $images = [];

            $block = [
                'type' => 'blockquote',
                'content' => [['type' => 'paragraph', 'content' => $this->inlineContent($node, $images)]],
            ];

            return array_merge([$block], $images);
        }

        $images = [];

        $block = [
            'type' => 'paragraph',
            'attrs' => array_merge(
                ['submissionBlockType' => $node->type],
                $this->formattingAttributes($node),
            ),
            'content' => $this->inlineContent($node, $images),
        ];

        // Images sit exactly where the source placed them: as their own
        // blocks immediately following the paragraph they were embedded in —
        // a figure paragraph in Word is a paragraph holding a drawing, so the
        // editor shows the paragraph (kept, never destroyed) and then the
        // figure itself.
        return array_merge([$block], $images);
    }

    /**
     * Formatting the author applied in the original upload, carried into the
     * workspace so the document can be accepted **as it was formatted**.
     *
     * First-line, left and hanging indents are always preserved (an explicit
     * 0 stays 0, an absent value stays absent so the APA default applies).
     * Deliberate alignment is preserved too — a centred title must still be
     * centred in the workspace.
     *
     * Line spacing and space-before/after are deliberately NOT carried: APA 7
     * defaults (double spacing, 0 pt gaps) always apply; only an explicit
     * editor decision changes them.
     *
     * @return array<string, mixed>
     */
    private function formattingAttributes(\App\Services\Documents\Ast\ManuscriptNode $node): array
    {
        $attrs = [];

        $alignment = $node->attribute('alignment');

        if (in_array($alignment, ['center', 'right', 'both', 'justify'], true)) {
            $attrs['textAlign'] = $alignment === 'both' ? 'justify' : $alignment;
        }

        foreach ([
            'first_line_indent' => 'firstLineIndent',
            'left_indent' => 'leftIndent',
            'hanging_indent' => 'hangingIndent',
        ] as $sourceKey => $attrName) {
            $value = $node->attribute($sourceKey);

            if (is_numeric($value)) {
                $attrs[$attrName] = (int) $value;
            }
        }

        return $attrs;
    }

    /** @return array<int, array<string, mixed>>|null */
    private function convertUnknownBlock(\App\Services\Documents\Ast\ManuscriptNode $node): ?array
    {
        // Whole tables become real Tiptap table nodes, so the browser editor
        // can insert, edit and delete rows and columns. Column spans from
        // merged cells are carried as the cell's colspan; a rowspan imported
        // from Word is not modelled (each Word row is imported per row), so a
        // rowspan>1 can only appear through editor merges, which DOCX export
        // writes back as true vertical merges.
        if ($node->type === \App\Services\Documents\Ast\NodeType::TABLE) {
            return $this->convertTable($node);
        }

        // Cover-page front matter (title-page lines, affiliation statements)
        // must stay one paragraph per line. Flattening it into a single
        // preserved block merged the author's separate lines into one
        // continuous text block — exactly the defect this module corrects.
        // Each line keeps its own runs, so inline emphasis survives too.
        if ($node->type === \App\Services\Documents\Ast\NodeType::COVER_PAGE) {
            $lines = [];

            foreach ($node->children as $line) {
                // A nested Word table the cover-page promoter folded in keeps
                // its real-grid conversion; the figures inside it are not
                // lost (Corrective Module 3): tables are expanded, not
                // flattened into a cover-page line.
                if ($line->type === \App\Services\Documents\Ast\NodeType::TABLE) {
                    $convertedTable = $this->convertTable($line);

                    if ($convertedTable !== null) {
                        foreach ($convertedTable as $block) {
                            $lines[] = $block;
                        }
                    }

                    continue;
                }

                // A figure embedded IN a cover-page line becomes its own block
                // right after that line — same placement contract as body
                // paragraphs, never a silent drop.
                $lineImages = [];

                $lines[] = [
                    'type' => 'paragraph',
                    'attrs' => ['submissionBlockType' => 'cover_page_line', 'preserved' => true],
                    'content' => $this->inlineContent($line, $lineImages),
                ];

                foreach ($lineImages as $image) {
                    $lines[] = $image;
                }
            }

            return $lines === [] ? null : $lines;
        }

        if ($node->type === \App\Services\Documents\Ast\NodeType::EQUATION) {
            return [[
                'type' => 'paragraph',
                'attrs' => ['submissionBlockType' => 'equation', 'preserved' => true],
                'content' => [[
                    'type' => 'text',
                    'text' => trim((string) $node->text),
                ]],
            ]];
        }

        $text = trim((string) $node->text);

        if ($text === '') {
            return null;
        }

        return [[
            'type' => 'paragraph',
            'attrs' => ['submissionBlockType' => $node->type, 'preserved' => true],
            'content' => [['type' => 'text', 'text' => $text]],
        ]];
    }

    /**
     * Converts a parsed table into Tiptap table / tableRow / tableCell nodes.
     *
     * The first row becomes header cells when Word recorded it as a header
     * row. Every cell always carries at least an empty paragraph because the
     * Tiptap schema requires block content inside every cell.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function convertTable(\App\Services\Documents\Ast\ManuscriptNode $node): ?array
    {
        $hasHeaderRow = (bool) $node->attribute('has_header_row', false);
        $rows = [];
        $rowIndex = 0;

        foreach ($node->children as $row) {
            $cells = [];

            foreach ($row->children as $cell) {
                $paragraphs = [];

                foreach ($cell->children as $cellParagraph) {
                    if (! in_array($cellParagraph->type, ['paragraph', 'heading', 'abstract', 'title'], true)) {
                        continue;
                    }

                    // Figures embedded inside a cell stay inside the cell:
                    // they are appended after the cell's paragraphs rather
                    // than escaping to document level.
                    $cellImages = [];
                    $inline = $this->inlineContent($cellParagraph, $cellImages);

                    $paragraphs[] = [
                        'type' => 'paragraph',
                        'content' => $inline === [] ? [] : $inline,
                    ];

                    foreach ($cellImages as $image) {
                        $paragraphs[] = $image;
                    }
                }

                if ($paragraphs === []) {
                    $paragraphs[] = ['type' => 'paragraph', 'content' => []];
                }

                $colspan = (int) ($cell->attribute('grid_span', 1) ?? 1);

                $cells[] = [
                    'type' => $hasHeaderRow && $rowIndex === 0 ? 'tableHeader' : 'tableCell',
                    'attrs' => [
                        'colspan' => max(1, min(64, $colspan)),
                        'rowspan' => 1,
                        'colwidth' => null,
                    ],
                    'content' => $paragraphs,
                ];
            }

            if ($cells === []) {
                continue;
            }

            $rows[] = ['type' => 'tableRow', 'content' => $cells];
            $rowIndex++;
        }

        if ($rows === []) {
            return null;
        }

        return [[
            'type' => 'table',
            'content' => $rows,
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function inlineContent(\App\Services\Documents\Ast\ManuscriptNode $node, ?array &$capturedImages = null): array
    {
        $capturedImages ??= [];
        $content = [];

        foreach ($node->children as $child) {
            // A drawing the parser resolved to a media part becomes its own
            // block node; the capture reference lets the block converter
            // place it in the document exactly after its host paragraph.
            if ($child->type === NodeType::INLINE_IMAGE) {
                $image = $this->submissionImageNode($child);

                if ($image !== null) {
                    $capturedImages[] = $image;
                }

                continue;
            }

            if ($child->type !== 'run') {
                continue;
            }

            $text = (string) $child->text;

            // Marks the parser reported on the run are carried faithfully: bold,
            // italic, underline, strikethrough, superscript and subscript. A
            // Word soft line break inside the run becomes a real hardBreak
            // node, NOT a raw \n the browser would silently collapse — a
            // line break inside a paragraph is distinct from a new paragraph.
            $marks = [];

            if ($child->attribute('bold') === true) {
                $marks[] = ['type' => 'bold'];
            }

            if ($child->attribute('italic') === true) {
                $marks[] = ['type' => 'italic'];
            }

            if ($child->attribute('underline') === true) {
                $marks[] = ['type' => 'underline'];
            }

            if ($child->attribute('is_strikethrough') === true) {
                $marks[] = ['type' => 'strike'];
            }

            if ($child->attribute('superscript') === true) {
                $marks[] = ['type' => 'superscript'];
            }

            if ($child->attribute('subscript') === true) {
                $marks[] = ['type' => 'subscript'];
            }

            foreach ($this->runInlineNodes($text, $marks) as $segment) {
                $content[] = $segment;
            }
        }

        if ($content !== []) {
            return $content;
        }

        // The paragraph holds no faithful runs: keep its flattened text, but
        // soft line breaks still survive as hardBreak nodes rather than
        // joining everything into one visible line.
        foreach ($this->runInlineNodes(trim((string) $node->text), []) as $segment) {
            $content[] = $segment;
        }

        return $content;
    }

    /**
     * Builds a Tiptap `submissionImage` block node from a parsed inline image.
     *
     * The `src` always points at the authorization-gated media endpoint —
     * never at a guessable public path — and is only available when the
     * extraction produced a media row for the drawing's part. When it did
     * not, the node is emitted with `src = null` plus a diagnostic reason so
     * the editor renders an honest placeholder instead of silently dropping
     * the figure.
     *
     * Display size: the EMU extent the source declared (1 px = 9525 EMU),
     * falling back to the probed pixel size of the extracted copy.
     *
     * @return array<string, mixed>|null
     */
    private function submissionImageNode(ManuscriptNode $node): ?array
    {
        $part = (string) ($node->attribute('media_part') ?? '');

        if ($part === '') {
            return null;
        }

        $media = $this->mediaByPart !== null ? ($this->mediaByPart[$part] ?? null) : null;

        $src = null;

        if ($media !== null) {
            $resolver = $this->mediaUrlResolver;
            $src = $resolver !== null ? $resolver($media) : null;
        }

        $emu = 9525;
        $cx = $node->attribute('extent_cx');
        $cy = $node->attribute('extent_cy');
        $width = is_numeric($cx) && (int) $cx > 0 ? (int) round(((int) $cx) / $emu) : $media?->width_px;
        $height = is_numeric($cy) && (int) $cy > 0 ? (int) round(((int) $cy) / $emu) : $media?->height_px;

        $attrs = [
            'src' => $src,
            'mediaId' => $media?->id,
            'mediaPart' => $part !== '' ? $part : null,
            'alt' => $node->attribute('description'),
            'title' => $node->attribute('name'),
            'width' => $width,
            'height' => $height,
            'preserved' => true,
        ];

        if ($src === null) {
            $attrs['diagnostic'] = $media === null
                ? 'The image part could not be extracted from the original manuscript.'
                : 'The media file is present, but no view route could be resolved.';
        }

        return ['type' => 'submissionImage', 'attrs' => $attrs];
    }

    /**
     * Splits a run's text into text/hardBreak inline nodes.
     *
     * @param  array<int, array<string, mixed>>  $marks
     * @return array<int, array<string, mixed>>
     */
    private function runInlineNodes(string $text, array $marks): array
    {
        $nodes = [];
        $segments = explode("\n", $text);

        foreach ($segments as $index => $segment) {
            if ($index > 0) {
                $nodes[] = ['type' => 'hardBreak'];
            }

            if ($segment === '') {
                continue;
            }

            $nodes[] = $marks === []
                ? ['type' => 'text', 'text' => $segment]
                : ['type' => 'text', 'text' => $segment, 'marks' => $marks];
        }

        return $nodes;
    }

    /** Extracts the plain-text projection of a Tiptap document. */
    public function plainText(array $content): string
    {
        return trim($this->walkText($content));
    }

    private function walkText(mixed $node): string
    {
        if (! is_array($node)) {
            return '';
        }

        if (isset($node['type']) && $node['type'] === 'text' && isset($node['text'])) {
            return (string) $node['text'];
        }

        $parts = [];

        foreach ((array) ($node['content'] ?? []) as $child) {
            $parts[] = $this->walkText($child);
        }

        $type = $node['type'] ?? null;

        if (in_array($type, ['paragraph', 'heading'], true)) {
            return implode('', $parts)."\n\n";
        }

        return implode('', $parts);
    }

    public function wordCount(array $content): int
    {
        $text = preg_replace('/\s+/u', ' ', $this->plainText($content)) ?? '';

        return count(array_filter(explode(' ', trim($text)), fn ($word) => $word !== ''));
    }

    public function blockCount(array $content): int
    {
        return count((array) ($content['content'] ?? []));
    }
}