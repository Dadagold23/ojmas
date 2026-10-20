<?php $__env->startSection('content'); ?>
<style>
.dash-hero {
    border: none;
    border-radius: 1.5rem;
    background: linear-gradient(135deg, rgba(13, 110, 253, .95), rgba(15, 23, 42, .92)),
    url('<?php echo e(asset('images/backgrounds/editor-hero.jpg')); ?>') center center / cover no-repeat;
    color: #fff;
    overflow: hidden;
    box-shadow: 0 20px 50px rgba(15, 23, 42, .16);
}

.dash-title {
    font-weight: 800;
    color: #0f172a;
}

.dash-stat-card {
    border: none;
    border-radius: 1.25rem;
    box-shadow: 0 18px 40px rgba(15, 23, 42, .08);
    transition: .3s ease;
}

.dash-stat-card:hover {
    transform: translateY(-4px);
}

.dash-stat-icon {
    width: 58px;
    height: 58px;
    border-radius: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
}

.dash-panel {
    border: none;
    border-radius: 1.25rem;
    box-shadow: 0 18px 40px rgba(15, 23, 42, .08);
}
</style>

<div class="container py-4">
    <div class="card dash-hero mb-4" data-aos="fade-up">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h2 class="fw-bold mb-3">Editorial Dashboard</h2>
                    <p class="mb-0 text-white-50">
                        Manage submissions, coordinate peer review, track decisions, and prepare accepted articles for
                        publication.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                    <i class="bi bi-pencil-square" style="font-size: 5rem; opacity: .18;"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4" data-aos="fade-up">
        <div>
            <h3 class="dash-title mb-1">Editor Dashboard</h3>
            <p class="text-muted mb-0">Overview of submissions and editorial activities.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?php echo e(route('editor.volumes.index')); ?>" class="btn btn-info btn-sm fw-semibold text-white">
                <i class="bi bi-journal-bookmark me-1"></i>Volumes
            </a>
            <a href="<?php echo e(route('editor.issues.index')); ?>" class="btn btn-secondary btn-sm fw-semibold">
                <i class="bi bi-journals me-1"></i>Issues
            </a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6 col-xl-3" data-aos="fade-up">
            <div class="card dash-stat-card h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between mb-3">
                        <div>
                            <div class="text-muted small mb-2">Assigned Submissions</div>
                            <h2 class="fw-bold mb-0"><?php echo e($stats['assigned'] ?? 0); ?></h2>
                        </div>
                        <div class="dash-stat-icon bg-primary-subtle text-primary">
                            <i class="bi bi-inbox"></i>
                        </div>
                    </div>
                    <small class="text-muted">All submissions currently in editorial scope.</small>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3" data-aos="fade-up" data-aos-delay="100">
            <div class="card dash-stat-card h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between mb-3">
                        <div>
                            <div class="text-muted small mb-2">Under Review</div>
                            <h2 class="fw-bold mb-0"><?php echo e($stats['under_review'] ?? 0); ?></h2>
                        </div>
                        <div class="dash-stat-icon bg-warning-subtle text-warning">
                            <i class="bi bi-search"></i>
                        </div>
                    </div>
                    <small class="text-muted">Manuscripts presently under peer review.</small>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3" data-aos="fade-up" data-aos-delay="200">
            <div class="card dash-stat-card h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between mb-3">
                        <div>
                            <div class="text-muted small mb-2">Accepted</div>
                            <h2 class="fw-bold mb-0"><?php echo e($stats['accepted'] ?? 0); ?></h2>
                        </div>
                        <div class="dash-stat-icon bg-success-subtle text-success">
                            <i class="bi bi-patch-check"></i>
                        </div>
                    </div>
                    <small class="text-muted">Manuscripts approved for publication.</small>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3" data-aos="fade-up" data-aos-delay="300">
            <div class="card dash-stat-card h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between mb-3">
                        <div>
                            <div class="text-muted small mb-2">Rejected</div>
                            <h2 class="fw-bold mb-0"><?php echo e($stats['rejected'] ?? 0); ?></h2>
                        </div>
                        <div class="dash-stat-icon bg-danger-subtle text-danger">
                            <i class="bi bi-x-octagon"></i>
                        </div>
                    </div>
                    <small class="text-muted">Manuscripts declined after review.</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card dash-panel" data-aos="fade-up">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold mb-1">Recent Submissions</h5>
                <p class="text-muted mb-0">Open the manuscript or jump into the editorial record.</p>
            </div>
            <a href="<?php echo e(route('editor.submissions.index')); ?>" class="btn btn-outline-primary btn-sm">
                View All
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4">Manuscript</th>
                            <th>Author</th>
                            <th>Status</th>
                            <th>Files</th>
                            <th class="pe-4 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $submissions ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $submission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <?php
                        $primaryFile = $submission->files->firstWhere('file_type', 'manuscript') ??
                        $submission->files->first();
                        $statusColor = [
                        'pending_payment' => 'warning',
                        'submitted' => 'primary',
                        'under_review' => 'warning',
                        'accepted' => 'success',
                        'rejected' => 'danger',
                        'published' => 'dark',
                        'revision_requested' => 'info',
                        ][$submission->status] ?? 'secondary';
                        ?>
                        <tr>
                            <td class="px-4">
                                <div class="fw-semibold small"><?php echo e(Str::limit($submission->title, 55)); ?></div>
                                <div class="text-muted" style="font-size:.75rem"><?php echo e($submission->submission_no); ?></div>
                            </td>
                            <td class="small text-muted"><?php echo e($submission->author->name ?? 'N/A'); ?></td>
                            <td>
                                <span class="badge bg-<?php echo e($statusColor); ?>">
                                    <?php echo e(ucwords(str_replace('_', ' ', $submission->status))); ?>

                                </span>
                            </td>
                            <td class="small text-muted"><?php echo e($submission->files->count()); ?> file(s)</td>
                            <td class="pe-4 text-end">
                                <div class="d-inline-flex gap-2">
                                    <?php if($primaryFile): ?>
                                    <a href="<?php echo e(route('editor.submissions.files.download', [$submission, $primaryFile])); ?>"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-download me-1"></i>Download Manuscript
                                    </a>
                                    <?php endif; ?>
                                    <a href="<?php echo e(route('editor.submissions.show', $submission)); ?>"
                                        class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-eye me-1"></i>Open
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No recent submissions.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card dash-panel mt-4" data-aos="fade-up">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
            <h5 class="fw-bold mb-1">Recent Editorial Activity</h5>
            <p class="text-muted mb-3">Latest actions and workflow updates.</p>
        </div>
        <div class="card-body px-4 pb-4">
            <ul class="list-group list-group-flush">
                <?php $__empty_1 = true; $__currentLoopData = $activities ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $activity): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <li class="list-group-item px-0"><?php echo e($activity); ?></li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <li class="list-group-item px-0 text-muted">No activity available.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\ojmas\resources\views/editor/dashboard.blade.php ENDPATH**/ ?>