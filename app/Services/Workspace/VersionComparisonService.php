<?php

namespace App\Services\Workspace;

use App\Models\ManuscriptSnapshot;

/**
 * Plain-text and word-level comparison between two workspace snapshots.
 *
 * This is a deterministic longest-common-subsequence diff over words. It is not
 * a merge tool and does not attempt to combine two versions automatically —
 * that is an editorial decision, and the workspace presents the two texts for
 * a human to reconcile.
 */
class VersionComparisonService
{
    /** @var array<int, string> */
    private array $leftWords = [];

    /** @var array<int, string> */
    private array $rightWords = [];

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     * @return array<string, mixed>
     */
    public function compare(ManuscriptSnapshot $left, ManuscriptSnapshot $right, int $contextSize = 3): array
    {
        $leftText = (string) $left->plain_text;
        $rightText = (string) $right->plain_text;

        if ($leftText === $rightText) {
            return [
                'identical' => true,
                'left' => $this->describe($left),
                'right' => $this->describe($right),
                'operations' => [],
                'summary' => ['added' => 0, 'removed' => 0, 'unchanged' => $this->countWords($leftText)],
            ];
        }

        $this->leftWords = $this->tokenise($leftText);
        $this->rightWords = $this->tokenise($rightText);

        $operations = $this->diff($contextSize);

        $added = 0;
        $removed = 0;
        $unchanged = 0;

        foreach ($operations as $operation) {
            $count = substr_count($operation['text'], ' ') + ($operation['text'] === '' ? 0 : 1);

            $added += $operation['type'] === 'insert' ? $count : 0;
            $removed += $operation['type'] === 'delete' ? $count : 0;
            $unchanged += $operation['type'] === 'equal' ? $count : 0;
        }

        return [
            'identical' => false,
            'left' => $this->describe($left),
            'right' => $this->describe($right),
            'operations' => $operations,
            'summary' => ['added' => $added, 'removed' => $removed, 'unchanged' => $unchanged],
        ];
    }

    /**
     * Longest common subsequence over words, then grouped into operations with
     * unchanged runs collapsed to their context window.
     *
     * @return array<int, array{type: string, text: string}>
     */
    private function diff(int $contextSize): array
    {
        $left = $this->leftWords;
        $right = $this->rightWords;

        $rows = count($left);
        $columns = count($right);

        // Guard against pathological inputs: a very large document would make
        // the O(n*m) table untenable, so very large pairs fall back to a
        // whole-document replace rather than exhausting memory.
        if ($rows * $columns > 4_000_000) {
            return array_values(array_filter([
                ['type' => 'delete', 'text' => implode(' ', $left)],
                ['type' => 'insert', 'text' => implode(' ', $right)],
            ], fn (array $operation) => $operation['text'] !== ''));
        }

        // lengths[i][j] = LCS length of left[i..] and right[j..]
        $lengths = array_fill(0, $rows + 1, array_fill(0, $columns + 1, 0));

        for ($i = $rows - 1; $i >= 0; $i--) {
            for ($j = $columns - 1; $j >= 0; $j--) {
                $lengths[$i][$j] = $left[$i] === $right[$j]
                    ? $lengths[$i + 1][$j + 1] + 1
                    : max($lengths[$i + 1][$j], $lengths[$i][$j + 1]);
            }
        }

        $operations = [];
        $i = 0;
        $j = 0;

        while ($i < $rows && $j < $columns) {
            if ($left[$i] === $right[$j]) {
                $operations[] = ['type' => 'equal', 'text' => $left[$i]];
                $i++;
                $j++;
            } elseif ($lengths[$i + 1][$j] >= $lengths[$i][$j + 1]) {
                $operations[] = ['type' => 'delete', 'text' => $left[$i]];
                $i++;
            } else {
                $operations[] = ['type' => 'insert', 'text' => $right[$j]];
                $j++;
            }
        }

        while ($i < $rows) {
            $operations[] = ['type' => 'delete', 'text' => $left[$i]];
            $i++;
        }

        while ($j < $columns) {
            $operations[] = ['type' => 'insert', 'text' => $right[$j]];
            $j++;
        }

        return $this->collapse($operations, $contextSize);
    }

    /**
     * Collapses long unchanged runs to their first and last N words, marking
     * what was skipped so the reader knows something was omitted.
     *
     * @param  array<int, array{type: string, text: string}>  $operations
     * @return array<int, array{type: string, text: string}>
     */
    private function collapse(array $operations, int $contextSize): array
    {
        $collapsed = [];
        $count = count($operations);
        $index = 0;

        while ($index < $count) {
            if ($operations[$index]['type'] !== 'equal') {
                $collapsed[] = $operations[$index];
                $index++;
                continue;
            }

            $runEnd = $index;

            while ($runEnd < $count && $operations[$runEnd]['type'] === 'equal') {
                $runEnd++;
            }

            $runLength = $runEnd - $index;

            if ($runLength <= ($contextSize * 2) + 1) {
                for ($k = $index; $k < $runEnd; $k++) {
                    $collapsed[] = $operations[$k];
                }
            } else {
                for ($k = $index; $k < $index + $contextSize; $k++) {
                    $collapsed[] = $operations[$k];
                }

                $collapsed[] = ['type' => 'skip', 'text' => '… '.($runLength - ($contextSize * 2)).' unchanged words …'];

                for ($k = $runEnd - $contextSize; $k < $runEnd; $k++) {
                    $collapsed[] = $operations[$k];
                }
            }

            $index = $runEnd;
        }

        return $collapsed;
    }

    /** @return array<int, string> */
    private function tokenise(string $text): array
    {
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';

        return $text === '' ? [] : explode(' ', $text);
    }

    private function countWords(string $text): int
    {
        return count($this->tokenise($text));
    }

    /** @return array<string, mixed> */
    private function describe(ManuscriptSnapshot $snapshot): array
    {
        return [
            'id' => $snapshot->id,
            'revision' => $snapshot->revision,
            'label' => $snapshot->label,
            'word_count' => $snapshot->word_count,
            'author' => $snapshot->author?->name,
            'created_at' => $snapshot->created_at?->toIso8601String(),
            'sha256' => $snapshot->sha256,
        ];
    }
}