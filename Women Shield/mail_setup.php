<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require_admin();

$account = current_account();
$errors = [];
$form = current_mail_settings();

if (is_post()) {
    verify_csrf_token();
    $action = (string) ($_POST['action'] ?? 'save');
    $saveResult = save_mail_settings($_POST);
    $form = $saveResult['settings'];

    if (!$saveResult['success']) {
        $errors = $saveResult['errors'];
    } else {
        if ($action === 'test') {
            $testResult = send_mail_setup_test_email([
                'email' => (string) ($form['from_email'] ?: $form['smtp_username']),
                'name' => (string) ($account['name'] ?? 'Administrator'),
            ]);

            if ($testResult['success']) {
                flash_message('success', 'Email settings saved. ' . $testResult['message']);
            } else {
                flash_message('error', 'Email settings saved, but the test email failed. ' . $testResult['message']);
            }
        } else {
            flash_message('success', 'Email settings saved. Emergency Mode will now use the new SMTP setup.');
        }

        redirect_to('mail_setup.php');
    }
}

$mailStatus = mail_setup_status();
$testEmailTarget = (string) ($form['from_email'] ?: $form['smtp_username'] ?: 'Not configured yet');

render_header('Email Settings', [
    'description' => 'Configure SMTP email delivery for Women Shield emergency alerts.',
]);
?>

<section class="section-block settings-shell">
    <div class="section-heading settings-heading">
        <span class="eyebrow">Email Settings</span>
        <h1>Configure Women Shield Mail Delivery</h1>
        <p>Save the SMTP sender settings once from the admin area, then use the test button to verify that Emergency Mode can email trusted contacts.</p>
        <p>If you use Gmail or Google Workspace, use <strong>smtp.gmail.com</strong>, port <strong>587</strong>, <strong>TLS</strong>, and a Google App Password. PHPMailer is optional because Women Shield can also send with its built-in SMTP engine.</p>
    </div>
</section>

<section class="section-block settings-shell">
    <?php if ($mailStatus['transport_ready']): ?>
        <div class="flash flash-success settings-banner">
            Email notifications are configured.
        </div>
    <?php else: ?>
        <div class="flash flash-error settings-banner">
            Email notifications still need setup before Emergency Mode can send reliable alerts.
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="inline-errors">
            <?php foreach ($errors as $error): ?>
                <p><?= e($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <article class="panel settings-panel">
        <div class="panel-head">
            <div>
                <span class="eyebrow">SMTP Setup</span>
                <h2>Sender Configuration</h2>
            </div>
            <span class="badge <?= $mailStatus['transport_ready'] ? 'badge-safe' : 'badge-danger' ?>">
                <?= e($mailStatus['driver_label']) ?>
            </span>
        </div>

        <div class="pill-row settings-pills">
            <span class="pill">SMTP engine: <?= $mailStatus['phpmailer_available'] ? 'PHPMailer' : 'Built-in SMTP' ?></span>
            <span class="pill">Contacts with email: <?= e((string) $mailStatus['contacts_with_email']) ?>/<?= e((string) $mailStatus['contacts_total']) ?></span>
            <span class="pill">Config file: <?= $mailStatus['local_config'] ? 'Ready' : 'Will be created' ?></span>
        </div>

        <form method="post" class="form-grid two-col settings-form">
            <?= csrf_field() ?>

            <label>
                <span>SMTP Host</span>
                <input type="text" name="smtp_host" value="<?= e($form['smtp_host']) ?>" placeholder="smtp.gmail.com" required>
            </label>

            <label>
                <span>Public Site URL (optional)</span>
                <input type="url" name="public_site_url" value="<?= e($form['public_site_url']) ?>" placeholder="http://localhost/Logno">
            </label>

            <label>
                <span>SMTP Port</span>
                <input type="number" name="smtp_port" value="<?= e($form['smtp_port']) ?>" min="1" placeholder="587" required>
            </label>

            <label>
                <span>Encryption</span>
                <select name="encryption">
                    <option value="tls" <?= selected_option('tls', $form['encryption']) ?>>TLS / STARTTLS</option>
                    <option value="ssl" <?= selected_option('ssl', $form['encryption']) ?>>SSL</option>
                    <option value="none" <?= selected_option('none', $form['encryption']) ?>>None</option>
                </select>
            </label>

            <label>
                <span>SMTP Username</span>
                <input type="text" name="smtp_username" value="<?= e($form['smtp_username']) ?>" placeholder="your-email@gmail.com" required>
            </label>

            <label>
                <span>SMTP Password / App Password</span>
                <input type="password" name="smtp_password" value="" placeholder="Enter a new SMTP password">
                <small class="field-hint">Current value: <?= e($form['smtp_password_mask']) ?>. Leave blank to keep the saved password.</small>
            </label>

            <label class="checkbox-row full-span">
                <input type="checkbox" name="clear_saved_password" value="1" <?= $form['clear_saved_password'] ? 'checked' : '' ?>>
                <span>Clear saved SMTP password</span>
            </label>

            <label>
                <span>From Email</span>
                <input type="email" name="from_email" value="<?= e($form['from_email']) ?>" placeholder="your-email@gmail.com" required>
            </label>

            <label>
                <span>From Name</span>
                <input type="text" name="from_name" value="<?= e($form['from_name']) ?>" placeholder="Women Shield" required>
            </label>

            <div class="button-row full-span settings-actions">
                <button class="button button-primary" type="submit" name="action" value="save">Save Settings</button>
                <button class="button button-secondary" type="submit" name="action" value="test">Go To Test Email</button>
            </div>
        </form>
    </article>
</section>

<section class="card-grid two-up settings-shell">
    <article class="panel">
        <div class="panel-head">
            <div>
                <span class="eyebrow">Transport Status</span>
                <h2><?= $mailStatus['transport_ready'] ? 'SMTP Delivery Is Ready' : 'SMTP delivery needs attention' ?></h2>
            </div>
        </div>

        <?php if ($mailStatus['transport_issues']): ?>
            <ul class="feature-list compact">
                <?php foreach ($mailStatus['transport_issues'] as $issue): ?>
                    <li><?= e($issue) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>Your site mail transport is configured. Emergency Mode can now send through <?= e($mailStatus['driver_label']) ?>.</p>
        <?php endif; ?>
    </article>

    <article class="panel">
        <div class="panel-head">
            <div>
                <span class="eyebrow">Emergency Delivery</span>
                <h2><?= $mailStatus['contact_ready'] ? 'Emergency Contact Coverage Looks Good' : 'Some contacts still need email addresses' ?></h2>
            </div>
        </div>

        <?php if ($mailStatus['contact_issues']): ?>
            <ul class="feature-list compact">
                <?php foreach ($mailStatus['contact_issues'] as $issue): ?>
                    <li><?= e($issue) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>Your saved emergency network includes at least one contact with an email address, so Emergency Mode can notify them automatically.</p>
        <?php endif; ?>

        <p class="panel-note">The test email is sent to the configured sender email: <strong><?= e($testEmailTarget) ?></strong>.</p>
    </article>
</section>
