<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Payment $payment) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Payment Received')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('A journal payment has been received successfully.')
            ->line('Reference: ' . $this->payment->reference)
            ->line('Amount: ' . number_format($this->payment->amount, 2) . ' ' . $this->payment->currency)
            ->line('Purpose: ' . str_replace('_', ' ', $this->payment->purpose));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'      => 'payment_received',
            'title'     => 'Payment Received',
            'message'   => ucwords(str_replace('_', ' ', $this->payment->purpose)) . ' of ' . $this->payment->currency . ' ' . number_format($this->payment->amount, 2) . ' received.',
            'reference' => $this->payment->reference,
        ];
    }
}
