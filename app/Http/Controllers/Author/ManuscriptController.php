<?php

namespace App\Http\Controllers\Author;

use App\Http\Controllers\Controller;
use App\Models\ManuscriptValidation;
use App\Models\ManuscriptVersion;
use App\Models\Submission;
use App\Services\Documents\Exceptions\DocumentProcessingException;
use App\Services\Documents\ManuscriptAccessLogger;
use App\Services\Documents\ManuscriptProcessingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The author-facing manuscript formatting workspace.
 *
 * Everything here is server-side authorised through ManuscriptPolicy. Hiding a
 * button in the interface is never the control.
 */
class ManuscriptController extends Controller
{
    public function __construct(
        private readonly ManuscriptProcessingService $pipeline,
        private readonly ManuscriptAccessLogger $accessLog,
    ) {
    }

    /**
     * The author-facing manuscript formatting workspace.
     */
    public function show(Request $request, Submission $submission)
    {
        $this->authorize('viewReport', $submission);

        $submission->load([
            'author',
            'files',
            'manuscriptVersions.document',
        ]);

        $validation = $this->latestValidation($submission);
        $versions = $submission->manuscriptVersions()->with('document')->get();

        $this->accessLog->view($request->user(), $submission, $validation?->document);

        return view('author.manuscripts.show', [
            'submission' => $submission,
            'validation' => $validation,
            'versions' => $versions,
            'formattedPath' => $this->formattedPathFor($validation),
        ]);
    }

    /**
     * Runs the APA 7 formatting engine against the author's current version.
     */
    public function format(Request $request, Submission $submission)
    {
        $this->authorize('runFormatting', $submission);

        $version = $this->resolveCurrentVersion($submission);

        if ($version === null) {
            return back()->with('error', 'Upload a Word .docx manuscript before requesting automatic formatting.');
        }

        try {
            $result = $this->pipeline->process($version, $request->input('profile'));
        } catch (DocumentProcessingException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()->with(
                'error',
                'The manuscript could not be formatted automatically. The journal office has been notified and will assist.',
            );
        }

        $summary = $result->result->toArray()['summary'];

        return redirect()
            ->route('author.manuscripts.show', $submission)
            ->with('success', 'Formatting complete: '.data_get($summary, 'auto_fixed', 0).' issue(s) corrected automatically.')
            ->with('warning', data_get($summary, 'author_action_required', 0).' issue(s) still need your attention.');
    }

    /**
     * Streams the formatted DOCX. The original upload is never replaced by this.
     */
    public function download(Request $request, Submission $submission): SymfonyResponse
    {
        $this->authorize('downloadFormatted', $submission);

        $validation = $this->latestValidation($submission);
        $path = $this->formattedPathFor($validation);

        if ($path === null) {
            return back()->with('error', 'No formatted manuscript is available yet.');
        }

        $disk = Storage::disk(config('manuscripts.disk', 'private'));

        if (! $disk->exists($path)) {
            return back()->with('error', 'The formatted manuscript could not be found. Please request formatting again.');
        }

        $this->accessLog->download($request->user(), $submission, $validation?->document);

        $filename = sprintf(
            'OJMAS-%s-formatted.docx',
            $submission->submission_no,
        );

        return $disk->download($path, $filename);
    }

    /**
     * A read-only HTML preview of what the engine found and corrected.
     */
    public function report(Request $request, Submission $submission, ManuscriptValidation $validation)
    {
        $this->authorize('viewReport', $submission);

        abort_unless((int) $validation->submission_id === (int) $submission->id, 404);

        $validation->load('issues');

        $this->accessLog->view($request->user(), $submission, $validation->document);

        return view('author.manuscripts.report', [
            'submission' => $submission,
            'validation' => $validation,
            'errors' => $validation->issues->where('severity', 'error')->values(),
            'warnings' => $validation->issues->where('severity', 'warning')->values(),
            'changes' => $validation->issues->where('was_auto_fixed', true)->values(),
        ]);
    }

    /**
     * The author's approval of the formatted manuscript.
     *
     * Approval is the gate described in the module brief: the formatted document
     * must be inspected by the author before it proceeds into the editorial
     * workflow. It is deliberately not something an editor can do on the
     * author's behalf.
     */
    public function approve(Request $request, Submission $submission)
    {
        $this->authorize('approve', $submission);

        $validation = ManuscriptValidation::where('submission_id', $submission->id)
            ->latest()
            ->first();

        if ($validation === null || ! $validation->isCompleted()) {
            return back()->with('error', 'Run the formatting check before approving the manuscript.');
        }

        if ($validation->isApproved()) {
            return back()->with('warning', 'This manuscript has already been approved.');
        }

        $validation->update([
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        \App\Models\SubmissionStatusHistory::create([
            'submission_id' => $submission->id,
            'changed_by' => $request->user()->id,
            'old_status' => $submission->status,
            'new_status' => $submission->status,
            'note' => 'Author approved the APA 7 formatted manuscript after review.',
        ]);

        return back()->with('success', 'Thank you. Your formatted manuscript has been approved and will now enter editorial processing.');
    }

    private function resolveCurrentVersion(Submission $submission): ?ManuscriptVersion
    {
        if ($submission->current_version_id !== null) {
            $version = ManuscriptVersion::where('submission_id', $submission->id)
                ->where('id', $submission->current_version_id)
                ->where('format', 'original')
                ->first();

            if ($version !== null) {
                return $version;
            }
        }

        return ManuscriptVersion::where('submission_id', $submission->id)
            ->where('format', 'original')
            ->orderByDesc('version_number')
            ->first();
    }

    private function latestValidation(Submission $submission): ?ManuscriptValidation
    {
        return ManuscriptValidation::where('submission_id', $submission->id)
            ->with(['issues' => fn ($query) => $query->orderBy('severity')->orderBy('id')])
            ->latest()
            ->first();
    }

    /**
     * The storage path of the artefact produced by the given validation run.
     */
    private function formattedPathFor(?ManuscriptValidation $validation): ?string
    {
        if ($validation === null || ! $validation->isCompleted()) {
            return null;
        }

        $report = $validation->report_json;
        $path = is_array($report) ? data_get($report, 'formatted_document.storage_path') : null;

        return is_string($path) && $path !== '' ? $path : null;
    }
}