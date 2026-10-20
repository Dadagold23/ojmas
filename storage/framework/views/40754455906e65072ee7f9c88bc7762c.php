<?php $__env->startSection('content'); ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <span class="badge text-bg-light text-success px-3 py-2 mb-2">Reviewer workspace</span>
            <h3 class="fw-bold mb-1">My review assignments</h3>
            <p class="text-muted mb-0">
                You see only the manuscripts you have been invited to review, in blinded form.
            </p>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <?php $__currentLoopData = [
            ['Invitations awaiting your response', $stats['invitations'], 'warning'],
            ['Reviews in progress', $stats['in_progress'], 'primary'],
            ['Reviews completed', $stats['completed'], 'success'],
            ['Overdue', $stats['overdue'], 'danger'],
        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $value, $colour]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small"><?php echo e($label); ?></div>
                        <div class="fs-3 fw-bold text-<?php echo e($colour); ?>"><?php echo e($value); ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>

    <?php if($invitations->isNotEmpty()): ?>
        <div class="card border-warning shadow-sm mb-4">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-envelope-exclamation me-2 text-warning"></i>Invitations awaiting your response</h5>
                <?php $__currentLoopData = $invitations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invitation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="border rounded-3 p-3 mb-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <div class="fw-semibold"><?php echo e($invitation->submission?->title ?? 'Withdrawn submission'); ?></div>
                            <div class="text-muted small">
                                Invited <?php echo e($invitation->invited_at?->diffForHumans()); ?>

                                <?php if($invitation->due_at): ?>
                                    · Due <?php echo e($invitation->due_at->format('d M Y')); ?>

                                <?php endif; ?>
                            </div>
                        </div>
                        <form method="POST" action="<?php echo e(route('reviewer.workspace.invitations.respond', $invitation)); ?>" class="d-flex gap-2">
                            <?php echo csrf_field(); ?>
                            <button name="response" value="accepted" class="btn btn-sm btn-success">Accept</button>
                            <button name="response" value="declined" class="btn btn-sm btn-outline-secondary">Decline</button>
                        </form>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3"><i class="bi bi-clipboard-check me-2 text-primary"></i>Assignments</h5>

            <?php if($assignments->isEmpty()): ?>
                <p class="text-muted mb-0">You have no review assignments yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Manuscript</th>
                                <th>Reference</th>
                                <th>Due</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $__currentLoopData = $assignments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $assignment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold small"><?php echo e($assignment->submission?->title ?? 'Unavailable'); ?></div>
                                        <?php if($assignment->blind_code): ?>
                                            <code class="small text-muted"><?php echo e($assignment->blind_code); ?></code>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted"><?php echo e($assignment->submission?->subject_area ?? '—'); ?></td>
                                    <td class="small">
                                        <?php echo e($assignment->due_date?->format('d M Y') ?? '—'); ?>

                                        <?php if($assignment->isOverdue()): ?>
                                            <span class="badge text-bg-danger ms-1">Overdue</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($assignment->review?->isSubmitted()): ?>
                                            <span class="badge text-bg-success">Submitted</span>
                                        <?php elseif($assignment->review?->isDraft()): ?>
                                            <span class="badge text-bg-info">Draft saved</span>
                                        <?php elseif($assignment->status === 'declined'): ?>
                                            <span class="badge text-bg-secondary">Declined</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-warning"><?php echo e(ucfirst($assignment->status)); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?php echo e(route('reviewer.workspace.show', $assignment)); ?>" class="btn btn-sm btn-outline-primary">
                                            Open
                                        </a>
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
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\ojmas\resources\views/reviewer/workspace/dashboard.blade.php ENDPATH**/ ?>