<?php

namespace App\Services\Documents\Ast;

/**
 * A single node in the neutral manuscript tree.
 *
 * The engine deliberately uses one structural class with a type tag rather
 * than a deep class hierarchy: the formatting engine and the citation validator
 * need to read and rewrite every node, and a tagged union keeps that traversal
 * in one place and fully serialisable.
 */
final class ManuscriptNode
{
    /**
     * @param  string  $type       One of the NodeType constants.
     * @param  array<string, mixed>  $attributes  Type-specific metadata.
     * @param  array<int, ManuscriptNode>  $children  Child nodes (runs inside a paragraph, cells inside a table, …).
     * @param  string|null  $text  Flattened plain text for the node.
     */
    public function __construct(
        public readonly string $type,
        public readonly array $attributes = [],
        public readonly array $children = [],
        public readonly ?string $text = null,
    ) {
    }

    public function withAttributes(array $attributes): self
    {
        return new self($this->type, $attributes + $this->attributes, $this->children, $this->text);
    }

    public function withChildren(array $children): self
    {
        return new self($this->type, $this->attributes, $children, $this->text);
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function setAttribute(string $key, mixed $value): self
    {
        $attributes = $this->attributes;
        $attributes[$key] = $value;

        return new self($this->type, $attributes, $this->children, $this->text);
    }

    /** @return array<int, self> */
    public function descendants(): array
    {
        $all = [];

        foreach ($this->children as $child) {
            $all[] = $child;
            $all = array_merge($all, $child->descendants());
        }

        return $all;
    }

    /** @return array<int, self> */
    public function descendantsOfType(string $type): array
    {
        return array_values(array_filter(
            $this->descendants(),
            fn (self $node) => $node->type === $type,
        ));
    }

    public function isEmpty(): bool
    {
        return trim((string) $this->text) === '' && $this->children === [];
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'attributes' => $this->attributes,
            'text' => $this->text,
            'children' => array_map(fn (self $child) => $child->toArray(), $this->children),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['type'] ?? NodeType::PARAGRAPH),
            (array) ($data['attributes'] ?? []),
            array_map(
                fn (array $child) => self::fromArray($child),
                array_values((array) ($data['children'] ?? [])),
            ),
            isset($data['text']) ? (string) $data['text'] : null,
        );
    }
}