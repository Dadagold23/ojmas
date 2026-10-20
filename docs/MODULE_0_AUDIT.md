# MODULE 0 — EXISTING SYSTEM AUDIT

**Project:** Offa Journal of Multidisciplinary and Advanced Studies (OJMAS Journal)
**Audit date:** 2026-10-18
**Audit type:** Read-only. No production code, configuration, or database structure was modified.

Every statement below is marked:
- **[V]** = verified by reading files, running commands, or querying the live development database.
- **[A]** = assumption or inference, explicitly not verified.

---

## A. Verified Architecture and Environment

### A.1 Runtime stack [V]

| Component | Verified value | Command / source |
|---|---|---|
| Laravel | **12.54.1** | `php artisan --version` |
| PHP | **8.2.12** (ZTS, Visual C++ 2019 x64, Windows build) | `php -v` |
| Composer | 2.10.2 | `composer --version` |
| Node / npm | 24.19.0 / 11.17.0 | `node -v`, `npm -v` |
| Database | MySQL/MariaDB via XAMPP, `ojmas_journal` @ 127.0.0.1:3306 | `.env`, `config/database.php` |
| Session driver | `database` | `php artisan about` |
| Cache driver | `database` | `php artisan about` |
| Queue driver | `database` | `php artisan about` |
| Mail | `smtp` | `php artisan about` |
| Broadcast | `log` | `php artisan about` |
| Spatie Permission | **6.25.0** | `php artisan about` |

### A.2 Composer dependencies (production) [V]

```
barryvdh/laravel-dompdf      ^3.1
laravel/framework            ^12.0
laravel/tinker               ^2.10.1
spatie/laravel-permission    ^6.25
```
**That is the entire production dependency list.** No DOCX library, no PDF text extractor, no PDF-to-image library, no headless-browser driver.

Dev dependencies: fakerphp/faker, laravel/breeze ^2.4, laravel/pail, laravel/pint, laravel/sail, mockery, nunomaduro/collision, phpunit ^11.5.

### A.3 npm dependencies [V]

`@tailwindcss/forms`, `@tailwindcss/vite ^4`, `alpinejs ^3.4.2`, `autoprefixer`, `axios`, `concurrently`, `postcss`, `tailwindcss ^3.1`, `vite ^7`.

**No rich-text editor of any kind is installed.** No CKEditor, no Tiptap, no Quill, no Lexical.

> ⚠ Pre-existing inconsistency: `@tailwindcss/vite ^4.0.0` is declared alongside `tailwindcss ^3.1.0`. Tailwind v4 and v3 are not compatible. This must be resolved before adding any large editor bundle.

### A.4 PHP extensions actually loaded [V]

**Present:** bcmath, bz2, calendar, ctype, curl, date, dom, exif, fileinfo, filter, ftp, gettext, hash, iconv, json, libxml, mbstring, mysqli, mysqlnd, openssl, pcre, PDO, pdo_mysql, pdo_sqlite, Phar, random, readline, Reflection, session, SimpleXML, SPL, standard, tokenizer, xml, xmlreader, xmlwriter, Zend OPcache, zip, zlib

**Absent (and required by the plan):**
- ❌ **`gd`** — no image resizing/rasterisation
- ❌ **`imagick`** — no ImageMagick / Ghostscript PDF pipeline
- ❌ **`intl`** — degraded `NumberFormatter`, collation, `transliterator_transliterate`
- ❌ `bcmath` is present; `sockets`, `pcntl`, `posix` absent (no long-running worker supervision available in-process)

**Critical compatibility facts:**
- ✅ **`zip` present** → OOXML (`.docx`) ZIP container reading/writing is feasible in pure PHP.
- ✅ **`dom` + `xmlreader` + `xmlwriter` + `SimpleXML`** present → direct OOXML XML manipulation is feasible.
- ❌ **`gd`/`imagick` absent** → **there is no viable automated DOCX→PDF raster route on this stack.** See §H.2.

### A.5 Repository state [V]

- Git: branch `main`, **a single commit** `c6e6abc "Initial commit"`, plus **33 modified uncommitted files** in the working tree.
- No CI configuration (`.github/` absent).
- `storage/logs/OJMAS_Journal.log` is **18.2 MB**; `storage/logs/laravel.log` is 476 KB.
- Uploads and logs are **correctly excluded** from git via nested `.gitignore` files (`storage/app/private/.gitignore`, `storage/app/private/submissions/.gitignore`, `storage/logs/.gitignore`). Verified with `git check-ignore`. **No manuscript files are committed.**

### A.6 Migration state [V]

**31 migrations, all Ran, all in batch 1.** No pending migrations. No destructive migrations detected.

### A.7 Development database is EMPTY [V] — important

A read-only query against the live development database returned:

| Table | Rows |
|---|---|
| `roles` | **0** |
| `permissions` | **0** |
| `users` | **0** |
| `submissions` | **0** |
| `article_files` | **0** |
| `reviews`, `reviewer_assignments`, `editorial_decisions` | **0** |
| `articles`, `issues`, `volumes` | **0** |
| `payments`, `invoices`, `receipts` | **0** |
| `audit_logs`, `activity_logs` | **0** |

Yet **15 manuscript files** (`.docx` + `.pdf`, including one 0-byte PDF) and **30 published/compiled journal PDFs** exist in `storage/`.

**Conclusion:** the local database is a freshly migrated, unseeded shell. Production data lives on the remote host (`/home/ojmas/laravel-app`, evidenced by log stack traces). **All Module 1+ testing must build its own fixtures; nothing can be assumed from existing records. Conversely, no risk of damaging real records exists in this working copy.**

### A.8 Application structure [V]

```
app/Http/Controllers/   Public(4) Auth(8) Admin(7) Editor(6) Author(3) Reviewer(3) Profile + PaystackWebhook
app/Http/Middleware/    RoleMiddleware, SecurityHeadersMiddleware, TrustProxies,
                        ValidateProductionHostMiddleware, EnsureTwoFactorEnabled (unregistered)
app/Http/Requests/      StoreSubmissionRequest, AssignReviewerRequest, EditorialDecisionRequest,
                        SubmitReviewRequest, LoginRequest, ProfileUpdateRequest
app/Models/             21 models
app/Notifications/      12 notifications (all ShouldQueue, all mail + database)
app/Policies/           SubmissionPolicy (only policy; auto-discovered)
app/Services/           Payments/PaymentGatewayInterface, PaystackService, ReceiptService,
                        SubmissionPaymentService; Publishing/PublicationService;
                        Exports/PaymentExportService, SubmissionExportService
routes/                 web.php (161 lines), auth.php, console.php
resources/views/        79 Blade files
database/migrations/    31
database/seeders/       DatabaseSeeder, AdminUserSeeder, RolePermissionSeeder,
                        JournalSettingSeeder, PaymentCategorySeeder, SampleVolumeAndIssueSeeder
database/factories/     UserFactory (only one)
tests/                  7 files, all inherited from Laravel Breeze. Zero domain tests.
```

**Jobs: none. Events: none (except Laravel's `Registered`). Listeners: none.**

### A.9 Middleware and authorization wiring [V]

`bootstrap/app.php`:
- Aliases: `role` → custom `RoleMiddleware`; `permission` → Spatie `PermissionMiddleware`; `role_or_permission` → Spatie `RoleOrPermissionMiddleware`.
- Appended to web group: `SecurityHeadersMiddleware`.
- CSRF except: `webhooks/paystack`.
- Production only: `TrustProxies`, `ValidateProductionHostMiddleware`, `URL::forceScheme('https')`.
- **`SubmissionPolicy` is NOT explicitly registered** — it works via Laravel 12's convention-based policy auto-discovery (`App\Models\Submission` → `App\Policies\SubmissionPolicy`). [V]

`RoleMiddleware` accepts pipe- or comma-separated role lists, splits both, and calls `$user->hasAnyRole()`.

### A.10 Existing test suite — ACTUAL RESULTS [V]

Command run: `php artisan test`

```
Tests:  3 failed, 24 passed (75 assertions)
Duration: ~79s
```

The three failures:

| Test | Failure |
|---|---|
| `Feature\ExampleTest > the application returns a successful response` | **500** — `PDOException: no such table: articles` |
| `Feature\Auth\EmailVerificationTest > email verification screen can be rendered` | **302** received, 200 expected |
| `Feature\ProfileTest > profile information can be updated` | `email_verified_at` not nulled after email change |

Root cause of #1 is verified: `phpunit.xml` sets `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:` but **`tests/TestCase.php` does not use `RefreshDatabase`**, so no migrations are ever run in the test environment.

**Verdict: the existing test suite is Breeze scaffolding with zero coverage of submissions, files, payments, permissions, the editorial workflow, or PDF generation.** It must be rebuilt as a foundation before any module can be honestly verified.

---

## B. Existing Submission and Editorial Workflow

### B.1 Verified current flow

1. **Register** — `POST /register` creates the user with legacy `role='Author'`, creates the Spatie role if missing, calls `syncRoles(['Author'])` and `syncLegacyRoleColumn('Author')`. Email verification is conditional on `config('auth.email_verification_enabled')` (= `env('EMAIL_VERIFICATION_ENABLED', true)`; currently `false` in `.env`).
2. **Submit** — `POST /author/submissions` (`Author\SubmissionController::store`, throttle 5/min):
   - `Submission` created with `submission_no = 'SUB-' . Ymd . '-' . 6 random chars`, `status = pending_payment`.
   - Co-authors written to `article_authors`.
   - Files stored to the `private` disk under `submissions/{manuscript,cover_letter,supplementary}` with a random 40-char filename; `ArticleFile` rows created.
   - `SubmissionStatusHistory` row created.
   - Notification queued to the journal office via `dispatch(fn)->afterResponse()`.
   - Paystack vetting-fee redirect initiated.
3. **Pay** — `Payment`, `Invoice`, `Receipt`; `hasPaidVettingFee()` gate.
4. **Assign reviewer** — `POST /editor/submissions/{s}/assign-reviewer`. Requires `hasPaidVettingFee()` and status ∈ `{submitted, revision_requested}`. Creates `ReviewerAssignment`, forces status → `under_review`, notifies the reviewer.
5. **Review** — `POST /reviewer/assignments/{assignment}/reviews` → `ReviewController::submit`. Creates `reviews` row, sets assignment → `completed`, notifies all Editors.
6. **Decide** — `POST /editor/submissions/{s}/decision`. Creates `EditorialDecision`, sets status directly to the decision value, notifies the author.
7. **Revise** — author may upload/delete files while status ∈ `{submitted, revision_requested}` and the vetting fee is paid.
8. **Assign for publication** — `POST /admin/submissions/{s}/assign-editor` (status must be `accepted`, publication fee paid) → sets `assigned_editor_id`, status → `screening`.
9. **Publish** — `POST /editor/submissions/{s}/publish` → `PublicationService::publish()` creates/updates `Article` + `ArticlePublication`, uploads PDF to the **public** disk, sets status → `published`, writes one `ActivityLog` row.

### B.2 Verified data model for the editorial workflow

```
submissions.status ENUM('draft','pending_payment','submitted','screening',
                        'under_review','revision_requested','accepted','rejected','published')

reviewer_assignments.status ENUM('assigned','accepted','declined','completed')
reviews:  reviewer_assignment_id, recommendation ENUM('accept','minor_revision','major_revision','reject'),
          comments_for_author, comments_for_editor, status, submitted_at
editorial_decisions: submission_id, editor_id, decision ENUM('accepted','rejected','revision_requested'), message
submission_status_histories: submission_id, changed_by, old_status, new_status, note
```

### B.3 VERIFIED DEFECTS in the existing workflow

These are facts in the current code. They are listed because Module 3 (role workspace) and Module 4 (workflow) build directly on them.

| ID | Severity | Finding | Evidence |
|---|---|---|---|
| **B1** | **Critical** | `Reviewer\AssignmentController::storeReview()` writes `submission_id`, `reviewer_id`, and `comments` into the `reviews` table. **None of those columns exist.** `reviews` has `reviewer_assignment_id, recommendation, comments_for_author, comments_for_editor, status, submitted_at`. The route `POST /reviewer/assignments/{assignment}/reviews` is live and will throw `SQLSTATE[42S22] Unknown column`. | `app/Http/Controllers/Reviewer/AssignmentController.php:44-51` vs live `reviews` schema |
| **B2** | **Critical** | **Reviewer report text is never displayed anywhere.** Grep for `comments_for_author` and `comments_for_editor` across all 79 Blade views returns **0 matches**. Editors cannot read reviewer reports in the UI. | `resources/views/**` grep |
| **B3** | Medium | `Reviewer\DashboardController` counts `status = 'pending'`, but the enum has no `pending` value → the "Active" KPI is permanently 0. | `Reviewer/DashboardController.php:19` |
| **B4** | Medium | `EnsureTwoFactorEnabled` reads `$user->two_factor_secret`. **No `two_factor_secret` column exists on `users`.** The middleware is also not registered in `bootstrap/app.php`. Dead code. | `EnsureTwoFactorEnabled.php:14`; live `users` schema |
| **B5** | **High** | `SubmissionPolicy::view()` returns `true` for **any** user holding the `Reviewer` role for **any** submission. There is no assignment scoping at the policy level. | `app/Policies/SubmissionPolicy.php:12-14` |
| **B6** | **High** | `Submission::canBeViewedBy()` likewise grants any `Reviewer` the right to open any author's submission detail page. `Author\SubmissionController::show()` uses it. ID enumeration yields full submission detail (abstract, keywords, files, payment status). | `app/Models/Submission.php:115-121`, `Author/SubmissionController.php:188-190` |
| **B7** | Medium | The `audit_logs` table and `AuditLog` model exist with a full `old_values`/`new_values`/`ip_address`/`user_agent` schema, but **nothing in `app/` ever writes to it.** `ActivityLog` is written from exactly one place (`PublicationService`). | `grep AuditLog` in `app/` → 2 hits, both the model |
| **B8** | Medium | In `config/filesystems.php`, the `local` disk root **and** the `private` disk root are **both** `storage_path('app/private')`. `FILESYSTEM_DISK=local`, so the *default* disk is the private manuscript directory. Any code that calls `Storage::put($path)` without an explicit disk writes into the manuscript store. | `config/filesystems.php:33-69` |
| **B9** | Medium | `article_files` has **no checksum column**. There is no way to prove a stored manuscript is byte-identical to what was uploaded. | live schema |
| **B10** | Medium | `users.role` (legacy VARCHAR) **and** Spatie `model_has_roles` are both authorization sources. `User::hasRole()` and `User::scopeRole()` are overridden to bridge them, including a `Managing Editor` ⇄ `Managing Admin` alias. Any new permission layer must live *on top of* this shim, not replace it. | `app/Models/User.php:65-148` |
| **B11** | Opportunity | `JournalSetting` model + `journal_settings` table exist and are **never read**. Journal identity (ISSN, fees, address, publisher) is hardcoded in `HomeController::$journalHighlights`. **This is the natural home for the OJMAS style profile.** | `grep JournalSetting` in `app/` → 1 hit (the model) |
| **B12** | Constraint | `submissions.status` is a MySQL `ENUM` with 9 fixed values. Module 4's 12 configurable stages cannot be added without altering the enum. | live `SHOW COLUMNS` |
| **B13** | Gap | **No revision-round counter, no stage column, no version table, no deadline/reminder fields** exist anywhere. | full schema review |
| **B14** | Gap | `RolePermissionSeeder` creates **5 roles and 0 permissions**. `RolePermissionSeeder` creates `Managing Editor` but not `Admin`; `AdminUserSeeder` creates `Admin` but not `Managing Editor`. The `permission:` middleware alias is registered but **never used on any route**. | seeders + `routes/web.php` |
| **B15** | Medium | `EditorialController::downloadFile()` on the **admin** route: when `$file->submission_id !== $submission->id`, instead of returning 404 it **silently substitutes a different file** (a manuscript fallback). Confused-deputy pattern. | `Editor/EditorialController.php:188-210` |
| **B16** | Low | `ArticleFile::resolvedPrivatePath()` probes **five candidate paths** including a type-derived guess. It was clearly written to recover from historical path bugs. It makes file resolution non-deterministic. | `app/Models/ArticleFile.php:24-62` |

### B.4 What the existing workflow can be reused for

| Component | Reuse decision |
|---|---|
| `submissions` / `submission_no` / `slug` / `status` | **Reuse as the single source of truth.** Never create a parallel manuscript table. |
| `article_files` + `resolvedPrivatePath()` + private disk | **Reuse the storage layout** (`submissions/{type}/`). Replace path probing with a deterministic resolver. |
| `submission_status_histories` | Reuse as the append-only workflow log (Module 4). |
| `editorial_decisions`, `reviewer_assignments`, `reviews` | Reuse; extend (add round, invitation, anonymity fields). |
| `article_publications` | Reuse for production/publication. `articles.doi` already exists and is unique. |
| `notifications` table + 12 notification classes + `ShouldQueue` | **Reuse the notification architecture** (Module 4 requirement). |
| Paystack services, `payments`, `invoices`, `receipts` | **Do not touch.** Prompt §5.1 forbids it. |
| `PublicationService` + DomPDF article/issue/receipt PDFs | **Reuse untouched** as the production pipeline. |

---

## C. Existing Role and Permission Implementation

### C.1 Verified roles [V]

Six role names exist in code: `Super Admin`, `Admin`, `Editor`, `Reviewer`, `Author`, `Managing Editor`.

> **Answer to prompt §2: "Determine whether this role already exists."**
> **YES — `Managing Editor` already exists.** It is created by `RolePermissionSeeder` as a Spatie role, and `User::normalizeRoleName()` treats `Managing Editor` and `Managing Admin` as the **same** role (both normalise to `managing_editor_admin`). Therefore `hasRole('Managing Editor')` ⇔ `hasRole('Managing Admin')`, and `scopeRole(['Managing Editor'])` silently also matches `Managing Admin` and vice versa.
> **No new role needs to be created.** Module 3 must extend the *permissions* attached to it, not duplicate it.

### C.2 Verified permissions [V]

**Zero.** The `permissions` table is empty in the development database and no seeder creates any permission. **All authorization in this application is role-name strings passed to the `role:` middleware**, plus ad-hoc `abort_unless()` calls inside controllers.

### C.3 Verified route → role matrix [V]

| Route group | Middleware |
|---|---|
| `/admin/*` (dashboard, reports, exports, submissions, users, manual payments, editor assignment, file recovery) | `role:Super Admin\|Admin\|Managing Editor` |
| `/admin/publications*` | `role:Super Admin\|Admin\|Managing Editor\|Editor` |
| `/editor/*` (dashboard, submissions, assign-reviewer, decision, **publish**, volumes, issues, correspondence) | `role:Editor\|Managing Editor\|Super Admin\|Admin` |
| `/author/*` (dashboard, submissions, files, payments, receipts) | `role:Author\|Super Admin` |
| `/reviewer/*` (dashboard, assignments, reviews, file downloads) | `role:Reviewer\|Super Admin` |
| `GET /dashboard` | `auth` + inline role→redirect logic; also accepts `Managing Admin` |
| `GET /author/submissions/{id}` | `auth` only (outside the role group); authorization delegated to `canBeViewedBy()` |

### C.4 Implications for Module 3 [V]

1. **`Admin` and `Super Admin` currently hold full editorial rights including `POST /editor/submissions/{s}/publish`.** Prompt §3.4 requires the Managing Editor role to *not* inherit unrestricted Super Admin privileges. Today it partly does, because `/editor/*` is shared with `Super Admin` and `Admin`. **Module 3 needs an explicit decision here.**
2. **Publication is not gated on a separate approval.** `EditorialController::publish()` only checks `hasPaidPublicationFee()` and `status ∈ {accepted, screening}`. Prompt §4: *"Do not allow a manuscript to be published simply because an editor has finished editing it."* This must be added.
3. **A permission layer must be additive.** Introducing Spatie permissions must not orphan the legacy `users.role` users. The `User::hasRole()`/`scopeRole()` shim must continue to work unchanged.
4. **Reviewer access is currently role-wide, not assignment-scoped** (B5/B6). Module 3 must invert this.

---

## D. Document-Processing and Editor Technologies Available

### D.1 What exists [V]

| Capability | Status |
|---|---|
| PDF **generation** | ✅ `barryvdh/laravel-dompdf ^3.1`. Used in exactly **3** places: `HomeController::articlePdf` (L267), `HomeController::issuePdf` (L392), `ReceiptService::generate` (L20). All are **HTML → PDF**. |
| PDF **parsing / text extraction** | ❌ none (`smalot/pdfparser`, `thioubvang/tpdfparser` etc. absent) |
| DOCX **read/write** | ❌ none (`phpoffice/phpword` absent) |
| DOCX → PDF conversion | ❌ none — no Imagick, no Ghostscript, no LibreOffice binding, no headless Chrome driver |
| Rich-text editor (JS) | ❌ none |
| Comments / tracked changes | ❌ none |
| Versioning | ❌ none |
| Private file delivery | ✅ `Storage::disk('private')->download()` in 3 controllers |
| Stored files | ✅ 15 submission files, 30 published/compiled PDFs on this machine |

### D.2 Direct answer to prompt §2.4

> *"Do not assume DomPDF can faithfully convert arbitrary DOCX documents; select and test a suitable conversion method."*

**Verified conclusion: DomPDF cannot read DOCX at all** — it is an HTML renderer with no OOXML parser, and `gd`/`imagick` are absent, so it cannot rasterise embedded images or delegate to a PDF engine.

**There is no automated DOCX→PDF conversion method available on this stack, and none can be added without new infrastructure** (a Document Server, or `ext-gd`/`ext-imagick` + Ghostscript installed on the host).

**Recommendation: retain the existing, working production path** — the editor uploads the final typeset PDF, which is stored on the public disk and served to readers. This is what the journal does today and it already works. I will document this as a **known limitation with a stated rationale**, rather than claim an automated pipeline that does not exist. **[Requires your confirmation — see §J.6]**

---

## E. Recommended Implementation Architecture

### E.1 Component selection and rationale

**Editor: Tiptap (ProseMirror), MIT, self-hosted.**

| Criterion | CKEditor 5 (self-host) | **Tiptap (ProseMirror)** | ONLYOFFICE Docs |
|---|---|---|---|
| Licence | GPL-2.0 core; **DOCX import/export, Comments, Track Changes, Revision History, OT Collaboration are all PAID commercial plugins** | **MIT** (ProseMirror MIT) | AGPL-3.0 Community Edition; paid above 20 concurrent connections |
| Cost for OJMAS | Paid (fails budget) | **Free** | Infra + possible licence |
| DOCX import/export | Paid plugin | Custom build | **Best in class** |
| Tracked changes | Partial/unreliable in OSS build | Custom build | Native, complete |
| Comments | Paid plugin | Custom build | Native, complete |
| Version history | Paid plugin | Custom build | Native, complete |
| Concurrent editing | Not native | Yjs (OSS) possible; paid Collaboration otherwise | Native |
| Resource footprint | ~1 MB JS | ~300 KB–1 MB JS | **Separate Document Server: ≥2 GB RAM, Docker/native package** |
| Fits current stack (Vite + Tailwind + Alpine + Blade) | Yes | **Yes, natively** | Requires a new service + reverse proxy |
| Hosting on current stack | Yes | **Yes** | **No** |

**Decision: Tiptap.** The deciding factor is that CKEditor's free self-hosted build **cannot meet Module 2's acceptance criteria** (DOCX import/export, comments, track changes, version history are premium-only), while ONLYOFFICE requires a Document Server with ≥2 GB RAM that the current hosting cannot provide. Tiptap is MIT, self-hosts inside the existing Vite/Tailwind/Alpine stack, and introduces zero new infrastructure, zero new services, and zero licence exposure.

**The accepted trade-off, stated honestly:** Tiptap does not give free DOCX fidelity or native tracked changes. We therefore build a **deterministic PHP OOXML service (Module 1)** that owns DOCX round-tripping, and we build track-changes/version-history storage in Laravel. **This is more engineering work, but it is deterministic, testable, and licence-clean.** I will not claim native DOCX fidelity we have not demonstrated.

### E.2 DOCX parsing and generation strategy (prompt deliverable 6)

**Library: `phpoffice/phpword` (MIT)** for high-level OOXML read/write, plus a hand-written OOXML layer over `ext-zip` + `ext-dom` for constructs PhpWord does not model: tracked changes (`w:ins`/`w:del`), comment ranges (`w:commentRangeStart`/`w:commentRangeEnd`), footnote/endnote preservation, content controls, and OMML equations.

**Five-stage pipeline:**

1. **Ingest (immutable).** Validate container magic bytes (`PK\x03\x04`), reject `vbaProject.bin` (macro-enabled), enforce uncompressed-size and entry-count ceilings (zip-bomb defence), reject path-traversal entry names, compute SHA-256. Store the original on the `private` disk. **The original is never opened for writing and never overwritten.**

2. **Structural pass (read-only).** Unzip to a temp dir. Parse `word/document.xml`, `styles.xml`, `numbering.xml`, `footnotes.xml`, `endnotes.xml`, `comments.xml`, `word/_rels/*`, `[Content_Types].xml`. Build a neutral `ManuscriptDocument` AST of typed blocks: `Heading(level)`, `Paragraph`, `Run(italic/bold/superscript)`, `Table`, `Figure(alt)`, `Equation(omml)`, `FootnoteRef`, `Citation(raw)`, `ReferenceEntry(raw)`, `Blockquote`, `Divider`. **Any node the parser does not recognise becomes an `UnsupportedElement` node that is preserved verbatim and flagged — never silently dropped** (prompt §1.3).

3. **APA 7 rule pass.** A versioned, unit-testable rule set. Two profiles:
   - `apa7_professional` — the APA 7 baseline.
   - `ojmas` — journal overrides, below.
   
   Rules cover page size/margins, font family and size, line spacing, paragraph indentation, page numbering, title-page layout, the five heading levels, in-text citation presentation, hanging indent and reference-list ordering, DOI/URL presentation, table and figure numbering/titles/notes/placement, appendix presentation, and capitalisation/italics/punctuation/spacing.

4. **Render.** Rebuild a DOCX from the AST into a **new** `ArticleFile` row. The original is untouched. A human-readable diff summary of every change is produced for the validation report.

5. **Validate.** Citation/reference cross-check (see §E.3) producing a structured report.

### E.3 Citation & reference validation (prompt §1.5)

Deterministic only — **no AI-generated bibliographic content** (prompt rule 10):

- In-text citations with **no** matching reference entry.
- Reference entries **never cited** in the text.
- Author-name and year inconsistencies between in-text citations and reference entries (e.g. `(Smith, 2020)` vs `Smyth, S. (2021)`).
- Incomplete bibliographic records (missing year, source, volume, pages, DOI).
- Probable duplicates (normalised author+year+title fingerprint).
- Malformed DOI (must match `^10.\d{4,9}/…`) and malformed/dead-looking URLs.
- Citation patterns needing manual inspection (page ranges, secondary citations, `et al.` misuse, missing locator).

**Never invented.** The service never produces an author, title, year, DOI, or page range it did not read from the document. Optional external metadata lookup (Crossref) is **off by default** and returns results labelled explicitly as `verified` vs `unverified_suggestion` in a separate field. The service never asserts that a citation *supports* a claim — it only asserts bibliographic presence and consistency.

### E.4 The OJMAS style profile — derived from verified journal policy

From `resources/views/guidelines.blade.php` (the journal's own published author guidelines) [V]:

| Requirement | Verified source | Overrides APA 7 professional? |
|---|---|---|
| **A4 paper** (not US Letter) | L71 "double spacing on A4 paper" | **Yes** |
| **Double spacing** | L71 | Matches APA 7 |
| **Title ≤ 18 words** | L56 | **Yes** — a journal constraint |
| **Author names + affiliations on a separate cover page** | L57 | **Yes** — APA 7 professional puts these on the title page |
| **Abstract on a separate sheet, no author names, ≤ 150 words** | L58 | **Yes** — APA 7 allows 150–250 and permits author names |
| **"Latest APA referencing style"** | L59 | Baseline = APA 7 |
| **Blinded review copy required** | L90 "all author information has been separated from the blinded review copy where required" | **Yes** — drives the confidentiality model |

**Conclusion:** OJMAS is closest to APA 7 **professional** paper conventions, with three material journal-specific overrides (A4, 18-word title limit, separate anonymous abstract sheet) **plus** a blinding requirement. **Student-paper rules must not be imposed.**

---

## F. Proposed Database Changes (prompt deliverable 4)

**All changes are strictly additive.** No table is dropped. No existing column is altered, renamed, or removed. `submissions.status` (ENUM) is **not** touched — a separate nullable `current_stage` column is added and kept in sync by the workflow service, so every existing query, view, and filter continues to work unchanged.

Every migration is written with a reversible `down()` that removes only what that migration created.

### F.1 Module 1 — ingestion & formatting

```sql
-- F1. sha256 + provenance on the existing article_files table (nullable => safe)
ALTER TABLE article_files
  ADD COLUMN sha256         CHAR(64) NULL,
  ADD COLUMN is_anonymised  TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN version_label  VARCHAR(120) NULL,
  ADD COLUMN uploaded_by    BIGINT UNSIGNED NULL;   -- FK users, SET NULL

-- F2. Immutable version container
manuscript_versions
  id, submission_id FK, version_number INT, label VARCHAR(120),
  source_file_id FK->article_files NULL, storage_path VARCHAR(255),
  mime_type, file_size BIGINT, sha256 CHAR(64),
  format ENUM('original','formatted','editorial'),
  origin ENUM('author_upload','apa7_format','editorial_edit','admin_recovery'),
  created_by FK->users NULL, created_at, updated_at
  UNIQUE (submission_id, version_number)

-- F3. Canonical parsed document
manuscript_documents
  id, manuscript_version_id FK, format('docx'), storage_path, sha256,
  rule_set_version VARCHAR(40), word_count INT, paragraph_count INT,
  structure_json LONGTEXT,            -- the AST
  status ENUM('queued','parsing','parsed','failed'),
  error_message TEXT NULL, created_at, updated_at

-- F4. One validation report per run
manuscript_validations
  id, manuscript_document_id FK, submission_id FK,
  profile ENUM('apa7_professional','ojmas'), profile_version VARCHAR(40),
  status ENUM('queued','running','completed','failed'),
  auto_fixed_count, author_action_count, unverifiable_count,
  unsupported_count, warning_count, error_count INT,
  report_json LONGTEXT, error_message TEXT NULL,
  approved_by FK->users NULL, approved_at DATETIME NULL,   -- author approval gate
  created_at, updated_at

-- F5. Individual findings (errors vs warnings stay distinguishable, per §1.6)
manuscript_validation_issues
  id, manuscript_validation_id FK,
  severity ENUM('error','warning','info'),
  category VARCHAR(60),        -- layout | typography | citation | reference | structure | conversion
  code VARCHAR(80),
  rule_id VARCHAR(80), rule_version VARCHAR(40),
  location_json JSON,          -- paragraph index / page / table index
  message TEXT, suggestion TEXT NULL,
  was_auto_fixed TINYINT(1) DEFAULT 0,
  requires_author_action TINYINT(1) DEFAULT 0,
  created_at

-- F6. Extracted citations & references (deterministic; never fabricated)
manuscript_citations
  id, manuscript_document_id FK, raw_text TEXT, author_token VARCHAR(255),
  year CHAR(4), locator VARCHAR(80), paragraph_index INT, match_key VARCHAR(191)
manuscript_references
  id, manuscript_document_id FK, ordinal INT, raw_text LONGTEXT,
  author_raw, year, title_raw, source_raw TEXT,
  doi_raw VARCHAR(191), url_raw TEXT, pages_raw VARCHAR(120),
  parsed_json JSON, match_key VARCHAR(191), duplicate_of_id FK NULL,
  verification_status ENUM('unverified','verified_external','unverified_suggestion')

-- F7. Configurable style profiles (seeded with apa7_professional + ojmas)
journal_style_profiles
  id, slug VARCHAR(80) UNIQUE, name, description, version VARCHAR(40),
  is_active TINYINT(1) DEFAULT 0, rules_json LONGTEXT,
  created_by FK->users NULL, created_at, updated_at
```

### F.2 Module 2 — workspace

```sql
manuscript_comments
  id, manuscript_document_id FK, parent_id FK NULL,
  author_id FK->users, anchor_from_json JSON, anchor_to_json JSON,
  quoted_text TEXT NULL, body LONGTEXT,
  visibility ENUM('author_visible','editorial','reviewer_confidential'),
  status ENUM('open','resolved','reopened'),
  resolved_by FK NULL, resolved_at DATETIME NULL, created_at, updated_at

manuscript_tracked_changes
  id, manuscript_version_id FK, change_uid VARCHAR(64) UNIQUE,
  type ENUM('insertion','deletion'), author_id FK->users,
  body_json LONGTEXT,
  status ENUM('pending','accepted','rejected'),
  reviewed_by FK NULL, reviewed_at DATETIME NULL, created_at, updated_at
```

### F.3 Modules 3 & 4 — roles, workflow, auditing

```sql
-- F8. Additive Spatie permissions, mapped to the 6 existing roles.
-- Existing accounts keep their roles; permissions are only ADDED.

-- F9. Configurable lifecycle (prompt §4: stages must remain configurable)
editorial_stages
  id, slug VARCHAR(80) UNIQUE, name, sort_order INT, is_terminal TINYINT(1),
  requires_payment TINYINT(1) DEFAULT 0,
  allowed_from_json JSON, required_permissions_json JSON, colour_hint VARCHAR(20)
editorial_stage_transitions
  id, from_stage_slug, to_stage_slug, label,
  required_permissions_json JSON, requires_deadline TINYINT(1) DEFAULT 0

-- F10. Workflow state on submissions (ADDITIVE - status ENUM untouched)
ALTER TABLE submissions
  ADD COLUMN current_stage      VARCHAR(80) NULL,
  ADD COLUMN current_version_id BIGINT UNSIGNED NULL,
  ADD COLUMN revision_round     INT NOT NULL DEFAULT 0,
  ADD COLUMN decision_due_at    DATETIME NULL;

-- F11. Revision rounds
revision_rounds
  id, submission_id FK, round_number INT, status ENUM('open','closed','superseded'),
  opened_by FK NULL, opened_at, due_at DATETIME NULL, closed_at DATETIME NULL,
  decision_id FK->editorial_decisions NULL, author_response LONGTEXT NULL,
  UNIQUE (submission_id, round_number)

-- F12. Reviewer invitations & reminders (Module 3.4)
reviewer_invitations
  id, submission_id FK, reviewer_id FK, round_id FK NULL,
  invited_by FK NULL, invited_at, responded_at DATETIME NULL,
  response ENUM('pending','accepted','declined') DEFAULT 'pending',
  last_reminded_at DATETIME NULL, reminder_count INT DEFAULT 0

-- F13. Sensitive document access audit (prompt §3.6 requirement - unmet today, see B7)
manuscript_access_logs
  id, user_id FK, submission_id FK, manuscript_document_id FK NULL,
  action ENUM('view','download','edit','export','print','anonymised_view'),
  ip_address VARCHAR(45), user_agent VARCHAR(255), created_at
  INDEX (submission_id, created_at)

-- F14. Reviewer anonymity (blind_code shown to reviewers instead of author identity)
ALTER TABLE reviewer_assignments
  ADD COLUMN blind_code VARCHAR(32) NULL,
  ADD COLUMN round_id BIGINT UNSIGNED NULL,
  ADD COLUMN invited_at DATETIME NULL,
  ADD COLUMN responded_at DATETIME NULL;

-- F15. Double-blind identifier on submissions
ALTER TABLE submissions
  ADD COLUMN anonymous_code VARCHAR(32) NULL;
```

**Rollback:** every `down()` drops only its own table/column. `F1`, `F10`, `F14`, `F15` drop only added columns. `down()` is written for each migration and will be exercised before deployment.

---

## G. Security and Reviewer-Confidentiality Assessment (prompt deliverable 7)

### G.1 Pre-existing risks — VERIFIED

| ID | Sev | Finding | Evidence |
|---|---|---|---|
| **S1** | **Critical** | **Reviewer access is role-wide, not assignment-scoped.** `SubmissionPolicy::view()` grants any `Reviewer` access to any submission; `canBeViewedBy()` does the same and gates the author-facing `show()` route. | `SubmissionPolicy.php:12`, `Submission.php:115` |
| **S2** | **Critical** | **No reviewer blinding exists anywhere.** `reviewer/assignments/show.blade.php` loads `submission.author` and renders the author view. | `Reviewer/ReviewController.php:45` |
| **S3** | **Critical** | **Filenames leak authorship.** `ArticleFile::original_name` is the author's own filename and is used verbatim in `Storage::download($path, $original_name)`. Authors routinely name files with their names, institutions, or manuscript IDs. DOCX `docProps/core.xml` additionally carries `dc:creator` and `cp:lastModifiedBy`. **Prompt: "Do not assume that hiding a name in the interface makes a document anonymous."** | `EditorialController.php:214-217` |
| **S4** | High | `admin` file download substitutes a *different* file when IDs mismatch instead of 404. | `EditorialController.php:188-210` |
| **S5** | High | **No audit trail is written.** `audit_logs` exists and is empty, forever. Requirement §3.6 "Auditing of sensitive document access and changes" is unmet. | §B.3 B7 |
| **S6** | High | **CSP has no `default-src` and no `script-src`.** Current policy: `base-uri 'self'; frame-ancestors 'self'; form-action 'self' https://checkout.paystack.com; object-src 'none'; upgrade-insecure-requests`. Inline and third-party scripts are unrestricted. A workspace editor will need `blob:`/`data:` for images — this must be a deliberate, tested policy, not an ad-hoc relaxation. | `SecurityHeadersMiddleware.php:24-26` |
| **S7** | Medium | Upload validation uses only Laravel `mimes:` (extension + guessed MIME). No magic-byte validation, no zip-bomb ceiling, no macro/`vbaProject.bin` rejection, no OOXML path-traversal check. `supplementary` additionally accepts `.zip`. | `StoreSubmissionRequest.php:28-30` |
| **S8** | Medium | `EnsureTwoFactorEnabled` references a non-existent `users.two_factor_secret` column and is not registered. | §B.3 B4 |
| **S9** | Medium | **All 12 notifications implement `ShouldQueue` with `QUEUE_CONNECTION=database`.** `DEPLOYMENT.md` instructs the operator to start `queue:work`, but **there is no evidence a worker is supervised on the production host.** If not running, editorial and payment notifications are silently accumulating in `jobs`. This must be verified on production before Module 4 adds more notifications. | `.env`, `DEPLOYMENT.md:83-89` |
| **S10** | Medium | `SESSION_ENCRYPT=false` in the dev `.env`. `.env.example` ships it as `false`; production must be verified as `true`. | `.env`, `.env.example:34` |
| **S11** | Medium | The test suite cannot run migrations and 3 tests fail. There is no safety net for any change. | §A.10 |
| **S12** | Low | No rate limiting on manuscript download endpoints (only on submission create and payment initiate). | `routes/web.php` |
| **S13** | Low | Blade `{{ }}` escapes by default and must be preserved for reviewer/author text. `{!! !!}` must be prohibited in comment rendering. | convention |

**Positives verified:** `.htaccess` blocks `\.env`, `artisan`, `composer.*`, `app|bootstrap|config|database|resources|routes|storage|tests|vendor` from web access; HTTPS is forced for the production host; `Storage::disk('private')` is outside the public web root; Paystack webhook is CSRF-excluded and signature-verified; CSRF protects all other POSTs; uploads are git-ignored.

### G.2 Confidentiality model to be built

- **Double-blind (consistent with the journal's published guidelines, §E.4):** reviewers receive an **anonymised derivative**, never the original. The derivative is produced by a dedicated service that: strips `docProps/core.xml`, `docProps/app.xml`, `word/settings.xml` (`rsid`, `w:author`), and `word/comments*.xml` authorship; rewrites `w:ins`/`w:del`/`w:comment` authors to a neutral code; replaces author names, affiliations, ORCIDs, emails, acknowledgements, and funding identifiers; and is stored under a **neutral generated filename** (`MANUSCRIPT-{anonymous_code}.docx`) — never the author's original filename.
- Every access to a document writes `manuscript_access_logs`.
- Reviewer visibility flags (`author_visible` / `editorial` / `reviewer_confidential`) are enforced server-side, never in the Blade template alone.
- Editors see reviewer identities; authors never see them (existing single-anonymous behaviour) — **unless you instruct otherwise.**

---

## H. Hosting and Deployment Compatibility (prompt deliverable 8)

### H.1 Verified hosting facts [V]

| Aspect | Finding |
|---|---|
| Production root | `/home/ojmas/laravel-app` (Linux) — from log stack traces |
| Web server | Apache + `mod_rewrite` (`deploy/apache/ojmaspublish.online.conf.example`, `.htaccess`) |
| Dev host | Windows XAMPP, `C:\xampp\htdocs\ojmas` |
| Doc-root modes supported | `DocumentRoot → public/` (preferred) **and** project root (root `.htaccess` rewrites into `public/`) |
| Upload limits (`.user.ini`) | `upload_max_filesize=1024M`, `post_max_size=1024M`, `memory_limit=1024M`, `max_execution_time=600`, `max_input_time=600`, `max_file_uploads=50` |
| Production DB failures in log | `Access denied for user 'ojmas_base'@'localhost'` (2026-04-08); `Access denied for user 'root'@'localhost'` (2026-09-07). **The production database credentials were wrong twice.** |
| Log size | `storage/logs/OJMAS_Journal.log` = 18.2 MB, no rotation configured in `config/logging.php` |

### H.2 Compatibility assessment for the new stack [V]

| Requirement | Status | Conclusion |
|---|---|---|
| OOXML ZIP read/write | ✅ `ext-zip` present | **Compatible** |
| OOXML XML parsing | ✅ `dom`, `xmlreader`, `SimpleXML` | **Compatible** |
| Writing DOCX | ✅ via PhpWord + `zip` | **Compatible** |
| DOCX → PDF conversion | ❌ no `gd`, no `imagick`, no Ghostscript, no LibreOffice, no headless browser | **NOT possible on this stack.** Existing manual editor-uploaded-PDF path retained. |
| Browser editor | ✅ client-side only; served from the existing Vite build | **Compatible** |
| Long-running document server | ❌ not available | ONLYOFFICE ruled out |
| Queue worker | ⚠ `database` driver; **worker presence on production unverified** | **Must be verified before Module 4** |
| Redis | ❌ not configured (env placeholders only) | Not required |

### H.3 Licensing position [V]

All currently installed production packages are MIT-compatible: `laravel/framework` (MIT), `barryvdh/laravel-dompdf` (MIT), `spatie/laravel-permission` (MIT).

- `phpoffice/phpword` = **MIT** → **no licence change.**
- `phpoffice/phpspreadsheet` = **MIT** (only if needed; probably not).
- Tiptap / ProseMirror = **MIT** → no licence change.
- CKEditor 5 commercial plugins = paid → rejected.
- ONLYOFFICE CE = **AGPL-3.0** → network-use copyleft; would require licence review. Rejected on infrastructure grounds first.

**Net effect: no new licence obligations and no new paid dependency, if Tiptap + PhpWord are approved.**

---

## I. Module 1 Implementation Plan and Acceptance Tests

### I.1 Build order

| Step | Work | Gate |
|---|---|---|
| M1.0 | **Test-harness foundation.** Add `RefreshDatabase` to `tests/TestCase.php`; add `APP_KEY` to `phpunit.xml`; fix the 3 pre-existing failures; add model factories for `Submission`, `ArticleFile`, `ReviewerAssignment`, `Review`. | Existing suite green |
| M1.1 | Migrations F1–F7 (`up` + tested `down`). | `migrate` + `migrate:rollback` clean |
| M1.2 | `DocxValidator` — magic bytes, zip-bomb ceilings, macro rejection, path-traversal rejection, encrypted/corrupt detection. | Unit tests incl. malicious fixtures |
| M1.3 | `ManuscriptIngestionService` — SHA-256, immutable private storage, status transitions, typed errors. | Original byte-identical after run |
| M1.4 | `DocxStructureParser` → `ManuscriptDocument` AST, incl. `UnsupportedElement` preservation. | Round-trip on 6 sample documents |
| M1.5 | `apa7` rule set as versioned config + `ApaRuleSetTest`. | Rule tests |
| M1.6 | `ojmas` profile (`A4`, 18-word title, separate cover page, anonymous ≤150-word abstract sheet). | Profile tests |
| M1.7 | `DocxRenderer` — AST → new DOCX; inject tracked-change/comment constructs via the OOXML layer. | Opens in Word/LibreOffice; media preserved |
| M1.8 | `CitationValidator` — deterministic cross-reference checks. | Positive + negative fixtures |
| M1.9 | Validation report + preview + author-approval gate + download route. | End-to-end |

### I.2 Acceptance tests (mapped to prompt §1.7)

| # | Criterion | Test |
|---|---|---|
| 1 | Original document unchanged | SHA-256 before/after identical; byte comparison |
| 2 | Formatted DOCX opens successfully | Unzips cleanly; valid `[Content_Types].xml`, `_rels`, `document.xml`; opens in LibreOffice/Word |
| 3 | Tables, images, equations, references, headings preserved or flagged | Byte-for-byte `word/media/*` comparison; OMML/table count parity; every loss produces an `UnsupportedElement` issue |
| 4 | Rules tested against representative samples | ≥6 fixtures: simple, multi-table, image-rich, equation-heavy, 100-reference, deliberately malformed |
| 5 | Report distinguishes errors from warnings | Assert `severity` counts; assert no `error` is downgraded to `warning` |
| 6 | Unsupported content never silently deleted | Assert `unsupported_count == 0` on lossless inputs; assert every flagged element appears in the report |
| 7 | No auto-accept/reject on formatting alone | Assert `submissions.status` is unchanged by a formatting run |
| 8 | Author approval required before editorial progression | Assert the submission cannot advance without `manuscript_validations.approved_by` |
| 9 | Zip-bomb / macro / traversal rejected | Malicious fixtures; assert rejection + typed error |
| 10 | Determinism | Same input → byte-identical output across two runs |

---

## J. Unresolved Decisions Requiring Your Approval

**J.1 — Browser editor.** Proceed with **Tiptap (MIT)**, accepting that DOCX fidelity and tracked changes are *engineered by us* rather than provided natively? The alternatives are **CKEditor 5 paid** (DOCX/comments/track-changes are premium-only) or **ONLYOFFICE Docs Community** (best fidelity, but AGPL-3.0 and needs a ≥2 GB Document Server your current hosting cannot run). *My recommendation: Tiptap.*

**J.2 — DOCX library.** Approve adding **`phpoffice/phpword` (MIT)** as the only new PHP dependency? It is the sole new production package. Alternative is hand-rolling OOXML over `ext-zip`/`ext-dom` — more control, considerably more risk and effort.

**J.3 — Review model.** The journal's published guidelines say author information must be **separated from the blinded review copy**. Should I implement **double-blind** (reviewers see an anonymised derivative and cannot learn author identity), or **single-blind** (reviewers see the manuscript with authors masked in the UI only)? *I strongly recommend double-blind, given your own instruction that "hiding a name in the interface" is not anonymity.* This decision changes the data model and the storage design.

**J.4 — Pre-existing defects B1, B2, B5, B6.** These are live bugs (a crashing reviewer-review endpoint, invisible reviewer reports, and role-wide reviewer access). **Fixing them touches production behaviour.** May I fix them as a prerequisite to Module 1, or must I leave them untouched and work around them?

**J.5 — Test harness.** `tests/TestCase.php` currently cannot run migrations, so 3 tests fail and there is no safety net. May I fix the harness and add factories as Module 1 step M1.0?

**J.6 — DOCX→PDF.** There is no automated conversion path on this stack (no `gd`/`imagick`/Ghostscript/LibreOffice). I propose **retaining the existing editor-uploaded-PDF publication path** and documenting it as a known limitation, rather than installing infrastructure to automate it. Confirm, or should I evaluate adding `ext-gd` + Ghostscript or a headless-browser route?

**J.7 — Managing Editor scope.** Today `/editor/*` is shared by `Editor`, `Managing Editor`, `Super Admin` **and `Admin`**, and any of them can publish. Your §3.4 says Managing Editor must not inherit unrestricted Super Admin. Should **`Admin` lose `POST /editor/submissions/{s}/publish`** and be reduced to payments/users/reports only?

**J.8 — Role/permission strategy.** Permissions are currently **empty**; everything is role strings. May I introduce an **additive** Spatie permission set mapped onto the 6 existing roles, leaving the `users.role` shim intact? This changes how routes are guarded, so I want explicit approval.

**J.9 — Editorial workflow stages.** `submissions.status` is a 9-value MySQL ENUM. My plan **adds** a nullable `current_stage` and a `editorial_stages` config table, keeping the ENUM in sync for backward compatibility. Approve, or would you prefer the ENUM altered to the full 12-stage list?

**J.10 — Production data access.** The local database is empty (0 users, 0 submissions); production data is remote. May I build my own synthetic fixtures for testing, and would you like me to receive a **sanitised database dump** for realistic testing? (Prompt §5.2: *"Never use confidential author manuscripts for testing without authorization."*)

**J.11 — Queue worker on production.** All 12 notifications are `ShouldQueue` on the `database` driver. Do you want me to verify a worker is running, or shall I document it as a deployment prerequisite and add reminder scheduling that degrades gracefully without one?

**J.12 — Log rotation.** `storage/logs/OJMAS_Journal.log` is 18.2 MB with no rotation. May I add daily rotation as part of the deployment work?

---

## Summary of Verified Facts vs Assumptions

**Verified by direct inspection:** every version, dependency, extension, table, column, enum value, route, role name, controller path, line number, test result, log entry, and journal-policy quotation cited above.

**Explicitly NOT verified (requires production access or your confirmation):**
- Production `.env` values, production PHP extensions, production DB user/permissions.
- Whether a queue worker is running in production.
- Whether the 15 manuscript files present on this machine are real submissions (the local DB is empty, so they are orphaned).
- Whether `ojmaspublish.online` resolves to the host described in the logs.
- The true production row counts of every table.

**No source file, configuration file, migration, or database record was modified during this audit.** The only artifact created is this document.

---

# Module 0 audit is complete. Do you approve this architecture and authorize implementation of Module 1: Manuscript Ingestion and APA 7 Formatting Engine?