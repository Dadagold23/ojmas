<?php

namespace App\Http\Controllers\Author;

use App\Http\Controllers\Controller;
use App\Models\Receipt;

class ReceiptController extends Controller
{
    public function pdf(Receipt $receipt)
    {
        $receipt->load(['payment.user', 'payment.submission']);

        return view('author.receipts.pdf', compact('receipt'));
    }
}
