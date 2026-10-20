<?php $__env->startSection('content'); ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <span class="badge text-bg-light text-success px-3 py-2 mb-2">Reviewer workspace</span>
            <h3 class="fw-bold mb-1">Invitations</h3>
            <p class="text-muted mb-0">Every invitation you have received, and how you responded.</p>
        </div>
        <a href="<?php echo e(route('reviewer.workspace.dashboard')); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>

    <?php if($errors->any()): ?>
        <div class="alert alert-danger rounded-4 mb-4">
            <ul class="mb-0 ps-3"><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <?php if($invitations->isEmpty()): ?>
                <p class="text-muted mb-0">No invitations yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Manuscript</th>
                                <th>Invited</th>
                                <th>Due</th>
                                <th>Response</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $__currentLoopData = $invitations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invitation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr>
                                    <td class="small fw-semibold"><?php echo e($invitation->submission?->title ?? 'Unavailable'); ?></td>
                                    <td class="small text-muted">
                                        <?php echo e($invitation->invited_at?->format('d M Y') ?? '—'); ?>

                                        <?php if($invitation->inviter): ?>
                                            · by <?php echo e($invitation->inviter->name); ?>

                                        <?php endif; ?>
                                    </td>
                                    <td class="small">
                                        <?php echo e($invitation->due_at?->format('d M Y') ?? '—'); ?>

                                        <?php if($invitation->isOverdue()): ?>
                                            <span class="badge text-bg-danger ms-1">Overdue</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge text-bg-<?php echo e($invitation->response === 'accepted' ? 'success' : ($invitation->response === 'declined' ? 'secondary' : 'warning')); ?>">
                                            <?php echo e(ucfirst($invitation->response)); ?>

                                        </span>
                                    </td>
                                    <td class="text-end small text-muted">
                                        <?php if($invitation->responded_at): ?>
                                            <?php echo e($invitation->responded_at->format('d M Y')); ?>

                                        <?php elseif($invitation->submission): ?>
                                            <form method="POST" action="<?php echo e(route('reviewer.workspace.invitations.respond', $invitation)); ?>" class="d-inline-flex gap-1">
                                                <?php echo csrf_field(); ?>
                                                <button name="response" value="accepted" class="btn btn-sm btn-outline-success">Accept</button>
                                                <button name="response" value="declined" class="btn btn-sm btn-outline-secondary">Decline</button>
                                            </form>
                                        <?php endif; ?>
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
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\ojmas\resources\views/reviewer/workspace/invitations.blade.php ENDPATH**/ ?>