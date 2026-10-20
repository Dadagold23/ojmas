<?php $__env->startSection('content'); ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <span class="badge text-bg-light text-primary px-3 py-2 mb-2">Proofs</span>
            <h3 class="fw-bold mb-1"><?php echo e($submission->submission_no); ?></h3>
            <p class="text-muted mb-0"><?php echo e($submission->title); ?></p>
        </div>
        <a href="<?php echo e(route('author.revisions.show', $submission)); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Revision record
        </a>
    </div>

    <?php if(session('success')): ?>
        <div class="alert alert-success rounded-4 mb-4"><?php echo e(session('success')); ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-file-earmark-check me-2 text-primary"></i>Your proofs</h6>

                    <p class="small text-muted">
                        These are the final formatted copies of your manuscript. Please read every page —
                        your approval is what allows the article to proceed to production.
                    </p>

                    <div class="list-group list-group-flush mb-3">
                        <?php $__empty_1 = true; $__currentLoopData = $submission->files; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $file): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <div class="list-group-item px-2 d-flex justify-content-between align-items-center">
                                <div class="small">
                                    <div class="fw-semibold"><?php echo e($file->original_name); ?></div>
                                    <div class="text-muted"><?php echo e(number_format($file->file_size / 1024, 0)); ?> kB
                                        <?php if($file->is_anonymised): ?><span>· anonymised copy</span><?php endif; ?>
                                    </div>
                                </div>
                                <a href="<?php echo e(route('author.manuscripts.download', $submission)); ?>"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-download me-1"></i>Download
                                </a>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <div class="list-group-item px-2 small text-muted">No files are on record.</div>
                        <?php endif; ?>
                    </div>

                    <a href="<?php echo e(route('workspace.show', $submission)); ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-columns-gap me-1"></i>Open the full workspace for version history
                    </a>

                    <div class="alert alert-light border rounded-3 small mb-0 mt-3">
                        <i class="bi bi-info-circle me-1"></i>
                        Approval covers what is in the file: text, figures, tables, author details and references.
                        Later corrections require new proofs.
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-primary border-3 shadow-sm mb-3">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-pen me-2 text-primary"></i>Your decision</h6>

                    <form method="POST" action="<?php echo e(route('author.proofs.approve', $submission)); ?>">
                        <?php echo csrf_field(); ?>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="status" id="proof-approve"
                                   value="approved" checked required>
                            <label class="form-check-label small fw-semibold" for="proof-approve">
                                I approve these proofs for publication
                            </label>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="status" id="proof-corrections"
                                   value="corrections_requested" required>
                            <label class="form-check-label small fw-semibold" for="proof-corrections">
                                I request corrections
                            </label>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold" for="proof-notes">Notes for the editor (optional)</label>
                            <textarea name="notes" id="proof-notes" rows="4" class="form-control form-control-sm"
                                      placeholder="Where corrections are needed — or anything the production team should know."></textarea>
                        </div>

                        <button class="btn btn-primary btn-sm w-100">Submit approval decision</button>
                    </form>
                </div>
            </div>

            <?php if($approvals->isNotEmpty()): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3"><i class="bi bi-clock-history me-2 text-muted"></i>Previous approvals</h6>

                        <?php $__currentLoopData = $approvals; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $approval): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="border-bottom pb-2 mb-2 small">
                                <span class="badge <?php echo e($approval->status === 'approved' ? 'text-bg-success' : 'text-bg-warning'); ?> me-1">
                                    <?php echo e($approval->status === 'approved' ? 'Approved' : 'Corrections requested'); ?>

                                </span>
                                <span class="text-muted"><?php echo e($approval->approved_at?->format('d M Y H:i') ?? $approval->created_at->format('d M Y H:i')); ?></span>
                                <?php if($approval->notes): ?>
                                    <div class="text-muted"><?php echo e(\Illuminate\Support\Str::limit($approval->notes, 140)); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\ojmas\resources\views/author/proofs/show.blade.php ENDPATH**/ ?>