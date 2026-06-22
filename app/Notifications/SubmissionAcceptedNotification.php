<?php

namespace App\Notifications;

use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubmissionAcceptedNotification extends Notification implements ShouldQueue
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
            ->subject('Congratulations! Your Submission Has Been Accepted')
            ->greeting('Dear ' . $notifiable->name . ',')
            ->line('We are pleased to inform you that your manuscript has been accepted for publication.')
            ->line('**Title:** ' . $this->submission->title)
            ->line('**Submission No:** ' . $this->submission->submission_no)
            ->line('To proceed with publication, please complete the publication fee payment.')
            ->action('Pay Publication Fee', route('author.submissions.show', $this->submission))
            ->line('Thank you for submitting to OJMAS.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'           => 'submission_accepted',
            'title'          => 'Submission Accepted',
            'message'        => 'Your manuscript "' . $this->submission->title . '" has been accepted.',
            'submission_id'  => $this->submission->id,
            'submission_no'  => $this->submission->submission_no,
            'action_url'     => route('author.submissions.show', $this->submission),
        ];
    }
}
