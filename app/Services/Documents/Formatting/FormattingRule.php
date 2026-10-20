<?php

namespace App\Services\Documents\Formatting;

use App\Services\Documents\Ast\ParsedManuscript;

/**
 * One deterministic APA 7 / OJMAS requirement check.
 *
 * Rules never invent content and never rewrite an author's argument. They may
 * only correct formatting that is objectively wrong, and they must report
 * anything they cannot decide.
 */
interface FormattingRule
{
    /** Stable identifier, e.g. "apa7.page_geometry". */
    public function id(): string;

    /** Report grouping: layout | typography | structure | citation | content. */
    public function category(): string;

    /**
     * @return array<int, RuleFinding>
     */
    public function evaluate(ParsedManuscript $manuscript, StyleProfile $profile): array;
}