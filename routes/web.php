<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Editor\EditorialController;
use App\Http\Controllers\Editor\DashboardController as EditorDashboardController;
use App\Http\Controllers\Editor\SubmissionController as EditorSubmissionController;
use App\Http\Controllers\Editor\IssueController as EditorIssueController;
use App\Http\Controllers\Editor\VolumeController as EditorVolumeController;
use App\Http\Controllers\Editor\CorrespondenceController;
use App\Http\Controllers\Author\ManuscriptController;
use App\Http\Controllers\Author\SubmissionController;
use App\Http\Controllers\Author\PaymentController as AuthorPaymentController;
use App\Http\Controllers\Author\ReceiptController as AuthorReceiptController;
use App\Http\Controllers\Reviewer\ReviewController;
use App\Http\Controllers\Reviewer\DashboardController as ReviewerDashboardController;
use App\Http\Controllers\Reviewer\AssignmentController as ReviewerAssignmentController;

use App\Http\Controllers\Admin\PublicationController as AdminPublicationController;
use App\Http\Controllers\Admin\AdCampaignController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ManagingEditorController;
use App\Http\Controllers\Admin\SystemConfigurationController;
use App\Http\Controllers\Author\RevisionController;
use App\Http\Controllers\Author\ProofApprovalController;
use App\Http\Controllers\Editor\EditorWorkspaceController;
use App\Http\Controllers\ManuscriptWorkspaceController;
use App\Http\Controllers\PaystackWebhookController;
use App\Http\Controllers\Reviewer\ReviewerWorkspaceController;

// ─── Paystack webhook (no auth, no CSRF) ────────────────────────────────────
Route::post('/webhooks/paystack', [PaystackWebhookController::class, 'handle'])->name('webhooks.paystack');

// ─── Public routes ────────────────────────────────────────────────────────────
Route::get('/',                        [HomeController::class, 'index'])->name('home');
Route::get('/archives',                [HomeController::class, 'archives'])->name('archives');
Route::get('/guidelines',              [HomeController::class, 'guidelines'])->name('guidelines');
Route::get('/contact',                 [HomeController::class, 'contact'])->name('contact');
Route::get('/editorial',               [HomeController::class, 'editorial'])->name('editorial');
Route::get('/editorial-board',         [HomeController::class, 'editorialBoard'])->name('editorial.board');
Route::get('/editorial-advisers',      [HomeController::class, 'editorialAdvisers'])->name('editorial.advisers');
Route::get('/scope-and-policies',      [HomeController::class, 'scopePolicies'])->name('scope.policies');
Route::get('/call-for-papers',         [HomeController::class, 'callForPapers'])->name('call.for.papers');
Route::post('/contact',                [ContactController::class, 'store'])->name('contact.store');
Route::get('/issues',                  [HomeController::class, 'issues'])->name('issues.index');
Route::get('/issue/{id}',              [HomeController::class, 'issue'])->name('issue.show');
Route::get('/issue/{id}/pdf',          [HomeController::class, 'issuePdf'])->name('issue.pdf');
Route::get('/article/{slug}',          [HomeController::class, 'article'])->name('article.show');
Route::get('/article/{slug}/pdf',      [HomeController::class, 'articlePdf'])->name('article.pdf');
Route::get('/article/{slug}/download', [HomeController::class, 'downloadArticlePdf'])->name('article.download');
Route::get('/article/{slug}/preview',  [HomeController::class, 'articlePreview'])->name('article.preview');
Route::get('/article/{slug}/cite/{format}', [HomeController::class, 'cite'])->name('article.cite');
Route::get('/sitemap.xml',             [HomeController::class, 'sitemap'])->name('sitemap');
Route::get('/calls-for-papers',        [\App\Http\Controllers\AdPublicController::class, 'callsForPapers'])->name('ads.calls');
Route::get('/ads/{campaign}/click',    [\App\Http\Controllers\AdPublicController::class, 'click'])->middleware('throttle:60,1')->name('ads.click');

// ─── Auth routes ──────────────────────────────────────────────────────────────
require __DIR__.'/auth.php';

Route::middleware('auth')->get('/author/submissions/{submission}', [SubmissionController::class, 'show'])
    ->whereNumber('submission')
    ->name('author.submissions.show');

// ─── Authenticated routes ─────────────────────────────────────────────────────
Route::middleware(array_filter([
    'auth',
    config('auth.email_verification_enabled') ? 'verified' : null,
]))->group(function () {

    // Dashboard — role-based redirect
    Route::get('/dashboard', function () {
        $user = auth()->user();
        if ($user->hasRole('Super Admin') || $user->hasRole('Admin') || $user->hasRole('Managing Editor') || $user->hasRole('Managing Admin')) {
            return redirect()->route('admin.dashboard');
        }
        if ($user->hasRole('Editor'))   return redirect()->route('editor.dashboard');
        if ($user->hasRole('Reviewer'))  return redirect()->route('reviewer.dashboard');
        if ($user->hasRole('Author'))    return redirect()->route('author.dashboard');
        abort(403, 'No role assigned to this account.');
    })->name('dashboard');

    // Profile
    Route::get('/profile',    [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile',  [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ── Admin ─────────────────────────────────────────────────────────────────
    Route::prefix('admin')->name('admin.')->middleware('role:Super Admin|Admin|Managing Editor')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/upload-diagnostics', [AdminDashboardController::class, 'uploadDiagnostics'])->name('upload-diagnostics');
        Route::post('/upload-diagnostics', [AdminDashboardController::class, 'runUploadDiagnostics'])->name('upload-diagnostics.run');
        Route::get('/reports',   [ReportController::class, 'index'])->name('reports.index');
        Route::get('/exports/submissions', [ExportController::class, 'submissions'])->name('exports.submissions');
        Route::get('/exports/payments',    [ExportController::class, 'payments'])->name('exports.payments');
        Route::get('/submissions/{submission}',                              [EditorialController::class, 'show'])->name('submissions.show');
        Route::get('/submissions/{submission}/files/{file}/download',        [EditorialController::class, 'downloadFile'])->name('submissions.files.download');
        Route::post('/submissions/{submission}/assign-editor',               [AdminController::class, 'assignEditor'])->name('submissions.assign-editor');
        Route::post('/submissions/{submission}/files/recover',               [AdminController::class, 'recoverMissingManuscript'])->name('submissions.files.recover');
        Route::post('/submissions/{submission}/payments/manual',             [AdminController::class, 'recordManualPayment'])->name('submissions.payments.manual');
        Route::resource('users', UserController::class);

        // ── Managing Editor workflow (§3.4) ───────────────────────────────
        Route::prefix('workflow')->name('workflow.')->middleware('auth')->group(function () {
            Route::get('/',                    [ManagingEditorController::class, 'index'])->name('index');
            Route::get('/reviewers',            [ManagingEditorController::class, 'reviewerTracking'])->name('reviewers');
            Route::post('/invitations/{invitation}/remind', [ManagingEditorController::class, 'remind'])->middleware('throttle:20,1')->name('invitations.remind');
            Route::get('/report',               [ManagingEditorController::class, 'report'])->name('report');
        });

        // ── System configuration (§3.5, Super Admin) ───────────────────────
        Route::prefix('configuration')->name('configuration.')->middleware('auth')->group(function () {
            Route::get('/permissions',                     [SystemConfigurationController::class, 'permissions'])->name('permissions');
            Route::post('/permissions/sync',               [SystemConfigurationController::class, 'resync'])->name('permissions.sync');
            Route::put('/permissions/{role}',              [SystemConfigurationController::class, 'updateRolePermissions'])->name('permissions.role');
            Route::get('/style-profiles',                  [SystemConfigurationController::class, 'styleProfiles'])->name('style-profiles');
            Route::put('/style-profiles/{profile}',        [SystemConfigurationController::class, 'saveStyleProfile'])->name('style-profiles.update');
            Route::post('/style-profiles/{profile}/toggle', [SystemConfigurationController::class, 'toggleStyleProfile'])->name('style-profiles.toggle');
            Route::get('/workflow',                        [SystemConfigurationController::class, 'workflow'])->name('workflow');
            Route::put('/workflow/{stage}',                [SystemConfigurationController::class, 'updateStage'])->name('workflow.update');
            Route::get('/retention',                       [SystemConfigurationController::class, 'retention'])->name('retention');
            Route::put('/retention/{policy}',              [SystemConfigurationController::class, 'updateRetention'])->name('retention.update');
        });
    });

    Route::prefix('admin')->name('admin.')->middleware('role:Super Admin|Admin|Managing Editor|Editor')->group(function () {
        Route::resource('publications', AdminPublicationController::class);
        Route::get('/publications/{publication}/download', [AdminPublicationController::class, 'download'])->name('publications.download');
    });

    // ── Advertisements & Announcements (Addendum M2) ─────────────────────
    // Admin and Super Admin only, and every method additionally checks the
    // specific advertising permission through the gate.
    Route::prefix('admin/advertisements')->name('admin.advertisements.')->middleware('role:Super Admin|Admin')->group(function () {
        Route::get('/',                     [AdCampaignController::class, 'index'])->name('index');
        Route::get('/placements',           [AdCampaignController::class, 'placements'])->name('placements');
        Route::post('/placements',          [AdCampaignController::class, 'updatePlacement'])->name('placements.update');
        Route::get('/create',               [AdCampaignController::class, 'create'])->name('create');
        Route::post('/',                    [AdCampaignController::class, 'store'])->name('store');
        Route::get('/{advertisement}',      [AdCampaignController::class, 'show'])->name('show');
        Route::get('/{advertisement}/edit', [AdCampaignController::class, 'edit'])->name('edit');
        Route::post('/{advertisement}/edit',[AdCampaignController::class, 'update'])->name('update');
        Route::post('/{advertisement}/status', [AdCampaignController::class, 'transition'])->name('transition');
    });

    // ── Editor ────────────────────────────────────────────────────────────────
    Route::prefix('editor')->name('editor.')->middleware('role:Editor|Managing Editor|Super Admin|Admin')->group(function () {
        Route::get('/dashboard',   [EditorDashboardController::class, 'index'])->name('dashboard');

        Route::get('/submissions',                                    [EditorialController::class, 'index'])->name('submissions.index');
        Route::get('/submissions/{submission}',                       [EditorialController::class, 'show'])->name('submissions.show');
        Route::get('/submissions/{submission}/files/{file}/download', [EditorialController::class, 'downloadFile'])->name('submissions.files.download');
        Route::post('/submissions/{submission}/assign-reviewer',      [EditorialController::class, 'assignReviewer'])->name('submissions.assignReviewer');
        Route::post('/submissions/{submission}/decision',             [EditorialController::class, 'decision'])->name('submissions.decision');

        Route::get('/submissions/{submission}/publish',  [EditorSubmissionController::class, 'publishForm'])->name('submissions.publish.form');
        Route::post('/submissions/{submission}/publish', [EditorSubmissionController::class, 'publish'])->name('submissions.publish');

        Route::get('/volumes',        [EditorVolumeController::class, 'index'])->name('volumes.index');
        Route::get('/volumes/create', [EditorVolumeController::class, 'create'])->name('volumes.create');
        Route::post('/volumes',       [EditorVolumeController::class, 'store'])->name('volumes.store');
        Route::post('/volumes/{volume}/toggle-status', [EditorVolumeController::class, 'toggleStatus'])->name('volumes.toggle-status');

        Route::get('/issues',        [EditorIssueController::class, 'index'])->name('issues.index');
        Route::get('/issues/create', [EditorIssueController::class, 'create'])->name('issues.create');
        Route::post('/issues',       [EditorIssueController::class, 'store'])->name('issues.store');
        Route::get('/issues/{issue}', [EditorIssueController::class, 'show'])->name('issues.show');
        Route::post('/issues/{issue}/reorder', [EditorIssueController::class, 'reorder'])->name('issues.reorder');
        Route::post('/issues/{issue}/upload-cover', [EditorIssueController::class, 'uploadCover'])->name('issues.upload-cover');
        Route::post('/issues/{issue}/attach-articles', [EditorIssueController::class, 'attachArticles'])->name('issues.attach-articles');
        Route::post('/issues/{issue}/upload-pdf', [EditorIssueController::class, 'uploadPdf'])->name('issues.upload-pdf');
        Route::delete('/issues/{issue}/delete-pdf', [EditorIssueController::class, 'deletePdf'])->name('issues.delete-pdf');
        Route::post('/issues/{issue}/toggle-status', [EditorIssueController::class, 'toggleStatus'])->name('issues.toggle-status');

        Route::get('/submissions/{submission}/correspondence',  [CorrespondenceController::class, 'index'])->name('correspondence.index');
        Route::post('/submissions/{submission}/correspondence', [CorrespondenceController::class, 'store'])->name('correspondence.store');

        // ── Editorial workspace (§3.3) ──────────────────────────────────────
        Route::prefix('workspace')->name('workspace.')->middleware('auth')->group(function () {
            Route::get('/',                             [EditorWorkspaceController::class, 'dashboard'])->name('dashboard');
            Route::get('/submissions/{submission}',      [EditorWorkspaceController::class, 'show'])->name('show');
            Route::post('/submissions/{submission}/assign-reviewer', [EditorWorkspaceController::class, 'assignReviewer'])->middleware('throttle:20,1')->name('assign');
            Route::post('/submissions/{submission}/decision',        [EditorWorkspaceController::class, 'decide'])->middleware('throttle:20,1')->name('decision');
            Route::post('/submissions/{submission}/transition',      [EditorWorkspaceController::class, 'transition'])->middleware('throttle:30,1')->name('transition');
            Route::get('/submissions/{submission}/blinded-copy',     [EditorWorkspaceController::class, 'blindedCopy'])->name('blinded');
            Route::get('/submissions/{submission}/audit',             [EditorWorkspaceController::class, 'audit'])->name('audit');
        });
    });

    // ── Author ────────────────────────────────────────────────────────────────
    Route::prefix('author')->name('author.')->middleware('role:Author|Super Admin')->group(function () {
        Route::get('/dashboard',                    [SubmissionController::class, 'dashboard'])->name('dashboard');

        Route::get('/submissions',                  [SubmissionController::class, 'index'])->name('submissions.index');
        Route::get('/submissions/create',           [SubmissionController::class, 'create'])->name('submissions.create');
        Route::post('/submissions',                 [SubmissionController::class, 'store'])->middleware('throttle:5,1')->name('submissions.store');
        Route::delete('/submissions/{submission}',  [SubmissionController::class, 'destroy'])->name('submissions.destroy');

        Route::post('/submissions/{submission}/files',              [SubmissionController::class, 'uploadFile'])->name('submissions.files.store');
        Route::delete('/submissions/{submission}/files/{file}',     [SubmissionController::class, 'deleteFile'])->name('submissions.files.destroy');

        Route::get('/payments',                             [AuthorPaymentController::class, 'index'])->name('payments.index');
        Route::post('/payments/initiate/{submission}',      [AuthorPaymentController::class, 'initiate'])->middleware('throttle:10,1')->name('payments.initiate');
        Route::get('/payments/callback',                    [AuthorPaymentController::class, 'callback'])->name('payments.callback');
        Route::get('/receipts/{receipt}/pdf',               [AuthorReceiptController::class, 'pdf'])->name('receipts.pdf');

        // ── APA 7 automated formatting workspace ─────────────────────────────
        // Authorisation is enforced by ManuscriptPolicy on every action; the
        // buttons shown in the interface are a convenience, not the control.
        Route::get('/submissions/{submission}/formatting',              [ManuscriptController::class, 'show'])->name('manuscripts.show');
        Route::post('/submissions/{submission}/formatting',             [ManuscriptController::class, 'format'])->name('manuscripts.format');
        Route::post('/submissions/{submission}/formatting/approve',     [ManuscriptController::class, 'approve'])->name('manuscripts.approve');
        Route::get('/submissions/{submission}/formatting/download',     [ManuscriptController::class, 'download'])->name('manuscripts.download');
        Route::get('/submissions/{submission}/formatting/{validation}', [ManuscriptController::class, 'report'])->name('manuscripts.report');

        // ── Revision response (§3.1) ───────────────────────────────────────
        Route::prefix('revisions')->name('revisions.')->group(function () {
            Route::get('/submissions/{submission}',        [RevisionController::class, 'show'])->name('show');
            Route::post('/submissions/{submission}/respond', [RevisionController::class, 'respond'])->name('respond');
            Route::get('/submissions/{submission}/compare',  [RevisionController::class, 'compare'])->name('compare');
        });

        // ── Proof approval (§4.11) ─────────────────────────────────────────
        Route::prefix('proofs')->name('proofs.')->group(function () {
            Route::get('/submissions/{submission}',  [ProofApprovalController::class, 'show'])->name('show');
            Route::post('/submissions/{submission}', [ProofApprovalController::class, 'store'])->middleware('throttle:10,1')->name('approve');
        });
    });

    // ── Browser manuscript workspace (Module 2) ────────────────────────────
    // Shared across roles: the workspace is opened by authors, reviewers and
    // editors alike, and SubmissionPolicy decides what each of them may do.
    Route::prefix('workspace')->name('workspace.')->middleware([
        'auth',
        'throttle:120,1',
    ])->group(function () {
        Route::get('/submissions/{submission}',                      [ManuscriptWorkspaceController::class, 'show'])->name('show');
        Route::get('/submissions/{submission}/document',            [ManuscriptWorkspaceController::class, 'document'])->name('document');
        Route::post('/submissions/{submission}/save',               [ManuscriptWorkspaceController::class, 'save'])->name('save');
        Route::post('/submissions/{submission}/comments',           [ManuscriptWorkspaceController::class, 'storeComment'])->name('comments.store');
        Route::patch('/submissions/{submission}/comments/{comment}', [ManuscriptWorkspaceController::class, 'updateCommentStatus'])->name('comments.status');
        Route::post('/submissions/{submission}/changes',            [ManuscriptWorkspaceController::class, 'storeChange'])->name('changes.store');
        Route::post('/submissions/{submission}/changes/resolve',    [ManuscriptWorkspaceController::class, 'resolveChange'])->name('changes.resolve');
        Route::get('/submissions/{submission}/versions',            [ManuscriptWorkspaceController::class, 'versions'])->name('versions');
        Route::post('/submissions/{submission}/versions',           [ManuscriptWorkspaceController::class, 'saveVersion'])->name('versions.save');
        Route::get('/submissions/{submission}/versions/{left}/{right}/compare', [ManuscriptWorkspaceController::class, 'compare'])->name('versions.compare');
        Route::post('/submissions/{submission}/versions/{snapshot}/restore',  [ManuscriptWorkspaceController::class, 'restore'])->name('versions.restore');
        Route::get('/submissions/{submission}/export',              [ManuscriptWorkspaceController::class, 'export'])->name('export');
    });

    // ── Reviewer ──────────────────────────────────────────────────────────────
    Route::prefix('reviewer')->name('reviewer.')->middleware('role:Reviewer|Super Admin')->group(function () {
        Route::get('/dashboard',                          [ReviewerDashboardController::class, 'index'])->name('dashboard');
        Route::get('/assignments',                        [ReviewerAssignmentController::class, 'index'])->name('assignments.index');
        Route::get('/assignments/{assignment}',           [ReviewerAssignmentController::class, 'show'])->name('assignments.show');
        Route::post('/assignments/{assignment}/reviews',  [ReviewerAssignmentController::class, 'storeReview'])->name('reviews.store');
        Route::get('/submissions/{submission}/files/{file}/download', [ReviewerAssignmentController::class, 'downloadFile'])->name('submissions.files.download');

        // ── Reviewer workspace (§3.2) ───────────────────────────────────────
        Route::prefix('workspace')->name('workspace.')->middleware('auth')->group(function () {
            Route::get('/',                                          [ReviewerWorkspaceController::class, 'dashboard'])->name('dashboard');
            Route::get('/invitations',                               [ReviewerWorkspaceController::class, 'invitations'])->name('invitations');
            Route::post('/invitations/{invitation}/respond',          [ReviewerWorkspaceController::class, 'respond'])->middleware('throttle:20,1')->name('invitations.respond');
            Route::get('/assignments/{assignment}',                   [ReviewerWorkspaceController::class, 'show'])->name('show');
            Route::post('/assignments/{assignment}/draft',            [ReviewerWorkspaceController::class, 'saveDraft'])->middleware('throttle:30,1')->name('draft');
            Route::post('/assignments/{assignment}/submit',           [ReviewerWorkspaceController::class, 'submitReview'])->middleware('throttle:10,1')->name('submit');
            Route::post('/assignments/{assignment}/withdraw',         [ReviewerWorkspaceController::class, 'withdraw'])->name('withdraw');
            Route::get('/submissions/{submission}/blinded-manuscript', [ReviewerWorkspaceController::class, 'downloadBlinded'])->name('blinded');
            Route::get('/submissions/{submission}/supplementary/{fileId}', [ReviewerWorkspaceController::class, 'downloadSupplementary'])->name('supplementary');
        });
    });

});
