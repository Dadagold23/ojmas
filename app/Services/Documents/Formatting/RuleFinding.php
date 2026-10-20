<?php

namespace App\Services\Documents\Formatting;

/**
 * A single, actionable observation produced by a formatting rule.
 *
 * Severity is deliberately three-valued so the validation report can separate
 * genuine errors from advisory warnings, as the module brief requires:
 *
 *  - error   — the manuscript does not satisfy a requirement the engine can
 *              determine from the document alone (wrong page size, abstract over
 *              the journal ceiling, malformed DOI).
 *  - warning — something that may be wrong and needs a human to look, but that
 *              the engine cannot decide (a citation without a matching entry,
 *              a possible duplicate reference).
 *  - info    — a change the engine applied, or an element it preserved but could
 *              not map onto a manuscript construct.
 */
final class RuleFinding
{
    public const SEVERITY_ERROR = 'error';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_INFO = 'info';

    /**
     * @param  array<string, mixed>  $location
     * @param  array{operation: string, payload: array<string, mixed>}|null  $fix
     */
    public function __construct(
        public readonly string $severity,
        public readonly string $category,
        public readonly string $code,
        public readonly string $message,
        public readonly string $ruleId,
        public readonly string $ruleVersion,
        public readonly ?string $suggestion = null,
        public readonly bool $wasAutoFixed = false,
        public readonly bool $requiresAuthorAction = false,
        public readonly array $location = [],
        public readonly ?array $fix = null,
    ) {
    }

    public static function error(
        string $category,
        string $code,
        string $message,
        string $ruleId,
        string $ruleVersion,
        ?string $suggestion = null,
        array $location = [],
        bool $requiresAuthorAction = true,
    ): self {
        return new self(
            self::SEVERITY_ERROR, $category, $code, $message, $ruleId, $ruleVersion,
            $suggestion, false, $requiresAuthorAction, $location, null,
        );
    }

    public static function warning(
        string $category,
        string $code,
        string $message,
        string $ruleId,
        string $ruleVersion,
        ?string $suggestion = null,
        array $location = [],
    ): self {
        return new self(
            self::SEVERITY_WARNING, $category, $code, $message, $ruleId, $ruleVersion,
            $suggestion, false, true, $location, null,
        );
    }

    public static function info(
        string $category,
        string $code,
        string $message,
        string $ruleId,
        string $ruleVersion,
        array $location = [],
    ): self {
        return new self(
            self::SEVERITY_INFO, $category, $code, $message, $ruleId, $ruleVersion,
            null, false, false, $location, null,
        );
    }

    /**
     * A violation the engine is able to correct deterministically.
     *
     * These become severity "info" findings with was_auto_fixed set, so the
     * author sees exactly what changed rather than a wall of errors.
     *
     * @param  array{operation: string, payload: array<string, mixed>}  $fix
     * @param  array<string, mixed>  $location
     */
    public static function autoFixed(
        string $category,
        string $code,
        string $message,
        string $ruleId,
        string $ruleVersion,
        array $fix,
        array $location = [],
    ): self {
        return new self(
            self::SEVERITY_INFO, $category, $code, $message, $ruleId, $ruleVersion,
            null, true, false, $location, $fix,
        );
    }

    /**
     * A construct the engine preserved verbatim but could not map onto a
     * manuscript construct. The author must confirm it reads correctly.
     *
     * @param  array<string, mixed>  $location
     */
    public static function preserved(
        string $category,
        string $code,
        string $message,
        string $ruleId,
        string $ruleVersion,
        ?string $detail = null,
        array $location = [],
    ): self {
        return new self(
            self::SEVERITY_WARNING, $category, $code, $message, $ruleId, $ruleVersion,
            $detail, true, true, $location, null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'severity' => $this->severity,
            'category' => $this->category,
            'code' => $this->code,
            'message' => $this->message,
            'rule_id' => $this->ruleId,
            'rule_version' => $this->ruleVersion,
            'suggestion' => $this->suggestion,
            'was_auto_fixed' => $this->wasAutoFixed,
            'requires_author_action' => $this->requiresAuthorAction,
            'location' => $this->location,
        ];
    }
}