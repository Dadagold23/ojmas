<?php $__env->startSection('content'); ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <span class="badge text-bg-light text-danger px-3 py-2 mb-2">Workflow management</span>
            <h3 class="fw-bold mb-1">Reviewer tracking</h3>
            <p class="text-muted mb-0">Invitations, responses, workload — one place per reviewer.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?php echo e(route('admin.workflow.index')); ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Pipeline
            </a>
            <a href="<?php echo e(route('admin.workflow.report')); ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-download me-1"></i>Export CSV
            </a>
        </div>
    </div>

    <?php if($errors->any()): ?>
        <div class="alert alert-danger rounded-4 mb-4">
            <ul class="mb-0 ps-3"><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul>
        </div>
    <?php endif; ?>

    
    <?php if($overdue->isNotEmpty()): ?>
        <div class="card border-danger shadow-sm mb-4">
            <div class="card-body p-4">
                <h6 class="fw-bold text-danger mb-3"><i class="bi bi-hourglass-split me-2"></i>Overdue reviewer responses — <?php echo e($overdue->count()); ?></h6>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>Reviewer</th><th>Manuscript</th><th>Invited</th><th>Was due</th><th>Reminders</th><th></th></tr></thead>
                        <tbody>
                            <?php $__currentLoopData = $overdue; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invitation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr>
                                    <td class="small fw-semibold"><?php echo e($invitation->reviewer?->name); ?></td>
                                    <td class="small"><?php echo e(Str::limit($invitation->submission?->title, 50)); ?></td>
                                    <td class="small text-muted"><?php echo e($invitation->invited_at?->format('d M Y')); ?></td>
                                    <td class="small text-danger"><?php echo e($invitation->due_at->diffForHumans()); ?></td>
                                    <td class="small"><?php echo e($invitation->reminder_count); ?></td>
                                    <td class="text-end">
                                        <form method="POST" action="<?php echo e(route('admin.workflow.invitations.remind', $invitation)); ?>">
                                            <?php echo csrf_field(); ?>
                                            <button class="btn btn-sm btn-outline-warning">
                                                <i class="bi bi-bell me-1"></i>Record reminder
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3 text-warning">Pending responses — <?php echo e($pending->count()); ?></h6>
                    <?php $__currentLoopData = $pending; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invitation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="d-flex justify-content-between align-items-center border rounded-3 px-3 py-2 mb-2">
                            <div class="small">
                                <div class="fw-semibold"><?php echo e($invitation->reviewer?->name); ?></div>
                                <div class="text-muted"><?php echo e(Str::limit($invitation->submission?->title, 45)); ?></div>
                            </div>
                            <form method="POST" action="<?php echo e(route('admin.workflow.invitations.remind', $invitation)); ?>">
                                <?php echo csrf_field(); ?>
                                <button class="btn btn-sm btn-outline-secondary" title="Record a reminder">
                                    <i class="bi bi-bell"></i>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <?php if($pending->isEmpty()): ?><p class="text-muted small mb-0">None pending.</p><?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3 text-secondary">Declined — <?php echo e($declined->count()); ?></h6>
                    <?php $__currentLoopData = $declined; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invitation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="border rounded-3 px-3 py-2 mb-2 small">
                            <span class="fw-semibold"><?php echo e($invitation->reviewer?->name); ?></span>
                            <span class="text-muted">· <?php echo e(Str::limit($invitation->submission?->title, 45)); ?></span>
                            <?php if($invitation->response_note): ?>
                                <div class="text-muted">“<?php echo e(Str::limit($invitation->response_note, 90)); ?>”</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <?php if($declined->isEmpty()): ?><p class="text-muted small mb-0">No declines recorded.</p><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <h6 class="fw-bold mb-3"><i class="bi bi-people me-2 text-primary"></i>Reviewer workload</h6>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Reviewer</th><th>Active assignments</th><th>Completed all-time</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = $workload; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr>
                                <td class="small fw-semibold"><?php echo e($row->reviewer->name); ?></td>
                                <td class="small">
                                    <span class="badge <?php echo e($row->active > 3 ? 'text-bg-warning' : 'text-bg-light'); ?>"><?php echo e($row->active); ?></span>
                                </td>
                                <td class="small text-muted"><?php echo e($row->completed); ?></td>
                                <td class="text-end small text-muted">
                                    <?php if($row->active === 0): ?>
                                        <span class="text-success">Available</span>
                                    <?php else: ?>
                                        <?php echo e($row->active); ?> in progress
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\ojmas\resources\views/admin/workflow/reviewers.blade.php ENDPATH**/ ?>