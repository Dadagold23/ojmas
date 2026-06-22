<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class JournalOfficePaymentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Payment $payment) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $author     = $this->payment->user;
        $submission = $this->payment->submission;
        $purposeLabel = $this->payment->purpose === 'publication_fee'
            ? 'Publication Fee'
            : 'Vetting Fee';

        $subject = $this->payment->purpose === 'publication_fee'
            ? '[OJMAS] Publication Fee Received — ' . ($submission->submission_no ?? $this->payment->reference)
            : '[OJMAS] Vetting Fee Received — ' . ($submission->submission_no ?? $this->payment->reference);

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Payment Confirmed — ' . $purposeLabel)
            ->line('A payment has been successfully verified on the OJMAS platform.')
            ->line('---')
            ->line('**Payment Reference:** ' . $this->payment->reference)
            ->line('**Purpose:** ' . $purposeLabel)
            ->line('**Amount:** ' . $this->payment->currency . ' ' . number_format($this->payment->amount, 2))
            ->line('**Gateway:** ' . ucfirst($this->payment->gateway ?? 'Paystack'))
            ->line('**Paid At:** ' . ($this->payment->paid_at?->format('d M Y, H:i') ?? now()->format('d M Y, H:i')))
            ->line('---')
            ->line('**Author:** ' . ($author->name ?? 'N/A') . ' <' . ($author->email ?? 'N/A') . '>')
            ->line('**Submission No:** ' . ($submission->submission_no ?? 'N/A'))
            ->line('**Manuscript Title:** ' . ($submission->title ?? 'N/A'))
            ->when(
                $this->payment->purpose === 'publication_fee',
                fn($mail) => $mail->line('**Next Step:** The author has paid the publication fee. Please proceed to publish the article.')
                                  ->action('Publish Article', route('admin.publications.create')),
                fn($mail) => $mail->line('**Next Step:** The author has paid the vetting fee. Please assign a reviewer.')
                                  ->action('View Submission', route('editor.submissions.show', $submission))
            )
            ->line('This is an automated notification from the OJMAS system.');
    }
}
