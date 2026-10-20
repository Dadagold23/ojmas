<?php

namespace App\Services\Documents\Ast;

use App\Services\Documents\Ingestion\SecurityValidationReport;

/**
 * The complete neutral representation of a parsed manuscript, together with the
 * source-document facts the formatting engine and validators need but that do
 * not belong to the content tree itself.
 */
final class ParsedManuscript
{
    /**
     * @param  array<int, ManuscriptNode>  $blocks      Top-level block nodes.
     * @param  array<string, mixed>  $sectionProperties  Page geometry as found in the source.
     * @param  array<int, array<string, mixed>>  $media  Embedded images/objects found in the package.
     * @param  array<int, array<string, mixed>>  $footnotes
     * @param  array<string, mixed>  $coreProperties  docProps/core.xml values (creator, lastModifiedBy, …).
     */
    public function __construct(
        public readonly array $blocks,
        public readonly array $sectionProperties,
        public readonly array $media,
        public readonly array $footnotes,
        public readonly array $coreProperties,
        public readonly ?SecurityValidationReport $security = null,
        public readonly int $wordCount = 0,
        public readonly int $paragraphCount = 0,
    ) {
    }

    public function toArray(): array
    {
        return [
            'section_properties' => $this->sectionProperties,
            'media' => $this->media,
            'footnotes' => $this->footnotes,
            'core_properties' => $this->coreProperties,
            'word_count' => $this->wordCount,
            'paragraph_count' => $this->paragraphCount,
            'security' => $this->security?->toArray(),
            'blocks' => array_map(fn (ManuscriptNode $block) => $block->toArray(), $this->blocks),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            array_map(
                fn (array $block) => ManuscriptNode::fromArray($block),
                array_values((array) ($data['blocks'] ?? [])),
            ),
            (array) ($data['section_properties'] ?? []),
            (array) ($data['media'] ?? []),
            (array) ($data['footnotes'] ?? []),
            (array) ($data['core_properties'] ?? []),
            null,
            (int) ($data['word_count'] ?? 0),
            (int) ($data['paragraph_count'] ?? 0),
        );
    }

    /** @return array<int, ManuscriptNode> */
    public function blocksOfType(string $type): array
    {
        return array_values(array_filter($this->blocks, fn (ManuscriptNode $block) => $block->type === $type));
    }

    /** @return array<int, ManuscriptNode> */
    public function headings(): array
    {
        return $this->blocksOfType(NodeType::HEADING);
    }

    public function referenceEntries(): array
    {
        return $this->blocksOfType(NodeType::REFERENCE_ENTRY);
    }

    /** @return array<int, ManuscriptNode> */
    public function unsupportedNodes(): array
    {
        $found = [];

        foreach ($this->blocks as $block) {
            if ($block->type === NodeType::UNSUPPORTED) {
                $found[] = $block;
            }

            $found = array_merge($found, $block->descendantsOfType(NodeType::UNSUPPORTED));
        }

        return $found;
    }

    public function title(): ?string
    {
        $titleBlock = $this->blocksOfType(NodeType::TITLE)[0] ?? null;

        if ($titleBlock !== null) {
            return $titleBlock->text;
        }

        return null;
    }

    public function abstract(): ?ManuscriptNode
    {
        return $this->blocksOfType(NodeType::ABSTRACT)[0] ?? null;
    }

    /** Plain-text projection of the whole manuscript body, excluding references. */
    public function bodyText(): string
    {
        $parts = [];

        foreach ($this->blocks as $block) {
            if ($block->type === NodeType::REFERENCE_ENTRY || $block->type === NodeType::REFERENCE_LIST) {
                continue;
            }

            if ($block->text !== null && trim($block->text) !== '') {
                $parts[] = $block->text;
            }
        }

        return implode("\n", $parts);
    }
}