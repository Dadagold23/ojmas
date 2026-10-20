<?php

namespace App\Notifications;

use App\Models\ManuscriptProofApproval;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The author answered the proof request. The editorial side is told which
 * way the answer went, and why if corrections are wanted.
 */
class ProofApprovalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ManuscriptProofApproval $approval) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approved = $this->approval->status === ManuscriptProofApproval::STATUS_APPROVED;

        return (new MailMessage)
            ->subject($approved ? 'Proofs Approved by the Author' : 'Corrections Requested on Proofs')
            ->greeting('Dear Editor,')
            ->line($approved
                ? 'The author has approved the proofs of the manuscript below.'
                : 'The author has requested corrections on the proofs of the manuscript below.')
            ->line('Manuscript: '.$this->approval->submission->title)
            ->when(! empty($this->approval->notes), fn ($m) => $m->line('Author note: '.(string) $this->approval->notes))
            ->line($approved
                ? 'The article is now one step closer to production.'
                : 'Please attend to the corrections and re-issue the proofs.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'proof_approval',
            'title' => $this->approval->status === ManuscriptProofApproval::STATUS_APPROVED
                ? 'Proofs approved'
                : 'Corrections requested on proofs',
            'message' => $this->approval->submission->title,
            'status' => $this->approval->status,
        ];
    }
}
