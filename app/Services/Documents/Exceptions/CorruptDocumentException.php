<?php

namespace App\Services\Documents\Exceptions;

/**
 * The file is a genuine container but could not be read: truncated ZIP, invalid
 * XML, missing required OOXML parts.
 */
class CorruptDocumentException extends DocumentProcessingException
{
    public function __construct(string $message, string $errorCode = 'document_corrupt', array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, $errorCode, $context, $previous);
    }
}