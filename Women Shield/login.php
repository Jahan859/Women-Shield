<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$loginMode = (string) ($_GET['mode'] ?? 'user');
$loginMode = $loginMode === 'admin' ? 'admin' : 'user';

if (is_authenticated()) {
    redirect_to(authenticated_home_path());
}

$errors = [];

if (is_post()) {
    verify_csrf_token();
    $loginMode = (string) ($_POST['mode'] ?? $loginMode);
    $loginMode = $loginMode === 'admin' ? 'admin' : 'user';

    try {
        $success = $loginMode === 'admin'
            ? attempt_admin_login((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''))
            : attempt_login((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));

        if ($success) {
            flash_message('success', $loginMode === 'admin'
                ? 'Welcome back. Admin controls are ready.'
                : 'Welcome back. Your safety dashboard is ready.');
            redirect_to(authenticated_home_path());
        }

        $errors[] = $loginMode === 'admin'
            ? 'Admin username or password is incorrect.'
            : 'Email or password is incorrect.';
    } catch (Throwable $exception) {
        $errors[] = $loginMode === 'admin'
            ? 'Admin login failed. Make sure the `admin` table exists and your XAMPP database is running.'
            : 'Database connection failed. Import the schema and verify config/local.php for XAMPP.';
    }
}

render_header('Login', ['page_class' => 'auth-page']);
?>

<section class="auth-wrap">
    <div class="panel auth-panel">
        <span class="eyebrow"><?= $loginMode === 'admin' ? 'Admin Login' : 'Login System' ?></span>
        <h1><?= $loginMode === 'admin' ? 'Login To Admin Controls' : 'Login To Your Safety Workspace' ?></h1>
        <p>
            <?= $loginMode === 'admin'
                ? 'Use your admin username or legacy admin email to access the admin dashboard and email setup controls.'
                : 'Use your registered email to access reports, emergency tools, and AI assistance.' ?>
        </p>

        <?php if ($errors): ?>
            <div class="inline-errors">
                <?php foreach ($errors as $error): ?>
                    <p><?= e($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="mode" value="<?= e($loginMode) ?>">
            <?php if ($loginMode === 'admin'): ?>
                <label>
                    <span>Admin Username Or Email</span>
                    <input type="text" name="username" placeholder="admin" required>
                </label>
            <?php else: ?>
                <label>
                    <span>Email Address</span>
                    <input type="email" name="email" placeholder="you@example.com" required>
                </label>
            <?php endif; ?>
            <label>
                <span>Password</span>
                <input type="password" name="password" placeholder="Minimum 8 characters" required>
            </label>
            <button class="button button-primary" type="submit"><?= $loginMode === 'admin' ? 'Admin Login' : 'Login' ?></button>
        </form>

        <div class="auth-links">
            <?php if ($loginMode !== 'admin'): ?>
                <p><a href="<?= e(route_url('forgot_password.php')) ?>">Forgot password?</a></p>
                <p>Need an account? <a href="<?= e(route_url('register.php')) ?>">Register here</a></p>
            <?php endif; ?>
        </div>
    </div>
</section>
