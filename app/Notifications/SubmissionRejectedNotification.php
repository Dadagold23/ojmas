<?php

namespace App\Notifications;

use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubmissionRejectedNotification extends Notification implements ShouldQueue
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
            ->subject('Submission Decision: Rejected')
            ->greeting('Dear ' . $notifiable->name . ',')
            ->line('We regret to inform you that your submission was not accepted.')
            ->line('Title: ' . $this->submission->title)
            ->line('Editor Message: ' . $this->message)
            ->action('View Submission', route('author.submissions.show', $this->submission));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'          => 'submission_rejected',
            'title'         => 'Submission Not Accepted',
            'message'       => 'Your manuscript "' . $this->submission->title . '" was not accepted. ' . $this->message,
            'submission_id' => $this->submission->id,
            'submission_no' => $this->submission->submission_no,
            'action_url'    => route('author.submissions.show', $this->submission),
        ];
    }
}
