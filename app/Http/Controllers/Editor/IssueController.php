<?php

namespace App\Http\Controllers\Editor;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Issue;
use App\Models\Submission;
use App\Models\SubmissionStatusHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IssueController extends Controller
{
    public function index(): View
    {
        $issues = Issue::with('volume')->latest()->paginate(15);

        return view('auth.editor.issues.index', compact('issues'));
    }

    public function create(): View
    {
        $volumes = \App\Models\Volume::orderByDesc('year')->orderBy('title')->get();

        return view('auth.editor.issues.create', compact('volumes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'volume_id' => ['required', 'exists:volumes,id'],
            'issue_no' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:draft,published'],
            'published_on' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
        ]);

        $slug = \Illuminate\Support\Str::slug($validated['title']) . '-' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(4));

        $issue = new Issue();
        $issue->volume_id = $validated['volume_id'];
        $issue->issue_no = (int) $validated['issue_no'];
        $issue->title = $validated['title'];
        $issue->status = $validated['status'];
        $issue->published_on = $validated['published_on'] ?? ($validated['status'] === 'published' ? now() : null);
        $issue->description = $validated['description'];
        $issue->slug = $slug;
        $issue->save();

        return redirect()
            ->route('editor.issues.index')
            ->with('success', 'Issue created successfully.');
    }

    public function show(Issue $issue): View
    {
        $issue->load(['volume', 'articles.author']);

        $acceptedSubmissions = Submission::with('author')
            ->whereIn('status', ['accepted', 'published'])
            ->whereHas('payments', fn ($query) => $query->where('purpose', 'publication_fee')->where('status', 'paid'))
            ->whereDoesntHave('article', function ($query) use ($issue) {
                $query->where('issue_id', '!=', $issue->id);
            })
            ->latest()
            ->get();

        return view('auth.editor.issues.show', compact('issue', 'acceptedSubmissions'));
    }

    public function attachArticles(Request $request, Issue $issue): RedirectResponse
    {
        $validated = $request->validate([
            'submission_ids' => ['required', 'array', 'min:1'],
            'submission_ids.*' => ['integer', 'exists:submissions,id'],
            'published_on' => ['nullable', 'date'],
        ]);

        $publishedAt = $validated['published_on'] ?? now()->toDateString();

        $submissions = Submission::with('author')
            ->whereIn('id', $validated['submission_ids'])
            ->get();

        foreach ($submissions as $submission) {
            abort_unless($submission->hasPaidPublicationFee(), 422, "Publication fee must be paid before submission {$submission->submission_no} can be published.");

            $oldStatus = $submission->status;

            Article::updateOrCreate(
                ['submission_id' => $submission->id],
                [
                    'issue_id' => $issue->id,
                    'title' => $submission->title,
                    'slug' => $submission->slug ?: \Illuminate\Support\Str::slug($submission->title) . '-' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(5)),
                    'abstract' => $submission->abstract,
                    'keywords' => $submission->keywords,
                    'published_on' => $publishedAt,
                    'status' => 'published',
                ]
            );

            if ($submission->status !== 'published') {
                $submission->update([
                    'status' => 'published',
                ]);

                if (class_exists(SubmissionStatusHistory::class)) {
                    SubmissionStatusHistory::create([
                        'submission_id' => $submission->id,
                        'changed_by' => auth()->id(),
                        'old_status' => $oldStatus,
                        'new_status' => 'published',
                        'note' => "Submission attached to issue '{$issue->title}' and published.",
                    ]);
                }
            }
        }

        return redirect()
            ->route('editor.issues.show', $issue->id)
            ->with('success', 'Selected articles attached to issue successfully.');
    }

    public function uploadPdf(Request $request, Issue $issue): RedirectResponse
    {
        $validated = $request->validate([
            'compiled_pdf' => ['required', 'file', 'mimes:pdf', 'max:40960'], // 40 MB max
        ]);

        if ($issue->pdf_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($issue->pdf_path);
        }

        $path = $request->file('compiled_pdf')->store('compiled_journals', 'public');

        $issue->update([
            'pdf_path' => $path,
        ]);

        return redirect()
            ->route('editor.issues.show', $issue->id)
            ->with('success', 'Compiled journal PDF uploaded successfully.');
    }

    public function deletePdf(Issue $issue): RedirectResponse
    {
        if ($issue->pdf_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($issue->pdf_path);
            $issue->update([
                'pdf_path' => null,
            ]);
        }

        return redirect()
            ->route('editor.issues.show', $issue->id)
            ->with('success', 'Compiled journal PDF deleted successfully.');
    }

    public function toggleStatus(Issue $issue): RedirectResponse
    {
        $newStatus = $issue->status === 'published' ? 'draft' : 'published';
        $issue->update(['status' => $newStatus]);

        return redirect()
            ->back()
            ->with('success', "Issue status updated to '{$newStatus}' successfully.");
    }
}
