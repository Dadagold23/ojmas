<?php

namespace App\Services\Publishing;

use App\Models\ActivityLog;
use App\Models\Article;
use App\Models\ArticlePublication;
use App\Models\Submission;
use Illuminate\Support\Str;

class PublicationService
{
    public function publish(Submission $submission, int $issueId, ?string $pdfPath = null, ?string $note = null): Article
    {
        $article = Article::updateOrCreate(
            ['submission_id' => $submission->id],
            [
                'issue_id' => $issueId,
                'title' => $submission->title,
                'slug' => Str::slug($submission->title) . '-' . strtolower(Str::random(5)),
                'abstract' => $submission->abstract,
                'keywords' => $submission->keywords,
                'published_on' => now(),
                'status' => 'published',
            ]
        );

        ArticlePublication::updateOrCreate(
            ['article_id' => $article->id],
            [
                'published_by' => auth()->id(),
                'published_on' => now(),
                'published_pdf_path' => $pdfPath,
                'note' => $note,
            ]
        );

        $submission->update(['status' => 'published']);

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'published_article',
            'module' => 'publication',
            'loggable_type' => Article::class,
            'loggable_id' => $article->id,
            'meta' => [
                'submission_id' => $submission->id,
                'issue_id' => $issueId,
            ],
        ]);

        return $article;
    }
}
