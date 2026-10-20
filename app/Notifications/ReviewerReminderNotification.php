<?php

namespace App\Notifications;

use App\Models\ReviewerInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A reminder to a reviewer whose response is still outstanding.
 *
 * Sent through the existing notification architecture (queued, mail +
 * database), in step with the recorded reminder trail in the reviewer
 * tracking screen.
 */
class ReviewerReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ReviewerInvitation $invitation) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Reminder: Review Invitation Awaiting Your Response')
            ->greeting('Dear '.$notifiable->name.',')
            ->line('A review invitation is still awaiting your response.')
            ->line('Reminder '.(int) $this->invitation->reminder_count.' sent.');

        if ($this->invitation->due_at !== null) {
            $message->line('A response is requested by '.$this->invitation->due_at->format('d M Y').'.');
        }

        return $message
            ->line('If you are unable to review, declining promptly helps the editor re-assign the manuscript.')
            ->line('Thank you for serving the journal.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'reviewer_reminder',
            'title' => 'Review invitation reminder',
            'message' => 'Your response to a review invitation is still awaited.',
            'reminder_count' => (int) $this->invitation->reminder_count,
            'due_at' => $this->invitation->due_at?->toDateString(),
        ];
    }
}
