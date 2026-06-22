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

        Issue::create($validated);

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
                    'author_id' => $submission->author_id,
                    'title' => $submission->title,
                    'slug' => $submission->slug,
                    'abstract' => $submission->abstract,
                    'keywords' => $submission->keywords,
                    'subject_area' => $submission->subject_area,
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
                        'from_status' => $oldStatus,
                        'to_status' => 'published',
                        'remarks' => "Submission attached to issue '{$issue->title}' and published.",
                    ]);
                }
            }
        }

        return redirect()
            ->route('editor.issues.show', $issue->id)
            ->with('success', 'Selected articles attached to issue successfully.');
    }
}
