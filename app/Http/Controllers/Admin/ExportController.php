<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Exports\PaymentExportService;
use App\Services\Exports\SubmissionExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function submissions(SubmissionExportService $service): StreamedResponse
    {
        $csv = $service->toCsv();

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, 'submissions.csv');
    }

    public function payments(PaymentExportService $service): StreamedResponse
    {
        $csv = $service->toCsv();

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, 'payments.csv');
    }
}
