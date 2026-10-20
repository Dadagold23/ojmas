<?php

namespace App\Services\Documents\Parsing;

use App\Services\Documents\Exceptions\CorruptDocumentException;
use ZipArchive;

/**
 * Reads a DOCX package out of a ZIP archive without ever writing to disk.
 *
 * Extraction to a temporary directory would re-introduce the path-traversal
 * risk the security validator already rejects, so every part is read into
 * memory from the archive handle instead.
 */
final class OoxmlPackage
{
    private const DOCUMENT_PART = 'word/document.xml';
    private const STYLES_PART = 'word/styles.xml';
    private const NUMBERING_PART = 'word/numbering.xml';
    private const FOOTNOTES_PART = 'word/footnotes.xml';
    private const CORE_PROPERTIES_PART = 'docProps/core.xml';
    private const RELATIONSHIPS_PART = 'word/_rels/document.xml.rels';

    /** @var array<string, string> */
    private array $parts = [];

    private function __construct()
    {
    }

    public static function open(string $absolutePath): self
    {
        $zip = new ZipArchive();

        if ($zip->open($absolutePath, ZipArchive::RDONLY) !== true) {
            throw new CorruptDocumentException(
                'The document package could not be opened for reading.',
                'document_container_unreadable',
            );
        }

        $package = new self();

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);

                if ($stat === false || str_ends_with((string) $stat['name'], '/')) {
                    continue;
                }

                $contents = $zip->getFromIndex($i);

                if ($contents === false) {
                    continue;
                }

                $package->parts[(string) $stat['name']] = $contents;
            }

            if (! isset($package->parts[self::DOCUMENT_PART])) {
                throw new CorruptDocumentException(
                    'The document package does not contain a Word document body.',
                    'document_missing_body',
                );
            }
        } finally {
            $zip->close();
        }

        return $package;
    }

    public function has(string $part): bool
    {
        return isset($this->parts[$part]);
    }

    public function get(string $part): ?string
    {
        return $this->parts[$part] ?? null;
    }

    public function documentXml(): string
    {
        return $this->parts[self::DOCUMENT_PART];
    }

    public function stylesXml(): ?string
    {
        return $this->get(self::STYLES_PART);
    }

    public function numberingXml(): ?string
    {
        return $this->get(self::NUMBERING_PART);
    }

    public function footnotesXml(): ?string
    {
        return $this->get(self::FOOTNOTES_PART);
    }

    public function endnotesXml(): ?string
    {
        return $this->get('word/endnotes.xml');
    }

    public function corePropertiesXml(): ?string
    {
        return $this->get(self::CORE_PROPERTIES_PART);
    }

    public function relationshipsXml(): ?string
    {
        return $this->get(self::RELATIONSHIPS_PART);
    }

    /** @return array<int, string> Part names beginning with word/media/. */
    public function mediaPartNames(): array
    {
        $media = [];

        foreach (array_keys($this->parts) as $name) {
            if (str_starts_with($name, 'word/media/')) {
                $media[] = $name;
            }
        }

        return $media;
    }

    /** @return array<int, string> Part names beginning with word/embeddings/. */
    public function embeddedObjectNames(): array
    {
        $objects = [];

        foreach (array_keys($this->parts) as $name) {
            if (str_starts_with($name, 'word/embeddings/')) {
                $objects[] = $name;
            }
        }

        return $objects;
    }

    /** @return array<string, string> Raw bytes of a part, for verbatim preservation. */
    public function raw(string $part): ?string
    {
        return $this->parts[$part] ?? null;
    }

    /**
     * External relationship targets declared by the document body.
     *
     * Flagged rather than rejected: a manuscript may legitimately link to a
     * dataset, but the validation report must surface it.
     *
     * @return array<int, array{id: string, target: string, mode: string|null}>
     */
    public function externalRelationships(): array
    {
        $xml = $this->relationshipsXml();

        if ($xml === null) {
            return [];
        }

        $relationships = [];

        foreach ($this->loadXml($xml, 'document relationships')?->childNodes ?? [] as $node) {
            if (! $node instanceof \DOMElement || $node->localName !== 'Relationship') {
                continue;
            }

            if (strtolower((string) $node->getAttribute('TargetMode')) !== 'external') {
                continue;
            }

            $relationships[] = [
                'id' => (string) $node->getAttribute('Id'),
                'target' => (string) $node->getAttribute('Target'),
                'mode' => 'external',
            ];
        }

        return $relationships;
    }

    public function loadXml(string $xml, string $context = 'document part'): ?\DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new \DOMDocument();
        $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOENT | LIBXML_COMPACT);

        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($ok === false || $dom->documentElement === null) {
            throw new CorruptDocumentException(
                "The {$context} could not be parsed as XML. The document may be corrupted.",
                'document_invalid_xml',
                ['libxml_error' => $errors !== [] ? trim($errors[0]->message) : null],
            );
        }

        return $dom;
    }
}