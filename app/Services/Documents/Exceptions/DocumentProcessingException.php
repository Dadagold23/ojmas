<?php

namespace App\Services\Documents\Exceptions;

use RuntimeException;

/**
 * Base class for every failure raised by the manuscript processing pipeline.
 *
 * Every subclass carries a stable machine-readable code so the validation
 * report can distinguish a security rejection from a corrupt file from an
 * unsupported format, rather than collapsing them into one generic error.
 */
class DocumentProcessingException extends RuntimeException
{
    public function __construct(
        string $message,
        protected string $errorCode = 'document_processing_failed',
        protected array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function context(): array
    {
        return $this->context;
    }

    /** Severity the validation report should record for this failure. */
    public function severity(): string
    {
        return 'error';
    }
}