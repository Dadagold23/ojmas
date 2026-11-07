<?php

namespace App\Services\Orcid;

use Carbon\CarbonInterface;

/**
 * The validated result of an ORCID authorization-code exchange.
 */
final class OrcidIdentity
{
    public function __construct(
        public readonly string $orcid,
        public readonly string $accessToken,
        public readonly ?string $refreshToken,
        public readonly ?CarbonInterface $expiresAt,
        public readonly string $scope,
        public readonly ?string $name = null,
    ) {
    }
}
