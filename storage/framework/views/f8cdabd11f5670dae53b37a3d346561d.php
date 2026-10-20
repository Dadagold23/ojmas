<?php $__env->startSection('content'); ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <span class="badge text-bg-light text-warning px-3 py-2 mb-2">Revision</span>
            <h3 class="fw-bold mb-1">Compare versions</h3>
            <p class="text-muted mb-0"><?php echo e($submission->title); ?></p>
        </div>
        <a href="<?php echo e(route('author.revisions.show', $submission)); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to the revision record
        </a>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h6 class="fw-bold mb-3">What you submitted and what was revised</h6>

            <?php if($versions->isEmpty()): ?>
                <p class="text-muted small mb-0">No versions are on record yet.</p>
            <?php else: ?>
                <div class="row g-3">
                    <?php $__currentLoopData = $versions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $version): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 h-100 <?php echo e($index === 0 ? 'border-primary' : ''); ?>">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong class="small">v<?php echo e($version->version_number ?? $version->getKey()); ?></strong>
                                    <?php if($index === 0): ?><span class="badge text-bg-light">original</span><?php endif; ?>
                                </div>
                                <dl class="row small mb-2">
                                    <dt class="col-5 text-muted">Type</dt>
                                    <dd class="col-7"><?php echo e($version->origin ?? '—'); ?></dd>

                                    <dt class="col-5 text-muted">Created</dt>
                                    <dd class="col-7"><?php echo e($version->created_at?->format('d M Y H:i')); ?></dd>
                                </dl>
                                <?php if($version->document): ?>
                                    <a href="<?php echo e(route('workspace.show', $submission)); ?>" class="btn btn-sm btn-outline-primary">
                                        Inspect in the workspace
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="alert alert-info rounded-4 small">
        <i class="bi bi-info-circle me-1"></i>
        Full side-by-side diffing, tracked changes and version restore live in the manuscript workspace.
        Anything a reviewer marked there was written against the version they were sent.
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\ojmas\resources\views/author/revisions/compare.blade.php ENDPATH**/ ?>