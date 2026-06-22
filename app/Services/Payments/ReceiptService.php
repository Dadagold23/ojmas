<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Receipt;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReceiptService
{
    public function generate(Payment $payment): Receipt
    {
        $receipt = $payment->receipt ?: Receipt::create([
            'payment_id' => $payment->id,
            'receipt_no' => 'RCT-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
        ]);

        $pdf = Pdf::loadView('author.receipts.pdf', [
            'payment' => $payment->load(['user', 'submission', 'receipt']),
            'receipt' => $receipt,
        ]);

        $path = 'receipts/' . $receipt->receipt_no . '.pdf';
        Storage::disk('public')->put($path, $pdf->output());

        $receipt->update(['pdf_path' => $path]);

        return $receipt->fresh();
    }
}
