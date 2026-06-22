<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\User;
use App\Notifications\JournalOfficePaymentNotification;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\ReceiptGeneratedNotification;
use App\Services\Payments\ReceiptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaystackWebhookController extends Controller
{
    public function __construct(protected ReceiptService $receiptService) {}

    public function handle(Request $request)
    {
        // Verify signature using webhook secret
        $secret    = config('services.paystack.webhook_secret');
        $signature = $request->header('X-Paystack-Signature');

        if (blank($secret)) {
            Log::error('Paystack webhook secret is not configured.');
            return response('Unauthorized', 401);
        }

        $computed  = hash_hmac('sha512', $request->getContent(), $secret);

        if (!hash_equals($computed, (string) $signature)) {
            Log::warning('Paystack webhook signature mismatch', ['ip' => $request->ip()]);
            return response('Unauthorized', 401);
        }

        $payload = $request->json()->all();
        $event   = $payload['event'] ?? null;
        $data    = $payload['data'] ?? [];

        if ($event !== 'charge.success') {
            return response('OK', 200);
        }

        $reference = $data['reference'] ?? null;
        $payment   = Payment::where('reference', $reference)->first();

        if (!$payment || $payment->status === 'paid') {
            return response('OK', 200);
        }

        $payment->update([
            'status'                 => 'paid',
            'gateway_transaction_id' => $data['id'] ?? null,
            'gateway_response'       => $data,
            'paid_at'                => now(),
        ]);

        if ($payment->invoice) {
            $payment->invoice->update(['status' => 'paid']);
        }

        try {
            $receipt = $this->receiptService->generate($payment);
            $payment->user->notify(new PaymentReceivedNotification($payment));
            $payment->user->notify(new ReceiptGeneratedNotification($payment, $receipt));

            User::role(['Super Admin', 'Managing Editor'])->get()
                ->each(fn ($admin) => $admin->notify(new PaymentReceivedNotification($payment)));

            // Notify journal office email directly
            \Illuminate\Support\Facades\Notification::route('mail', config('mail.from.address'))
                ->notify(new JournalOfficePaymentNotification($payment));
        } catch (\Throwable $e) {
            Log::error('Paystack webhook notification failed: ' . $e->getMessage());
        }

        return response('OK', 200);
    }
}
