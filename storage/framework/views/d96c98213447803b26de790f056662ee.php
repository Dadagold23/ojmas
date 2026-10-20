<?php $__env->startSection('content'); ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <span class="badge text-bg-light text-danger px-3 py-2 mb-2">System configuration</span>
            <h3 class="fw-bold mb-1">Roles and permissions</h3>
            <p class="text-muted mb-0">
                Permissions are granted per role. Changes take effect on the next request.
            </p>
        </div>
        <form method="POST" action="<?php echo e(route('admin.configuration.permissions.sync')); ?>">
            <?php echo csrf_field(); ?>
            <button class="btn btn-outline-primary btn-sm" onclick="return confirm('Re-sync the permission catalogue? Missing permissions are created and default grants re-applied; nothing is revoked.')">
                <i class="bi bi-arrow-repeat me-1"></i>Re-sync catalogue
            </button>
        </form>
    </div>

    <?php if(session('success')): ?>
        <div class="alert alert-success rounded-4 mb-4"><?php echo e(session('success')); ?></div>
    <?php endif; ?>

    <div class="alert alert-info rounded-4 small mb-4">
        <i class="bi bi-shield-lock me-1"></i>
        The <strong>Managing Editor</strong> role can never be granted these capabilities:
        <?php $__currentLoopData = $prohibitions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $prohibition): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <code class="me-1"><?php echo e($prohibition); ?></code>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        — the server refuses them even if they are submitted, so user management, payments, publication,
        editorial decisions and system configuration always stay out of its reach.
    </div>

    <?php $__currentLoopData = $grouped; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $domain => $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-0 pt-3 px-4">
                <h6 class="fw-bold mb-0"><?php echo e($group['label']); ?></h6>
            </div>
            <div class="card-body pt-0 px-4 pb-3">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40%">Permission</th>
                                <?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <th class="text-center small"><?php echo e($role->name); ?></th>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $__currentLoopData = $group['permissions']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $permission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr>
                                    <td><code class="small"><?php echo e($permission); ?></code></td>
                                    <?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <td class="text-center">
                                            <?php if(in_array($permission, $granted[$role->name] ?? [], true)): ?>
                                                <i class="bi bi-check-lg text-success"></i>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <h6 class="fw-bold mb-3">Adjust a role</h6>
            <p class="text-muted small">
                Editing is per-role rather than per-checkbox-grid so every change is one deliberate action.
                Sync the catalogue first if a permission you need is not yet in the database.
            </p>
            <form method="POST" action="#" id="role-edit-form">
                <?php echo csrf_field(); ?>
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Role</label>
                        <select id="role-picker" class="form-select form-select-sm">
                            <option value="">Select a role</option>
                            <?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($role->name); ?>"><?php echo e($role->name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Permissions to grant</label>
                        <input type="text" id="permissions-input" class="form-control form-control-sm"
                               placeholder="space or comma separated permission names">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary btn-sm w-100" disabled id="role-save">Save</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var grants = <?php echo json_encode($granted, 15, 512) ?>;
    var picker = document.getElementById('role-picker');
    var input = document.getElementById('permissions-input');
    var save = document.getElementById('role-save');
    var form = document.getElementById('role-edit-form');

    picker.addEventListener('change', function () {
        if (picker.value === '') { return; }
        input.value = (grants[picker.value] || []).join(', ');
        save.disabled = false;
        form.setAttribute('action', picker.value
            ? '<?php echo e(url("admin/configuration/permissions")); ?>/' + encodeURIComponent(picker.value)
            : '#');
    });

    form.addEventListener('submit', function (event) {
        if (!picker.value) { event.preventDefault(); }
    });
});
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\ojmas\resources\views/admin/configuration/permissions.blade.php ENDPATH**/ ?>