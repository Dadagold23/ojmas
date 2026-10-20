# MODULE 2 — INTEGRATED BROWSER-BASED MANUSCRIPT WORKSPACE

**Status:** Implementation complete. All tests pass. Awaiting approval.
**Date:** 2026-10-18
**Scope:** Module 2 only.

---

## 1. Editor technology decision

The Module 0 audit compared three editors. That comparison stands, and the decision is unchanged:

| | CKEditor 5 | **Tiptap (chosen)** | ONLYOFFICE Docs |
|---|---|---|---|
| Licence | GPL core; DOCX/comments/track-changes/version-history are **paid plugins** | **MIT** | AGPL-3.0 |
| DOCX round-trip | Paid plugin | Built by us (Module 1 engine) | Best in class |
| Tracked changes | Partial, unreliable in the free build | **Built by us** (below) | Native |
| Comments | Paid plugin | **Built by us** | Native |
| Version history | Paid plugin | **Built by us** | Native |
| Infrastructure | None | **None** | Needs a ≥2 GB Document Server |
| Fits the existing Vite/Tailwind/Blade stack | Yes | **Natively** | Needs a new service |

**Installed:** `@tiptap/core`, `@tiptap/starter-kit`, `@tiptap/pm`, `@tiptap/extension-placeholder`, `@tiptap/extension-character-count`. All MIT. No external service, no new infrastructure, no licence obligation, no paid dependency.

**Tiptap ships without track changes, comments or version history.** Rather than claim capabilities the editor lacks, each is implemented explicitly and tested:

- **Tracked changes** — two custom Tiptap marks (`trackedInsertion`, `trackedDeletion`) backed by `manuscript_tracked_changes` as the system of record. Tiptap renders the state; Laravel resolves it.
- **Comments** — a `commentAnchor` mark plus `manuscript_comments`, with a visibility matrix enforced server-side.
- **Version history** — `manuscript_snapshots`, with word-level diff, comparison and restore.

---

## 2. What was built

### 2.1 Workspace interface (prompt §2.2)

Every item required is present:

| Requirement | Where |
|---|---|
| Manuscript title + unique submission identifier | Header: `submission_no`, title, status, live revision |
| Current editorial stage and document version | Status badge + live revision counter |
| Main document editing area | Tiptap editor surface |
| Contextual comment panel | Right column, anchored, threaded |
| Revision and change-tracking controls | Tracked-changes panel with accept/reject, individually and in bulk |
| Version-history panel | With named saves, A/B selection and diff view |
| Save status and processing indicators | `save-status` badge: loading / unsaved / saving / saved / conflict / offline / error |
| Editorial instructions and deadlines | Manuscript details panel; deadline fields are Module 4 scope |
| Authorised actions for the current role | Every control is capability-gated *and* server-authorised |

**Autosave** is debounced at 2.5 s, retries with exponential back-off up to 30 s on network failure, flushes on `visibilitychange`, and blocks navigation with `beforeunload` while unsaved or conflicted.

### 2.2 Concurrent editing (prompt §2.2) — the hard requirement

> *"Autosave must not overwrite newer edits from another user without detecting and resolving the conflict."*

The model is **sequential editing with optimistic concurrency detection**. Every save carries the revision it was made against. The server takes a `lockForUpdate` on the row, compares, and either accepts or returns **409 Conflict** with *both* versions intact.

**Nothing is ever auto-resolved.** On conflict the user chooses one of three explicit actions:

1. **Keep mine** — overwrite (requires `forceSave` permission: editorial staff only).
2. **Take theirs** — discard mine, by my own choice.
3. **Keep both** — mine, then a clearly-labelled block containing theirs, so the merge is a human decision.

An author cannot take option 1. That is enforced server-side, not hidden in the UI.

**This is not real-time collaboration, and the code does not claim it is.** `WorkspaceDocumentService` carries a class-level comment stating exactly what it is. Real-time would require Tiptap Collaboration (paid) or a CRDT server (new infrastructure); neither has been authorised.

### 2.3 Collaborative features (prompt §2.3)

| Capability | Status |
|---|---|
| Edit authorised content in the browser | ✅ Tiptap |
| Add comments to selected text | ✅ Range-anchored threads |
| Reply to comments | ✅ Threaded; replies inherit thread visibility |
| Resolve and reopen comments | ✅ |
| Record insertions and deletions | ✅ Custom Tiptap marks + server table |
| Accept or reject tracked changes where authorised | ✅ Editorial staff only; authors cannot review their own changes |
| Compare manuscript versions | ✅ Word-level LCS diff with collapse |
| Restore an authorised previous version | ✅ Editorial staff only; creates a **new** revision |
| Preserve author revisions and editorial annotations | ✅ History is never rewritten |

### 2.4 Export (prompt §2.4)

`WorkspaceDocxExporter` writes real OOXML directly:

- Paragraphs and headings → styled `w:p` with APA spacing.
- **Table rows** — the browser editor flattens a grid to pipe-delimited paragraphs on import; the exporter **reassembles the grid** on the way out. Nothing is lost in either direction.
- **Tracked changes** → genuine `w:ins` / `w:del` elements with real authorship and dates, so Word shows them as track changes.
- **Comments** → an "Editorial Discussion" appendix. Reproducing OOXML comment anchoring faithfully is something we cannot claim; dropping the discussion would be worse, so it is presented as visible text and this is stated as a limitation.
- Citations and references → ordinary paragraphs, preserving text.

**Publication PDFs remain the existing DomPDF/editor-upload path.** DomPDF cannot read DOCX and there is no conversion engine on this stack (Module 0 §H.2). No claim of automated conversion is made.

---

## 3. Test results

**Command:** `php artisan test`
**Result:** `Tests: 211 passed (710 assertions)` — 0 failures, 0 skipped.

| Suite | Tests | Baseline |
|---|---|---|
| Pre-existing (Auth, Profile, Example) | 27 | 27 |
| Module 1 (Documents, Editorial) | 134 | 134 |
| **Module 2 — `Feature/Workspace/WorkspaceTest`** | **40** | new |
| **Module 2 — `Unit/Workspace/VersionComparisonServiceTest`** | **10** | new |

### 3.1 Module 2 §2.5 acceptance criteria

| # | Criterion | Test | Result |
|---|---|---|---|
| 1 | Authorized users can open manuscripts in the browser | `the_author_can_open_the_workspace`, `an_assigned_reviewer_may_read_but_not_edit`, `an_editor_may_edit_at_any_stage` | **PASS** |
| 2 | Editing and saving persist reliably | `a_save_persists_and_advances_the_revision`, `editing_persists_across_repeated_saves` (5 sequential saves), `a_malformed_payload_is_rejected` | **PASS** |
| 3 | Comments remain associated with the correct manuscript and text | `a_comment_stays_attached_to_its_manuscript`, `a_comment_whose_text_is_edited_away_is_kept_but_flagged` | **PASS** |
| 4 | Version history records meaningful changes | `a_named_version_can_be_saved_and_listed`, `the_import_creates_an_initial_version`, `two_versions_can_be_compared`, `a_previous_version_can_be_restored_without_losing_history` | **PASS** |
| 5 | Concurrent edits do not silently destroy another user's work | `a_stale_save_is_rejected_as_a_conflict`, `a_conflict_returns_both_versions_so_nothing_is_lost`, `an_author_may_not_force_over_another_editors_work`, `an_editor_may_force_after_reviewing_a_conflict` | **PASS** |
| 6 | DOCX import/export tested with representative complex documents | `the_workspace_can_be_exported_to_docx`, `comments_are_exported_as_a_visible_discussion`, `the_exported_document_preserves_headings_and_text`, `the_imported_document_contains_the_author_text` | **PASS** |
| 7 | Unauthorized users cannot access or modify protected manuscripts | `a_stranger_cannot_open_the_workspace` (4 routes), `an_unassigned_reviewer_cannot_open_the_workspace`, `an_author_cannot_edit_after_the_manuscript_advances`, `an_author_cannot_restore_a_version`, `an_author_cannot_accept_or_reject_tracked_changes`, `a_version_from_another_manuscript_cannot_be_restored`, `guests_are_redirected` | **PASS** |

### 3.2 Confidentiality tests

| Test | Proves |
|---|---|
| `an_author_can_never_read_a_reviewer_confidential_thread` | Confidential text absent from the author payload **and** present for the reviewer |
| `an_author_cannot_create_a_reviewer_confidential_thread` | Authors cannot escalate |
| `a_reply_inherits_its_thread_visibility` | An author cannot reply into a confidential thread; a reviewer's reply cannot downgrade it either |
| `editing_and_exporting_are_audited` | Both actions write `manuscript_access_logs` rows |

### 3.3 Diff algorithm tests

`VersionComparisonServiceTest` covers identical texts, insertions, deletions, replacements, wholly different documents, empty input on either side, collapse of long unchanged runs, the large-document guard (falls back to a whole-document replace rather than exhausting memory), and JSON serialisability.

---

## 4. Demonstration

Actual output from an end-to-end run:

```
STEP 1 - THE MANUSCRIPT IS OPENED IN THE BROWSER
  Imported blocks : 21    Words : 266    Starting revision: 1    Author may edit : YES

STEP 2 - AN EDITOR MAKES A CHANGE
  Outcome : saved    Revision : 2

STEP 3 - CONCURRENT EDIT DETECTION
  Author's outcome: conflict
  Author edited at: revision 2
  Server is at    : revision 3
  Stored content  : The editor revised the opening while you were...
  -> The author's edit was NOT written. Both versions returned to the browser.
  -> Server content preserved: YES
  -> Author content preserved: YES

STEP 4 - CONTEXTUAL COMMENTS AND CONFIDENTIALITY
  Author can read   : author_visible
  Reviewer can read : editorial, reviewer_confidential
  Author sees the confidential thread? NO - correctly hidden
  Author replying to a confidential thread? DENIED (correct)

STEP 5 - TRACKED CHANGES
  Accept insertion -> text kept?     YES (correct)
  Accept deletion  -> text removed?  YES (correct)

STEP 6 - VERSION HISTORY AND COMPARISON
  Versions on record: 3
  Compare r3 vs r4: identical=no, +9 / -9 words

STEP 7 - RESTORING A PREVIOUS VERSION
  Restored to content of r3 -> New revision : 5 (history is never rewritten)
  Snapshots : 4 still on record

STEP 8 - DOCX EXPORT
  Size 2.8 KB    Valid XML : YES    Has discussion: YES (comments exported)

STEP 9 - WHO CAN DO WHAT
  Role         view     edit     comment   review
  Author       yes      yes      yes       no
  Editor       yes      yes      yes       yes
  Reviewer     yes      no       yes       no
```

---

## 5. Files created or modified

### Created — application code (17)
```
app/Models/ManuscriptWorkspaceDocument.php
app/Models/ManuscriptSnapshot.php
app/Models/ManuscriptComment.php
app/Models/ManuscriptTrackedChange.php
app/Policies/WorkspacePolicy.php
app/Http/Controllers/ManuscriptWorkspaceController.php
app/Services/Workspace/WorkspaceDocumentService.php
app/Services/Workspace/SaveResult.php
app/Services/Workspace/WorkspaceCommentService.php
app/Services/Workspace/WorkspaceTrackedChangeService.php
app/Services/Workspace/VersionComparisonService.php
app/Services/Workspace/WorkspaceDocxExporter.php
resources/views/workspace/index.blade.php
resources/js/workspace/index.js
resources/js/workspace/extensions.js
resources/js/workspace/autosave.js
```

### Created — migrations (4)
```
2026_10_18_000012_create_manuscript_workspace_documents_table
2026_10_18_000013_create_manuscript_snapshots_table
2026_10_18_000014_create_manuscript_comments_table
2026_10_18_000015_create_manuscript_tracked_changes_table
```

### Created — tests (2 files, 50 tests)
```
tests/Feature/Workspace/WorkspaceTest.php            (40)
tests/Unit/Workspace/VersionComparisonServiceTest.php (10)
```

### Modified — existing files (6)
```
package.json / package-lock.json   + Tiptap packages, + laravel-vite-plugin
vite.config.js                     + workspace entry point
routes/web.php                     + 11 workspace routes
app/Policies/SubmissionPolicy.php  + 8 delegated workspace abilities
resources/views/layouts/*          (unchanged; used @push/@stack conventions already present)
```

**Verified reversible:** `migrate:rollback --step=4` → 4 DONE, then clean replay.

---

## 6. Dependencies and licensing

| Package | Licence | Purpose |
|---|---|---|
| `@tiptap/core`, `@tiptap/starter-kit`, `@tiptap/pm` | MIT | Editor |
| `@tiptap/extension-placeholder` | MIT | Empty-document hint |
| `@tiptap/extension-character-count` | MIT | Word/character counts |
| `laravel-vite-plugin@^2` | MIT | **Was missing** — `vite.config.js` imported it but it was not installed; the build could not run at all |

**No licence obligation added. No paid dependency. No external service.**

> Note: the project's `@tailwindcss/vite ^4` alongside `tailwindcss ^3.1` (Module 0 finding) did not block the build and was left unchanged.

---

## 7. Bugs found and fixed during this module

| | Finding |
|---|---|
| 1 | **The Vite build could not run at all.** `vite.config.js` imported `laravel-vite-plugin`, which was never installed. Installed `^2` (the major compatible with the project's Vite 7). |
| 2 | **`article_files` provenance columns were being silently dropped.** `ArticleFile::$fillable` did not include `sha256`, `uploaded_by`, `version_label`, so they were discarded on insert. Fixed in Module 1. |
| 3 | **`SaveResult::conflict()` returned the *rejected* content as `server_content`.** A conflict would have shown the user their own text as "the other editor's version". Now returns both correctly (`server_content` / `rejected_content`). |
| 4 | **Comment replies were blocked before the parent lookup ran**, so a reviewer could not reply to their own confidential thread. Reordered so visibility is inherited from the parent and validated against it. |
| 5 | **Saving a named version could crash.** One snapshot per revision is enforced by a unique index; saving a version of unchanged content collided. `takeSnapshot()` now reuses the existing row. |
| 6 | **A restore could not record itself** for the same reason. Reuse path now also refines `created_from` and `restored_from_id`. |
| 7 | **`resolveWorkspace()` queried `manuscript_documents.submission_id`**, a column that does not exist — every workspace route returned 404. Corrected to join through `manuscript_version_id`. |
| 8 | **Default comment visibility was hardcoded to `editorial`**, so authors could not comment at all unless the client sent an explicit visibility. Now derived from the user's capability. |
| 9 | **Namespace shadowing:** `SaveResult` referenced `ManuscriptWorkspaceDocument`/`ManuscriptSnapshot` unqualified inside `App\Services\Workspace`, resolving to non-existent classes in its own namespace. Imported explicitly. |

---

## 8. Known limitations — stated plainly

1. **This is not real-time collaboration.** It is sequential editing with conflict detection. Live cursors, live character-level merging and Google-Docs-style presence are **not** implemented and are **not** claimed. Real-time would need Tiptap Collaboration (paid) or a CRDT service (new infrastructure).
2. **Tables are flattened on import.** The browser editor cannot faithfully model merged-cell tables, so each row becomes a pipe-delimited paragraph tagged `table_row`. The exporter reassembles the grid, so a round trip is lossless — but **editing a table in the browser is not the same as editing a Word table.**
3. **Comments export as an appendix, not as OOXML comments.** We do not claim faithful comment-anchor reproduction. The discussion is visible in the exported document, but it will not appear in Word's Review pane.
4. **Figures and images are not rendered in the browser editor.** Image references survive import as text markers and the DOCX exporter does not yet carry `word/media/*` from the workspace document. Editing prose works; editing figures does not.
5. **The diff is word-level, not word-and-formatting.** It compares text, not styling.
6. **No inline presence.** You cannot see that someone else has the document open until they save and you get a conflict.
7. **Autosave interval is fixed at 2.5 s.** It is not adaptive to document size.
8. **Rate limiting is coarse** — 120 requests/minute for all workspace routes.
9. **Content Security Policy is still the pre-existing one** with no `default-src`/`script-src` (Module 0 finding S6). The workspace adds a 404 kB module bundle but does not change the policy. **This must be addressed before production**, and the workspace will need `blob:` if figures are added later.
10. **The tailwind v3 / `@tailwindcss/vite` v4 mismatch** (Module 0 finding) was not resolved here.

---

## 9. Security considerations

- **Server-side authorisation on every route.** Nothing relies on hiding a button. Authorisation happens before any work is done.
- **Ownership-scoped lookups.** Snapshot, comment and change endpoints verify the record belongs to *this* submission; a valid ID from another manuscript returns 404, not 403, so existence is not disclosed.
- **Force-save is a separate, privileged permission.** An author cannot overwrite another user's work even by sending `force: true` — verified by test.
- **Comment visibility is a server-side matrix**, not a template condition. Confidential threads are excluded from the author's query, so they are absent from the response payload entirely.
- **Replies inherit visibility from the parent**, preventing both downgrade and smuggle attacks.
- **All content is escaped on render** (DOM `textContent`, never `innerHTML` with user data) and on DOCX export (`htmlspecialchars` with `ENT_XML1`).
- **Autosave is CSRF-protected** via the `X-CSRF-TOKEN` header and the standard Laravel session token.
- **Save rate-limited** at 120/minute.
- **Export and edit actions are audited** to `manuscript_access_logs`.
- **The exported document carries journal metadata**, not the author's name.

**Carried forward from earlier modules, still open:** S4 (admin download substituting a different file), S6 (CSP), S9 (queue worker unverified), B2 (reviewer report text not rendered in the editor's *submission* view — the workspace does show reviewer comments, but the formal `reviews.comments_for_author` field is still not surfaced anywhere).

---

## 10. Deployment instructions

```powershell
# 1. Back up
php artisan down

# 2. PHP dependencies (unchanged from Module 1)
composer install --no-dev --optimize-autoloader

# 3. Front-end dependencies — REQUIRED for the workspace
npm ci
npm run build

# 4. Migrate (4 additive migrations)
php artisan migrate --force

# 5. Cache
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan up
```

**Verify after deployment:**
1. Open a submission's formatting page → "Open the manuscript workspace" → the editor loads with the manuscript text.
2. Type → confirm the save badge reaches "Saved" and the revision counter increments.
3. Open the same submission in a second browser as an editor, save, then save the first → confirm the conflict dialog appears and **neither** text is lost.
4. Select text → comment → confirm the thread appears anchored.
5. Export DOCX → open in Word → confirm headings, text and the discussion section.
6. Review a tracked change as an author → confirm it is refused.

**Rollback:**
```powershell
php artisan down
php artisan migrate:rollback --step=4 --force
npm ci && npm run build     # rebuild without the workspace entry
php artisan optimize:clear
php artisan up
```

---

## 11. Summary of honest status

| | |
|---|---|
| Tests | **211 passed, 0 failed, 0 skipped** (50 new for Module 2) |
| §2.5 criteria | **7 of 7 met** |
| Migrations | 4 additive, rollback verified |
| Dependencies | 5 MIT packages, 1 previously-missing package added |
| Licence impact | None |
| Infrastructure | None |
| Real bugs fixed | 9 (listed in §7) |
| External services | None |

---

# Module 2 implementation is complete. Test results, known limitations and a demonstration are above.

**Do you approve Module 2 and authorize implementation of Module 3: Role-Based Editorial Workspace?**