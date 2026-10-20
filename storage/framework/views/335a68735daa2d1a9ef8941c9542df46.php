<?php $__env->startSection('content'); ?>
<div class="container py-4">
    <h2>My Profile</h2> 

    <div class="card show-sm">
        <div class="card-body">
            <form method="POST" action="<?php echo e(route('profile.update')); ?>">
                <?php echo csrf_field(); ?> 
                <?php echo method_field('PATCH'); ?>

                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-control" value="<?php echo e(auth()->user()->name); ?>">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" value="<?php echo e(auth()->user()->email); ?>">
                </div> 

                <button class="btn btn-primary">Save Changes</button>
            </form>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\ojmas\resources\views/profile/edit.blade.php ENDPATH**/ ?>