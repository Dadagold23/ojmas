<?php

namespace App\Services\Documents;

use App\Models\ManuscriptAccessLog;
use App\Models\ManuscriptDocument;
use App\Models\ManuscriptVersion;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Records every access to a manuscript document.
 *
 * The application's `audit_logs` table is never written to by any code, which
 * the Module 0 audit flagged. Sensitive document access is therefore logged
 * here so that "who opened this manuscript, and when" has an answer.
 */
class ManuscriptAccessLogger
{
    public function log(
        ?User $user,
        Submission $submission,
        string $action,
        ?ManuscriptDocument $document = null,
        ?Request $request = null,
    ): ManuscriptAccessLog {
        $request ??= request();

        return ManuscriptAccessLog::create([
            'user_id' => $user?->id,
            'submission_id' => $submission->id,
            'manuscript_document_id' => $document?->id,
            'action' => $action,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255) ?: null,
        ]);
    }

    public function view(?User $user, Submission $submission, ?ManuscriptDocument $document = null, ?Request $request = null): ManuscriptAccessLog
    {
        return $this->log($user, $submission, ManuscriptAccessLog::ACTION_VIEW, $document, $request);
    }

    public function download(?User $user, Submission $submission, ?ManuscriptDocument $document = null, ?Request $request = null): ManuscriptAccessLog
    {
        return $this->log($user, $submission, ManuscriptAccessLog::ACTION_DOWNLOAD, $document, $request);
    }

    public function export(?User $user, Submission $submission, ?ManuscriptDocument $document = null, ?Request $request = null): ManuscriptAccessLog
    {
        return $this->log($user, $submission, ManuscriptAccessLog::ACTION_EXPORT, $document, $request);
    }

    /** @return array<int, ManuscriptAccessLog> */
    public function recentFor(Submission $submission, int $limit = 50)
    {
        return ManuscriptAccessLog::where('submission_id', $submission->id)
            ->with('user')
            ->latest()
            ->limit($limit)
            ->get()
            ->all();
    }

    public function forVersion(ManuscriptVersion $version): bool
    {
        return ManuscriptAccessLog::where('submission_id', $version->submission_id)->exists();
    }
}