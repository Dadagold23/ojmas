<?php

namespace App\Services\Orcid;

use RuntimeException;

/**
 * A safe-to-display OAuth failure. The message never includes the
 * authorization code, tokens or the client secret.
 */
class OrcidOauthException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $reason = 'oauth_error',
    ) {
        parent::__construct($message);
    }

    public static function notConfigured(): self
    {
        return new self('ORCID sign-in is not configured for this journal.', 'not_configured');
    }

    public static function cancelled(): self
    {
        return new self('ORCID authorisation was cancelled. You can try again whenever you are ready.', 'cancelled');
    }

    public static function invalidResponse(): self
    {
        return new self('ORCID returned an incomplete authentication response. Please try again.', 'invalid_response');
    }

    public static function exchangeFailed(): self
    {
        return new self('ORCID could not verify the sign-in request. Please try again.', 'exchange_failed');
    }

    public static function unavailable(): self
    {
        return new self('ORCID is temporarily unavailable. Please try again in a few minutes.', 'unavailable');
    }
}
