<?php

namespace App\Notifications;

use App\Models\CorrespondenceMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CorrespondenceMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public CorrespondenceMessage $message) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->message->subject)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('You received a new journal correspondence message.')
            ->line($this->message->message);
    }
}
