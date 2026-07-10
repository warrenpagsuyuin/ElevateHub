<nav class="app-navbar">
    <strong>Elevate DGM</strong>

    <?php if(auth()->guard()->check()): ?>
        <form method="POST" action="<?php echo e(route('logout')); ?>" class="logout-form">
            <?php echo csrf_field(); ?>
            <button type="submit" class="logout-button">Log out</button>
        </form>
    <?php endif; ?>
</nav>
<?php /**PATH C:\Users\PLPASIG\ElevateHub\elevate-dgm\laravel-app\resources\views/layouts/navbar.blade.php ENDPATH**/ ?>