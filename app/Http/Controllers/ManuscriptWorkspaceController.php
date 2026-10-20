<?php

namespace App\Http\Controllers;

use App\Models\ManuscriptComment;
use App\Models\ManuscriptDocument;
use App\Models\ManuscriptSnapshot;
use App\Models\ManuscriptTrackedChange;
use App\Models\ManuscriptWorkspaceDocument;
use App\Models\Submission;
use App\Services\Documents\ManuscriptAccessLogger;
use App\Services\Documents\Parsing\DocxStructureParser;
use App\Services\Workspace\SaveResult;
use App\Services\Workspace\VersionComparisonService;
use App\Services\Workspace\WorkspaceCommentService;
use App\Services\Workspace\WorkspaceDocxExporter;
use App\Services\Workspace\WorkspaceDocumentService;
use App\Services\Workspace\WorkspaceTrackedChangeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The browser-based manuscript workspace.
 *
 * Every action is authorised server-side through SubmissionPolicy before any
 * work is done. The payload contract is intentionally strict: the client is
 * never trusted to say who is editing, what revision it was editing against, or
 * whether a conflict may be overridden.
 */
class ManuscriptWorkspaceController extends Controller
{
    public function __construct(
        private readonly WorkspaceDocumentService $documents,
        private readonly WorkspaceCommentService $comments,
        private readonly WorkspaceTrackedChangeService $changes,
        private readonly VersionComparisonService $comparison,
        private readonly WorkspaceDocxExporter $exporter,
        private readonly ManuscriptAccessLogger $accessLog,
    ) {
    }

    /** The workspace shell. */
    public function show(Request $request, Submission $submission)
    {
        $this->authorize('openWorkspace', $submission);

        $workspace = $this->resolveWorkspace($request, $submission, true);

        $this->accessLog->view($request->user(), $submission, $workspace?->document);

        return view('workspace.index', [
            'submission' => $submission->load(['author', 'coAuthors', 'files']),
            'workspace' => $workspace,
            'capabilities' => [
                'canEdit' => $request->user()->can('editWorkspace', $submission),
                'canComment' => $request->user()->can('commentOnWorkspace', $submission),
                'canReviewChanges' => $request->user()->can('reviewWorkspaceChanges', $submission),
                'canRestore' => $request->user()->can('restoreWorkspaceVersion', $submission),
                'canCompare' => $request->user()->can('compareWorkspaceVersions', $submission),
                'canExport' => $request->user()->can('exportWorkspace', $submission),
                'canForceSave' => $request->user()->can('forceWorkspaceSave', $submission),
            ],
            'commentVisibilities' => $this->comments->writableVisibilities($request->user()),
            'pendingChangeCount' => $workspace?->pendingChanges()->count() ?? 0,
        ]);
    }

    /** The full editor payload. */
    public function document(Request $request, Submission $submission): JsonResponse
    {
        $this->authorize('openWorkspace', $submission);

        $workspace = $this->resolveWorkspace($request, $submission);

        if ($workspace === null) {
            return response()->json([
                'error' => 'No workspace document exists for this manuscript yet.',
                'code' => 'workspace_not_initialised',
            ], 404);
        }

        return response()->json([
            'revision' => (int) $workspace->revision,
            'content' => $workspace->content(),
            'wordCount' => (int) $workspace->word_count,
            'blockCount' => (int) $workspace->block_count,
            'lastSavedAt' => $workspace->last_saved_at?->toIso8601String(),
            'updatedBy' => $workspace->updater?->name,
            'comments' => $this->comments->threadsFor($workspace, $request->user()),
            'changes' => $this->changes->pendingFor($workspace),
            'snapshots' => array_map(
                fn (ManuscriptSnapshot $snapshot) => $snapshot->summary(),
                $workspace->recentSnapshots(15),
            ),
            'canEdit' => $request->user()->can('editWorkspace', $submission),
        ]);
    }

    /**
     * Autosave / manual save.
     *
     * A stale `baseRevision` is a conflict, never an overwrite.
     */
    public function save(Request $request, Submission $submission): JsonResponse
    {
        $this->authorize('editWorkspace', $submission);

        $validated = $request->validate([
            'content' => ['required', 'array'],
            'baseRevision' => ['required', 'integer', 'min:1'],
            'force' => ['nullable', 'boolean'],
            'snapshotLabel' => ['nullable', 'string', 'max:190'],
        ]);

        $workspace = $this->resolveWorkspace($request, $submission);

        if ($workspace === null) {
            return response()->json(['error' => 'No workspace document exists for this manuscript yet.'], 404);
        }

        $force = (bool) ($validated['force'] ?? false);

        if ($force && ! $request->user()->can('forceWorkspaceSave', $submission)) {
            return response()->json([
                'error' => 'You are not permitted to override another editor\'s changes.',
                'code' => 'force_not_permitted',
            ], 403);
        }

        $result = $this->documents->save(
            $workspace,
            $validated['content'],
            (int) $validated['baseRevision'],
            $request->user(),
            $force,
            $validated['snapshotLabel'] ?? null,
        );

        if ($result->isSaved()) {
            $this->accessLog->log($request->user(), $submission, \App\Models\ManuscriptAccessLog::ACTION_EDIT, $workspace->document);
        }

        return response()->json($result->toArray(), $this->statusFor($result));
    }

    // ───────────────────────── comments ─────────────────────────

    public function storeComment(Request $request, Submission $submission): JsonResponse
    {
        $this->authorize('commentOnWorkspace', $submission);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'anchorFrom' => ['nullable', 'integer', 'min:0'],
            'anchorTo' => ['nullable', 'integer', 'min:0'],
            'quotedText' => ['nullable', 'string', 'max:1000'],
            'visibility' => ['nullable', 'string'],
            'parentId' => ['nullable', 'integer'],
        ]);

        $workspace = $this->resolveWorkspace($request, $submission);

        if ($workspace === null) {
            return response()->json(['error' => 'No workspace document exists for this manuscript yet.'], 404);
        }

        $comment = $this->comments->create(
            $workspace,
            $request->user(),
            $validated['body'],
            $validated['anchorFrom'] ?? null,
            $validated['anchorTo'] ?? null,
            $validated['quotedText'] ?? null,
            $validated['visibility'] ?? $this->comments->defaultVisibilityFor($request->user()),
            $validated['parentId'] ?? null,
        );

        if ($comment === null) {
            return response()->json([
                'error' => 'The comment could not be created with the requested visibility.',
                'code' => 'comment_visibility_denied',
            ], 403);
        }

        return response()->json([
            'comment' => $comment->fresh(['author'])->toWorkspaceArray(),
            'revision' => (int) $workspace->revision,
        ], 201);
    }

    public function updateCommentStatus(
        Request $request,
        Submission $submission,
        ManuscriptComment $comment,
    ): JsonResponse {
        $this->authorize('commentOnWorkspace', $submission);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:resolved,reopened'],
        ]);

        $workspace = $this->resolveWorkspace($request, $submission);

        if ($workspace === null || (int) $comment->manuscript_workspace_document_id !== (int) $workspace->id) {
            return response()->json(['error' => 'That comment does not belong to this manuscript.'], 404);
        }

        $ok = $validated['status'] === 'resolved'
            ? $this->comments->resolve($workspace, $comment, $request->user())
            : $this->comments->reopen($workspace, $comment, $request->user());

        if (! $ok) {
            return response()->json([
                'error' => 'You are not permitted to change the status of that comment.',
                'code' => 'comment_status_denied',
            ], 403);
        }

        return response()->json([
            'comment' => $comment->fresh(['author', 'resolver'])->toWorkspaceArray(),
        ]);
    }

    // ───────────────────────── tracked changes ─────────────────────────

    public function storeChange(Request $request, Submission $submission): JsonResponse
    {
        $this->authorize('editWorkspace', $submission);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:insertion,deletion'],
            'text' => ['required', 'string', 'max:20000'],
            'anchorFrom' => ['nullable', 'integer', 'min:0'],
            'anchorTo' => ['nullable', 'integer', 'min:0'],
            'changeUid' => ['nullable', 'string', 'max:64'],
            'body' => ['nullable', 'array'],
        ]);

        $workspace = $this->resolveWorkspace($request, $submission);

        if ($workspace === null) {
            return response()->json(['error' => 'No workspace document exists for this manuscript yet.'], 404);
        }

        $change = $this->changes->record(
            $workspace,
            $request->user(),
            $validated['type'],
            $validated['text'],
            $validated['anchorFrom'] ?? null,
            $validated['anchorTo'] ?? null,
            $validated['body'] ?? null,
            $validated['changeUid'] ?? null,
        );

        if ($change === null) {
            return response()->json(['error' => 'The change could not be recorded.'], 422);
        }

        return response()->json([
            'change' => $change->fresh(['author'])->toWorkspaceArray(),
        ], 201);
    }

    public function resolveChange(Request $request, Submission $submission): JsonResponse
    {
        $this->authorize('reviewWorkspaceChanges', $submission);

        $validated = $request->validate([
            'changeUid' => ['required', 'string', 'max:64'],
            'decision' => ['required', 'string', 'in:accepted,rejected'],
        ]);

        $workspace = $this->resolveWorkspace($request, $submission);

        if ($workspace === null) {
            return response()->json(['error' => 'No workspace document exists for this manuscript yet.'], 404);
        }

        $change = $this->changes->resolve(
            $workspace,
            $validated['changeUid'],
            $validated['decision'],
            $request->user(),
        );

        if ($change === null) {
            return response()->json([
                'error' => 'That change could not be resolved. It may already have been reviewed.',
                'code' => 'change_not_resolvable',
            ], 404);
        }

        return response()->json([
            'change' => $change->fresh(['author', 'reviewer'])->toWorkspaceArray(),
            'removesText' => $change->resolutionRemovesText(),
        ]);
    }

    // ───────────────────────── versions ─────────────────────────

    public function versions(Request $request, Submission $submission): JsonResponse
    {
        $this->authorize('compareWorkspaceVersions', $submission);

        $workspace = $this->resolveWorkspace($request, $submission);

        if ($workspace === null) {
            return response()->json(['snapshots' => []]);
        }

        return response()->json([
            'revision' => (int) $workspace->revision,
            'snapshots' => $workspace->snapshots()->with('author')->limit(50)->get()
                ->map(fn (ManuscriptSnapshot $snapshot) => $snapshot->summary())
                ->values()
                ->all(),
        ]);
    }

    public function saveVersion(Request $request, Submission $submission): JsonResponse
    {
        $this->authorize('editWorkspace', $submission);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:190'],
        ]);

        $workspace = $this->resolveWorkspace($request, $submission);

        if ($workspace === null) {
            return response()->json(['error' => 'No workspace document exists for this manuscript yet.'], 404);
        }

        $snapshot = $this->documents->takeSnapshot(
            $workspace,
            ManuscriptSnapshot::FROM_MANUAL,
            $request->user(),
            $validated['label'],
        );

        return response()->json(['snapshot' => $snapshot->summary()], 201);
    }

    public function compare(
        Request $request,
        Submission $submission,
        ManuscriptSnapshot $left,
        ManuscriptSnapshot $right,
    ): JsonResponse {
        $this->authorize('compareWorkspaceVersions', $submission);

        $workspace = $this->resolveWorkspace($request, $submission);

        if ($workspace === null) {
            return response()->json(['error' => 'No workspace document exists for this manuscript yet.'], 404);
        }

        foreach ([$left, $right] as $snapshot) {
            if ((int) $snapshot->manuscript_workspace_document_id !== (int) $workspace->id) {
                return response()->json(['error' => 'Those versions do not belong to this manuscript.'], 404);
            }
        }

        return response()->json($this->comparison->compare($left->load('author'), $right->load('author')));
    }

    public function restore(
        Request $request,
        Submission $submission,
        ManuscriptSnapshot $snapshot,
    ): JsonResponse {
        $this->authorize('restoreWorkspaceVersion', $submission);

        $workspace = $this->resolveWorkspace($request, $submission);

        if ($workspace === null) {
            return response()->json(['error' => 'No workspace document exists for this manuscript yet.'], 404);
        }

        $result = $this->documents->restore($workspace, $snapshot, $request->user());

        if (! $result->isSaved()) {
            return response()->json($result->toArray(), $this->statusFor($result));
        }

        $this->documents->takeSnapshot(
            $result->document,
            ManuscriptSnapshot::FROM_RESTORE,
            $request->user(),
            'Restored from revision '.$snapshot->revision,
            $snapshot,
        );

        return response()->json([
            'revision' => (int) $result->document->revision,
            'content' => $result->document->content(),
            'restoredFrom' => $snapshot->revision,
        ]);
    }

    // ───────────────────────── export ─────────────────────────

    public function export(Request $request, Submission $submission): SymfonyResponse
    {
        $this->authorize('exportWorkspace', $submission);

        $workspace = $this->resolveWorkspace($request, $submission);

        if ($workspace === null) {
            return response()->json(['error' => 'No workspace document exists for this manuscript yet.'], 404);
        }

        $path = sprintf(
            '%s/%s-workspace-r%d.docx',
            trim((string) config('manuscripts.paths.formatted', 'manuscripts/formatted'), '/'),
            Str::slug($submission->submission_no),
            (int) $workspace->revision,
        );

        $absolute = Storage::disk(config('manuscripts.disk', 'private'))->path($path);
        $directory = dirname($absolute);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            return response()->json(['error' => 'Unable to prepare the export directory.'], 500);
        }

        $this->exporter->export($workspace, $absolute);

        $this->accessLog->export($request->user(), $submission, $workspace->document);

        return Storage::disk(config('manuscripts.disk', 'private'))
            ->download($path, sprintf('%s-workspace-r%d.docx', $submission->submission_no, (int) $workspace->revision));
    }

    // ───────────────────────── internals ─────────────────────────

    /**
     * Resolves the working document for a submission.
     *
     * The workspace document hangs off the parsed manuscript chain
     * (submission → version → document → workspace). When no version exists —
     * the case for every submission created before the ingestion pipeline, or
     * uploaded out-of-band — the workspace is bootstrapped directly from the
     * original uploaded DOCX on this first open, so editorial staff can edit
     * in the browser instead of being left with download-only pages.
     */
    private function resolveWorkspace(Request $request, Submission $submission, bool $create = false): ?ManuscriptWorkspaceDocument
    {
        $workspace = $this->documents->resolveForSubmission($submission);

        if ($workspace !== null || ! $create) {
            return $workspace;
        }

        return $this->documents->bootstrapForSubmission($submission, $request->user());
    }

    private function statusFor(SaveResult $result): int
    {
        return match ($result->outcome) {
            SaveResult::OUTCOME_CONFLICT => 409,
            SaveResult::OUTCOME_INVALID => 422,
            default => 200,
        };
    }
}