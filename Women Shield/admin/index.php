<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$mailStatus = mail_setup_status();

render_header('Admin Dashboard', [
    'description' => 'Privacy-first admin controls for Women Shield.',
]);
?>

<section class="section-block">
    <div class="section-heading">
        <span class="eyebrow">Admin Dashboard</span>
        <h1>Privacy-first admin controls</h1>
        <p>Admin access is limited to system configuration. User reports, contacts, activity, and live emergency details are hidden here.</p>
    </div>
</section>

<section class="card-grid two-up">
    <article class="panel">
        <div class="panel-head">
            <div>
                <span class="eyebrow">Email Delivery</span>
                <h2><?= $mailStatus['transport_ready'] ? 'Email delivery is configured' : 'Email delivery needs setup' ?></h2>
            </div>
            <span class="badge <?= $mailStatus['transport_ready'] ? 'badge-safe' : 'badge-danger' ?>">
                <?= e($mailStatus['driver_label']) ?>
            </span>
        </div>

        <?php if ($mailStatus['transport_issues']): ?>
            <ul class="feature-list compact">
                <?php foreach ($mailStatus['transport_issues'] as $issue): ?>
                    <li><?= e($issue) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>SMTP settings are ready for emergency alerts. Open Email Setup if you need to update sender details or run another test email.</p>
        <?php endif; ?>

        <a class="button button-primary" href="<?= e(route_url('mail_setup.php')) ?>">Open Email Setup</a>
    </article>

    <article class="panel">
        <div class="panel-head">
            <div>
                <span class="eyebrow">Privacy Mode</span>
                <h2>User activity is hidden</h2>
            </div>
        </div>

        <ul class="feature-list compact">
            <li>Reports are not shown in the admin panel.</li>
            <li>Emergency contacts are not shown in the admin panel.</li>
            <li>User activity timelines are not shown in the admin panel.</li>
            <li>Emergency email alerts no longer include location details.</li>
        </ul>
    </article>
</section>

<section class="card-grid two-up">
    <article class="panel">
        <div class="panel-head">
            <div>
                <span class="eyebrow">Admin Access</span>
                <h2>What this dashboard can do</h2>
            </div>
        </div>

        <p>Use the admin account for SMTP configuration, sender identity, and mail testing only.</p>

        <div class="pill-row">
            <span class="pill">Email Setup</span>
            <span class="pill">SMTP Test</span>
            <span class="pill">Config Review</span>
        </div>
    </article>

    <article class="panel">
        <div class="panel-head">
            <div>
                <span class="eyebrow">Protected Data</span>
                <h2>Not visible to admin</h2>
            </div>
        </div>

        <ul class="feature-list compact">
            <li>User report descriptions</li>
            <li>Emergency contact lists</li>
            <li>Live or saved location details</li>
            <li>Personal activity history</li>
        </ul>
    </article>
</section>

<?php render_footer(); ?>
