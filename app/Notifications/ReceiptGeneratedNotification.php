<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Models\Receipt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReceiptGeneratedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Payment $payment, public Receipt $receipt) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Receipt Generated')
            ->greeting('Dear ' . $notifiable->name . ',')
            ->line('Your payment receipt has been generated successfully.')
            ->line('Receipt No: ' . $this->receipt->receipt_no)
            ->action('Download Receipt', route('author.receipts.pdf', $this->receipt));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'       => 'receipt_generated',
            'title'      => 'Receipt Generated',
            'message'    => 'Your receipt ' . $this->receipt->receipt_no . ' is ready.',
            'action_url' => route('author.receipts.pdf', $this->receipt),
        ];
    }
}
