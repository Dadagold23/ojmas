<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Review;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $paidPaymentsQuery = Payment::query()
            ->where(function ($query) {
                $query->where('status', 'paid')
                    ->orWhereNotNull('paid_at');
            });

        $stats = [
            'submissions' => Submission::where('status', '!=', 'pending_payment')->count(),
            'published' => Submission::where('status', 'published')->count(),
            'pending_reviews' => Submission::whereIn('status', ['under_review', 'resubmitted'])->count(),
            'payments' => (clone $paidPaymentsQuery)->sum('amount'),
            'paid_payments' => (clone $paidPaymentsQuery)->count(),
        ];

        $recentSubmissions = Submission::with(['author', 'files'])
            ->latest()
            ->take(10)
            ->get();

        $recentPayments = Payment::with(['user', 'submission', 'receipt'])
            ->where(function ($query) {
                $query->where('status', 'paid')
                    ->orWhereNotNull('paid_at');
            })
            ->latest('paid_at')
            ->latest()
            ->take(10)
            ->get();

        $pendingAssignment = Submission::with('author')
            ->where('status', 'accepted')
            ->whereNull('assigned_editor_id')
            ->latest()
            ->get();

        $editors = User::role(['Editor', 'Managing Editor'])->orderBy('name')->get();

        return view('admin.dashboard', compact(
            'stats',
            'recentSubmissions',
            'recentPayments',
            'pendingAssignment',
            'editors'
        ));
    }

    public function uploadDiagnostics()
    {
        $privateDisk = Storage::disk('private');
        $submissionsPath = $privateDisk->path('submissions');
        $manuscriptPath = $privateDisk->path('submissions/manuscript');
        $coverLetterPath = $privateDisk->path('submissions/cover_letter');
        $supplementaryPath = $privateDisk->path('submissions/supplementary');
        $tempDir = ini_get('upload_tmp_dir') ?: sys_get_temp_dir();

        $privateDisk->makeDirectory('submissions');
        $privateDisk->makeDirectory('submissions/manuscript');
        $privateDisk->makeDirectory('submissions/cover_letter');
        $privateDisk->makeDirectory('submissions/supplementary');

        $writeProbe = null;
        $writeProbeError = null;

        try {
            $writeProbe = 'submissions/manuscript/.healthcheck-' . now()->timestamp . '.txt';
            $privateDisk->put($writeProbe, 'upload diagnostics');
            $privateDisk->delete($writeProbe);
        } catch (\Throwable $e) {
            $writeProbeError = $e->getMessage();
        }

        $checks = [
            [
                'label' => 'upload_max_filesize',
                'value' => ini_get('upload_max_filesize'),
                'ok' => true,
            ],
            [
                'label' => 'post_max_size',
                'value' => ini_get('post_max_size'),
                'ok' => true,
            ],
            [
                'label' => 'max_execution_time',
                'value' => ini_get('max_execution_time'),
                'ok' => true,
            ],
            [
                'label' => 'max_input_time',
                'value' => ini_get('max_input_time'),
                'ok' => true,
            ],
            [
                'label' => 'memory_limit',
                'value' => ini_get('memory_limit'),
                'ok' => true,
            ],
            [
                'label' => 'upload_tmp_dir',
                'value' => $tempDir,
                'ok' => is_dir($tempDir) && is_writable($tempDir),
            ],
            [
                'label' => 'private submissions root',
                'value' => $submissionsPath,
                'ok' => is_dir($submissionsPath) && is_writable($submissionsPath),
            ],
            [
                'label' => 'manuscript directory',
                'value' => $manuscriptPath,
                'ok' => is_dir($manuscriptPath) && is_writable($manuscriptPath),
            ],
            [
                'label' => 'cover letter directory',
                'value' => $coverLetterPath,
                'ok' => is_dir($coverLetterPath) && is_writable($coverLetterPath),
            ],
            [
                'label' => 'supplementary directory',
                'value' => $supplementaryPath,
                'ok' => is_dir($supplementaryPath) && is_writable($supplementaryPath),
            ],
            [
                'label' => 'private disk write probe',
                'value' => $writeProbeError ? 'Failed: ' . $writeProbeError : 'Passed',
                'ok' => $writeProbeError === null,
            ],
        ];

        return view('admin.upload-diagnostics', compact('checks'));
    }

    public function runUploadDiagnostics(Request $request)
    {
        $result = [
            'content_length' => (string) $request->server('CONTENT_LENGTH', 'unknown'),
            'post_max_size' => ini_get('post_max_size'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'has_file' => $request->hasFile('probe_file') ? 'yes' : 'no',
            'files_count' => is_array($_FILES ?? null) ? count($_FILES) : 0,
            'php_file_error' => 'no file received',
            'php_file_error_label' => 'No upload detected by PHP',
            'client_name' => null,
            'client_size' => null,
            'client_mime' => null,
        ];

        if (isset($_FILES['probe_file']) && is_array($_FILES['probe_file'])) {
            $errorCode = (int) ($_FILES['probe_file']['error'] ?? UPLOAD_ERR_NO_FILE);
            $result['php_file_error'] = $errorCode;
            $result['php_file_error_label'] = $this->phpUploadErrorLabel($errorCode);
            $result['client_name'] = $_FILES['probe_file']['name'] ?? null;
            $result['client_size'] = $_FILES['probe_file']['size'] ?? null;
            $result['client_mime'] = $_FILES['probe_file']['type'] ?? null;
        }

        if ($request->hasFile('probe_file')) {
            $file = $request->file('probe_file');
            $result['php_file_error'] = $file->getError();
            $result['php_file_error_label'] = $this->phpUploadErrorLabel($file->getError());
            $result['client_name'] = $file->getClientOriginalName();
            $result['client_size'] = $file->getSize();
            $result['client_mime'] = $file->getMimeType();
            $result['is_valid'] = $file->isValid() ? 'yes' : 'no';
        }

        return redirect()
            ->route('admin.upload-diagnostics')
            ->with('upload_probe_result', $result);
    }

    private function phpUploadErrorLabel(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_OK => 'UPLOAD_ERR_OK',
            UPLOAD_ERR_INI_SIZE => 'UPLOAD_ERR_INI_SIZE',
            UPLOAD_ERR_FORM_SIZE => 'UPLOAD_ERR_FORM_SIZE',
            UPLOAD_ERR_PARTIAL => 'UPLOAD_ERR_PARTIAL',
            UPLOAD_ERR_NO_FILE => 'UPLOAD_ERR_NO_FILE',
            UPLOAD_ERR_NO_TMP_DIR => 'UPLOAD_ERR_NO_TMP_DIR',
            UPLOAD_ERR_CANT_WRITE => 'UPLOAD_ERR_CANT_WRITE',
            UPLOAD_ERR_EXTENSION => 'UPLOAD_ERR_EXTENSION',
            default => 'UNKNOWN',
        };
    }
}
