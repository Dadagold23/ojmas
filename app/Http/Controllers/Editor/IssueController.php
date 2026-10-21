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
            'cover_image' => ['nullable', 'image', 'max:2048'],
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

        if ($request->hasFile('cover_image')) {
            $issue->cover_image_path = $request->file('cover_image')->store('issue-covers', 'public');
        }

        $issue->save();

        return redirect()
            ->route('editor.issues.index')
            ->with('success', 'Issue created successfully.');
    }

    /**
     * Records the journal's approved editorial sequence for an issue's
     * articles. Positions arrive as `order[<articleId>] = <position>`; every
     * id must belong to this very issue (no cross-issue tampering), and
     * articles the form omitted keep their stored order.
     */
    public function reorder(Request $request, Issue $issue): RedirectResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['required', 'integer', 'min:1'],
        ]);

        $issueArticleIds = $issue->articles()->pluck('id')->all();
        $unknown = array_diff(array_keys($validated['order']), $issueArticleIds);

        abort_if($unknown !== [], 422, 'One or more articles do not belong to this issue.');

        foreach ($validated['order'] as $articleId => $position) {
            Article::where('id', $articleId)->where('issue_id', $issue->id)
                ->update(['issue_order' => (int) $position]);
        }

        return redirect()
            ->route('editor.issues.show', $issue)
            ->with('success', 'Article order saved for this issue.');
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

    /**
     * Sets or replaces the issue's cover image (public disk, image only,
     * 2 MB cap). The old file is removed only after the new one validates, so
     * a failed upload never leaves the issue with no cover and no file.
     */
    public function uploadCover(Request $request, Issue $issue): RedirectResponse
    {
        $validated = $request->validate([
            'cover_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $path = $request->file('cover_image')->store('issue-covers', 'public');

        abort_if($path === false, 422, 'The cover image could not be stored.');

        $previous = $issue->cover_image_path;

        $issue->update(['cover_image_path' => $path]);

        if ($previous) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($previous);
        }

        return redirect()
            ->route('editor.issues.show', $issue)
            ->with('success', 'Issue cover updated successfully.');
    }

    public function deletePdf(Issue $issue): RedirectResponse
    {
        if ($issue->pdf_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($issue->pdf_path);
            $issue->update([
                'pdf_path' => null,
            ]);
        }        return redirect()
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
