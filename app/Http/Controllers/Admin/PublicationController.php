<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticlePublication;
use App\Models\Issue;
use App\Models\Submission;
use App\Notifications\ArticlePublishedNotification;
use App\Services\Publishing\PublicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class PublicationController extends Controller
{
    public function index()
    {
        $publications = ArticlePublication::with(['article.submission.author', 'article.issue', 'publisher'])
            ->latest()
            ->paginate(15);

        return view('admin.publications.index', compact('publications'));
    }

    public function create()
    {
        $issues   = Issue::with('volume')->latest()->get();
        $authors  = \App\Models\User::orderBy('name')->get();

        return view('admin.publications.create', compact('issues', 'authors'));
    }

    public function store(Request $request, PublicationService $service)
    {
        $validated = $request->validate([
            'title'         => ['required', 'string', 'max:500'],
            'author_id'     => ['required', 'exists:users,id'],
            'abstract'      => ['required', 'string'],
            'keywords'      => ['nullable', 'string', 'max:500'],
            'subject_area'  => ['nullable', 'string', 'max:255'],
            'issue_id'      => ['required', 'exists:issues,id'],
            'published_pdf' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            'note'          => ['nullable', 'string', 'max:1000'],
        ]);

        $submission = Submission::create([
            'submission_no'           => 'PUB-' . strtoupper(\Illuminate\Support\Str::random(8)),
            'author_id'               => $validated['author_id'],
            'title'                   => $validated['title'],
            'slug'                    => \Illuminate\Support\Str::slug($validated['title']) . '-' . strtolower(\Illuminate\Support\Str::random(5)),
            'abstract'                => $validated['abstract'],
            'keywords'                => $validated['keywords'] ?? null,
            'subject_area'            => $validated['subject_area'] ?? null,
            'status'                  => 'published',
            'is_corresponding_author' => true,
        ]);

        $pdfPath = $request->file('published_pdf')
            ->store('published/articles', 'public');

        $article = $service->publish(
            $submission,
            (int) $validated['issue_id'],
            $pdfPath,
            $validated['note'] ?? null
        );

        // Notify the author if requested
        if ($request->boolean('notify_author')) {
            $author = \App\Models\User::find($validated['author_id']);
            if ($author) {
                $author->notify(new ArticlePublishedNotification($article));
            }
        }

        return redirect()->route('admin.publications.index')
            ->with('success', 'Article published successfully' . ($request->boolean('notify_author') ? ' and author notified.' : '.'));
    }

    public function show(ArticlePublication $publication)
    {
        $publication->load(['article.submission.author', 'article.submission.files', 'article.issue.volume', 'publisher']);
        return view('admin.publications.show', compact('publication'));
    }

    public function edit(ArticlePublication $publication)
    {
        $publication->load('article.submission');
        $issues = Issue::with('volume')->latest()->get();
        return view('admin.publications.edit', compact('publication', 'issues'));
    }

    public function update(Request $request, ArticlePublication $publication)
    {
        abort_unless($publication->article?->submission?->hasPaidPublicationFee(), 422, 'Publication fee must remain paid for this publication record.');

        $validated = $request->validate([
            'issue_id'      => ['required', 'exists:issues,id'],
            'published_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'note'          => ['nullable', 'string', 'max:1000'],
        ]);

        if ($request->hasFile('published_pdf')) {
            // Delete old file
            if ($publication->published_pdf_path) {
                Storage::disk('public')->delete($publication->published_pdf_path);
            }
            $validated['published_pdf_path'] = $request->file('published_pdf')
                ->store('published/articles', 'public');
        }

        $publication->update([
            'note'               => $validated['note'] ?? $publication->note,
            'published_pdf_path' => $validated['published_pdf_path'] ?? $publication->published_pdf_path,
        ]);

        $publication->article->update(['issue_id' => $validated['issue_id']]);

        return redirect()->route('admin.publications.index')
            ->with('success', 'Publication updated successfully.');
    }

    public function destroy(ArticlePublication $publication)
    {
        if ($publication->published_pdf_path) {
            Storage::disk('public')->delete($publication->published_pdf_path);
        }

        $article = $publication->article;
        $publication->delete();

        if ($article) {
            $article->submission?->update(['status' => 'accepted']);
            $article->delete();
        }

        return redirect()->route('admin.publications.index')
            ->with('success', 'Publication removed successfully.');
    }

    public function download(ArticlePublication $publication)
    {
        abort_unless($publication->published_pdf_path &&
            Storage::disk('public')->exists($publication->published_pdf_path), 404);

        return Storage::disk('public')->download(
            $publication->published_pdf_path,
            $publication->article->title . '.pdf'
        );
    }
}
