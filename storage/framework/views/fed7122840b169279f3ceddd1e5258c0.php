<?php $__env->startSection('content'); ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <span class="badge text-bg-light text-success px-3 py-2 mb-2">
                Blinded review
                <?php if($assignment->blind_code): ?>
                    · <?php echo e($assignment->blind_code); ?>

                <?php endif; ?>
            </span>
            <h3 class="fw-bold mb-1"><?php echo e($assignment->submission?->title ?? 'Manuscript'); ?></h3>
            <p class="text-muted mb-0">
                Subject: <?php echo e($assignment->submission?->subject_area ?? '—'); ?>

                · Due <?php echo e($assignment->due_date?->format('d M Y') ?? 'no date set'); ?>

            </p>
        </div>
        <a href="<?php echo e(route('reviewer.workspace.dashboard')); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>

    <?php if(session('success')): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 mb-4"><?php echo e(session('success')); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if(session('warning')): ?>
        <div class="alert alert-warning alert-dismissible fade show rounded-4 mb-4"><?php echo e(session('warning')); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if($errors->any()): ?>
        <div class="alert alert-danger rounded-4 mb-4">
            <ul class="mb-0 ps-3"><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul>
        </div>
    <?php endif; ?>

    <div class="alert alert-info rounded-4 mb-4 small">
        <i class="bi bi-eye-slash me-1"></i>
        This is a <strong>blinded</strong> copy. Author names, affiliations, contact details, funding and
        acknowledgements have been removed, and the file carries a neutral name. Please do not attempt to
        identify the authors. If you believe you can identify them, contact the editorial office and ask for
        a different assignment.
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-file-earmark-word me-2 text-primary"></i>Manuscript files</h6>

                    <a href="<?php echo e(route('reviewer.workspace.blinded', $assignment->submission)); ?>" class="btn btn-primary btn-sm mb-2">
                        <i class="bi bi-download me-1"></i>Download the blinded manuscript
                    </a>

                    <?php $supplementary = $assignment->submission?->files->where('file_type', 'supplementary') ?? collect(); ?>

                    <?php if($supplementary->isNotEmpty()): ?>
                        <ul class="list-unstyled small mt-3 mb-0">
                            <?php $__currentLoopData = $supplementary; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $file): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <li class="mb-1">
                                    <a href="<?php echo e(route('reviewer.workspace.supplementary', [$assignment->submission, $file->id])); ?>">
                                        <?php echo e($file->original_name); ?>

                                    </a>
                                </li>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card border-0 shadow-sm mt-3">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-chat-left-text me-2 text-primary"></i>Comments</h6>
                    <p class="text-muted small mb-2">
                        Open the manuscript workspace to add contextual comments. Your comments are visible to
                        the editorial team; mark them confidential if they should not reach the author.
                    </p>
                    <a href="<?php echo e(route('workspace.show', $assignment->submission)); ?>" class="btn btn-outline-primary btn-sm">
                        Open the workspace
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-pencil-square me-2 text-primary"></i>Your review</h6>

                    <?php if($isSubmitted): ?>
                        <div class="alert alert-success small rounded-3">
                            <i class="bi bi-patch-check me-1"></i>
                            Your review was submitted on <?php echo e($review->submitted_at?->format('d M Y, H:i')); ?>.
                            It is now part of the editorial record and cannot be edited.
                        </div>

                        <dl class="row small mb-3">
                            <dt class="col-5 text-muted">Recommendation</dt>
                            <dd class="col-7 fw-semibold"><?php echo e($review->recommendationLabel()); ?></dd>

                            <dt class="col-5 text-muted">For the author</dt>
                            <dd class="col-7"><?php echo e(Str::limit($review->comments_for_author, 160)); ?></dd>
                        </dl>

                        <form method="POST" action="<?php echo e(route('reviewer.workspace.withdraw', $assignment)); ?>"
                              onsubmit="return confirm('Withdraw your submitted review? It will be recorded, and you may then revise and resubmit.')">
                            <?php echo csrf_field(); ?>
                            <div class="mb-2">
                                <input type="text" name="reason" class="form-control form-control-sm"
                                       placeholder="Reason for withdrawal (optional)">
                            </div>
                            <button class="btn btn-outline-danger btn-sm">Withdraw and revise</button>
                        </form>
                    <?php else: ?>
                        <?php if($review?->isDraft()): ?>
                            <div class="alert alert-info small rounded-3">
                                A draft is saved. Only you can see it until you submit.
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="<?php echo e(route('reviewer.workspace.draft', $assignment)); ?>">
                            <?php echo csrf_field(); ?>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold">Recommendation</label>
                                <select name="recommendation" class="form-select form-select-sm">
                                    <option value="">Not yet decided</option>
                                    <?php $__currentLoopData = $recommendations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($value); ?>" <?php if($review?->recommendation === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </div>

                            <div class="mb-2">
                                <label class="form-label small fw-semibold">Comments for the author</label>
                                <textarea name="comments_for_author" rows="6" class="form-control form-control-sm"><?php echo e($review?->comments_for_author); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Confidential comments for the editor</label>
                                <textarea name="comments_for_editor" rows="3" class="form-control form-control-sm"><?php echo e($review?->comments_for_editor); ?></textarea>
                                <div class="form-text">Never shown to the author.</div>
                            </div>

                            <button class="btn btn-outline-primary btn-sm">Save draft</button>
                        </form>

                        <hr class="my-3">

                        <form method="POST" action="<?php echo e(route('reviewer.workspace.submit', $assignment)); ?>">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="recommendation" value="<?php echo e($review?->recommendation); ?>">
                            <input type="hidden" name="comments_for_author" value="<?php echo e($review?->comments_for_author); ?>">
                            <input type="hidden" name="comments_for_editor" value="<?php echo e($review?->comments_for_editor); ?>">

                            <?php if(! $review?->recommendation || ! $review?->comments_for_author): ?>
                                <p class="text-muted small mb-2">
                                    Save a draft with a recommendation and author comments first. Submitting is
                                    what puts your report into the editorial record.
                                </p>
                            <?php else: ?>
                                <button class="btn btn-success btn-sm"
                                        onclick="return confirm('Submit this review? It becomes part of the editorial record and cannot be edited afterwards.')">
                                    <i class="bi bi-send me-1"></i>Submit final review
                                </button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\ojmas\resources\views/reviewer/workspace/show.blade.php ENDPATH**/ ?>