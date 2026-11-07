<?php

namespace App\Services\Orcid;

use RuntimeException;

class DuplicateOrcidException extends RuntimeException
{
    public function __construct(string $message = 'This ORCID iD is already linked to another OJMAS account.')
    {
        parent::__construct($message);
    }
}
