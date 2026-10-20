<?php $__env->startSection('content'); ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <span class="badge text-bg-light text-danger px-3 py-2 mb-2">System configuration</span>
            <h3 class="fw-bold mb-1">Journal style profiles</h3>
            <p class="text-muted mb-0">
                Formatting rules live here, not in code. A rule change takes effect on the next formatting run.
            </p>
        </div>
    </div>

    <?php if(session('success')): ?>
        <div class="alert alert-success rounded-4 mb-4"><?php echo e(session('success')); ?></div>
    <?php endif; ?>

    <?php $__currentLoopData = $profiles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $profile): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="fw-bold mb-0"><?php echo e($profile->name); ?>

                        <small class="text-muted fw-normal">· <?php echo e($profile->slug); ?></small>
                    </h6>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge <?php echo e($profile->is_active ? 'text-bg-success' : 'text-bg-secondary'); ?>">
                        <?php echo e($profile->is_active ? 'Active' : 'Inactive'); ?>

                    </span>
                    <form method="POST" action="<?php echo e(route('admin.configuration.style-profiles.toggle', $profile)); ?>">
                        <?php echo csrf_field(); ?>
                        <button class="btn btn-sm btn-outline-secondary"><?php echo e($profile->is_active ? 'Deactivate' : 'Activate'); ?></button>
                    </form>
                </div>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="<?php echo e(route('admin.configuration.style-profiles.update', $profile)); ?>">
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('PUT'); ?>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Profile name</label>
                            <input type="text" name="name" value="<?php echo e($profile->name); ?>" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Version</label>
                            <input type="text" name="version" value="<?php echo e($profile->version); ?>" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold d-block">Active</label>
                            <div class="form-check form-switch">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" id="active-<?php echo e($profile->id); ?>" class="form-check-input" <?php if($profile->is_active): echo 'checked'; endif; ?>>
                                <label class="form-check-label small" for="active-<?php echo e($profile->id); ?>">Used for new submissions</label>
                            </div>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Title, maximum words</label>
                            <input type="number" name="rules[title_max_words]" value="<?php echo e($profile->rules_json['title_max_words'] ?? ''); ?>" min="1" max="100" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Abstract, maximum words</label>
                            <input type="number" name="rules[abstract_max_words]" value="<?php echo e($profile->rules_json['abstract_max_words'] ?? ''); ?>" min="1" max="2000" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Paper size</label>
                            <input type="text" name="rules[paper_label]" value="<?php echo e($profile->rules_json['paper']['label'] ?? ''); ?>" class="form-control form-control-sm" readonly>
                        </div>
                    </div>

                    <div class="alert alert-light border small mb-3">
                        Stored rules currently applied: <?php echo e(count($profile->rules_json)); ?>.
                        Only the numeric limits and switches above are editable; the deeper session and width
                        settings are shown read-only because a wrong value there breaks the document renderer.
                    </div>

                    <button class="btn btn-primary btn-sm">Save profile</button>
                </form>
            </div>
        </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\ojmas\resources\views/admin/configuration/style-profiles.blade.php ENDPATH**/ ?>