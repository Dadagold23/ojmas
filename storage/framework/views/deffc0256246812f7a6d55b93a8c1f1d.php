<?php $__env->startSection('content'); ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <span class="badge text-bg-light text-danger px-3 py-2 mb-2">Workflow management</span>
            <h3 class="fw-bold mb-1">Editorial pipeline</h3>
            <p class="text-muted mb-0">What is moving, what is stuck, and who has been asked for what.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?php echo e(route('admin.workflow.reviewers')); ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-people me-1"></i>Reviewer tracking
            </a>
            <a href="<?php echo e(route('admin.workflow.report')); ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-download me-1"></i>Export report (CSV)
            </a>
        </div>
    </div>

    <?php if($errors->any() || session('warning')): ?>
        <div class="alert alert-warning rounded-4 mb-4"><?php echo e(session('warning')); ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <?php $__currentLoopData = [
            ['Awaiting an editorial decision', $overview['awaiting_decision'], 'info', 'bi-gavel'],
            ['Awaiting reviewer assignment', $overview['awaiting_assignment'], 'warning', 'bi-person-plus'],
            ['With the author', $overview['in_revision'], 'secondary', 'bi-pencil'],
            ['In peer review', $overview['in_peer_review'], 'primary', 'bi-people'],
        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $value, $colour, $icon]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small"><?php echo e($label); ?></div>
                            <div class="fs-3 fw-bold text-<?php echo e($colour); ?>"><?php echo e($value); ?></div>
                        </div>
                        <i class="bi <?php echo e($icon); ?> fs-3 text-<?php echo e($colour); ?> opacity-50"></i>
                    </div>
                </div>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-danger">
                <div class="card-body">
                    <div class="text-muted small">Overdue manuscripts</div>
                    <div class="fs-2 fw-bold text-danger"><?php echo e($overview['overdue']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-warning">
                <div class="card-body">
                    <div class="text-muted small">Reviewer responses pending</div>
                    <div class="fs-2 fw-bold text-warning"><?php echo e($overview['pending_reviewer_responses']); ?></div>
                    <div class="text-danger small"><?php echo e($overview['overdue_reviewer_responses']); ?> overdue</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-success">
                <div class="card-body">
                    <div class="text-muted small">Accepted, awaiting production</div>
                    <div class="fs-2 fw-bold text-success"><?php echo e($overview['accepted_awaiting_production']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-secondary">
                <div class="card-body">
                    <div class="text-muted small">Published to date</div>
                    <div class="fs-2 fw-bold"><?php echo e($overview['published']); ?></div>
                </div>
            </div>
        </div>
    </div>

    <?php if($overdue->isNotEmpty()): ?>
        <div class="card border-danger shadow-sm mb-4">
            <div class="card-body p-4">
                <h6 class="fw-bold text-danger mb-3"><i class="bi bi-hourglass-split me-2"></i>Delayed manuscripts — past their stage deadline</h6>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>Manuscript</th><th>Stage</th><th>Editor</th><th>Was due</th><th></th></tr></thead>
                        <tbody>
                            <?php $__currentLoopData = $overdue; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $submission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr>
                                    <td class="small">
                                        <div class="fw-semibold"><?php echo e(Str::limit($submission->title, 60)); ?></div>
                                        <code class="text-muted"><?php echo e($submission->submission_no); ?></code>
                                    </td>
                                    <td class="small"><?php echo e($submission->currentStageName()); ?></td>
                                    <td class="small text-muted"><?php echo e($submission->assignedEditor?->name ?? 'Unassigned'); ?></td>
                                    <td class="small text-danger fw-semibold"><?php echo e($submission->decision_due_at->diffForHumans()); ?></td>
                                    <td class="text-end">
                                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', $submission)): ?>
                                            <a href="<?php echo e(route('editor.workspace.show', $submission)); ?>" class="btn btn-sm btn-outline-secondary">Open</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-gavel me-2 text-info"></i>Awaiting a decision</h6>
                    <?php if($awaitingDecision->isEmpty()): ?>
                        <p class="text-muted small mb-0">Nothing is waiting on an editor right now.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light"><tr><th>Manuscript</th><th>Reports</th><th></th></tr></thead>
                                <tbody>
                                    <?php $__currentLoopData = $awaitingDecision; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $submission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <tr>
                                            <td class="small">
                                                <div class="fw-semibold"><?php echo e(Str::limit($submission->title, 55)); ?></div>
                                                <code class="text-muted"><?php echo e($submission->submission_no); ?></code>
                                            </td>
                                            <td class="small">
                                                <?php echo e($submission->reviewerAssignments->where('status', 'completed')->count()); ?>

                                                / <?php echo e($submission->reviewerAssignments->count()); ?>

                                            </td>
                                            <td class="text-end">
                                                <a href="<?php echo e(route('editor.workspace.show', $submission)); ?>" class="btn btn-sm btn-outline-secondary">Open</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-person-plus me-2 text-warning"></i>Awaiting reviewer assignment</h6>
                    <?php if($awaitingAssignment->isEmpty()): ?>
                        <p class="text-muted small mb-0">Every active manuscript has at least one reviewer.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light"><tr><th>Manuscript</th><th>Editor</th><th></th></tr></thead>
                                <tbody>
                                    <?php $__currentLoopData = $awaitingAssignment; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $submission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <tr>
                                            <td class="small">
                                                <div class="fw-semibold"><?php echo e(Str::limit($submission->title, 55)); ?></div>
                                                <code class="text-muted"><?php echo e($submission->submission_no); ?></code>
                                            </td>
                                            <td class="small text-muted"><?php echo e($submission->assignedEditor?->name ?? 'Unassigned'); ?></td>
                                            <td class="text-end">
                                                <a href="<?php echo e(route('editor.workspace.show', $submission)); ?>" class="btn btn-sm btn-outline-secondary">Open</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-4">
        <div class="card-body p-4">
            <h6 class="fw-bold mb-3"><i class="bi bi-bar-chart me-2 text-primary"></i>Manuscripts by stage</h6>
            <div class="row g-2">
                <?php $__empty_1 = true; $__currentLoopData = $distribution; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stage => $count): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="d-flex justify-content-between align-items-center border rounded-3 px-3 py-2">
                            <span class="small"><?php echo e($stage); ?></span>
                            <span class="badge text-bg-light"><?php echo e($count); ?></span>
                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <p class="text-muted small mb-0">No stages configured yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\ojmas\resources\views/admin/workflow/dashboard.blade.php ENDPATH**/ ?>