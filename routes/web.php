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
use App\Http\Controllers\Author\SubmissionController;
use App\Http\Controllers\Author\PaymentController as AuthorPaymentController;
use App\Http\Controllers\Author\ReceiptController as AuthorReceiptController;
use App\Http\Controllers\Reviewer\ReviewController;
use App\Http\Controllers\Reviewer\DashboardController as ReviewerDashboardController;
use App\Http\Controllers\Reviewer\AssignmentController as ReviewerAssignmentController;

use App\Http\Controllers\Admin\PublicationController as AdminPublicationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\PaystackWebhookController;

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
        if ($user->hasRole('Super Admin') || $user->hasRole('Admin'))   return redirect()->route('admin.dashboard');
        if ($user->hasRole('Editor') || $user->hasRole('Managing Editor')) return redirect()->route('editor.dashboard');
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
        Route::resource('publications', AdminPublicationController::class);
        Route::get('/publications/{publication}/download', [AdminPublicationController::class, 'download'])->name('publications.download');
    });

    // ── Editor ────────────────────────────────────────────────────────────────
    Route::prefix('editor')->name('editor.')->middleware('role:Editor|Managing Editor|Super Admin')->group(function () {
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

        Route::get('/issues',        [EditorIssueController::class, 'index'])->name('issues.index');
        Route::get('/issues/create', [EditorIssueController::class, 'create'])->name('issues.create');
        Route::post('/issues',       [EditorIssueController::class, 'store'])->name('issues.store');
        Route::get('/issues/{issue}', [EditorIssueController::class, 'show'])->name('issues.show');
        Route::post('/issues/{issue}/attach-articles', [EditorIssueController::class, 'attachArticles'])->name('issues.attach-articles');

        Route::get('/submissions/{submission}/correspondence',  [CorrespondenceController::class, 'index'])->name('correspondence.index');
        Route::post('/submissions/{submission}/correspondence', [CorrespondenceController::class, 'store'])->name('correspondence.store');
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
    });

    // ── Reviewer ──────────────────────────────────────────────────────────────
    Route::prefix('reviewer')->name('reviewer.')->middleware('role:Reviewer|Super Admin')->group(function () {
        Route::get('/dashboard',                          [ReviewerDashboardController::class, 'index'])->name('dashboard');
        Route::get('/assignments',                        [ReviewerAssignmentController::class, 'index'])->name('assignments.index');
        Route::get('/assignments/{assignment}',           [ReviewerAssignmentController::class, 'show'])->name('assignments.show');
        Route::post('/assignments/{assignment}/reviews',  [ReviewerAssignmentController::class, 'storeReview'])->name('reviews.store');
        Route::get('/submissions/{submission}/files/{file}/download', [ReviewerAssignmentController::class, 'downloadFile'])->name('submissions.files.download');
    });

});
