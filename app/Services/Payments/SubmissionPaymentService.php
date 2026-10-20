<?php

namespace App\Services\Payments;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentCategory;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Str;

class SubmissionPaymentService
{
    public function __construct(
        protected PaystackService $gateway,
    ) {}

    public function initializeForSubmission(Submission $submission, User $user, string $purpose): array
    {
        $purpose = in_array($purpose, ['vetting_fee', 'publication_fee'], true)
            ? $purpose
            : 'vetting_fee';

        if ($submission->hasPaidPurpose($purpose)) {
            return [
                'status' => false,
                'reason' => 'already_paid',
                'message' => 'This payment has already been completed for the submission.',
            ];
        }

        [$category, $amount, $currency] = $this->resolvePricing($purpose);
        $payment = $this->findOrCreateUnpaidPayment($submission, $user, $purpose, $category?->id, $amount, $currency);

        $response = $this->gateway->initialize([
            'email' => $this->resolvePaystackEmail($user->email),
            'amount' => $payment->amount,
            'reference' => $payment->reference,
            'callback_url' => route('author.payments.callback'),
            'currency' => $payment->currency,
            'metadata' => [
                'payment_id' => $payment->id,
                'submission_id' => $submission->id,
                'purpose' => $purpose,
            ],
        ]);

        if (!($response['status'] ?? false)) {
            return [
                'status' => false,
                'reason' => 'gateway_failed',
                'payment' => $payment,
                'message' => $response['message'] ?? 'We could not start your payment right now. Please try again in a moment.',
                'response' => $response,
            ];
        }

        return [
            'status' => true,
            'payment' => $payment,
            'authorization_url' => $response['data']['authorization_url'],
            'response' => $response,
        ];
    }

    private function resolvePricing(string $purpose): array
    {
        $slug = $purpose === 'publication_fee' ? 'publication-fee' : 'vetting-fee';
        $category = PaymentCategory::where('slug', $slug)->first();
        $amount = $category?->default_amount ?? ($purpose === 'publication_fee' ? 50000 : 15000);
        $currency = $category?->currency ?? 'NGN';

        return [$category, $amount, $currency];
    }

    private function findOrCreateUnpaidPayment(
        Submission $submission,
        User $user,
        string $purpose,
        ?int $paymentCategoryId,
        float|int $amount,
        string $currency
    ): Payment {
        $payment = Payment::where('submission_id', $submission->id)
            ->where('purpose', $purpose)
            ->whereIn('status', ['pending', 'failed'])
            ->latest()
            ->first();

        if ($payment) {
            // Paystack treats a reference as single-use: re-initialising with an
            // already-used reference fails with "Duplicate Transaction Reference".
            // A retry therefore always gets a fresh reference.
            $payment->update([
                'status' => 'pending',
                'amount' => $amount,
                'currency' => $currency,
                'gateway' => 'paystack',
                'reference' => 'PAY-' . strtoupper(Str::random(10)),
            ]);

            if ($payment->invoice) {
                $payment->invoice->update([
                    'amount' => $amount,
                    'currency' => $currency,
                    'status' => 'pending',
                    'due_date' => now()->addDays(7),
                ]);
            }

            return $payment->fresh();
        }

        $invoice = Invoice::create([
            'user_id' => $user->id,
            'submission_id' => $submission->id,
            'payment_category_id' => $paymentCategoryId,
            'invoice_no' => 'INV-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'pending',
            'due_date' => now()->addDays(7),
        ]);

        return Payment::create([
            'user_id' => $user->id,
            'submission_id' => $submission->id,
            'invoice_id' => $invoice->id,
            'payment_category_id' => $paymentCategoryId,
            'reference' => 'PAY-' . strtoupper(Str::random(10)),
            'purpose' => $purpose,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'pending',
            'gateway' => 'paystack',
        ]);
    }

    /**
     * Paystack rejects non-routable TLDs (.test, .local, .example etc).
     */
    private function resolvePaystackEmail(string $email): string
    {
        $domain = substr(strrchr($email, '@'), 1);
        $fakeTlds = ['test', 'local', 'example', 'invalid', 'localhost'];
        $tld = strtolower(substr(strrchr($domain, '.'), 1));

        if (in_array($tld, $fakeTlds, true)) {
            return config('mail.from.address', $email);
        }

        return $email;
    }
}
