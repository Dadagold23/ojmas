<?php

namespace App\Services\Publishing;

use App\Models\ActivityLog;
use App\Models\Article;
use App\Models\ArticlePublication;
use App\Models\Submission;
use Illuminate\Support\Str;

class PublicationService
{
    public function __construct(private PublicationReadinessService $readiness) {}

    public function publish(Submission $submission, int $issueId, ?string $pdfPath = null, ?string $note = null, array $attributes = []): Article
    {
        // Module 4: publication may not follow merely from an editor finishing
        // editing. The readiness gate refuses, constructively, until every
        // required editorial approval is on record.
        $assessment = $this->readiness->assess($submission);

        if (! $assessment['ready']) {
            $unmet = collect($assessment['requirements'])
                ->filter(fn ($requirement) => ! $requirement['met'])
                ->map(fn ($requirement) => $requirement['name'].' — '.$requirement['detail']);

            \Illuminate\Support\Facades\DB::table('manuscript_workflow_events')->insert([
                'submission_id' => $submission->id,
                'actor_id' => auth()->id(),
                'event' => 'publication_refused',
                'reason' => $unmet->implode(' | '),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            throw \Illuminate\Validation\ValidationException::withMessages([
                'readiness' => $unmet->all(),
            ]);
        }

        $article = Article::updateOrCreate(
            ['submission_id' => $submission->id],
            array_merge([
                'issue_id' => $issueId,
                'title' => $submission->title,
                'slug' => Str::slug($submission->title) . '-' . strtolower(Str::random(5)),
                'abstract' => $submission->abstract,
                'keywords' => $submission->keywords,
                'published_on' => now(),
                'status' => 'published',
            ], array_intersect_key($attributes, array_flip(['licence', 'copyright_note'])))
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
