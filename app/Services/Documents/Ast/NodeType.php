<?php

namespace App\Services\Documents\Ast;

/**
 * Canonical node type vocabulary for the parsed manuscript tree.
 */
final class NodeType
{
    public const TITLE = 'title';
    public const HEADING = 'heading';
    public const PARAGRAPH = 'paragraph';
    public const COVER_PAGE = 'cover_page';
    public const ABSTRACT = 'abstract';
    public const KEYWORDS = 'keywords';
    public const REFERENCE_LIST = 'reference_list';
    public const REFERENCE_ENTRY = 'reference_entry';
    public const CITATION = 'citation';
    public const TABLE = 'table';
    public const TABLE_ROW = 'table_row';
    public const TABLE_CELL = 'table_cell';
    public const FIGURE = 'figure';
    public const EQUATION = 'equation';
    public const BLOCKQUOTE = 'blockquote';
    public const FOOTNOTE = 'footnote';
    public const FOOTNOTE_REFERENCE = 'footnote_reference';
    public const APPENDIX_HEADING = 'appendix_heading';
    public const DECLARATION = 'declaration';
    public const PAGE_BREAK = 'page_break';
    public const SECTION_PROPERTIES = 'section_properties';

    /**
     * A construct the parser recognised as a valid OOXML element but could not
     * map onto a semantic manuscript construct.
     *
     * These are preserved verbatim in the tree and surfaced in the validation
     * report. They are never dropped.
     */
    public const UNSUPPORTED = 'unsupported';

    public const STRUCTURAL = [
        self::TITLE,
        self::HEADING,
        self::COVER_PAGE,
        self::ABSTRACT,
        self::KEYWORDS,
        self::REFERENCE_LIST,
        self::REFERENCE_ENTRY,
        self::APPENDIX_HEADING,
        self::DECLARATION,
        self::SECTION_PROPERTIES,
    ];

    /** Heading levels APA 7 defines, 1-5. */
    public const HEADING_LEVELS = [1, 2, 3, 4, 5];
}