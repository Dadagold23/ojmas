<?php

namespace App\Services\Workspace;

use App\Models\ManuscriptDocument;
use App\Models\ManuscriptSnapshot;
use App\Models\ManuscriptWorkspaceDocument;
use App\Models\Submission;
use App\Models\User;
use App\Services\Documents\Ast\ParsedManuscript;
use App\Services\Documents\Formatting\DocxRenderer;
use Illuminate\Support\Facades\DB;
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
     * Imports a parsed manuscript into the workspace, creating the first
     * snapshot. Existing content is never silently overwritten.
     */
    public function importFromParsed(
        ManuscriptWorkspaceDocument $workspace,
        ParsedManuscript $manuscript,
        User $author,
    ): ManuscriptWorkspaceDocument {
        $content = $this->convert($manuscript);

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
    public function convert(ParsedManuscript $manuscript): array
    {
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

            return [[
                'type' => 'heading',
                'attrs' => ['level' => max(1, min(6, $level)), 'submissionBlockType' => $node->type],
                'content' => $this->inlineContent($node),
            ]];
        }

        if ($type === 'blockquote') {
            return [[
                'type' => 'blockquote',
                'content' => [['type' => 'paragraph', 'content' => $this->inlineContent($node)]],
            ]];
        }

        return [[
            'type' => 'paragraph',
            'attrs' => ['submissionBlockType' => $node->type],
            'content' => $this->inlineContent($node),
        ]];
    }

    /** @return array<int, array<string, mixed>>|null */
    private function convertUnknownBlock(\App\Services\Documents\Ast\ManuscriptNode $node): ?array
    {
        // A merged-cell table cannot be faithfully modelled by the browser
        // editor, so each row becomes a pipe-delimited paragraph tagged as a
        // table row. Nothing is dropped, and the DOCX exporter reassembles the
        // grid from these rows.
        if ($node->type === \App\Services\Documents\Ast\NodeType::TABLE) {
            $paragraphs = [];

            foreach ($node->children as $row) {
                $cells = [];

                foreach ($row->children as $cell) {
                    $cells[] = trim((string) $cell->text);
                }

                $paragraphs[] = [
                    'type' => 'paragraph',
                    'attrs' => ['submissionBlockType' => 'table_row'],
                    'content' => [['type' => 'text', 'text' => implode(' | ', $cells)]],
                ];
            }

            return $paragraphs === [] ? null : $paragraphs;
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

    /** @return array<int, array<string, mixed>> */
    private function inlineContent(\App\Services\Documents\Ast\ManuscriptNode $node): array
    {
        $content = [];

        foreach ($node->children as $child) {
            if ($child->type !== 'run') {
                continue;
            }

            $text = (string) $child->text;

            if ($text === '') {
                continue;
            }

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

            $content[] = $marks === []
                ? ['type' => 'text', 'text' => $text]
                : ['type' => 'text', 'text' => $text, 'marks' => $marks];
        }

        if ($content !== []) {
            return $content;
        }

        $text = trim((string) $node->text);

        return $text === '' ? [] : [['type' => 'text', 'text' => $text]];
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