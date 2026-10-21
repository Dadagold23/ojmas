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

        // The picker lists manuscripts already in the editorial pipeline that
        // have not been published yet. Publication happens through the
        // PublicationService readiness gate — a form can no longer invent a
        // standalone PUB-* record that bypasses the journal's approvals.
        $candidates = Submission::with('author')
            ->where('status', '!=', 'published')
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();

        return view('admin.publications.create', compact('issues', 'candidates'));
    }

    public function store(Request $request, PublicationService $service)
    {
        $validated = $request->validate([
            'submission_id' => ['required', 'exists:submissions,id'],
            'issue_id'      => ['required', 'exists:issues,id'],
            'published_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'note'          => ['nullable', 'string', 'max:1000'],
        ]);

        $submission = Submission::with('author')->findOrFail($validated['submission_id']);

        // One publication per manuscript: republishing must not silently
        // overwrite the existing article and publication record.
        abort_if(Article::where('submission_id', $submission->id)->exists(), 422, 'This manuscript has already been published.');

        $pdfPath = $request->hasFile('published_pdf')
            ? $request->file('published_pdf')->store('published/articles', 'public')
            : null;

        // Publishes through the Module 4 readiness gate; a manuscript that is
        // missing approvals throws a ValidationException whose messages are
        // the constructive list of what is still outstanding.
        $article = $service->publish(
            $submission,
            (int) $validated['issue_id'],
            $pdfPath,
            $validated['note'] ?? null
        );

        if ($request->boolean('notify_author')) {
            $submission->author?->notify(new ArticlePublishedNotification($article));
        }

        return redirect()->route('admin.publications.index')
            ->with('success', "'{$submission->submission_no}' published"
                .($request->boolean('notify_author') ? ' and the author notified.' : '.'));
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
