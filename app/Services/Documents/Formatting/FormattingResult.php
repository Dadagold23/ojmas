<?php

namespace App\Services\Documents\Formatting;

use App\Services\Documents\Ast\ParsedManuscript;

/**
 * The outcome of one formatting run: what was found, what was corrected, and
 * what the author still has to deal with.
 */
final class FormattingResult
{
    /**
     * @param  array<int, RuleFinding>  $findings
     */
    public function __construct(
        public readonly ParsedManuscript $original,
        public readonly ParsedManuscript $formatted,
        public readonly array $findings,
        public readonly StyleProfile $profile,
    ) {
    }

    /** @return array<int, RuleFinding> */
    public function ofSeverity(string $severity): array
    {
        return array_values(array_filter(
            $this->findings,
            fn (RuleFinding $finding) => $finding->severity === $severity,
        ));
    }

    public function errors(): array
    {
        return $this->ofSeverity(RuleFinding::SEVERITY_ERROR);
    }

    public function warnings(): array
    {
        return $this->ofSeverity(RuleFinding::SEVERITY_WARNING);
    }

    public function autoFixedCount(): int
    {
        return count(array_filter($this->findings, fn (RuleFinding $finding) => $finding->wasAutoFixed));
    }

    /** @return array<int, RuleFinding> */
    public function authorActionCount(): int
    {
        return count(array_filter(
            $this->findings,
            fn (RuleFinding $finding) => $finding->requiresAuthorAction && ! $finding->wasAutoFixed,
        ));
    }

    /** @return array<int, RuleFinding> */
    public function unsupportedCount(): int
    {
        return count(array_filter(
            $this->findings,
            fn (RuleFinding $finding) => $finding->category === 'conversion'
                && $finding->code === 'content.unsupported_element',
        ));
    }

    public function isClean(): bool
    {
        return $this->errors() === [] && $this->authorActionCount() === 0;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'profile' => [
                'slug' => $this->profile->slug,
                'name' => $this->profile->name,
                'version' => $this->profile->version,
            ],
            'summary' => [
                'errors' => count($this->errors()),
                'warnings' => count($this->warnings()),
                'auto_fixed' => $this->autoFixedCount(),
                'author_action_required' => $this->authorActionCount(),
                'unsupported_elements' => $this->unsupportedCount(),
            ],
            'findings' => array_map(fn (RuleFinding $finding) => $finding->toArray(), $this->findings),
        ];
    }
}