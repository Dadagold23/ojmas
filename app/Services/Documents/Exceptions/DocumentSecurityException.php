<?php

namespace App\Services\Documents\Exceptions;

/**
 * The upload was rejected by the security validator: macro payload, zip bomb,
 * path traversal, encrypted container, or a non-OOXML file disguised as DOCX.
 */
class DocumentSecurityException extends DocumentProcessingException
{
    public function __construct(string $message, string $errorCode = 'document_rejected_security', array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, $errorCode, $context, $previous);
    }
}