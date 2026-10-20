<?php $__env->startSection('content'); ?>
<style>
    .auth-wrap{
        min-height: calc(100vh - 120px);
        display: flex;
        align-items: center;
        background:
            linear-gradient(rgba(248,250,252,.86), rgba(248,250,252,.92)),
            url('<?php echo e(asset('images/backgrounds/auth-bg.jpg')); ?>') center center / cover no-repeat;
    }

    .auth-card{
        border: none;
        border-radius: 1.5rem;
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(15, 23, 42, .12);
    }

    .auth-side{
        min-height: 100%;
        background:
            linear-gradient(135deg, rgba(15,23,42,.92), rgba(245,158,11,.82)),
            url('<?php echo e(asset('images/backgrounds/auth-side.jpg')); ?>') center center / cover no-repeat;
        color: #fff;
        position: relative;
    }

    .auth-side::before{
        content: "";
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at top right, rgba(255,255,255,.08), transparent 25%);
    }

    .auth-side-content{
        position: relative;
        z-index: 2;
    }

    .auth-logo-box{
        width: 78px;
        height: 78px;
        border-radius: 1.25rem;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255,255,255,.12);
        border: 1px solid rgba(255,255,255,.18);
        font-size: 2rem;
    }

    .auth-form-area{
        background: #fff;
    }

    .auth-title{
        font-weight: 800;
        color: #0f172a;
    }

    .auth-subtitle{
        color: #64748b;
    }

    .form-control{
        border-radius: .9rem;
        padding: .9rem 1rem;
    }

    .form-control:focus{
        box-shadow: 0 0 0 .25rem rgba(13,110,253,.12);
        border-color: #86b7fe;
    }

    .auth-btn{
        border-radius: .9rem;
        padding: .9rem 1rem;
        font-weight: 600;
    }
</style>

<div class="auth-wrap">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-10 col-lg-11">
                <div class="card auth-card">
                    <div class="row g-0">
                        <div class="col-lg-5 d-none d-lg-block">
                            <div class="auth-side h-100 p-5">
                                <div class="auth-side-content d-flex flex-column justify-content-between h-100">
                                    <div>
                                        <div class="auth-logo-box mb-4">
                                            <i class="bi bi-lock"></i>
                                        </div>
                                        <h2 class="fw-bold mb-3">Confirm Your Password</h2>
                                        <p class="mb-0 text-white-50">
                                            For security, please confirm your password before continuing with this action.
                                        </p>
                                    </div>

                                    <div>
                                        <div class="d-flex">
                                            <i class="bi bi-check-circle-fill me-2 text-dark"></i>
                                            <span>This helps protect your journal account and sensitive actions.</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-7">
                            <div class="auth-form-area p-4 p-md-5">
                                <div class="mb-4" data-aos="fade-up">
                                    <h3 class="auth-title mb-2">Confirm Password</h3>
                                    <p class="auth-subtitle mb-0">
                                        This is a secure area of the application. Please confirm your password before continuing.
                                    </p>
                                </div>

                                <?php if($errors->any()): ?>
                                    <div class="alert alert-danger rounded-4" data-aos="fade-up">
                                        <ul class="mb-0 ps-3">
                                            <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <li><?php echo e($error); ?></li>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" action="<?php echo e(route('password.confirm')); ?>" data-aos="fade-up" data-aos-delay="100">
                                    <?php echo csrf_field(); ?>
<div class="mb-4">
                                        <label for="password" class="form-label fw-semibold">Password</label>
                                        <input
                                            id="password"
                                            type="password"
                                            name="password"
                                            class="form-control"
                                            placeholder="Enter your password"
                                            required
                                            autocomplete="current-password"
                                        >
                                    </div>

                                    <button type="submit" class="btn btn-warning auth-btn w-100">
                                        <i class="bi bi-shield-lock me-2"></i>Confirm Password
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <p class="text-center mt-4 footer-mini mb-0">
                    OJMAS &mdash; Secure access confirmation
                </p>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\ojmas\resources\views/auth/confirm-password.blade.php ENDPATH**/ ?>