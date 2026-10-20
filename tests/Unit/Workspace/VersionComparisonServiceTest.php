<?php

namespace Tests\Unit\Workspace;

use App\Models\ManuscriptSnapshot;
use App\Models\ManuscriptWorkspaceDocument;
use App\Services\Workspace\VersionComparisonService;
use Tests\TestCase;

class VersionComparisonServiceTest extends TestCase
{
    private VersionComparisonService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new VersionComparisonService();
    }

    private function snapshot(string $plainText, int $revision = 1): ManuscriptSnapshot
    {
        return new ManuscriptSnapshot([
            'manuscript_workspace_document_id' => 1,
            'revision' => $revision,
            'content_json' => ['type' => 'doc', 'content' => []],
            'plain_text' => $plainText,
            'word_count' => str_word_count($plainText),
            'block_count' => 1,
            'sha256' => hash('sha256', $plainText),
            'created_from' => 'manual',
        ]);
    }

    public function test_identical_texts_report_no_difference(): void
    {
        $result = $this->service->compare(
            $this->snapshot('The quick brown fox.'),
            $this->snapshot('The quick brown fox.'),
        );

        $this->assertTrue($result['identical']);
        $this->assertSame([], $result['operations']);
        $this->assertSame(0, $result['summary']['added']);
        $this->assertSame(0, $result['summary']['removed']);
    }

    public function test_an_insertion_is_reported(): void
    {
        $result = $this->service->compare(
            $this->snapshot('The cat sat.'),
            $this->snapshot('The cat sat quietly.'),
        );

        $this->assertFalse($result['identical']);
        $this->assertGreaterThan(0, $result['summary']['added']);

        $inserted = implode(' ', array_column(array_filter(
            $result['operations'],
            fn (array $operation) => $operation['type'] === 'insert',
        ), 'text'));

        // A word-level LCS re-anchors the shared trailing period, so a single
        // token may also appear as removed. What matters is that the new word
        // is reported and the surrounding text is preserved.
        $this->assertStringContainsString('quietly', $inserted);

        $reconstructed = preg_replace(
            '/\s+/',
            ' ',
            trim(implode(' ', array_map(
                fn (array $operation) => $operation['type'] === 'delete' ? '' : $operation['text'],
                array_filter($result['operations'], fn (array $operation) => $operation['type'] !== 'skip'),
            ))),
        ) ?? '';

        $this->assertStringContainsString('The cat sat', $reconstructed);
    }

    public function test_a_deletion_is_reported(): void
    {
        $result = $this->service->compare(
            $this->snapshot('The cat sat quietly.'),
            $this->snapshot('The cat sat.'),
        );

        $this->assertGreaterThan(0, $result['summary']['removed']);

        $deleted = implode(' ', array_column(array_filter(
            $result['operations'],
            fn (array $operation) => $operation['type'] === 'delete',
        ), 'text'));

        $this->assertStringContainsString('quietly', $deleted);
    }

    public function test_a_replacement_is_reported_as_both(): void
    {
        $result = $this->service->compare(
            $this->snapshot('The cat sat.'),
            $this->snapshot('The dog sat.'),
        );

        $this->assertGreaterThan(0, $result['summary']['added']);
        $this->assertGreaterThan(0, $result['summary']['removed']);
    }

    public function test_a_completely_different_document_is_handled(): void
    {
        $result = $this->service->compare(
            $this->snapshot('alpha beta gamma delta'),
            $this->snapshot('one two three four five six seven'),
        );

        $this->assertFalse($result['identical']);
        $this->assertNotEmpty($result['operations']);
    }

    public function test_empty_text_is_handled(): void
    {
        $result = $this->service->compare($this->snapshot(''), $this->snapshot('Something.'));

        $this->assertFalse($result['identical']);
        $this->assertGreaterThan(0, $result['summary']['added']);
    }

    public function test_both_empty_texts_are_identical(): void
    {
        $result = $this->service->compare($this->snapshot(''), $this->snapshot(''));

        $this->assertTrue($result['identical']);
    }

    public function test_long_unchanged_runs_are_collapsed(): void
    {
        $long = str_repeat('alpha beta gamma delta epsilon zeta ', 200);

        $result = $this->service->compare(
            $this->snapshot(trim($long)),
            $this->snapshot(trim($long).' appended'),
        );

        $skips = array_filter($result['operations'], fn (array $operation) => $operation['type'] === 'skip');

        $this->assertNotEmpty($skips, 'A long unchanged run must be collapsed, not dumped in full.');
        $this->assertStringContainsString('unchanged words', reset($skips)['text']);
    }

    public function test_a_very_large_comparison_degrades_gracefully(): void
    {
        // ~2500 words each: the LCS table would be 6.25M cells, past the guard.
        $left = trim(str_repeat('alpha beta gamma delta epsilon ', 500));
        $right = trim(str_repeat('alpha beta gamma delta epsilon ', 499).'omega');

        $result = $this->service->compare($this->snapshot($left), $this->snapshot($right));

        $this->assertFalse($result['identical']);
        $this->assertNotEmpty($result['operations'], 'The fallback must still produce a usable diff.');
        $this->assertSame(
            ['delete', 'insert'],
            array_values(array_unique(array_column($result['operations'], 'type'))),
        );
    }

    public function test_the_result_is_json_serialisable(): void
    {
        $result = $this->service->compare(
            $this->snapshot('One two.'),
            $this->snapshot('One three.'),
        );

        $encoded = json_encode($result);

        $this->assertIsString($encoded);
        $this->assertNotFalse(json_decode($encoded, true));
    }
}