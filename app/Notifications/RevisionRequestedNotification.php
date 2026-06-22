<?php

namespace App\Notifications;

use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RevisionRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Submission $submission, public string $message) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Revision Requested')
            ->greeting('Dear ' . $notifiable->name . ',')
            ->line('A revision has been requested for your manuscript.')
            ->line('Title: ' . $this->submission->title)
            ->line('Editor Message: ' . $this->message)
            ->action('View Submission', route('author.submissions.show', $this->submission));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'          => 'revision_requested',
            'title'         => 'Revision Requested',
            'message'       => 'A revision has been requested for: ' . $this->submission->title,
            'action_url'    => route('author.submissions.show', $this->submission),
        ];
    }
}
