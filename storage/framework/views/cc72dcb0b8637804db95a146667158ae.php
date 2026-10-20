<?php $__env->startSection('content'); ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <span class="badge text-bg-light text-danger px-3 py-2 mb-2">System configuration</span>
            <h3 class="fw-bold mb-1">Editorial workflow</h3>
            <p class="text-muted mb-0">The lifecycle as data: stages, deadlines and the permissions each step demands.</p>
        </div>
    </div>

    <?php if(session('success')): ?>
        <div class="alert alert-success rounded-4 mb-4"><?php echo e(session('success')); ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h6 class="fw-bold mb-3"><i class="bi bi-diagram-3 me-2 text-primary"></i>Configured transitions</h6>

            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>From</th><th></th><th>To</th><th>Permission required</th><th>Flags</th></tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = $transitions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $transition): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr>
                                <td class="small"><code><?php echo e($transition->from_stage_slug); ?></code></td>
                                <td class="text-muted">→</td>
                                <td class="small"><code><?php echo e($transition->to_stage_slug); ?></code></td>
                                <td class="small">
                                    <?php $__empty_1 = true; $__currentLoopData = $transition->required_permissions ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $permission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                        <code class="small"><?php echo e($permission); ?></code>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                        <span class="text-muted">any signed-in staff</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small">
                                    <?php if($transition->notifies_author): ?><span class="badge text-bg-light">notifies author</span><?php endif; ?>
                                    <?php if($transition->opens_revision_window): ?><span class="badge text-bg-warning">opens revision window</span><?php endif; ?>
                                    <?php if($transition->requires_note): ?><span class="badge text-bg-secondary">reason required</span><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <h6 class="fw-bold mb-3">Stages</h6>

    <?php $__currentLoopData = $stages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stage): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <span class="badge <?php echo e($stage->badgeClass()); ?> me-2"><?php echo e($stage->sort_order); ?></span>
                        <strong><?php echo e($stage->name); ?></strong>
                        <small class="text-muted ms-2"><code><?php echo e($stage->slug); ?></code></small>
                    </div>
                    <div class="d-flex gap-1">
                        <?php if($stage->is_initial): ?><span class="badge text-bg-primary">entry point</span><?php endif; ?>
                        <?php if($stage->is_terminal): ?><span class="badge text-bg-dark">terminal</span><?php endif; ?>
                        <?php if($stage->isBlinded()): ?><span class="badge text-bg-secondary">blinded</span><?php endif; ?>
                        <?php if($stage->requires_payment): ?><span class="badge text-bg-warning">payment gate</span><?php endif; ?>
                    </div>
                </div>

                <form method="POST" action="<?php echo e(route('admin.configuration.workflow.update', $stage)); ?>">
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('PUT'); ?>

                    <div class="row g-2 mb-2">
                        <div class="col-md-5">
                            <label class="form-label small fw-semibold">Stage name</label>
                            <input type="text" name="name" value="<?php echo e($stage->name); ?>" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Default deadline (days)</label>
                            <input type="number" name="default_deadline_days" value="<?php echo e($stage->default_deadline_days); ?>" min="0" max="365" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" id="stage-active-<?php echo e($stage->id); ?>" class="form-check-input" <?php if($stage->is_active): echo 'checked'; endif; ?>>
                                <label class="form-check-label small" for="stage-active-<?php echo e($stage->id); ?>">Stage is active</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Permissions required to work on a manuscript at this stage</label>
                        <input type="text" name="required_permissions" value="<?php echo e(implode(', ', $stage->required_permissions ?? [])); ?>" class="form-control form-control-sm" placeholder="comma separated, e.g. manuscript.view, workflow.transition">
                    </div>

                    <button class="btn btn-outline-primary btn-sm">Save stage</button>
                </form>
            </div>
        </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\ojmas\resources\views/admin/configuration/workflow.blade.php ENDPATH**/ ?>