<?php

namespace App\Notifications;

use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewSubmissionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Submission $submission) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $author    = $this->submission->author;
        $fileCount = $this->submission->files->count();

        return (new MailMessage)
            ->subject('[OJMAS] New Manuscript Submission — ' . $this->submission->submission_no)
            ->greeting('New Submission Received')
            ->line('A new manuscript has been submitted to the OJMAS and requires your attention.')
            ->line('---')
            ->line('**Submission No:** ' . $this->submission->submission_no)
            ->line('**Title:** ' . $this->submission->title)
            ->line('**Author:** ' . ($author->name ?? 'N/A') . ' <' . ($author->email ?? 'N/A') . '>')
            ->line('**Institution:** ' . ($author->institution ?? 'Not provided'))
            ->line('**Subject Area:** ' . ($this->submission->subject_area ?? 'Not specified'))
            ->line('**Keywords:** ' . ($this->submission->keywords ?? 'None'))
            ->line('**Files Uploaded:** ' . $fileCount)
            ->line('**Submitted At:** ' . $this->submission->created_at->format('d M Y, H:i'))
            ->line('---')
            ->line('**Abstract:**')
            ->line($this->submission->abstract)
            ->action('View Submission in Dashboard', route('editor.submissions.show', $this->submission))
            ->line('Please log in to the editorial dashboard to review and process this submission.');
    }
}
