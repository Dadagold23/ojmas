<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentCategory;
use App\Models\Submission;
use App\Models\SubmissionStatusHistory;
use App\Models\User;
use App\Notifications\EditorAssignedForPublicationNotification;
use App\Notifications\JournalOfficePaymentNotification;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\ReceiptGeneratedNotification;
use App\Services\Payments\ReceiptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class AdminController extends Controller
{
    public function __construct(
        protected ReceiptService $receiptService,
    ) {}

    public function dashboard()
    {
        $stats = [
            'users'        => User::count(),
            'submissions'  => Submission::count(),
            'paid_payments'=> Payment::where('status', 'paid')->count(),
            'revenue'      => Payment::where('status', 'paid')->sum('amount'),
        ];

        $recentPayments = Payment::with(['user', 'submission', 'receipt'])
            ->latest()->take(10)->get();

        // Accepted submissions awaiting editor assignment for publication
        $pendingAssignment = Submission::with('author')
            ->where('status', 'accepted')
            ->whereNull('assigned_editor_id')
            ->latest()->get();

        $editors = User::role(['Editor', 'Managing Editor'])->orderBy('name')->get();

        return view('admin.dashboard', compact('stats', 'recentPayments', 'pendingAssignment', 'editors'));
    }

    public function assignEditor(Request $request, Submission $submission)
    {
        $request->validate([
            'editor_id' => ['required', 'exists:users,id'],
        ]);

        abort_unless($submission->status === 'accepted', 422, 'Only accepted submissions can be assigned.');
        abort_unless($submission->hasPaidPublicationFee(), 422, 'Publication fee must be paid before an editor can be assigned for publication.');

        $editor = User::findOrFail($request->editor_id);
        abort_unless($editor->hasAnyRole(['Editor', 'Managing Editor']), 422, 'Selected user is not an editor.');

        $oldStatus = $submission->status;

        $submission->update([
            'assigned_editor_id' => $editor->id,
            'status'             => 'screening',
        ]);

        SubmissionStatusHistory::create([
            'submission_id' => $submission->id,
            'changed_by'    => auth()->id(),
            'old_status'    => $oldStatus,
            'new_status'    => 'screening',
            'note'          => 'Assigned to ' . $editor->name . ' for publication by admin.',
        ]);

        try {
            $editor->notify(new EditorAssignedForPublicationNotification($submission));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Editor assignment notification failed: ' . $e->getMessage());
        }

        return back()->with('success', $editor->name . ' has been assigned to publish this manuscript.');
    }

    public function recordManualPayment(Request $request, Submission $submission)
    {
        $validated = $request->validate([
            'purpose' => ['required', 'in:vetting_fee,publication_fee'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'paid_at' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($submission->hasPaidPurpose($validated['purpose'])) {
            return back()->with('warning', 'That payment has already been recorded for this submission.');
        }

        $slug = $validated['purpose'] === 'publication_fee' ? 'publication-fee' : 'vetting-fee';
        $category = PaymentCategory::where('slug', $slug)->first();
        $amount = $validated['amount'] ?? $category?->default_amount ?? ($validated['purpose'] === 'publication_fee' ? 50000 : 15000);
        $currency = strtoupper($validated['currency'] ?? $category?->currency ?? 'NGN');
        $manualReference = trim((string) ($validated['reference'] ?? ''));
        $manualReference = $manualReference !== ''
            ? $manualReference
            : 'MANUAL-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(6));

        $payment = Payment::where('submission_id', $submission->id)
            ->where('purpose', $validated['purpose'])
            ->whereIn('status', ['pending', 'failed'])
            ->latest()
            ->first();

        $referenceOwner = Payment::where('reference', $manualReference)->first();
        if ($referenceOwner && (!$payment || $referenceOwner->id !== $payment->id)) {
            return back()->withErrors([
                'reference' => 'That payment reference is already in use. Use a unique reference or leave it blank to generate one automatically.',
            ])->withInput();
        }

        if (!$payment) {
            $invoice = Invoice::create([
                'user_id' => $submission->author_id,
                'submission_id' => $submission->id,
                'payment_category_id' => $category?->id,
                'invoice_no' => 'INV-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'paid',
                'due_date' => now(),
            ]);

            $payment = new Payment([
                'user_id' => $submission->author_id,
                'submission_id' => $submission->id,
                'invoice_id' => $invoice->id,
                'payment_category_id' => $category?->id,
                'purpose' => $validated['purpose'],
            ]);
        } elseif ($payment->invoice) {
            $payment->invoice->update([
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'paid',
            ]);
        }

        $payment->fill([
            'reference' => $manualReference,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'paid',
            'gateway' => 'manual_approval',
            'gateway_transaction_id' => $manualReference,
            'gateway_response' => [
                'source' => 'admin_manual_approval',
                'approved_by' => auth()->id(),
                'approved_by_name' => auth()->user()?->name,
                'note' => $validated['note'] ?? null,
            ],
            'paid_at' => $validated['paid_at'] ?? now(),
        ])->save();

        if ($payment->invoice) {
            $payment->invoice->update(['status' => 'paid']);
        }

        $warningMessage = null;

        try {
            $receipt = $this->receiptService->generate($payment);
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Manual payment receipt generation failed: ' . $e->getMessage(), [
                'payment_id' => $payment->id,
                'submission_id' => $submission->id,
            ]);

            $receipt = null;
            $warningMessage = 'Manual payment was recorded, but the receipt could not be generated immediately.';
        }

        try {
            $payment->user->notify(new PaymentReceivedNotification($payment));

            if ($receipt) {
                $payment->user->notify(new ReceiptGeneratedNotification($payment, $receipt));
            }

            User::role(['Super Admin', 'Managing Editor'])->get()
                ->each(fn (User $admin) => $admin->notify(new PaymentReceivedNotification($payment)));

            Notification::route('mail', config('mail.from.address'))
                ->notify(new JournalOfficePaymentNotification($payment));
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Manual payment notification failed: ' . $e->getMessage(), [
                'payment_id' => $payment->id,
                'submission_id' => $submission->id,
            ]);
        }

        $message = ucfirst(str_replace('_', ' ', $validated['purpose'])) . ' recorded manually for ' . $submission->submission_no . '.';

        return back()
            ->with('success', $message)
            ->with('warning', $warningMessage);
    }

    public function recoverMissingManuscript(Request $request, Submission $submission)
    {
        $validated = $request->validate([
            'manuscript' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:1048576'],
            'note' => ['nullable', 'string', 'max:1000'],
            'replace_existing' => ['nullable', 'boolean'],
        ]);

        $existingManuscript = $submission->files()
            ->where('file_type', 'manuscript')
            ->latest()
            ->first();

        if ($existingManuscript && !$request->boolean('replace_existing')) {
            return back()->withErrors([
                'manuscript' => 'A manuscript file is already attached. Tick replace if you want to overwrite the current manuscript record.',
            ]);
        }

        $file = $request->file('manuscript');

        if (!$file || !$file->isValid()) {
            return back()->withErrors([
                'manuscript' => 'The recovery upload did not reach the server cleanly. Please choose the file again and retry.',
            ]);
        }

        $disk = Storage::disk('private');
        $disk->makeDirectory('submissions/manuscript');
        $storedPath = $file->store('submissions/manuscript', 'private');

        if (!$storedPath) {
            return back()->withErrors([
                'manuscript' => 'The recovered manuscript could not be stored on the private disk.',
            ]);
        }

        try {
            DB::transaction(function () use ($submission, $existingManuscript, $validated, $storedPath, $file, $disk) {
                if ($existingManuscript) {
                    $oldPath = $existingManuscript->file_path;
                    $existingManuscript->update([
                        'original_name' => $file->getClientOriginalName(),
                        'file_path' => $storedPath,
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                    ]);

                    if ($oldPath && $oldPath !== $storedPath && $disk->exists($oldPath)) {
                        $disk->delete($oldPath);
                    }
                } else {
                    $submission->files()->create([
                        'file_type' => 'manuscript',
                        'original_name' => $file->getClientOriginalName(),
                        'file_path' => $storedPath,
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                    ]);
                }

                SubmissionStatusHistory::create([
                    'submission_id' => $submission->id,
                    'changed_by' => auth()->id(),
                    'old_status' => $submission->status,
                    'new_status' => $submission->status,
                    'note' => trim((string) ($validated['note'] ?? '')) !== ''
                        ? 'Admin recovered manuscript upload. Note: ' . trim((string) $validated['note'])
                        : 'Admin recovered manuscript upload after the original file went missing.',
                ]);
            });
        } catch (Throwable $e) {
            if ($disk->exists($storedPath)) {
                $disk->delete($storedPath);
            }

            \Illuminate\Support\Facades\Log::error('Manual manuscript recovery failed: ' . $e->getMessage(), [
                'submission_id' => $submission->id,
                'admin_id' => auth()->id(),
            ]);

            return back()->withErrors([
                'manuscript' => 'The manuscript was uploaded for recovery, but the submission record could not be updated.',
            ]);
        }

        $action = $existingManuscript ? 'replaced' : 'restored';

        return back()->with('success', 'Manuscript file ' . $action . ' successfully for ' . $submission->submission_no . '.');
    }
}
