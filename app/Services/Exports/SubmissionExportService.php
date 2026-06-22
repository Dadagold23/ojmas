<?php

namespace App\Services\Exports;

use App\Models\Submission;

class SubmissionExportService
{
    public function toCsv(): string
    {
        $rows = Submission::with('author')->latest()->get();

        $output = fopen('php://temp', 'r+');
        fputcsv($output, ['Submission No', 'Title', 'Author', 'Status', 'Date']);

        foreach ($rows as $row) {
            fputcsv($output, [
                $row->submission_no,
                $row->title,
                $row->author->name ?? '',
                $row->status,
                $row->created_at,
            ]);
        }

        rewind($output);
        return stream_get_contents($output);
    }
}
