<?php

namespace App\Notifications;

use App\Models\Article;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ArticlePublishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Article $article) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Article Published Successfully')
            ->greeting('Dear ' . ($notifiable->name ?? 'Author') . ',')
            ->line('Your article has been published successfully.')
            ->line('Title: ' . $this->article->title)
            ->action('View Published Article', route('article.show', $this->article->slug));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'article_published',
            'title' => 'Article Published',
            'message' => 'Your article "' . $this->article->title . '" has been published successfully.',
            'article_id' => $this->article->id,
            'submission_id' => $this->article->submission_id,
            'action_url' => route('article.show', $this->article->slug),
        ];
    }
}
