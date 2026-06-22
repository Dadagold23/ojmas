<?php $__env->startSection('content'); ?>
<style>
.policy-hero {
    border: none;
    border-radius: 1.75rem;
    color: #fff;
    background:
        radial-gradient(circle at 10% 20%, rgba(255, 255, 255, .14), transparent 26%),
        linear-gradient(135deg, #0f172a, #0ea5e9 55%, #22c55e 120%);
    box-shadow: 0 22px 55px rgba(15, 23, 42, .16);
}

.policy-card {
    border: none;
    border-radius: 1.35rem;
    box-shadow: 0 18px 40px rgba(15, 23, 42, .08);
}

.policy-list li {
    margin-bottom: .85rem;
}
</style>

<div class="container py-4">
    <div class="card policy-hero mb-5" data-aos="fade-up">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="fw-bold mb-3">Scope and Editorial Policies</h1>
                    <p class="mb-0 text-white-50">OJMAS publishes interdisciplinary, policy-relevant, and
                        research-driven work that expands knowledge across academic and professional fields.</p>
                </div>
                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                    <i class="bi bi-diagram-3-fill" style="font-size: 4.8rem; opacity: .16;"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6" data-aos="fade-up">
            <div class="card policy-card h-100">
                <div class="card-body p-4 p-lg-5">
                    <h3 class="fw-bold mb-4">Core Scope</h3>
                    <ul class="policy-list ps-3 mb-0">
                        <?php $__currentLoopData = $scopeAreas; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li><?php echo e($item); ?></li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-6" data-aos="fade-up" data-aos-delay="100">
            <div class="card policy-card h-100">
                <div class="card-body p-4 p-lg-5">
                    <h3 class="fw-bold mb-4">Editorial Policies</h3>
                    <ul class="policy-list ps-3 mb-0">
                        <?php $__currentLoopData = $policies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li><?php echo e($item); ?></li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-7" data-aos="fade-up">
            <div class="card policy-card h-100">
                <div class="card-body p-4 p-lg-5">
                    <h3 class="fw-bold mb-4">Manuscript Preparation</h3>
                    <ul class="policy-list ps-3 mb-0">
                        <?php $__currentLoopData = $guidelines; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li><?php echo e($item); ?></li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-5" data-aos="fade-up" data-aos-delay="100">
            <div class="card policy-card h-100">
                <div class="card-body p-4 p-lg-5">
                    <h3 class="fw-bold mb-4">Submission Notes</h3>
                    <ul class="policy-list ps-3 mb-0">
                        <?php $__currentLoopData = $submissionInfo; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li><?php echo e($item); ?></li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\ojmas-journal\resources\views/journal/scope-policies.blade.php ENDPATH**/ ?>