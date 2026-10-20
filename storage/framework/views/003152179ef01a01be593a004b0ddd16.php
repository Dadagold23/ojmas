<?php $__env->startSection('content'); ?>
<style>
    .panel-card { border: none; border-radius: 1.25rem; box-shadow: 0 18px 40px rgba(15,23,42,.08); }
    .meta-badge { border-radius: 999px; padding: .45rem .9rem; font-size: .82rem; }
    .file-row { transition: background .15s; }
    .file-row:hover { background: #f1f5f9; }
    .file-icon { width: 40px; height: 40px; border-radius: .75rem; display:flex; align-items:center; justify-content:center; font-size: 1.1rem; flex-shrink:0; }
</style>

<div class="container py-4">
    <?php
        $isAdminSubmissionRoute = request()->routeIs('admin.*');
        $backRoute = $isAdminSubmissionRoute ? route('admin.dashboard') : route('editor.submissions.index');
        $downloadRouteName = $isAdminSubmissionRoute ? 'admin.submissions.files.download' : 'editor.submissions.files.download';
        $manuscriptFile = $submission->files->firstWhere('file_type', 'manuscript');
        $hasPaidVetting = $submission->payments->where('purpose', 'vetting_fee')->where('status', 'paid')->isNotEmpty();
        $hasPaidPublication = $submission->payments->where('purpose', 'publication_fee')->where('status', 'paid')->isNotEmpty();
        $latestVettingPayment = $submission->payments->where('purpose', 'vetting_fee')->sortByDesc('created_at')->first();
        $latestPublicationPayment = $submission->payments->where('purpose', 'publication_fee')->sortByDesc('created_at')->first();
        $canManuallyApprovePayment = auth()->user()->hasAnyRole(['Admin', 'Managing Editor', 'Super Admin']);
        $canAssignReviewer = $hasPaidVetting && in_array($submission->status, ['submitted', 'revision_requested'], true);
    ?>

    
    <div class="d-flex justify-content-between align-items-center mb-4" data-aos="fade-up">
        <div>
            <h3 class="fw-bold mb-1">Submission Details</h3>
            <p class="text-muted mb-0 small">Editorial overview and action controls for this manuscript.</p>
        </div>
        <a href="<?php echo e($backRoute); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>

    <?php if(session('success')): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-4 mb-4">
        <?php echo e(session('success')); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if(session('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-4 mb-4">
        <?php echo e(session('error')); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if(session('warning')): ?>
    <div class="alert alert-warning alert-dismissible fade show rounded-4 mb-4">
        <?php echo e(session('warning')); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if($errors->any()): ?>
    <div class="alert alert-danger rounded-4 mb-4">
        <ul class="mb-0 ps-3"><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($e); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul>
    </div>
    <?php endif; ?>

    <div class="row g-4">

        
        <div class="col-lg-8">

            
            <div class="card panel-card mb-4" data-aos="fade-up">
                <div class="card-body p-4">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge text-bg-light meta-badge"><?php echo e($submission->submission_no); ?></span>
                        <?php
                            $colors = ['pending_payment'=>'warning','submitted'=>'primary','under_review'=>'info','revision_requested'=>'warning',
                                       'accepted'=>'success','rejected'=>'danger','published'=>'dark'];
                            $color = $colors[$submission->status] ?? 'secondary';
                        ?>
                        <span class="badge bg-<?php echo e($color); ?> meta-badge"><?php echo e(ucwords(str_replace('_',' ',$submission->status))); ?></span>
                        <?php if($submission->subject_area): ?>
                        <span class="badge text-bg-warning meta-badge"><?php echo e($submission->subject_area); ?></span>
                        <?php endif; ?>
                    </div>
                    <h4 class="fw-bold mb-3"><?php echo e($submission->title); ?></h4>
                    <div class="row g-2 text-muted small">
                        <div class="col-md-6"><i class="bi bi-person me-1"></i><strong>Author:</strong> <?php echo e($submission->author->name ?? 'N/A'); ?></div>
                        <div class="col-md-6"><i class="bi bi-envelope me-1"></i><?php echo e($submission->author->email ?? ''); ?></div>
                        <div class="col-md-6"><i class="bi bi-calendar me-1"></i><strong>Submitted:</strong> <?php echo e($submission->created_at?->format('d M Y')); ?></div>
                        <?php if($submission->keywords): ?>
                        <div class="col-12"><i class="bi bi-tags me-1"></i><strong>Keywords:</strong> <?php echo e($submission->keywords); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            
            <div class="card panel-card mb-4" data-aos="fade-up">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-text-paragraph me-2 text-primary"></i>Abstract</h6>
                    <p class="mb-0 small lh-lg"><?php echo nl2br(e($submission->abstract)); ?></p>
                </div>
            </div>

            
            <div class="card panel-card mb-4" data-aos="fade-up">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-folder2-open me-2 text-primary"></i>Uploaded Files</h6>
                    <?php if($isAdminSubmissionRoute && !$manuscriptFile): ?>
                    <div class="alert alert-warning border rounded-3 small mb-3">
                        The manuscript file is missing for this submission. Use the admin recovery form below to upload the author's copy and restore download access.
                    </div>
                    <?php endif; ?>
                    <?php $__empty_1 = true; $__currentLoopData = $submission->files; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $file): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <?php
                        $iconMap = ['manuscript'=>['bi-file-earmark-word','bg-primary-subtle text-primary'],
                                    'cover_letter'=>['bi-file-earmark-text','bg-success-subtle text-success'],
                                    'supplementary'=>['bi-file-earmark-zip','bg-warning-subtle text-warning']];
                        [$icon, $iconClass] = $iconMap[$file->file_type] ?? ['bi-file-earmark','bg-secondary-subtle text-secondary'];
                    ?>
                    <div class="d-flex align-items-center justify-content-between py-2 px-2 file-row rounded-3 mb-1">
                        <div class="d-flex align-items-center gap-3">
                            <div class="file-icon <?php echo e($iconClass); ?>">
                                <i class="bi <?php echo e($icon); ?>"></i>
                            </div>
                            <div>
                                <div class="fw-semibold small"><?php echo e($file->original_name); ?></div>
                                <div class="text-muted" style="font-size:.78rem">
                                    <?php echo e(ucwords(str_replace('_',' ',$file->file_type))); ?>

                                    &middot; <?php echo e(number_format($file->file_size / 1024, 1)); ?> KB
                                    &middot; <?php echo e($file->created_at->format('d M Y')); ?>

                                </div>
                            </div>
                        </div>
                        <button class="btn btn-sm btn-outline-primary"
                                data-bs-toggle="modal"
                                data-bs-target="#fileModal"
                                data-name="<?php echo e($file->original_name); ?>"
                                data-type="<?php echo e(ucwords(str_replace('_',' ',$file->file_type))); ?>"
                                data-size="<?php echo e(number_format($file->file_size / 1024, 1)); ?> KB"
                                data-date="<?php echo e($file->created_at->format('d M Y, H:i')); ?>"
                                data-url="<?php echo e(route($downloadRouteName, [$submission, $file])); ?>">
                            <i class="bi bi-download me-1"></i>Download
                        </button>
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <p class="text-muted small mb-0">No files uploaded for this submission.</p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if($isAdminSubmissionRoute): ?>
            <div class="card panel-card mb-4" data-aos="fade-up" id="manuscript-recovery">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-life-preserver me-2 text-primary"></i>Recover Missing Manuscript</h6>
                    <p class="text-muted small mb-3">
                        Use this only when the author already submitted the manuscript, but the stored file is missing from the platform. The recovered file will be attached to this submission and restore secure downloads.
                    </p>
                    <?php if($manuscriptFile): ?>
                    <div class="alert alert-info border rounded-3 small mb-3">
                        A manuscript is already attached: <strong><?php echo e($manuscriptFile->original_name); ?></strong>. Upload a replacement only if the current file is wrong or unreadable.
                    </div>
                    <?php endif; ?>
                    <form method="POST" action="<?php echo e(route('admin.submissions.files.recover', $submission)); ?>" enctype="multipart/form-data">
                        <?php echo csrf_field(); ?>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Recovered Manuscript</label>
                            <input type="file" name="manuscript" class="form-control form-control-sm" accept=".pdf,.doc,.docx" required>
                            <div class="form-text">Accepted formats: PDF, DOC, DOCX up to 1 GB.</div>
                        </div>
                        <?php if($manuscriptFile): ?>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" value="1" id="replace_existing" name="replace_existing">
                            <label class="form-check-label small" for="replace_existing">
                                Replace the current manuscript record with this recovered file
                            </label>
                        </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Recovery Note</label>
                            <textarea name="note" rows="3" class="form-control form-control-sm" placeholder="Example: Author resent manuscript after original storage record was missing"></textarea>
                        </div>
                        <button class="btn btn-outline-primary btn-sm w-100 fw-semibold">
                            <i class="bi bi-upload me-1"></i>Recover Manuscript File
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            
            <?php if($submission->coAuthors->count()): ?>
            <div class="card panel-card mb-4" data-aos="fade-up">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-people me-2 text-primary"></i>Co-Authors</h6>
                    <div class="row g-3">
                        <?php $__currentLoopData = $submission->coAuthors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ca): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3">
                                <div class="fw-semibold small"><?php echo e($ca->full_name); ?></div>
                                <div class="text-muted" style="font-size:.8rem"><?php echo e($ca->email ?? ''); ?></div>
                                <div class="text-muted" style="font-size:.8rem"><?php echo e($ca->institution ?? ''); ?><?php echo e($ca->country ? ', '.$ca->country : ''); ?></div>
                            </div>
                        </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            
            <?php if($submission->reviewerAssignments->count()): ?>
            <div class="card panel-card mb-4" data-aos="fade-up">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-clipboard-check me-2 text-primary"></i>Review Assignments</h6>
                    <?php $__currentLoopData = $submission->reviewerAssignments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $assignment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div>
                            <div class="fw-semibold small"><?php echo e($assignment->reviewer->name ?? 'N/A'); ?></div>
                            <div class="text-muted" style="font-size:.78rem">Due: <?php echo e($assignment->due_date?->format('d M Y') ?? 'N/A'); ?></div>
                        </div>
                        <span class="badge bg-<?php echo e($assignment->status === 'completed' ? 'success' : 'warning'); ?>">
                            <?php echo e(ucfirst($assignment->status)); ?>

                        </span>
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>
            <?php endif; ?>

            
            <?php $submittedReviews = $submission->reviewerAssignments->filter(fn ($a) => $a->review !== null && $a->review->status === 'submitted'); ?>
            <?php if($submittedReviews->isNotEmpty()): ?>
            <div class="card panel-card mb-4" data-aos="fade-up">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-chat-square-text me-2 text-primary"></i>Reviewer Reports</h6>

                    <?php $__currentLoopData = $submittedReviews; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $assignment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="border rounded-3 p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <span class="badge bg-<?php echo e($assignment->review->recommendation === 'reject' ? 'danger' : ($assignment->review->recommendation === 'accept' ? 'success' : 'warning')); ?> me-1">
                                        <?php echo e($assignment->review->recommendationLabel()); ?>

                                    </span>
                                    <span class="text-muted" style="font-size:.8rem">
                                        <?php echo e($assignment->reviewer->name); ?> ·
                                        <?php echo e($assignment->review->submitted_at?->format('d M Y, H:i')); ?>

                                    </span>
                                </div>
                                <span class="badge bg-light text-dark">Read-only</span>
                            </div>

                            <div class="small mb-2">
                                <strong>For the author:</strong>
                                <div class="text-muted" style="white-space: pre-line"><?php echo e($assignment->review->comments_for_author); ?></div>
                            </div>

                            <?php if($assignment->review->comments_for_editor): ?>
                                <details class="small">
                                    <summary class="text-muted">Confidential comments for the editor</summary>
                                    <div class="text-muted border-start ps-2 mt-1" style="white-space: pre-line"><?php echo e($assignment->review->comments_for_editor); ?></div>
                                </details>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                    <div class="alert alert-light border rounded-3 small mb-0">
                        <i class="bi bi-lock me-1"></i>
                        Submitted reviews are part of the editorial record and cannot be edited by anyone,
                        including editors. If a report needs correcting, ask the reviewer to withdraw and re-submit.
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>

        
        <div class="col-lg-4">

            
            <div class="card panel-card mb-4" data-aos="fade-up">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-person-check me-2 text-primary"></i>Assign Reviewer</h6>
                    <?php if(!$canAssignReviewer): ?>
                    <div class="alert alert-warning border rounded-3 small mb-0">
                        Reviewer assignment is locked until the vetting fee is confirmed and the submission leaves the <strong>pending payment</strong> state.
                    </div>
                    <?php else: ?>
                    <form method="POST" action="<?php echo e(route('editor.submissions.assignReviewer', $submission)); ?>">
                        <?php echo csrf_field(); ?>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Reviewer</label>
                            <select name="reviewer_id" class="form-select form-select-sm" required>
                                <option value="">Select reviewer</option>
                                <?php $__currentLoopData = $reviewers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($r->id); ?>"><?php echo e($r->name); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Due Date</label>
                            <input type="date" name="due_date" class="form-control form-control-sm"
                                   value="<?php echo e(now()->addDays(14)->toDateString()); ?>">
                        </div>
                        <button class="btn btn-primary btn-sm w-100">Assign Reviewer</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card panel-card mb-4" data-aos="fade-up" id="payment-actions">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-credit-card-2-front me-2 text-primary"></i>Payment Status</h6>
                    <div class="border rounded-3 p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <div class="fw-semibold small">Vetting Fee</div>
                                <div class="text-muted small">
                                    <?php echo e($hasPaidVetting ? 'Paid' : 'Not yet recorded'); ?>

                                </div>
                            </div>
                            <span class="badge bg-<?php echo e($hasPaidVetting ? 'success' : 'warning'); ?>">
                                <?php echo e($hasPaidVetting ? 'Paid' : 'Pending'); ?>

                            </span>
                        </div>
                        <?php if($latestVettingPayment): ?>
                        <div class="text-muted small mt-2">
                            Ref: <?php echo e($latestVettingPayment->reference); ?> | Status: <?php echo e(ucfirst($latestVettingPayment->status)); ?>

                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="border rounded-3 p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <div class="fw-semibold small">Publication Fee</div>
                                <div class="text-muted small">
                                    <?php echo e($hasPaidPublication ? 'Paid' : 'Not yet recorded'); ?>

                                </div>
                            </div>
                            <span class="badge bg-<?php echo e($hasPaidPublication ? 'success' : 'warning'); ?>">
                                <?php echo e($hasPaidPublication ? 'Paid' : 'Pending'); ?>

                            </span>
                        </div>
                        <?php if($latestPublicationPayment): ?>
                        <div class="text-muted small mt-2">
                            Ref: <?php echo e($latestPublicationPayment->reference); ?> | Status: <?php echo e(ucfirst($latestPublicationPayment->status)); ?>

                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if($canManuallyApprovePayment): ?>
                    <div class="alert alert-light border rounded-3 small mb-3">
                        Use manual approval only when gateway verification or callback handling failed, but you have already confirmed the payment externally.
                    </div>
                    <form method="POST" action="<?php echo e(route('admin.submissions.payments.manual', $submission)); ?>">
                        <?php echo csrf_field(); ?>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Payment Purpose</label>
                            <select name="purpose" class="form-select form-select-sm" required>
                                <option value="">Select payment</option>
                                <?php if (! ($hasPaidVetting)): ?>
                                <option value="vetting_fee">Vetting Fee</option>
                                <?php endif; ?>
                                <?php if (! ($hasPaidPublication)): ?>
                                <option value="publication_fee">Publication Fee</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Verified Reference</label>
                            <input type="text" name="reference" class="form-control form-control-sm" placeholder="Bank transfer ref, Paystack ref, teller no">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label small fw-semibold">Amount</label>
                                <input type="number" step="0.01" min="0" name="amount" class="form-control form-control-sm" placeholder="Auto if blank">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-semibold">Paid At</label>
                                <input type="datetime-local" name="paid_at" class="form-control form-control-sm">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Admin Note</label>
                            <textarea name="note" rows="3" class="form-control form-control-sm" placeholder="Why manual approval was needed"></textarea>
                        </div>
                        <button class="btn btn-outline-success btn-sm w-100 fw-semibold">
                            <i class="bi bi-shield-check me-1"></i>Record Manual Payment
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            
            <div class="card panel-card mb-4" data-aos="fade-up">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-gavel me-2 text-primary"></i>Editorial Decision</h6>
                    <form method="POST" action="<?php echo e(route('editor.submissions.decision', $submission)); ?>">
                        <?php echo csrf_field(); ?>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Decision</label>
                            <select name="decision" class="form-select form-select-sm" required>
                                <option value="">Select decision</option>
                                <option value="revision_requested">Request Revision</option>
                                <option value="accepted">Accept</option>
                                <option value="rejected">Reject</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Message to Author</label>
                            <textarea name="message" rows="4" class="form-control form-control-sm"
                                      placeholder="Remarks or instructions for the author..."></textarea>
                        </div>
                        <button class="btn btn-dark btn-sm w-100">Submit Decision</button>
                    </form>
                </div>
            </div>

            
            <?php if(in_array($submission->status, ['screening','published'])): ?>
            <div class="card panel-card border-success" style="border:2px solid #198754 !important" data-aos="fade-up">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-2"><i class="bi bi-send-check me-2 text-success"></i>Publish to Issue</h6>
                    <?php if($submission->status === 'screening'): ?>
                    <p class="text-muted small mb-3">
                        Assigned to: <strong><?php echo e($submission->assignedEditor->name ?? 'N/A'); ?></strong>
                    </p>
                    <a href="<?php echo e(route('editor.submissions.publish.form', $submission)); ?>"
                       class="btn btn-success btn-sm w-100">
                        <i class="bi bi-journal-arrow-up me-1"></i>Publish Article
                    </a>
                    <?php else: ?>
                    <span class="badge bg-dark">Published</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('modals'); ?>
<div class="modal fade" id="fileModal" tabindex="-1" aria-labelledby="fileModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 overflow-hidden">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold" id="fileModalLabel">
                    <i class="bi bi-file-earmark-arrow-down me-2 text-primary"></i>Download File
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <div class="bg-light rounded-3 p-4 mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="file-icon bg-primary-subtle text-primary" style="width:48px;height:48px;border-radius:.75rem;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                        <div>
                            <div class="fw-semibold" id="modal-file-name"></div>
                            <div class="text-muted small" id="modal-file-meta"></div>
                        </div>
                    </div>
                </div>
                <p class="text-muted small mb-0">
                    <i class="bi bi-shield-lock me-1 text-success"></i>
                    This file is stored securely. Download is logged for audit purposes.
                </p>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-0 gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <a id="modal-download-btn" href="#" class="btn btn-primary btn-sm fw-semibold">
                    <i class="bi bi-download me-1"></i>Download File
                </a>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopPush(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
    document.getElementById('fileModal').addEventListener('show.bs.modal', function (e) {
        const btn = e.relatedTarget;
        document.getElementById('modal-file-name').textContent = btn.dataset.name;
        document.getElementById('modal-file-meta').textContent =
            btn.dataset.type + ' · ' + btn.dataset.size + ' · ' + btn.dataset.date;
        document.getElementById('modal-download-btn').href = btn.dataset.url;
    });
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\ojmas\resources\views/editor/submissions/show.blade.php ENDPATH**/ ?>