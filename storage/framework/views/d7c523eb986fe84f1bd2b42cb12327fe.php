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

    .verify-card{
        border: none;
        border-radius: 1.5rem;
        box-shadow: 0 20px 60px rgba(15, 23, 42, .12);
        overflow: hidden;
    }

    .verify-hero{
        background:
            linear-gradient(135deg, rgba(13,110,253,.95), rgba(15,23,42,.92)),
            url('<?php echo e(asset('images/backgrounds/auth-side.jpg')); ?>') center center / cover no-repeat;
        color: #fff;
    }

    .verify-icon{
        width: 88px;
        height: 88px;
        border-radius: 1.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255,255,255,.12);
        border: 1px solid rgba(255,255,255,.18);
        font-size: 2.2rem;
        margin: 0 auto 1rem;
    }

    .verify-title{
        font-weight: 800;
        color: #0f172a;
    }

    .verify-btn{
        border-radius: .9rem;
        padding: .9rem 1rem;
        font-weight: 600;
    }
</style>

<div class="auth-wrap">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">
                <div class="card verify-card">
                    <div class="verify-hero text-center p-5" data-aos="fade-down">
                        <div class="verify-icon">
                            <i class="bi bi-envelope-check"></i>
                        </div>
                        <h2 class="fw-bold mb-2">Verify Your Email Address</h2>
                        <p class="mb-0 text-white-50">
                            Before proceeding, please verify your email address by clicking the link we sent to your inbox.
                        </p>
                    </div>

                    <div class="card-body p-4 p-md-5 text-center">
                        <?php if(session('status') == 'verification-link-sent'): ?>
                            <div class="alert alert-success rounded-4" data-aos="fade-up">
                                A new verification link has been sent to the email address you provided during registration.
                            </div>
                        <?php endif; ?>

                        <p class="text-muted mb-4" data-aos="fade-up">
                            If you didn’t receive the email, we can send another verification link right away.
                        </p>

                        <form method="POST" action="<?php echo e(route('verification.send')); ?>" class="mb-3" data-aos="fade-up" data-aos-delay="100">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="btn btn-primary verify-btn px-4">
                                <i class="bi bi-send-check me-2"></i>Resend Verification Email
                            </button>
                        </form>

                        <form method="POST" action="<?php echo e(route('logout')); ?>" data-aos="fade-up" data-aos-delay="200">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="btn btn-outline-danger verify-btn px-4">
                                <i class="bi bi-box-arrow-right me-2"></i>Log Out
                            </button>
                        </form>
                    </div>
                </div>

                <p class="text-center mt-4 footer-mini mb-0">
                    OJMAS &mdash; Email verification required for secure access
                </p>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\ojmas\resources\views/auth/verify-email.blade.php ENDPATH**/ ?>