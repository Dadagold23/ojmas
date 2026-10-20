<?php $__env->startSection('content'); ?>
<style>
    .auth-wrap { min-height: calc(100vh - 120px); display:flex; align-items:center;
        background: linear-gradient(rgba(248,250,252,.86), rgba(248,250,252,.92)),
        url('<?php echo e(asset('images/backgrounds/auth-bg.jpg')); ?>') center center / cover no-repeat; }
    .auth-card { border:none; border-radius:1.5rem; overflow:hidden; box-shadow:0 20px 60px rgba(15,23,42,.12); }
    .auth-side { min-height:100%;
        background: linear-gradient(135deg, rgba(15,23,42,.92), rgba(245,158,11,.82)),
        url('<?php echo e(asset('images/backgrounds/auth-side.jpg')); ?>') center center / cover no-repeat;
        color:#fff; position:relative; }
    .auth-side::before { content:""; position:absolute; inset:0;
        background: radial-gradient(circle at bottom left, rgba(13,110,253,.2), transparent 28%); }
    .auth-side-content { position:relative; z-index:2; }
    .auth-logo-box { width:78px; height:78px; border-radius:1.25rem; display:flex; align-items:center;
        justify-content:center; background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.18); font-size:2rem; }
    .auth-form-area { background:#fff; }
    .auth-title { font-weight:800; color:#0f172a; }
    .auth-subtitle { color:#64748b; }
    .form-control { border-radius:.9rem; padding:.75rem 1rem; }
    .form-control:focus { box-shadow:0 0 0 .25rem rgba(13,110,253,.12); border-color:#86b7fe; }
    .input-group .form-control { border-radius:.9rem 0 0 .9rem; }
    .input-group .btn { border-radius:0 .9rem .9rem 0; border-color:#dee2e6; }
    .auth-btn { border-radius:.9rem; padding:.9rem 1rem; font-weight:600; }
    .section-divider { font-size:.78rem; text-transform:uppercase; letter-spacing:.08em; color:#94a3b8; font-weight:600; }
</style>

<div class="auth-wrap">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-11 col-lg-12">
                <div class="card auth-card">
                    <div class="row g-0">

                        
                        <div class="col-lg-4 d-none d-lg-block">
                            <div class="auth-side h-100 p-5">
                                <div class="auth-side-content d-flex flex-column justify-content-between h-100">
                                    <div>
                                        <div class="auth-logo-box mb-4">
                                            <i class="bi bi-journal-richtext"></i>
                                        </div>
                                        <h2 class="fw-bold mb-3">Join OJMAS</h2>
                                        <p class="mb-0 text-white-50">
                                            Create your author account and become part of a secure digital publishing ecosystem.
                                        </p>
                                    </div>
                                    <div>
                                        <div class="d-flex mb-3">
                                            <i class="bi bi-check-circle-fill me-2 text-warning"></i>
                                            <span>Submit and track manuscripts online.</span>
                                        </div>
                                        <div class="d-flex mb-3">
                                            <i class="bi bi-check-circle-fill me-2 text-warning"></i>
                                            <span>Monitor editorial decisions and reviews.</span>
                                        </div>
                                        <div class="d-flex">
                                            <i class="bi bi-check-circle-fill me-2 text-warning"></i>
                                            <span>Professional journal platform for modern research.</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        
                        <div class="col-lg-8">
                            <div class="auth-form-area p-4 p-md-5">
                                <div class="mb-4" data-aos="fade-up">
                                    <h3 class="auth-title mb-1">Create your account</h3>
                                    <p class="auth-subtitle mb-0">Fill in your details to register on OJMAS.</p>
                                </div>

                                <?php if($errors->any()): ?>
                                <div class="alert alert-danger rounded-4 mb-4">
                                    <ul class="mb-0 ps-3"><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($e); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul>
                                </div>
                                <?php endif; ?>

                                <form method="POST" action="<?php echo e(route('register')); ?>" data-aos="fade-up" data-aos-delay="100">
                                    <?php echo csrf_field(); ?>

                                    
                                    <p class="section-divider mb-3">Personal Information</p>
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold small">Full Name <span class="text-danger">*</span></label>
                                            <input type="text" name="name" class="form-control <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                                                   value="<?php echo e(old('name')); ?>" placeholder="e.g. Dr. John Adeyemi" required autofocus>
                                            <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold small">Email Address <span class="text-danger">*</span></label>
                                            <input type="email" name="email" class="form-control <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                                                   value="<?php echo e(old('email')); ?>" placeholder="you@example.com" required autocomplete="username">
                                            <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold small">Phone Number <span class="text-danger">*</span></label>
                                            <input type="text" name="phone" class="form-control <?php $__errorArgs = ['phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                                                   value="<?php echo e(old('phone')); ?>" placeholder="+234 800 000 0000" required>
                                            <?php $__errorArgs = ['phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold small">Country <span class="text-danger">*</span></label>
                                            <input type="text" name="country" class="form-control <?php $__errorArgs = ['country'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                                                   value="<?php echo e(old('country')); ?>" placeholder="e.g. Nigeria" required>
                                            <?php $__errorArgs = ['country'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label fw-semibold small">Institution / Affiliation <span class="text-danger">*</span></label>
                                            <input type="text" name="institution" class="form-control <?php $__errorArgs = ['institution'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                                                   value="<?php echo e(old('institution')); ?>" placeholder="e.g. University of Lagos" required>
                                            <?php $__errorArgs = ['institution'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                        </div>
                                    </div>

                                    
                                    <p class="section-divider mb-3">Security</p>
                                    <div class="row g-3 mb-4">
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold small">Password <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <input type="password" id="password" name="password"
                                                       class="form-control <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                                                       placeholder="Create a password" required autocomplete="new-password">
                                                <button type="button" class="btn btn-outline-secondary"
                                                        onclick="togglePassword('password', this)" tabindex="-1">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold small">Confirm Password <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <input type="password" id="password_confirmation" name="password_confirmation"
                                                       class="form-control" placeholder="Repeat your password"
                                                       required autocomplete="new-password">
                                                <button type="button" class="btn btn-outline-secondary"
                                                        onclick="togglePassword('password_confirmation', this)" tabindex="-1">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-warning auth-btn w-100 mb-3">
                                        <i class="bi bi-person-plus-fill me-2"></i>Create Account
                                    </button>

                                    <div class="text-center">
                                        <span class="text-muted">Already registered?</span>
                                        <a href="<?php echo e(route('login')); ?>" class="text-decoration-none fw-semibold ms-1">Login here</a>
                                    </div>
                                </form>
                            </div>
                        </div>

                    </div>
                </div>
                <p class="text-center mt-4 footer-mini mb-0">OJMAS &mdash; Secure registration for academic publishing</p>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword(fieldId, btn) {
    const input = document.getElementById(fieldId);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\ojmas\resources\views/auth/register.blade.php ENDPATH**/ ?>