<?php $__env->startSection('content'); ?>
<style>
.cfp-hero {
    border: none;
    border-radius: 1.8rem;
    overflow: hidden;
    color: #fff;
    background: linear-gradient(135deg, rgba(15, 23, 42, .92), rgba(37, 99, 235, .9)),
    url('<?php echo e(asset('images/journal/call-for-paper.png')); ?>') center center / cover no-repeat;
    box-shadow: 0 25px 60px rgba(15, 23, 42, .2);
}

.cfp-panel {
    border: none;
    border-radius: 1.4rem;
    box-shadow: 0 18px 42px rgba(15, 23, 42, .08);
}

.cfp-image {
    width: 100%;
    border-radius: 1.25rem;
    box-shadow: 0 18px 36px rgba(15, 23, 42, .12);
}

.cfp-chip {
    display: inline-flex;
    align-items: center;
    padding: .55rem .85rem;
    border-radius: 999px;
    background: #eff6ff;
    color: #0b5ed7;
    font-weight: 700;
    font-size: .92rem;
}

.cfp-stack {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}
</style>

<div class="container py-4">
    <div class="card cfp-hero mb-5" data-aos="fade-up">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="fw-bold mb-3">Offa Journal of Multidisciplinary and Advanced Studies</h1>
                    <p class="mb-2 fs-5"><?php echo e($call['tagline']); ?></p>
                    <p class="mb-0 text-white-50">ISSN: <?php echo e($call['issn']); ?></p>
                </div>
                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                    <a href="<?php echo e(route('register')); ?>" class="btn btn-warning btn-lg px-4">
                        Submit Interest
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-5" data-aos="fade-right">
            <img src="<?php echo e(asset('images/journal/call-for-paper.png')); ?>" alt="OJMAS call for paper flyer"
                class="cfp-image">
        </div>
        <div class="col-lg-7" data-aos="fade-left">
            <div class="card cfp-panel h-100">
                <div class="card-body p-4 p-lg-5">
                    <h2 class="fw-bold mb-3">Why Publish with OJMAS?</h2>
                    <p class="fs-5"><?php echo e($call['intro']); ?></p>
                    <p class="text-muted mb-4"><?php echo e($call['value']); ?></p>
                    <div class="d-flex flex-wrap gap-2">
                        <?php $__currentLoopData = $call['scope']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <span class="cfp-chip"><?php echo e($item); ?></span>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6" data-aos="fade-up">
            <div class="card cfp-panel h-100">
                <div class="card-body p-4 p-lg-5">
                    <h3 class="fw-bold mb-4">Author Guidelines</h3>
                    <ul class="ps-3 mb-0">
                        <?php $__currentLoopData = $call['authorsGuidelines']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li class="mb-3"><?php echo e($item); ?></li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-6" data-aos="fade-up" data-aos-delay="100">
            <div class="card cfp-panel h-100">
                <div class="card-body p-4 p-lg-5">
                    <h3 class="fw-bold mb-4">Review and Publication</h3>
                    <ul class="ps-3 mb-4">
                        <?php $__currentLoopData = $call['reviewPublication']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li class="mb-3"><?php echo e($item); ?></li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </ul>
                    <h4 class="fw-bold mb-3">Submission Notes</h4>
                    <ul class="ps-3 mb-0">
                        <?php $__currentLoopData = $call['submissionGuidelines']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li class="mb-3"><?php echo e($item); ?></li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-12 cfp-stack" data-aos="fade-up" data-aos-delay="100">
            <div class="card cfp-panel">
                <div class="card-body p-4 p-lg-5">
                    <h3 class="fw-bold mb-4">OJMAS Journal</h3>
                    <div class="border rounded-4 p-4 bg-light">
                        <div class="fs-5 fw-bold mb-2"><?php echo e($call['ojmasCredentials']['publisher']); ?></div>
                        <p class="mb-2"><strong>Website:</strong> <?php echo e($call['ojmasCredentials']['website']); ?></p>
                        <p class="mb-2"><strong>Email:</strong> <?php echo e($call['ojmasCredentials']['email']); ?></p>
                        <p class="mb-3"><strong>Phone:</strong> <?php echo e($call['ojmasCredentials']['phone']); ?></p>
                        <hr class="my-4">
                        <div class="text-muted small text-uppercase fw-semibold mb-2">Licensing Information</div>
                        <div class="fw-bold mb-2"><?php echo e($call['credentials']['publisher']); ?></div>
                        <p class="mb-0"><strong>License:</strong> <?php echo e($call['credentials']['license']); ?></p>
                    </div>
                </div>
            </div>
            <div class="card cfp-panel">
                <div class="card-body p-4 p-lg-5">
                    <h3 class="fw-bold mb-4">Enquiries and Contact</h3>
                    <div class="row g-3">
                        <?php $__currentLoopData = $call['contacts']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $person): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="col-md-6">
                            <div class="border rounded-4 p-4 h-100">
                                <h5 class="fw-bold mb-2"><?php echo e($person['name']); ?></h5>
                                <?php if(!empty($person['role'])): ?>
                                <p class="text-muted fw-semibold mb-2"><?php echo e($person['role']); ?></p>
                                <?php endif; ?>
                                <p class="mb-2"><i
                                        class="bi bi-envelope-fill text-primary me-2"></i><?php echo e($person['email']); ?></p>
                                <p class="mb-0"><i
                                        class="bi bi-telephone-fill text-primary me-2"></i><?php echo e($person['phone']); ?></p>
                            </div>
                        </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\ojmas-journal\resources\views/journal/call-for-papers.blade.php ENDPATH**/ ?>