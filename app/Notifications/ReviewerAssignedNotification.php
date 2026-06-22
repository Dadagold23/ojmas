<?php

namespace App\Notifications;

use App\Models\ReviewerAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReviewerAssignedNotification extends Notification implements ShouldQueue
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
            ->subject('New Manuscript Review Assignment')
            ->greeting('Dear ' . $notifiable->name . ',')
            ->line('A manuscript has been assigned to you for review.')
            ->line('Title: ' . $this->assignment->submission->title)
            ->action('Open Assignment', route('reviewer.assignments.show', $this->assignment))
            ->line('Thank you for serving as a reviewer.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'          => 'reviewer_assigned',
            'title'         => 'New Review Assignment',
            'message'       => 'You have been assigned to review: ' . $this->assignment->submission->title,
            'action_url'    => route('reviewer.assignments.show', $this->assignment),
        ];
    }
}
