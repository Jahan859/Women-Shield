<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if (is_authenticated()) {
    redirect_to(authenticated_home_path());
}

$errors = [];
$formData = [
    'name' => '',
    'phone' => '',
    'email' => '',
];

if (is_post()) {
    verify_csrf_token();
    $formData = [
        'name' => trim((string) ($_POST['name'] ?? '')),
        'phone' => trim((string) ($_POST['phone'] ?? '')),
        'email' => trim((string) ($_POST['email'] ?? '')),
    ];

    try {
        $errors = register_account($_POST);

        if ($errors === []) {
            flash_message('success', 'Your account has been created successfully.');
            redirect_to(authenticated_home_path());
        }
    } catch (Throwable $exception) {
        $errors[] = 'Database connection failed. Import the schema and verify your XAMPP MySQL credentials.';
    }
}

render_header('Register', ['page_class' => 'auth-page']);
?>

<section class="auth-wrap">
    <div class="panel auth-panel wide">
        <span class="eyebrow">Create Account</span>
        <h1>Set Up Your Women Shield Profile</h1>
        <p>Add your personal contact details so the dashboard can organize alerts and emergency plans.</p>

        <?php if ($errors): ?>
            <div class="inline-errors">
                <?php foreach ($errors as $error): ?>
                    <p><?= e($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" class="form-grid two-col">
            <?= csrf_field() ?>
            <label>
                <span>Full Name</span>
                <input type="text" name="name" value="<?= e($formData['name']) ?>" placeholder="Your name" required>
            </label>
            <label>
                <span>Phone Number</span>
                <input type="text" name="phone" value="<?= e($formData['phone']) ?>" placeholder="01712345678" inputmode="numeric" maxlength="11" pattern="01[0-9]{9}" title="Enter an 11-digit Bangladesh mobile number">
            </label>
            <label>
                <span>Email Address</span>
                <input type="email" name="email" value="<?= e($formData['email']) ?>" placeholder="you@example.com" required>
            </label>
            <label>
                <span>Password</span>
                <input type="password" name="password" placeholder="Minimum 8 characters" required>
            </label>
            <p class="panel-note full-span">Bangladesh mobile numbers only: 11 digits starting with <strong>01</strong>. Example: <strong>01712345678</strong>.</p>
            <button class="button button-primary full-span" type="submit">Create Account</button>
        </form>

        <div class="auth-links">
            <p>Already registered? <a href="<?= e(route_url('login.php')) ?>">Login instead</a></p>
        </div>
    </div>
</section>

