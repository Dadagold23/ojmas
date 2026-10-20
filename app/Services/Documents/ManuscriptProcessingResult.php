<?php

namespace App\Services\Documents;

use App\Models\ManuscriptDocument;
use App\Models\ManuscriptValidation;
use App\Models\ManuscriptVersion;
use App\Services\Documents\Formatting\FormattingResult;
use App\Services\Documents\Formatting\StyleProfile;

/**
 * Everything one pipeline run produced, in one value object.
 */
final readonly class ManuscriptProcessingResult
{
    public function __construct(
        public ManuscriptVersion $version,
        public ManuscriptDocument $document,
        public ?ManuscriptValidation $validation,
        public FormattingResult $result,
        public StyleProfile $profile,
        public string $formattedPath,
    ) {
    }

    public function isApproved(): bool
    {
        return $this->validation !== null && $this->validation->isApproved();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'version' => [
                'id' => $this->version->id,
                'version_number' => $this->version->version_number,
                'sha256' => $this->version->sha256,
            ],
            'profile' => [
                'slug' => $this->profile->slug,
                'version' => $this->profile->version,
            ],
            'summary' => $this->result->toArray()['summary'],
            'formatted_path' => $this->formattedPath,
        ];
    }
}