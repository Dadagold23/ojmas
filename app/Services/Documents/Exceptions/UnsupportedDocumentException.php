<?php

namespace App\Services\Documents\Exceptions;

/**
 * A document the engine cannot process losslessly, e.g. legacy .doc binary,
 * a scanned PDF with no text layer, or an encrypted DOCX.
 *
 * The journal may still receive this document. It simply cannot be
 * automatically formatted, and the validation report must say so explicitly
 * rather than failing silently.
 */
class UnsupportedDocumentException extends DocumentProcessingException
{
    public function __construct(string $message, string $errorCode = 'document_unsupported', array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, $errorCode, $context, $previous);
    }

    public function severity(): string
    {
        return 'warning';
    }
}