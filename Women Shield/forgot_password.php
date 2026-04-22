<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if (is_authenticated()) {
    redirect_to(authenticated_home_path());
}

$errors = [];
$formData = [
    'email' => '',
    'phone' => '',
];

if (is_post()) {
    verify_csrf_token();
    $formData = [
        'email' => trim((string) ($_POST['email'] ?? '')),
        'phone' => trim((string) ($_POST['phone'] ?? '')),
    ];

    try {
        $errors = reset_account_password(
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['phone'] ?? ''),
            (string) ($_POST['password'] ?? ''),
            (string) ($_POST['password_confirmation'] ?? '')
        );

        if ($errors === []) {
            flash_message('success', 'Password reset successfully. Please sign in with your new password.');
            redirect_to('login.php');
        }
    } catch (Throwable $exception) {
        $errors[] = 'Database connection failed. Start MySQL and verify config/local.php.';
    }
}

render_header('Forgot Password', ['page_class' => 'auth-page']);
?>

<section class="auth-wrap">
    <div class="panel auth-panel wide">
        <span class="eyebrow">Account Recovery</span>
        <h1>Reset your password</h1>
        <p>Use your registered email. If your account has a phone number saved, enter the same number for verification.</p>

        <?php if ($errors): ?>
            <div class="inline-errors">
                <?php foreach ($errors as $error): ?>
                    <p><?= e($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" class="form-grid two-col">
            <?= csrf_field() ?>
            <label class="full-span">
                <span>Email Address</span>
                <input type="email" name="email" value="<?= e($formData['email']) ?>" placeholder="you@example.com" required>
            </label>
            <label>
                <span>Phone Number</span>
                <input type="text" name="phone" value="<?= e($formData['phone']) ?>" placeholder="01712345678" inputmode="numeric" maxlength="11" pattern="01[0-9]{9}" title="Enter an 11-digit Bangladesh mobile number">
            </label>
            <label>
                <span>New Password</span>
                <input type="password" name="password" placeholder="Minimum 8 characters" required>
            </label>
            <label>
                <span>Confirm Password</span>
                <input type="password" name="password_confirmation" placeholder="Re-enter new password" required>
            </label>
            <p class="panel-note full-span">If your account has a saved phone number, enter the same Bangladesh mobile number in 11-digit format.</p>
            <button class="button button-primary full-span" type="submit">Reset Password</button>
        </form>

        <div class="auth-links">
            <p><a href="<?= e(route_url('login.php')) ?>">Back to login</a></p>
            <p>Need a new account instead? <a href="<?= e(route_url('register.php')) ?>">Register here</a></p>
        </div>
    </div>
</section>

<?php render_footer(); ?>
