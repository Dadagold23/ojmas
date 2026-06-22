<?php

namespace App\Notifications;

use App\Models\ReviewerAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReviewCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ReviewerAssignment $assignment) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Review Completed')
            ->line('A reviewer has completed a manuscript review.')
            ->line('Title: ' . $this->assignment->submission->title)
            ->action('View Submission', route('editor.submissions.show', $this->assignment->submission));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'       => 'review_completed',
            'title'      => 'Review Completed',
            'message'    => 'A review has been submitted for: ' . $this->assignment->submission->title,
            'action_url' => route('editor.submissions.show', $this->assignment->submission),
        ];
    }
}
