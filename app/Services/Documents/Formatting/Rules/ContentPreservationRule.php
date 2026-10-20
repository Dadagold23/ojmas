<?php

namespace App\Services\Documents\Formatting\Rules;

use App\Services\Documents\Ast\ManuscriptNode;
use App\Services\Documents\Ast\NodeType;
use App\Services\Documents\Ast\ParsedManuscript;
use App\Services\Documents\Formatting\FormattingRule;
use App\Services\Documents\Formatting\RuleFinding;
use App\Services\Documents\Formatting\StyleProfile;

/**
 * Guards the module's hard requirement that unsupported content is never
 * silently dropped.
 *
 * Every construct the parser could not map onto a manuscript element is
 * surfaced here as an explicit finding, and every piece of media, footnote and
 * equation found in the package is counted so the report can prove that
 * preservation was attempted for each one.
 */
class ContentPreservationRule implements FormattingRule
{
    private const VERSION = '1.0.0';

    public function id(): string
    {
        return 'ojmas.content_preservation';
    }

    public function category(): string
    {
        return 'conversion';
    }

    public function evaluate(ParsedManuscript $manuscript, StyleProfile $profile): array
    {
        $findings = [];

        $unsupported = $manuscript->unsupportedNodes();

        foreach ($unsupported as $node) {
            $findings[] = RuleFinding::preserved(
                $this->category(),
                'content.unsupported_element',
                sprintf(
                    'A %s element was preserved unchanged but could not be mapped onto a manuscript construct. Please open the '
                    .'formatted document and confirm it reads correctly.',
                    (string) $node->attribute('ooxml_tag', 'unknown'),
                ),
                $this->id(),
                self::VERSION,
                (string) $node->attribute('reason'),
                ['ooxml_tag' => $node->attribute('ooxml_tag'), 'preserved_text' => $node->attribute('preserved_text')],
            );
        }

        foreach ($manuscript->media as $index => $media) {
            $findings[] = RuleFinding::info(
                $this->category(),
                'content.media_preserved',
                sprintf(
                    'Embedded media part %s (%s, %d bytes) is carried through to the formatted document.',
                    (string) ($media['part'] ?? 'media-'.$index),
                    $this->describeBytes((int) ($media['bytes'] ?? 0)),
                    (int) ($media['bytes'] ?? 0),
                ),
                $this->id(),
                self::VERSION,
                ['part' => $media['part'] ?? null],
            );
        }

        foreach ($manuscript->footnotes as $note) {
            $findings[] = RuleFinding::info(
                $this->category(),
                'content.note_preserved',
                sprintf('A %s was preserved with its text intact.', (string) ($note['kind'] ?? 'note')),
                $this->id(),
                self::VERSION,
                ['note_id' => $note['id'] ?? null, 'kind' => $note['kind'] ?? null],
            );
        }

        $equations = $manuscript->blocksOfType(NodeType::EQUATION);

        foreach ($manuscript->blocks as $block) {
            $equations = array_merge($equations, $block->descendantsOfType(NodeType::EQUATION));
        }

        if ($equations !== []) {
            $findings[] = RuleFinding::info(
                $this->category(),
                'content.equations_preserved',
                sprintf('%d equation(s) were preserved unchanged. Check their appearance in the formatted document.', count($equations)),
                $this->id(),
                self::VERSION,
                ['equation_count' => count($equations)],
            );
        }

        $trackedChanges = array_merge(
            $manuscript->blocksOfType('tracked_insertion'),
            $manuscript->blocksOfType('tracked_deletion'),
        );

        foreach ($manuscript->blocks as $block) {
            $trackedChanges = array_merge(
                $trackedChanges,
                $block->descendantsOfType('tracked_insertion'),
                $block->descendantsOfType('tracked_deletion'),
            );
        }

        if ($trackedChanges !== []) {
            $authors = array_values(array_unique(array_filter(array_map(
                fn (ManuscriptNode $change) => $change->attribute('author'),
                $trackedChanges,
            ))));

            $findings[] = RuleFinding::info(
                $this->category(),
                'content.tracked_changes_preserved',
                sprintf(
                    '%d tracked change(s) by %s were preserved and carried through to the formatted document.',
                    count($trackedChanges),
                    $authors === [] ? 'an unidentified author' : implode(', ', array_map('strval', $authors)),
                ),
                $this->id(),
                self::VERSION,
                ['change_count' => count($trackedChanges), 'authors' => $authors],
            );
        }

        if ($manuscript->media !== [] && $manuscript->paragraphCount === 0) {
            $findings[] = RuleFinding::warning(
                $this->category(),
                'content.no_body_paragraphs',
                'The document contains media but no readable body paragraphs. If the manuscript was submitted as a scanned '
                .'document or an image-only file, it cannot be formatted automatically.',
                $this->id(),
                self::VERSION,
                'Upload an editable Word document containing selectable text.',
                [],
            );
        }

        return $findings;
    }

    private function describeBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' bytes';
        }

        return round($bytes / 1024, 1).' KB';
    }
}