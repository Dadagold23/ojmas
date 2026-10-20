<?php

namespace App\Services\Documents\Exceptions;

/**
 * The upload exceeded a configured size ceiling.
 */
class DocumentTooLargeException extends DocumentProcessingException
{
    public function __construct(string $message, string $errorCode = 'document_too_large', array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, $errorCode, $context, $previous);
    }
}