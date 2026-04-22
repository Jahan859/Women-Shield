<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require_login();

$user = current_user();
$userId = (int) $user['id'];
$editingReport = null;
$errors = [];

if (is_post()) {
    verify_csrf_token();
    $action = (string) ($_POST['action'] ?? 'save');

    if ($action === 'delete') {
        delete_report($userId, (int) ($_POST['report_id'] ?? 0));
        flash_message('success', 'Report deleted.');
        redirect_to('reports.php');
    }

    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));

    if ($title === '' || $description === '') {
        $errors[] = 'Title and description are required.';
    }

    if ($errors === []) {
        $reportId = !empty($_POST['report_id']) ? (int) $_POST['report_id'] : null;
        upsert_report($userId, $_POST, $reportId);
        flash_message('success', $reportId ? 'Report updated successfully.' : 'Report created and analyzed.');
        redirect_to('reports.php');
    }
}

$reports = get_user_reports($userId);

if (!empty($_GET['edit'])) {
    $editingReport = get_report_for_user($userId, (int) $_GET['edit']);
}

render_header('Reports', [
    'description' => 'Create, update, and review incident reports with AI analysis.',
]);
?>

<section class="section-block">
    <div class="section-heading">
        <span class="eyebrow">Report CRUD</span>
        <h1>Capture Incidents With AI-assisted Risk Analysis</h1>
        <p>Each report is categorized, danger-scored, checked by the Fake Report Agent, and paired with safety tips.</p>
    </div>
</section>

<section class="card-grid two-up">
    <article class="panel">
        <div class="panel-head">
            <div>
                <span class="eyebrow"><?= $editingReport ? 'Update Incident' : 'Create Incident' ?></span>
                <h2><?= $editingReport ? 'Edit report details' : 'New Safety Report' ?></h2>
            </div>
        </div>

        <?php if ($errors): ?>
            <div class="inline-errors">
                <?php foreach ($errors as $error): ?>
                    <p><?= e($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="report_id" value="<?= e((string) ($editingReport['id'] ?? '')) ?>">
            <label>
                <span>Title</span>
                <input type="text" name="title" value="<?= e((string) ($editingReport['title'] ?? '')) ?>" placeholder="Suspicious taxi ride near campus" required>
            </label>
            <label>
                <span>Severity</span>
                <select name="severity">
                    <?php foreach (['low', 'medium', 'high', 'critical'] as $severity): ?>
                        <option value="<?= e($severity) ?>" <?= selected_option($severity, (string) ($editingReport['severity'] ?? 'medium')) ?>><?= e(ucfirst($severity)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="full-span">
                <span>Description</span>
                <textarea name="description" rows="6" placeholder="Describe what happened, the sequence of events, and any identifiers." required><?= e((string) ($editingReport['description'] ?? '')) ?></textarea>
            </label>
            <label>
                <span>Incident Time</span>
                <input type="datetime-local" name="incident_time" value="<?= e(datetime_local_value($editingReport['incident_time'] ?? null)) ?>">
            </label>
            <label>
                <span>Status</span>
                <select name="status">
                    <?php foreach (['new', 'in_review', 'resolved'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= selected_option($status, (string) ($editingReport['status'] ?? 'new')) ?>><?= e(ucwords(str_replace('_', ' ', $status))) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="full-span">
                <span>Location Summary</span>
                <input type="text" name="location_text" value="<?= e((string) ($editingReport['location_text'] ?? '')) ?>" placeholder="Dhanmondi 27 bus stop, Dhaka">
            </label>
            <label>
                <span>Latitude</span>
                <input type="number" step="0.000001" name="latitude" value="<?= e((string) ($editingReport['latitude'] ?? '')) ?>" placeholder="23.746466">
            </label>
            <label>
                <span>Longitude</span>
                <input type="number" step="0.000001" name="longitude" value="<?= e((string) ($editingReport['longitude'] ?? '')) ?>" placeholder="90.376015">
            </label>
            <button class="button button-primary" type="submit">Save Report</button>
        </form>
    </article>

    <article class="panel">
        <div class="panel-head">
            <div>
                <span class="eyebrow">AI Output</span>
                <h2>What happens after save</h2>
            </div>
        </div>

        <ul class="feature-list">
            <li>AI Categorization assigns the closest incident class from the text.</li>
            <li>Danger Score AI blends severity, context, keywords, and night risk.</li>
            <li>Safety Tips AI gives immediate guidance tailored to the category and score.</li>
            <li>Fake Report Agent raises a trust flag when details look inconsistent.</li>
            <li>Alert Agent creates dashboard alerts for elevated-risk incidents.</li>
        </ul>
        <a class="button button-secondary button-small" href="<?= e(route_url('assistant.php')) ?>">Ask AI Assistant</a>
    </article>
</section>

<section class="section-block">
    <div class="panel">
        <div class="panel-head">
            <div>
                <span class="eyebrow">Report History</span>
                <h2>Your saved incidents</h2>
            </div>
        </div>

        <?php if ($reports): ?>
            <div class="table-shell">
                <table>
                    <thead>
                    <tr>
                        <th>Incident</th>
                        <th>AI Category</th>
                        <th>Danger</th>
                        <th>Night Risk</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($reports as $report): ?>
                        <tr>
                            <td>
                                <strong><?= e($report['title']) ?></strong>
                                <p><?= e(truncate_text($report['location_text'] ?: $report['description'], 70)) ?></p>
                            </td>
                            <td>
                                <span class="badge badge-caution"><?= e(ucfirst($report['ai_category'])) ?></span>
                                <?php if (!empty($report['is_flagged_fake'])): ?>
                                    <span class="badge badge-muted">Review</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="<?= e(badge_class_for_score((int) $report['danger_score'])) ?>"><?= e((string) $report['danger_score']) ?></span></td>
                            <td><?= e((string) $report['night_risk']) ?></td>
                            <td><span class="<?= e(badge_class_for_status((string) $report['status'])) ?>"><?= e(ucwords(str_replace('_', ' ', $report['status']))) ?></span></td>
                            <td>
                                <div class="button-row compact">
                                    <a class="button button-secondary button-small" href="<?= e(route_url('reports.php?edit=' . $report['id'])) ?>">Edit</a>
                                    <form method="post" class="inline-form" data-confirm="Delete this report?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="report_id" value="<?= e((string) $report['id']) ?>">
                                        <button class="button button-ghost button-small" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="6" class="table-details">
                                <div class="inline-columns">
                                    <div>
                                        <strong>Safety Tips</strong>
                                        <ul class="feature-list compact">
                                            <?php foreach (array_slice(parse_json_array($report['safety_tips']), 0, 3) as $tip): ?>
                                                <li><?= e($tip) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    <div>
                                        <strong>Danger Reasons</strong>
                                        <ul class="feature-list compact">
                                            <?php foreach (array_slice(parse_json_array($report['danger_reasons']), 0, 3) as $reason): ?>
                                                <li><?= e($reason) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>No reports submitted yet. Create one to activate the AI pipeline and agent workflows.</p>
        <?php endif; ?>
    </div>
</section>

