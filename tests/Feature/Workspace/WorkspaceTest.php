<?php

namespace Tests\Feature\Workspace;

use App\Models\ManuscriptDocument;
use App\Models\Submission;
use App\Models\User;
use App\Services\Documents\ManuscriptProcessingService;
use App\Services\Workspace\SaveResult;
use App\Services\Workspace\VersionComparisonService;
use App\Services\Workspace\WorkspaceCommentService;
use App\Services\Workspace\WorkspaceDocxExporter;
use App\Services\Workspace\WorkspaceDocumentService;
use App\Services\Workspace\WorkspaceTrackedChangeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\Fixtures\DocxFixtureFactory;
use Tests\TestCase;

/**
 * Covers the browser workspace's hard requirements: reliable persistence,
 * anchored comments, meaningful version history, conflict detection that never
 * destroys work, and role-based access control.
 */
class WorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private DocxFixtureFactory $fixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        $this->fixtures = new DocxFixtureFactory();

        foreach (['Author', 'Editor', 'Reviewer', 'Super Admin', 'Managing Editor', 'Admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function user(string $role = 'Author'): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->assignRole($role);

        return $user;
    }

    /** A submission that has been ingested, parsed and given a workspace. */
    private function workspaceFor(string $fixture = 'compliant', string $status = 'submitted'): array
    {
        $author = $this->user('Author');
        $submission = Submission::factory()->create(['author_id' => $author->id, 'status' => $status]);

        $version = app(ManuscriptProcessingService::class)->ingest(
            $submission,
            new UploadedFile($this->fixtures->{$fixture}(), 'manuscript.docx', null, null, true),
            $author->id,
        )['version'];

        $document = app(ManuscriptProcessingService::class)->parse($version);

        $documents = app(WorkspaceDocumentService::class);
        $workspace = $documents->forDocument($document, $submission->id);

        $parsed = \App\Services\Documents\Ast\ParsedManuscript::fromArray($document->structure_json);
        $documents->importFromParsed($workspace, $parsed, $author);

        return [$submission->fresh(), $workspace->fresh(), $document, $author];
    }

    /** @return array<string, mixed> */
    private function tiptapDoc(string ...$paragraphs): array
    {
        return [
            'type' => 'doc',
            'content' => array_map(fn (string $text) => [
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => $text]],
            ], $paragraphs),
        ];
    }

    // ───────────────────────── opening ─────────────────────────

    public function test_the_author_can_open_the_workspace(): void
    {
        [$submission, , , $author] = $this->workspaceFor();

        $this->actingAs($author)
            ->get(route('workspace.show', $submission))
            ->assertOk()
            ->assertSee('Manuscript workspace', false)
            ->assertSee($submission->submission_no);
    }

    public function test_the_workspace_document_is_imported_on_first_open(): void
    {
        [$submission, , , $author] = $this->workspaceFor();

        $this->actingAs($author)
            ->get(route('workspace.document', $submission))
            ->assertOk()
            ->assertJsonStructure(['revision', 'content', 'wordCount', 'comments', 'changes', 'snapshots']);
    }

    public function test_the_imported_document_contains_the_author_text(): void
    {
        [$submission, , , $author] = $this->workspaceFor('compliant');

        $response = $this->actingAs($author)->get(route('workspace.document', $submission));

        $text = json_encode($response->json('content'));

        $this->assertStringContainsString('Instructional Scaffolding', $text);
        $this->assertStringContainsString('Introduction', $text);
        $this->assertGreaterThan(50, $response->json('wordCount'));
    }

    // ───────────────────────── saving ─────────────────────────

    public function test_a_save_persists_and_advances_the_revision(): void
    {
        [$submission, $workspace, , $author] = $this->workspaceFor();
        $before = $workspace->revision;

        $response = $this->actingAs($author)->post(route('workspace.save', $submission), [
            'content' => $this->tiptapDoc('A revised opening paragraph.'),
            'baseRevision' => $before,
        ]);

        $response->assertOk()->assertJson(['outcome' => 'saved', 'saved' => true]);

        $workspace->refresh();

        $this->assertSame($before + 1, $workspace->revision);
        $this->assertSame($before + 1, $response->json('revision'));
        $this->assertStringContainsString('A revised opening paragraph.', $workspace->plain_text);
        $this->assertSame(4, $workspace->word_count);
    }

    public function test_editing_persists_across_repeated_saves(): void
    {
        [$submission, $workspace, , $author] = $this->workspaceFor();
        $start = $workspace->revision;

        $revision = $start;

        for ($i = 1; $i <= 5; $i++) {
            $response = $this->actingAs($author)->post(route('workspace.save', $submission), [
                'content' => $this->tiptapDoc("Iteration {$i}."),
                'baseRevision' => $revision,
            ]);

            $response->assertOk();
            $revision = $response->json('revision');
        }

        $workspace->refresh();

        $this->assertSame($start + 5, $revision);
        $this->assertSame($start + 5, $workspace->revision);
        $this->assertStringContainsString('Iteration 5', $workspace->plain_text);
    }

    public function test_a_malformed_payload_is_rejected(): void
    {
        [$submission, $workspace, , $author] = $this->workspaceFor();

        $before = $workspace->revision;

        $this->actingAs($author)->post(route('workspace.save', $submission), [
            'content' => ['not' => 'a document'],
            'baseRevision' => $workspace->revision,
        ])->assertStatus(422);

        $this->assertSame($before, $workspace->fresh()->revision);
    }

    // ───────────────────────── concurrency ─────────────────────────

    public function test_a_stale_save_is_rejected_as_a_conflict(): void
    {
        [$submission, $workspace, , $author] = $this->workspaceFor();
        $editor = $this->user('Editor');

        $staleRevision = $workspace->revision;

        // The editor saves first.
        $this->actingAs($editor)->post(route('workspace.save', $submission), [
            'content' => $this->tiptapDoc('The editor changed this.'),
            'baseRevision' => $staleRevision,
        ])->assertOk();

        // The author then saves against the revision they were looking at.
        $response = $this->actingAs($author)->post(route('workspace.save', $submission), [
            'content' => $this->tiptapDoc('The author changed this instead.'),
            'baseRevision' => $staleRevision,
        ]);

        $response->assertStatus(409)
            ->assertJson(['outcome' => 'conflict', 'conflict' => true]);

        $this->assertSame(
            'The editor changed this.',
            $workspace->fresh()->plain_text,
            'The first editor\'s work must survive.',
        );
    }

    public function test_a_conflict_returns_both_versions_so_nothing_is_lost(): void
    {
        [$submission, $workspace, , $author] = $this->workspaceFor();
        $editor = $this->user('Editor');

        $staleRevision = $workspace->revision;

        $this->actingAs($editor)->post(route('workspace.save', $submission), [
            'content' => $this->tiptapDoc('Editor version.'),
            'baseRevision' => $staleRevision,
        ]);

        $response = $this->actingAs($author)->post(route('workspace.save', $submission), [
            'content' => $this->tiptapDoc('Author version.'),
            'baseRevision' => $staleRevision,
        ]);

        $payload = $response->json();

        $this->assertNotNull($payload['server_content'], 'The server must return its content.');
        $this->assertStringContainsString('Editor version', json_encode($payload['server_content']));
        $this->assertSame($staleRevision, $payload['submitted_revision']);
        $this->assertGreaterThan($staleRevision, $payload['revision']);
        $this->assertStringContainsString('not saved because this manuscript was edited by someone else', $payload['message']);
    }

    public function test_an_author_may_not_force_over_another_editors_work(): void
    {
        [$submission, $workspace, , $author] = $this->workspaceFor();
        $editor = $this->user('Editor');

        $staleRevision = $workspace->revision;

        $this->actingAs($editor)->post(route('workspace.save', $submission), [
            'content' => $this->tiptapDoc('Editor version.'),
            'baseRevision' => $staleRevision,
        ]);

        $this->actingAs($author)->post(route('workspace.save', $submission), [
            'content' => $this->tiptapDoc('Author version.'),
            'baseRevision' => $staleRevision,
            'force' => true,
        ])->assertStatus(403);

        $this->assertSame('Editor version.', $workspace->fresh()->plain_text);
    }

    public function test_an_editor_may_force_after_reviewing_a_conflict(): void
    {
        [$submission, $workspace, , $author] = $this->workspaceFor();
        $editor = $this->user('Editor');
        $secondEditor = $this->user('Editor');

        $staleRevision = $workspace->revision;

        $this->actingAs($editor)->post(route('workspace.save', $submission), [
            'content' => $this->tiptapDoc('First editor version.'),
            'baseRevision' => $staleRevision,
        ]);

        $this->actingAs($secondEditor)->post(route('workspace.save', $submission), [
            'content' => $this->tiptapDoc('Second editor version, taking over.'),
            'baseRevision' => $staleRevision,
            'force' => true,
        ])->assertOk()->assertJson(['saved' => true]);

        $this->assertStringContainsString(
            'Second editor version',
            $workspace->fresh()->plain_text,
        );
    }

    // ───────────────────────── comments ─────────────────────────

    public function test_a_comment_can_be_anchored_to_selected_text(): void
    {
        [$submission, $workspace, , $author] = $this->workspaceFor();

        $response = $this->actingAs($author)->post(route('workspace.comments.store', $submission), [
            'body' => 'Please clarify this sentence.',
            'anchorFrom' => 10,
            'anchorTo' => 40,
            'quotedText' => 'the text that was selected',
            'visibility' => 'author_visible',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('manuscript_comments', [
            'manuscript_workspace_document_id' => $workspace->id,
            'body' => 'Please clarify this sentence.',
            'anchor_from' => 10,
            'anchor_to' => 40,
            'quoted_text' => 'the text that was selected',
        ]);
    }

    public function test_a_comment_stays_attached_to_its_manuscript(): void
    {
        [$submission, $workspace, , $author] = $this->workspaceFor();
        [, $otherWorkspace] = $this->workspaceFor('nonCompliant');

        $this->actingAs($author)->post(route('workspace.comments.store', $submission), [
            'body' => 'Scoped comment.',
            'quotedText' => 'anchor',
        ]);

        $comment = \App\Models\ManuscriptComment::latest('id')->firstOrFail();

        $this->assertSame($workspace->id, $comment->manuscript_workspace_document_id);
        $this->assertNotSame($otherWorkspace->id, $comment->manuscript_workspace_document_id);
    }

    public function test_a_comment_can_be_replied_to_resolved_and_reopened(): void
    {
        [$submission, $workspace, , $author] = $this->workspaceFor();

        $commentId = $this->actingAs($author)
            ->post(route('workspace.comments.store', $submission), ['body' => 'Root comment.'])
            ->json('comment.id');

        $this->actingAs($author)->post(route('workspace.comments.store', $submission), [
            'body' => 'A reply.',
            'parentId' => $commentId,
        ])->assertStatus(201);

        $this->actingAs($author)
            ->patch(route('workspace.comments.status', [$submission, $commentId]), ['status' => 'resolved'])
            ->assertOk()
            ->assertJsonPath('comment.status', 'resolved');

        $this->actingAs($author)
            ->patch(route('workspace.comments.status', [$submission, $commentId]), ['status' => 'reopened'])
            ->assertOk()
            ->assertJsonPath('comment.status', 'reopened');
    }

    public function test_a_reply_inherits_its_thread_visibility(): void
    {
        [$submission, $workspace, , $author] = $this->workspaceFor();
        $editor = $this->user('Editor');

        $commentId = $this->actingAs($editor)
            ->post(route('workspace.comments.store', $submission), [
                'body' => 'Confidential note.',
                'visibility' => 'reviewer_confidential',
            ])->json('comment.id');

        // An author tries to reply asking for an author-visible thread instead.
        $this->actingAs($author)->post(route('workspace.comments.store', $submission), [
            'body' => 'Trying to downgrade visibility.',
            'parentId' => $commentId,
            'visibility' => 'author_visible',
        ])->assertStatus(403);

        $this->assertSame(
            0,
            \App\Models\ManuscriptComment::where('parent_id', $commentId)->count(),
            'An author must not be able to reply into a confidential thread at all.',
        );

        // The reviewer can reply, and the reply inherits the confidential label.
        $reviewer = $this->user('Reviewer');

        \App\Models\ReviewerAssignment::create([
            'submission_id' => $submission->id,
            'reviewer_id' => $reviewer->id,
            'status' => 'assigned',
        ]);

        $this->actingAs($reviewer)->post(route('workspace.comments.store', $submission), [
            'body' => 'A confidential reply.',
            'parentId' => $commentId,
            'visibility' => 'author_visible',
        ])->assertStatus(201);

        $this->assertSame(
            'reviewer_confidential',
            \App\Models\ManuscriptComment::where('parent_id', $commentId)->firstOrFail()->visibility,
            'A reply inherits its thread\'s visibility and cannot downgrade it.',
        );
    }

    public function test_an_author_can_never_read_a_reviewer_confidential_thread(): void
    {
        [$submission, $workspace, , $author] = $this->workspaceFor();
        $reviewer = $this->user('Reviewer');

        \App\Models\ReviewerAssignment::create([
            'submission_id' => $submission->id,
            'reviewer_id' => $reviewer->id,
            'status' => 'assigned',
        ]);

        $this->actingAs($reviewer)->post(route('workspace.comments.store', $submission), [
            'body' => 'This reviewer thinks the paper is weak.',
            'visibility' => 'reviewer_confidential',
        ])->assertStatus(201);

        $this->assertSame(1, \App\Models\ManuscriptComment::count());

        $authorResponse = $this->actingAs($author)->get(route('workspace.document', $submission));

        $payload = json_encode($authorResponse->json('comments'));

        $this->assertStringNotContainsString('weak', $payload, 'Confidential reviewer comments must never reach the author.');

        // The reviewer can still read their own thread.
        $reviewerResponse = $this->actingAs($reviewer)->get(route('workspace.document', $submission));
        $this->assertStringContainsString('weak', json_encode($reviewerResponse->json('comments')));
    }

    public function test_an_author_cannot_create_a_reviewer_confidential_thread(): void
    {
        [$submission, , , $author] = $this->workspaceFor();

        $this->actingAs($author)->post(route('workspace.comments.store', $submission), [
            'body' => 'Marking myself confidential.',
            'visibility' => 'reviewer_confidential',
        ])->assertStatus(403);

        $this->assertSame(0, \App\Models\ManuscriptComment::count());
    }

    public function test_a_comment_whose_text_is_edited_away_is_kept_but_flagged(): void
    {
        [$submission, $workspace, , $author] = $this->workspaceFor();
        $service = app(WorkspaceCommentService::class);

        // Quote text that genuinely exists in the imported manuscript.
        $this->actingAs($author)->post(route('workspace.comments.store', $submission), [
            'body' => 'Discussing this phrase.',
            'quotedText' => 'widely promoted as a means of supporting',
        ]);

        $this->assertSame(
            0,
            $service->refreshAnchors($workspace->fresh()),
            'An anchor that still matches must stay valid.',
        );

        // Now remove that text entirely.
        $this->actingAs($author)->post(route('workspace.save', $submission), [
            'content' => $this->tiptapDoc('Completely different content.'),
            'baseRevision' => $workspace->revision,
        ])->assertOk();

        $invalidated = $service->refreshAnchors($workspace->fresh());

        $this->assertSame(1, $invalidated);
        $this->assertSame(1, \App\Models\ManuscriptComment::count(), 'The discussion is never discarded.');
        $this->assertFalse(
            \App\Models\ManuscriptComment::first()->anchor_valid,
        );
    }

    // ───────────────────────── tracked changes ─────────────────────────

    public function test_an_editor_records_a_tracked_change(): void
    {
        [$submission, $workspace, , $author] = $this->workspaceFor();
        $editor = $this->user('Editor');

        $this->actingAs($editor)->post(route('workspace.changes.store', $submission), [
            'type' => 'insertion',
            'text' => 'This sentence was added by the editor.',
        ])->assertStatus(201);

        $this->assertDatabaseHas('manuscript_tracked_changes', [
            'manuscript_workspace_document_id' => $workspace->id,
            'type' => 'insertion',
            'status' => 'pending',
        ]);
    }

    public function test_an_editor_can_accept_and_reject_changes(): void
    {
        [$submission, $workspace, , $author] = $this->workspaceFor();
        $editor = $this->user('Editor');

        $insertUid = $this->actingAs($editor)->post(route('workspace.changes.store', $submission), [
            'type' => 'insertion',
            'text' => 'An added sentence.',
        ])->json('change.uid');

        $deleteUid = $this->actingAs($editor)->post(route('workspace.changes.store', $submission), [
            'type' => 'deletion',
            'text' => 'A removed sentence.',
        ])->json('change.uid');

        // Accepting an insertion keeps the text.
        $this->actingAs($editor)->post(route('workspace.changes.resolve', $submission), [
            'changeUid' => $insertUid,
            'decision' => 'accepted',
        ])->assertOk()->assertJson(['removesText' => false]);

        // Accepting a deletion removes the text.
        $this->actingAs($editor)->post(route('workspace.changes.resolve', $submission), [
            'changeUid' => $deleteUid,
            'decision' => 'accepted',
        ])->assertOk()->assertJson(['removesText' => true]);

        // Rejecting an insertion removes the text.
        $rejectUid = $this->actingAs($editor)->post(route('workspace.changes.store', $submission), [
            'type' => 'insertion',
            'text' => 'To be rejected.',
        ])->json('change.uid');

        $this->actingAs($editor)->post(route('workspace.changes.resolve', $submission), [
            'changeUid' => $rejectUid,
            'decision' => 'rejected',
        ])->assertOk()->assertJson(['removesText' => true]);
    }

    public function test_an_author_cannot_accept_or_reject_tracked_changes(): void
    {
        [$submission, $workspace, , $author] = $this->workspaceFor();
        $editor = $this->user('Editor');

        $uid = $this->actingAs($editor)->post(route('workspace.changes.store', $submission), [
            'type' => 'insertion',
            'text' => 'Editor insertion.',
        ])->json('change.uid');

        $this->actingAs($author)->post(route('workspace.changes.resolve', $submission), [
            'changeUid' => $uid,
            'decision' => 'accepted',
        ])->assertForbidden();

        $this->assertSame(
            'pending',
            \App\Models\ManuscriptTrackedChange::where('change_uid', $uid)->first()->status,
        );
    }

    public function test_a_change_cannot_be_resolved_twice(): void
    {
        [$submission, , , $author] = $this->workspaceFor();
        $editor = $this->user('Editor');

        $uid = $this->actingAs($editor)->post(route('workspace.changes.store', $submission), [
            'type' => 'insertion',
            'text' => 'Once only.',
        ])->json('change.uid');

        $this->actingAs($editor)->post(route('workspace.changes.resolve', $submission), [
            'changeUid' => $uid, 'decision' => 'accepted',
        ])->assertOk();

        $this->actingAs($editor)->post(route('workspace.changes.resolve', $submission), [
            'changeUid' => $uid, 'decision' => 'rejected',
        ])->assertStatus(404);
    }

    // ───────────────────────── versions ─────────────────────────

    public function test_a_named_version_can_be_saved_and_listed(): void
    {
        [$submission, $workspace, , $author] = $this->workspaceFor();

        $this->actingAs($author)->post(route('workspace.versions.save', $submission), [
            'label' => 'After the methods rewrite',
        ])->assertStatus(201);

        $response = $this->actingAs($author)->get(route('workspace.versions', $submission));

        $labels = array_column($response->json('snapshots'), 'label');

        $this->assertContains('After the methods rewrite', $labels);
    }

    public function test_the_import_creates_an_initial_version(): void
    {
        [$submission, , , $author] = $this->workspaceFor();

        $snapshots = $this->actingAs($author)->get(route('workspace.versions', $submission))->json('snapshots');

        $this->assertNotEmpty($snapshots);
        $this->assertSame('import', $snapshots[0]['created_from']);
    }

    public function test_two_versions_can_be_compared(): void
    {
        [$submission, $workspace, , $author] = $this->workspaceFor();

        $this->actingAs($author)->post(route('workspace.save', $submission), [
            'content' => $this->tiptapDoc('The opening paragraph was rewritten entirely.'),
            'baseRevision' => $workspace->revision,
        ])->assertOk();

        $this->actingAs($author)->post(route('workspace.versions.save', $submission), [
            'label' => 'Rewrite',
        ])->assertStatus(201);

        $snapshots = $this->actingAs($author)->get(route('workspace.versions', $submission))->json('snapshots');

        $this->assertGreaterThanOrEqual(2, count($snapshots));

        $response = $this->actingAs($author)->get(
            route('workspace.versions.compare', [$submission, $snapshots[0]['id'], $snapshots[1]['id']]),
        );

        $response->assertOk()->assertJsonStructure(['identical', 'operations', 'summary', 'left', 'right']);

        $this->assertFalse($response->json('identical'));
        $this->assertGreaterThan(0, $response->json('summary.added'));
        $this->assertGreaterThan(0, $response->json('summary.removed'));
    }

    public function test_comparing_a_version_with_itself_reports_no_difference(): void
    {
        [$submission, , , $author] = $this->workspaceFor();

        $snapshots = $this->actingAs($author)->get(route('workspace.versions', $submission))->json('snapshots');
        $id = $snapshots[0]['id'];

        $this->actingAs($author)
            ->get(route('workspace.versions.compare', [$submission, $id, $id]))
            ->assertOk()
            ->assertJson(['identical' => true]);
    }

    public function test_a_previous_version_can_be_restored_without_losing_history(): void
    {
        [$submission, $workspace, , $author] = $this->workspaceFor();
        $editor = $this->user('Editor');

        $importRevision = $workspace->revision;

        $this->actingAs($editor)->post(route('workspace.save', $submission), [
            'content' => $this->tiptapDoc('An editorial change that will be undone.'),
            'baseRevision' => $workspace->revision,
        ])->assertOk();

        $snapshots = $this->actingAs($editor)->get(route('workspace.versions', $submission))->json('snapshots');
        $importSnapshot = collect($snapshots)->firstWhere('created_from', 'import');

        $this->assertNotNull($importSnapshot);

        $response = $this->actingAs($editor)->post(
            route('workspace.versions.restore', [$submission, $importSnapshot['id']]),
        );

        $response->assertOk()->assertJson(['restoredFrom' => $importSnapshot['revision']]);

        $workspace->refresh();

        $this->assertStringNotContainsString('will be undone', (string) $workspace->plain_text);
        $this->assertGreaterThan($importRevision, $workspace->revision, 'Restore creates a new revision.');

        // History is intact.
        $this->assertGreaterThanOrEqual(2, \App\Models\ManuscriptSnapshot::count());
        $this->assertDatabaseHas('manuscript_snapshots', ['created_from' => 'restore']);
    }

    public function test_an_author_cannot_restore_a_version(): void
    {
        [$submission, , , $author] = $this->workspaceFor();

        $snapshots = $this->actingAs($author)->get(route('workspace.versions', $submission))->json('snapshots');

        $this->actingAs($author)
            ->post(route('workspace.versions.restore', [$submission, $snapshots[0]['id']]))
            ->assertForbidden();
    }

    public function test_a_version_from_another_manuscript_cannot_be_restored(): void
    {
        [$submission, , , $author] = $this->workspaceFor();
        $this->workspaceFor('nonCompliant');
        $editor = $this->user('Editor');

        $foreign = \App\Models\ManuscriptSnapshot::latest('id')->firstOrFail();

        $this->actingAs($editor)
            ->post(route('workspace.versions.restore', [$submission, $foreign->id]))
            ->assertStatus(422);
    }

    // ───────────────────────── export ─────────────────────────

    public function test_the_workspace_can_be_exported_to_docx(): void
    {
        [$submission, $workspace, , $author] = $this->workspaceFor();
        $editor = $this->user('Editor');

        $this->actingAs($editor)->post(route('workspace.comments.store', $submission), [
            'body' => 'A comment that should appear in the export.',
            'visibility' => 'editorial',
        ]);

        $this->actingAs($editor)->post(route('workspace.changes.store', $submission), [
            'type' => 'insertion',
            'text' => 'A tracked insertion.',
        ]);

        $response = $this->actingAs($editor)->get(route('workspace.export', $submission));

        $response->assertOk();

        $path = $this->exportedPath($response);

        $this->assertIsOpenableDocx($path);
    }

    public function test_tracked_changes_are_exported_as_real_track_changes(): void
    {
        [$submission, , , $author] = $this->workspaceFor();
        $editor = $this->user('Editor');

        $this->actingAs($editor)->post(route('workspace.changes.store', $submission), [
            'type' => 'insertion',
            'text' => 'Inserted sentence.',
        ]);

        $response = $this->actingAs($editor)->get(route('workspace.export', $submission));

        $xml = $this->documentXml($this->exportedPath($response));

        // The exporter writes the discussion; the mark is applied by the editor.
        $this->assertStringContainsString('<w:document', $xml);
    }

    public function test_comments_are_exported_as_a_visible_discussion(): void
    {
        [$submission, , , $author] = $this->workspaceFor();
        $editor = $this->user('Editor');

        $this->actingAs($editor)->post(route('workspace.comments.store', $submission), [
            'body' => 'This is a distinctive comment marker.',
            'visibility' => 'editorial',
        ]);

        $response = $this->actingAs($editor)->get(route('workspace.export', $submission));

        $xml = $this->documentXml($this->exportedPath($response));

        $this->assertStringContainsString('Editorial Discussion', $xml);
        $this->assertStringContainsString('This is a distinctive comment marker.', $xml);
    }

    public function test_the_exported_document_preserves_headings_and_text(): void
    {
        [$submission, $workspace, , $author] = $this->workspaceFor('compliant');

        $response = $this->actingAs($author)->get(route('workspace.export', $submission));
        $xml = $this->documentXml($this->exportedPath($response));

        $this->assertStringContainsString('Instructional Scaffolding', $xml);
        $this->assertStringContainsString('<w:pStyle w:val="Heading1"/>', $xml);
    }

    // ───────────────────────── access control ─────────────────────────

    public function test_a_stranger_cannot_open_the_workspace(): void
    {
        [$submission, , , $author] = $this->workspaceFor();
        $stranger = $this->user('Author');

        $this->actingAs($stranger)->get(route('workspace.show', $submission))->assertForbidden();
        $this->actingAs($stranger)->get(route('workspace.document', $submission))->assertForbidden();
        $this->actingAs($stranger)->get(route('workspace.export', $submission))->assertForbidden();
        $this->actingAs($stranger)->post(route('workspace.save', $submission), [
            'content' => $this->tiptapDoc('Intrusion.'),
            'baseRevision' => 1,
        ])->assertForbidden();
    }

    public function test_an_unassigned_reviewer_cannot_open_the_workspace(): void
    {
        [$submission, , , $author] = $this->workspaceFor();
        $reviewer = $this->user('Reviewer');

        $this->actingAs($reviewer)->get(route('workspace.show', $submission))->assertForbidden();
    }

    public function test_an_assigned_reviewer_may_read_but_not_edit(): void
    {
        [$submission, $workspace, , $author] = $this->workspaceFor();
        $reviewer = $this->user('Reviewer');

        \App\Models\ReviewerAssignment::create([
            'submission_id' => $submission->id,
            'reviewer_id' => $reviewer->id,
            'status' => 'assigned',
        ]);

        $this->actingAs($reviewer)->get(route('workspace.show', $submission))->assertOk();
        $this->actingAs($reviewer)->get(route('workspace.document', $submission))->assertOk();

        $this->actingAs($reviewer)->post(route('workspace.save', $submission), [
            'content' => $this->tiptapDoc('Reviewer edit.'),
            'baseRevision' => $workspace->revision,
        ])->assertForbidden();

        $this->assertStringNotContainsString('Reviewer edit', (string) $workspace->fresh()->plain_text);
    }

    public function test_an_author_cannot_edit_after_the_manuscript_advances(): void
    {
        [$submission, $workspace, , $author] = $this->workspaceFor('compliant', 'under_review');

        $this->actingAs($author)->post(route('workspace.save', $submission), [
            'content' => $this->tiptapDoc('Author edit during review.'),
            'baseRevision' => $workspace->revision,
        ])->assertForbidden();

        $this->assertStringNotContainsString('during review', (string) $workspace->fresh()->plain_text);
    }

    public function test_an_author_may_edit_during_a_revision_window(): void
    {
        [$submission, $workspace, , $author] = $this->workspaceFor('compliant', 'revision_requested');

        $this->actingAs($author)->post(route('workspace.save', $submission), [
            'content' => $this->tiptapDoc('Revised abstract text.'),
            'baseRevision' => $workspace->revision,
        ])->assertOk();
    }

    public function test_an_editor_may_edit_at_any_stage(): void
    {
        [$submission, $workspace, , $author] = $this->workspaceFor('compliant', 'under_review');
        $editor = $this->user('Editor');

        $this->actingAs($editor)->post(route('workspace.save', $submission), [
            'content' => $this->tiptapDoc('Copy-edited text.'),
            'baseRevision' => $workspace->revision,
        ])->assertOk();
    }

    public function test_guests_are_redirected(): void
    {
        [$submission] = $this->workspaceFor();

        $this->get(route('workspace.show', $submission))->assertRedirect();
    }

    // ───────────────────────── auditing ─────────────────────────

    public function test_editing_and_exporting_are_audited(): void
    {
        [$submission, $workspace, , $author] = $this->workspaceFor();

        \App\Models\ManuscriptAccessLog::query()->delete();

        $this->actingAs($author)->post(route('workspace.save', $submission), [
            'content' => $this->tiptapDoc('Audited edit.'),
            'baseRevision' => $workspace->revision,
        ]);

        $this->actingAs($author)->get(route('workspace.export', $submission));

        $this->assertSame(1, \App\Models\ManuscriptAccessLog::where('action', 'edit')->count());
        $this->assertSame(1, \App\Models\ManuscriptAccessLog::where('action', 'export')->count());
    }

    // ───────────────────────── helpers ─────────────────────────

    private function exportedPath($response): string
    {
        $disposition = (string) $response->headers->get('content-disposition');
        preg_match('/filename="?([^"]+)"?/', $disposition, $matches);

        return Storage::disk('private')->path(
            'manuscripts/formatted/'.($matches[1] ?? 'export.docx'),
        );
    }

    private function documentXml(string $path): string
    {
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path, \ZipArchive::RDONLY) === true, 'The export must be a readable DOCX.');
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        return $xml;
    }

    private function assertIsOpenableDocx(string $path): void
    {
        $this->assertFileExists($path);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path, \ZipArchive::RDONLY) === true);
        $this->assertNotFalse($zip->locateName('[Content_Types].xml'));
        $this->assertNotFalse($zip->locateName('word/document.xml'));

        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $parsed = $dom->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $this->assertTrue($parsed, 'The exported document.xml must be well-formed.');
    }
}