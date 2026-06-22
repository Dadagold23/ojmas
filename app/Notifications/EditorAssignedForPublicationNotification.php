<?php

namespace App\Notifications;

use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EditorAssignedForPublicationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Submission $submission) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[OJMAS] Assigned for Publication — ' . $this->submission->submission_no)
            ->greeting('Dear ' . $notifiable->name . ',')
            ->line('You have been assigned to handle the publication of the following accepted manuscript.')
            ->line('**Submission No:** ' . $this->submission->submission_no)
            ->line('**Title:** ' . $this->submission->title)
            ->line('**Author:** ' . ($this->submission->author->name ?? 'N/A'))
            ->line('Please log in to the editorial dashboard to proceed with publication.')
            ->action('View Submission', route('editor.submissions.show', $this->submission))
            ->line('Thank you.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'       => 'editor_assigned_publication',
            'title'      => 'Assigned for Publication',
            'message'    => 'You have been assigned to publish: ' . $this->submission->title,
            'action_url' => route('editor.submissions.show', $this->submission),
        ];
    }
}
