<?php $__env->startSection('content'); ?>
    <?php echo $__env->make('journal.partials.profile-grid', ['title' => $title, 'subtitle' => $subtitle, 'members' => $members], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\ojmas-journal\resources\views/journal/editorial-board.blade.php ENDPATH**/ ?>