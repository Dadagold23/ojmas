<?php

namespace App\Services\Exports;

use App\Models\Payment;

class PaymentExportService
{
    public function toCsv(): string
    {
        $rows = Payment::with(['user', 'submission'])->latest()->get();

        $output = fopen('php://temp', 'r+');
        fputcsv($output, ['Reference', 'User', 'Submission', 'Purpose', 'Amount', 'Status', 'Paid At']);

        foreach ($rows as $row) {
            fputcsv($output, [
                $row->reference,
                $row->user->name ?? '',
                $row->submission->title ?? '',
                $row->purpose,
                $row->amount,
                $row->status,
                $row->paid_at,
            ]);
        }

        rewind($output);
        return stream_get_contents($output);
    }
}
