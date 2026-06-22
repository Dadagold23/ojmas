<?php

namespace App\Http\Controllers\Author;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\JournalOfficePaymentNotification;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\ReceiptGeneratedNotification;
use App\Services\Payments\ReceiptService;
use App\Services\Payments\SubmissionPaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(
        protected SubmissionPaymentService $submissionPaymentService,
        protected ReceiptService $receiptService,
    ) {}

    public function index()
    {
        $payments = Payment::with(['submission', 'receipt'])
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate(10);

        $submissions = Submission::where('author_id', auth()->id())->latest()->get();

        return view('author.payments.index', compact('payments', 'submissions'));
    }

    public function initiate(Submission $submission)
    {
        $user = auth()->user();
        abort_unless($submission->canInitiatePaymentBy($user), 403, 'This action is unauthorized.');

        $purpose  = in_array(request('purpose'), ['vetting_fee', 'publication_fee'])
            ? request('purpose')
            : 'vetting_fee';

        $paymentResult = $this->submissionPaymentService->initializeForSubmission($submission, $user, $purpose);

        if ($paymentResult['status'] ?? false) {
            return redirect($paymentResult['authorization_url']);
        }

        $flashType = ($paymentResult['reason'] ?? null) === 'already_paid' ? 'warning' : 'error';

        return $this->redirectToSubmission(
            $submission->id,
            $paymentResult['message'] ?? 'We could not start your payment right now. Please try again in a moment.',
            $flashType,
        );
    }

    public function callback(Request $request)
    {
        $reference = (string) $request->get('reference');
        $payment = Payment::where('reference', $reference)->firstOrFail();

        if ($payment->status === 'paid') {
            return redirect()->route('author.dashboard')
                ->with('success', 'Payment already verified successfully.');
        }

        $response = app(\App\Services\Payments\PaystackService::class)->verify($reference);
        $data = $response['data'] ?? [];

        if (($response['status'] ?? false) && ($data['status'] ?? null) === 'success') {
            DB::transaction(function () use ($payment, $data, $response) {
                $payment->update([
                    'status' => 'paid',
                    'gateway_transaction_id' => $data['id'] ?? null,
                    'gateway_response' => $response,
                    'paid_at' => now(),
                ]);

                if ($payment->invoice) {
                    $payment->invoice->update(['status' => 'paid']);
                }

                if ($payment->purpose === 'vetting_fee' && $payment->submission) {
                    $submission = $payment->submission;

                    if ($submission->status === 'pending_payment') {
                        $submission->update(['status' => 'submitted']);
                    }

                    $submission->histories()->create([
                        'changed_by' => $payment->user_id,
                        'old_status' => 'pending_payment',
                        'new_status' => 'submitted',
                        'note' => 'Vetting fee confirmed. Submission is now ready for editorial processing.',
                    ]);
                }
            });

            $successMessage = 'Payment verified successfully. Your manuscript is now under review.';
            $warningMessage = null;

            try {
                $receipt = $this->receiptService->generate($payment);
            } catch (Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Receipt generation failed: ' . $e->getMessage(), [
                    'payment_id' => $payment->id,
                    'reference' => $payment->reference,
                ]);

                $receipt = null;
                $warningMessage = 'Your payment was recorded, but the receipt could not be generated immediately.';
            }

            try {
                $payment->user->notify(new PaymentReceivedNotification($payment));

                if ($receipt) {
                    $payment->user->notify(new ReceiptGeneratedNotification($payment, $receipt));
                }

                $admins = User::role(['Super Admin', 'Managing Editor'])->get();
                foreach ($admins as $admin) {
                    $admin->notify(new PaymentReceivedNotification($payment));
                }

                \Illuminate\Support\Facades\Notification::route('mail', config('mail.from.address'))
                    ->notify(new JournalOfficePaymentNotification($payment));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Payment notification failed: ' . $e->getMessage());
            }

            $redirect = redirect()->route('author.dashboard')->with('success', $successMessage);

            if ($warningMessage) {
                $redirect->with('warning', $warningMessage);
            }

            return $redirect;
        }

        $payment->update([
            'status' => 'failed',
            'gateway_response' => $response,
        ]);

        return $this->redirectToSubmission(
            $payment->submission_id,
            'We could not verify your payment yet. If you were charged, please wait a moment and try again or contact support with reference ' . $payment->reference . '.',
        );
    }

    public function receipt(Payment $payment)
    {
        abort_unless($payment->user_id === auth()->id(), 403);
        abort_unless($payment->receipt && $payment->receipt->pdf_path, 404);

        return Storage::disk('public')->download(
            $payment->receipt->pdf_path,
            $payment->receipt->receipt_no . '.pdf'
        );
    }

    private function redirectToSubmission(int $submissionId, string $message, string $flashType = 'error'): RedirectResponse
    {
        return redirect()
            ->route('author.submissions.show', $submissionId)
            ->with($flashType, $message);
    }
}
