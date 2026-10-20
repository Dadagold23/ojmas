<?php

namespace App\Http\Controllers\Author;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubmissionRequest;
use App\Models\ArticleFile;
use App\Models\Submission;
use App\Models\SubmissionStatusHistory;
use App\Notifications\NewSubmissionNotification;
use App\Services\Payments\SubmissionPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SubmissionController extends Controller
{
    public function dashboard()
    {
        $submissions = Submission::where('author_id', auth()->id())
            ->latest()
            ->take(5)
            ->get();

        $stats = [
            'total' => Submission::where('author_id', auth()->id())->count(),
            'submitted' => Submission::where('author_id', auth()->id())->where('status', 'submitted')->count(),
            'under_review' => Submission::where('author_id', auth()->id())->where('status', 'under_review')->count(),
            'published' => Submission::where('author_id', auth()->id())->where('status', 'published')->count(),
        ];

        return view('author.dashboard', compact('submissions', 'stats'));
    }

    public function index()
    {
        $submissions = Submission::with(['files', 'payments'])
            ->where('author_id', auth()->id())
            ->latest()
            ->paginate(10);

        return view('author.submissions.index', compact('submissions'));
    }

    public function create()
    {
        return view('author.submissions.create');
    }

    public function store(StoreSubmissionRequest $request, SubmissionPaymentService $submissionPaymentService)
    {
        $validated = $request->validated();
        $submission = null;
        $storedPaths = [];

        try {
            $this->ensurePrivateSubmissionStorageIsReady();

            $submission = DB::transaction(function () use ($request, $validated, &$storedPaths) {
                $submission = Submission::create([
                    'submission_no' => 'SUB-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
                    'author_id' => auth()->id(),
                    'title' => $validated['title'],
                    'slug' => Str::slug($validated['title']) . '-' . strtolower(Str::random(5)),
                    'abstract' => $validated['abstract'],
                    'keywords' => $validated['keywords'] ?? null,
                    'subject_area' => $validated['subject_area'] ?? null,
                    'status' => 'pending_payment',
                    'is_corresponding_author' => true,
                ]);

                if (!empty($validated['co_authors'])) {
                    foreach ($validated['co_authors'] as $index => $author) {
                        if (!empty($author['full_name'])) {
                            $submission->coAuthors()->create([
                                'full_name' => $author['full_name'],
                                'email' => $author['email'] ?? null,
                                'institution' => $author['institution'] ?? null,
                                'country' => $author['country'] ?? null,
                                'is_corresponding' => false,
                                'author_order' => $index + 2,
                            ]);
                        }
                    }
                }

                foreach (['manuscript', 'cover_letter', 'supplementary'] as $type) {
                    if (!$request->hasFile($type)) {
                        continue;
                    }

                    $file = $request->file($type);

                    if (!$file || !$file->isValid()) {
                        throw new \RuntimeException("The {$type} upload is invalid before storage.");
                    }

                    Storage::disk('private')->makeDirectory('submissions');
                    Storage::disk('private')->makeDirectory('submissions/' . $type);
                    $path = $file->store('submissions/' . $type, 'private');

                    if (!$path) {
                        throw new \RuntimeException("The {$type} file could not be stored on the private disk.");
                    }

                    $storedPaths[] = $path;

                    $submission->files()->create([
                        'file_type' => $type,
                        'original_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                    ]);
                }

                SubmissionStatusHistory::create([
                    'submission_id' => $submission->id,
                    'changed_by' => auth()->id(),
                    'old_status' => null,
                    'new_status' => 'pending_payment',
                    'note' => 'Initial manuscript submission saved and awaiting vetting fee payment.',
                ]);

                return $submission;
            });
        } catch (\Throwable $e) {
            foreach ($storedPaths as $storedPath) {
                Storage::disk('private')->delete($storedPath);
            }

            $errorId = strtoupper(Str::random(8));

            Log::error('Submission creation failed', [
                'error_id' => $errorId,
                'user_id' => auth()->id(),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $isUploadOrStorageFailure = str_contains($e->getMessage(), 'upload is invalid')
                || str_contains($e->getMessage(), 'stored on the private disk')
                || str_contains($e->getMessage(), 'submissions directory')
                || str_contains($e->getMessage(), 'not writable');

            return back()
                ->withInput()
                ->withErrors([
                    'manuscript' => $isUploadOrStorageFailure
                        ? 'The manuscript failed to upload. Please try again. If the file is within 1 GB and the error persists, the server storage path may not be writable yet.'
                        : "The manuscript upload was received, but the submission could not be saved completely. Reference: {$errorId}.",
                ]);
        }

        // Defer the office notification until after the response so
        // submission creation and payment redirect stay lightweight.
        dispatch(function () use ($submission) {
            $submission->loadMissing(['author', 'files']);

            try {
                Notification::route('mail', config('mail.from.address'))
                    ->notify(new NewSubmissionNotification($submission));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Submission notification failed: ' . $e->getMessage(), [
                    'submission_id' => $submission->id,
                ]);
            }
        })->afterResponse();

        $paymentResult = $submissionPaymentService->initializeForSubmission($submission, auth()->user(), 'vetting_fee');

        if ($paymentResult['status'] ?? false) {
            return redirect($paymentResult['authorization_url']);
        }

        return redirect()->to(route('author.submissions.show', $submission))
            ->with('pay', true)
            ->with('warning', 'Manuscript submitted successfully, but automatic payment redirect could not start. Please complete the vetting fee payment below.')
            ->with('error', $paymentResult['message'] ?? 'We could not start your payment right now. Please try again.');
    }

    public function show(Submission $submission)
    {
        $user = auth()->user();
        $canViewSubmission = $submission->canBeViewedBy($user);

        abort_unless($canViewSubmission, 403, 'This action is unauthorized.');

        $submission->load(['coAuthors', 'files', 'histories', 'payments']);
        $canManageSubmission = $submission->isOwnedBy($user);
        $canPayForSubmission = $submission->canInitiatePaymentBy($user);
        $canApprovePayments = $user->hasAnyRole(['Admin', 'Managing Editor', 'Super Admin']);

        $vettingCategory   = \App\Models\PaymentCategory::where('slug', 'vetting-fee')->first();
        $vettingFee        = $vettingCategory?->default_amount ?? 15000;
        $vettingCurrency   = $vettingCategory?->currency ?? 'NGN';
        $hasPaidVetting    = $submission->payments->where('purpose', 'vetting_fee')->where('status', 'paid')->isNotEmpty();

        $pubCategory       = \App\Models\PaymentCategory::where('slug', 'publication-fee')->first();
        $publicationFee    = $pubCategory?->default_amount ?? 50000;
        $pubCurrency       = $pubCategory?->currency ?? 'NGN';
        $hasPaidPublication = $submission->payments->where('purpose', 'publication_fee')->where('status', 'paid')->isNotEmpty();

        return view('author.submissions.show', compact(
            'submission', 'vettingFee', 'vettingCurrency', 'hasPaidVetting',
            'publicationFee', 'pubCurrency', 'hasPaidPublication', 'canManageSubmission', 'canPayForSubmission', 'canApprovePayments'
        ));
    }

    public function destroy(Submission $submission)
    {
        abort_unless($submission->author_id === auth()->id(), 403);
        abort_unless($submission->hasPaidVettingFee() || $submission->status !== 'submitted', 423, 'This submission is locked until the vetting fee is paid.');

        foreach ($submission->files as $file) {
            Storage::disk('private')->delete($file->file_path);
        }

        $submission->delete();

        return redirect()->route('author.submissions.index')
            ->with('success', 'Submission deleted successfully.');
    }

    public function uploadFile(Request $request, Submission $submission)
    {
        abort_unless($submission->author_id === auth()->id(), 403);
        abort_unless(in_array($submission->status, ['submitted', 'revision_requested']), 403);
        abort_unless($submission->hasPaidVettingFee(), 423, 'This submission is locked until the vetting fee is paid.');

        $request->validate([
            'file_type' => ['required', 'in:manuscript,cover_letter,supplementary'],
            'file'      => ['required', 'file', 'mimes:pdf,doc,docx,zip', 'max:1048576'],
        ]);

        $file = $request->file('file');
        $path = $file->store('submissions/' . $request->file_type, 'private');

        $submission->files()->create([
            'file_type'     => $request->file_type,
            'original_name' => $file->getClientOriginalName(),
            'file_path'     => $path,
            'mime_type'     => $file->getMimeType(),
            'file_size'     => $file->getSize(),
        ]);

        return back()->with('success', 'File uploaded successfully.');
    }

    public function deleteFile(Submission $submission, ArticleFile $file)
    {
        abort_unless($submission->author_id === auth()->id(), 403);
        abort_unless($file->submission_id === $submission->id, 403);
        abort_unless(in_array($submission->status, ['submitted', 'revision_requested']), 403);
        abort_unless($submission->hasPaidVettingFee(), 423, 'This submission is locked until the vetting fee is paid.');

        Storage::disk('private')->delete($file->file_path);
        $file->delete();

        return back()->with('success', 'File removed successfully.');
    }

    private function ensurePrivateSubmissionStorageIsReady(): void
    {
        $disk = Storage::disk('private');
        $disk->makeDirectory('submissions');
        $rootPath = $disk->path('submissions');

        if (!is_dir($rootPath)) {
            throw new \RuntimeException('The private submissions directory could not be created.');
        }

        if (!is_writable($rootPath)) {
            throw new \RuntimeException('The private submissions directory is not writable.');
        }
    }
}
