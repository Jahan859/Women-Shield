<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require_login();

$user = current_user();
$metrics = get_dashboard_metrics((int) $user['id']);
$latestReport = $metrics['latest_report'];
$latestAlertActions = $latestReport ? build_alert_agent_actions($latestReport, $metrics['contacts']) : null;

render_header('Dashboard', [
    'description' => 'Safety dashboard with AI insights, alerts, and monitoring.',
]);
?>

<section class="section-block">
    <div class="section-heading">
        <span class="eyebrow">Personal Dashboard</span>
        <h1>Welcome, <?= e($user['name']) ?></h1>
        <p>Track your reports, emergency readiness, and AI-generated recommendations from one screen.</p>
    </div>

    <div class="card-grid four-up">
        <article class="panel metric-card">
            <span class="metric-label">Reports</span>
            <strong><?= e((string) $metrics['total_reports']) ?></strong>
            <small>Total incidents filed</small>
        </article>
        <article class="panel metric-card">
            <span class="metric-label">High Risk</span>
            <strong><?= e((string) $metrics['high_risk_count']) ?></strong>
            <small>Danger score 70 or higher</small>
        </article>
        <article class="panel metric-card">
            <span class="metric-label">Contacts</span>
            <strong><?= e((string) $metrics['total_contacts']) ?></strong>
            <small>Emergency contacts on file</small>
        </article>
        <article class="panel metric-card">
            <span class="metric-label">Avg Danger</span>
            <strong><?= e((string) $metrics['average_danger']) ?></strong>
            <small>Calculated from your reports</small>
        </article>
    </div>
</section>

<section class="card-grid two-up">
    <article class="panel">
        <div class="panel-head">
            <div>
                <span class="eyebrow">Monitoring Agent</span>
                <h2><?= e($metrics['monitoring']['headline']) ?></h2>
            </div>
            <span class="badge badge-warn"><?= e($metrics['monitoring']['hotspot']) ?></span>
        </div>
        <p><?= e($metrics['monitoring']['summary']) ?></p>
        <div class="mini-stats">
            <span>Watch queue: <?= e((string) $metrics['monitoring']['watch_count']) ?></span>
            <span>Flagged reports: <?= e((string) $metrics['monitoring']['flagged_count']) ?></span>
        </div>
    </article>

    <article class="panel">
        <div class="panel-head">
            <div>
                <span class="eyebrow">AI Insights</span>
                <h2>Your Report Pattern</h2>
            </div>
            <span class="badge badge-caution"><?= e($metrics['insights']['peak_hour']) ?></span>
        </div>
        <p><?= e($metrics['insights']['community_summary']) ?></p>
        <div class="mini-stats">
            <span>Top category: <?= e($metrics['insights']['top_category']) ?></span>
            <span>Resolution rate: <?= e((string) $metrics['insights']['resolution_rate']) ?>%</span>
        </div>
    </article>
</section>

<section class="card-grid two-up">
    <article class="panel">
        <div class="panel-head">
            <div>
                <span class="eyebrow">Alert Agent</span>
                <h2>Action Guidance</h2>
            </div>
            <a class="button button-secondary button-small" href="<?= e(route_url('emergency.php')) ?>">Open Emergency Mode</a>
        </div>
        <?php if ($latestAlertActions): ?>
            <p><strong><?= e($latestAlertActions['level']) ?>:</strong> <?= e($latestAlertActions['message']) ?></p>
            <div class="pill-row">
                <?php foreach ($latestAlertActions['contacts'] as $contactName): ?>
                    <span class="pill"><?= e($contactName) ?></span>
                <?php endforeach; ?>
                <?php if ($latestAlertActions['contacts'] === []): ?>
                    <span class="pill">No contacts saved yet</span>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p>No reports yet. Once you create one, the alert agent will recommend what to do next.</p>
        <?php endif; ?>
    </article>

    <article class="panel">
        <div class="panel-head">
            <div>
                <span class="eyebrow">Emergency Status</span>
                <h2><?= $metrics['active_emergency'] ? 'Emergency Mode Active' : 'Emergency Mode Standby' ?></h2>
            </div>
            <span class="badge <?= $metrics['active_emergency'] ? 'badge-danger' : 'badge-safe' ?>">
                <?= $metrics['active_emergency'] ? 'Active' : 'Ready' ?>
            </span>
        </div>
        <p>
            <?= $metrics['active_emergency']
                ? 'Emergency Mode is active right now. Keep moving toward a safe place and share updates with trusted contacts.'
                : 'Emergency Mode can instantly create a response workflow, prepare a share message, and surface your top contacts.' ?>
        </p>
    </article>
</section>

<section class="card-grid two-up">
    <article class="panel">
        <div class="panel-head">
            <div>
                <span class="eyebrow">Recent Alerts</span>
                <h2>System Notifications</h2>
            </div>
        </div>
        <?php if ($metrics['alerts']): ?>
            <div class="timeline-list">
                <?php foreach ($metrics['alerts'] as $alert): ?>
                    <div class="timeline-item">
                        <strong><?= e(ucwords(str_replace('-', ' ', $alert['alert_type']))) ?></strong>
                        <p><?= e($alert['message']) ?></p>
                        <small><?= e(format_datetime($alert['created_at'])) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>No alerts yet. Alerts will appear when the system sees elevated risk or Emergency Mode is activated.</p>
        <?php endif; ?>
    </article>

    <article class="panel">
        <div class="panel-head">
            <div>
                <span class="eyebrow">Latest Report</span>
                <h2><?= $latestReport ? e($latestReport['title']) : 'No report Created Yet' ?></h2>
            </div>
            <?php if ($latestReport): ?>
                <span class="<?= e(badge_class_for_score((int) $latestReport['danger_score'])) ?>">Score <?= e((string) $latestReport['danger_score']) ?></span>
            <?php endif; ?>
        </div>
        <?php if ($latestReport): ?>
            <p><?= e(truncate_text($latestReport['description'], 200)) ?></p>
            <div class="mini-stats">
                <span>Category: <?= e(ucfirst($latestReport['ai_category'])) ?></span>
                <span>Status: <?= e(ucwords(str_replace('_', ' ', $latestReport['status']))) ?></span>
                <span>Night risk: <?= e((string) $latestReport['night_risk']) ?></span>
            </div>
            <ul class="feature-list compact">
                <?php foreach (array_slice(parse_json_array($latestReport['safety_tips']), 0, 3) as $tip): ?>
                    <li><?= e($tip) ?></li>
                <?php endforeach; ?>
            </ul>
            <a class="button button-secondary button-small" href="<?= e(route_url('reports.php')) ?>">Manage Reports</a>
        <?php else: ?>
            <p>Start with a report to activate categorization, danger scoring, safety tips, and agent workflows.</p>
        <?php endif; ?>
    </article>
</section>


