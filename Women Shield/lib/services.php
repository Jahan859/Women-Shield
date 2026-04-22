<?php

declare(strict_types=1);

function get_user_contacts(int $userId): array
{
    return db_all(
        'SELECT * FROM emergency_contacts WHERE user_id = :user_id ORDER BY priority_level ASC, created_at ASC',
        ['user_id' => $userId]
    );
}

function save_contact(int $userId, array $payload, ?int $contactId = null): array
{
    $name = trim((string) ($payload['name'] ?? ''));
    $relation = trim((string) ($payload['relation'] ?? ''));
    $phone = trim((string) ($payload['phone'] ?? ''));
    $email = strtolower(trim((string) ($payload['email'] ?? '')));
    $priorityLevel = max(1, min(5, (int) ($payload['priority_level'] ?? 1)));
    $errors = [];

    if ($name === '') {
        $errors[] = 'Contact name is required.';
    }

    if ($phone === '') {
        $errors[] = 'Phone number is required.';
    } elseif (!is_valid_bangladesh_phone_local($phone)) {
        $errors[] = bangladesh_phone_validation_message();
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Contact email must be a valid email address.';
    }

    if ($errors !== []) {
        return $errors;
    }

    $params = [
        'user_id' => $userId,
        'name' => $name,
        'relation' => $relation,
        'phone' => normalize_bangladesh_phone_for_storage($phone),
        'email' => $email,
        'priority_level' => $priorityLevel,
    ];

    if ($contactId) {
        $params['id'] = $contactId;
        db_run(
            'UPDATE emergency_contacts SET name = :name, relation = :relation, phone = :phone, email = :email, priority_level = :priority_level WHERE id = :id AND user_id = :user_id',
            $params
        );
        return [];
    }

    db_run(
        'INSERT INTO emergency_contacts (user_id, name, relation, phone, email, priority_level, created_at) VALUES (:user_id, :name, :relation, :phone, :email, :priority_level, NOW())',
        $params
    );

    return [];
}

function delete_contact(int $userId, int $contactId): void
{
    db_run('DELETE FROM emergency_contacts WHERE id = :id AND user_id = :user_id', [
        'id' => $contactId,
        'user_id' => $userId,
    ]);
}

function get_user_reports(int $userId): array
{
    return db_all(
        'SELECT * FROM reports WHERE user_id = :user_id ORDER BY created_at DESC',
        ['user_id' => $userId]
    );
}

function get_report_for_user(int $userId, int $reportId): ?array
{
    return db_one(
        'SELECT * FROM reports WHERE id = :id AND user_id = :user_id LIMIT 1',
        ['id' => $reportId, 'user_id' => $userId]
    );
}

function get_all_reports(): array
{
    return db_all(
        'SELECT reports.*, users.name AS reporter_name, users.email AS reporter_email
         FROM reports
         INNER JOIN users ON users.id = reports.user_id
         ORDER BY reports.created_at DESC'
    );
}

function upsert_report(int $userId, array $payload, ?int $reportId = null): int
{
    $cleanPayload = [
        'title' => trim((string) ($payload['title'] ?? '')),
        'description' => trim((string) ($payload['description'] ?? '')),
        'severity' => (string) ($payload['severity'] ?? 'medium'),
        'incident_time' => str_replace('T', ' ', (string) ($payload['incident_time'] ?? date('Y-m-d H:i:s'))),
        'location_text' => trim((string) ($payload['location_text'] ?? '')),
        'latitude' => ($payload['latitude'] ?? '') !== '' ? (float) $payload['latitude'] : null,
        'longitude' => ($payload['longitude'] ?? '') !== '' ? (float) $payload['longitude'] : null,
        'status' => in_array((string) ($payload['status'] ?? 'new'), ['new', 'in_review', 'resolved', 'rejected'], true)
            ? (string) ($payload['status'] ?? 'new')
            : 'new',
    ];

    $ai = build_report_ai_bundle($cleanPayload);
    $params = [
        'user_id' => $userId,
        'title' => $cleanPayload['title'],
        'description' => $cleanPayload['description'],
        'severity' => $cleanPayload['severity'],
        'incident_time' => $cleanPayload['incident_time'],
        'location_text' => $cleanPayload['location_text'],
        'latitude' => $cleanPayload['latitude'],
        'longitude' => $cleanPayload['longitude'],
        'status' => $cleanPayload['status'],
        'ai_category' => $ai['ai_category'],
        'ai_confidence' => $ai['ai_confidence'],
        'danger_score' => $ai['danger_score'],
        'danger_reasons' => json_encode($ai['danger_reasons'], JSON_THROW_ON_ERROR),
        'fake_score' => $ai['fake_score'],
        'is_flagged_fake' => $ai['is_flagged_fake'],
        'fake_reasons' => json_encode($ai['fake_reasons'], JSON_THROW_ON_ERROR),
        'night_risk' => $ai['night_risk'],
        'night_reason' => $ai['night_reason'],
        'safety_tips' => json_encode($ai['safety_tips'], JSON_THROW_ON_ERROR),
    ];

    if ($reportId) {
        $params['id'] = $reportId;
        db_run(
            'UPDATE reports
             SET title = :title, description = :description, severity = :severity, incident_time = :incident_time,
                 location_text = :location_text, latitude = :latitude, longitude = :longitude, status = :status,
                 ai_category = :ai_category, ai_confidence = :ai_confidence, danger_score = :danger_score,
                 danger_reasons = :danger_reasons, fake_score = :fake_score, is_flagged_fake = :is_flagged_fake,
                 fake_reasons = :fake_reasons, night_risk = :night_risk, night_reason = :night_reason,
                 safety_tips = :safety_tips, updated_at = NOW()
             WHERE id = :id AND user_id = :user_id',
            $params
        );
        $finalId = $reportId;
    } else {
        db_run(
            'INSERT INTO reports
             (user_id, title, description, severity, incident_time, location_text, latitude, longitude, status,
              ai_category, ai_confidence, danger_score, danger_reasons, fake_score, is_flagged_fake, fake_reasons,
              night_risk, night_reason, safety_tips, created_at, updated_at)
             VALUES
             (:user_id, :title, :description, :severity, :incident_time, :location_text, :latitude, :longitude, :status,
              :ai_category, :ai_confidence, :danger_score, :danger_reasons, :fake_score, :is_flagged_fake, :fake_reasons,
              :night_risk, :night_reason, :safety_tips, NOW(), NOW())',
            $params
        );
        $finalId = (int) db()->lastInsertId();
    }

    $report = get_report_for_user($userId, $finalId);

    if ($report) {
        sync_alert_agent($report);
    }

    return $finalId;
}

function delete_report(int $userId, int $reportId): void
{
    db_run('DELETE FROM reports WHERE id = :id AND user_id = :user_id', [
        'id' => $reportId,
        'user_id' => $userId,
    ]);
}

function sync_alert_agent(array $report): void
{
    $dangerScore = (int) ($report['danger_score'] ?? 0);
    $reportId = (int) ($report['id'] ?? 0);
    $userId = (int) ($report['user_id'] ?? 0);

    if ($dangerScore >= 70) {
        $existing = db_one(
            'SELECT id FROM alerts WHERE user_id = :user_id AND report_id = :report_id AND alert_type = :alert_type AND status = :status',
            [
                'user_id' => $userId,
                'report_id' => $reportId,
                'alert_type' => 'critical-report',
                'status' => 'open',
            ]
        );

        if (!$existing) {
            db_run(
                'INSERT INTO alerts (user_id, report_id, alert_type, message, status, created_at)
                 VALUES (:user_id, :report_id, :alert_type, :message, :status, NOW())',
                [
                    'user_id' => $userId,
                    'report_id' => $reportId,
                    'alert_type' => 'critical-report',
                    'message' => 'High-risk report detected by the alert agent. Review emergency contacts and Emergency Mode.',
                    'status' => 'open',
                ]
            );
        }
    }

    if (!empty($report['is_flagged_fake'])) {
        $existing = db_one(
            'SELECT id FROM alerts WHERE user_id = :user_id AND report_id = :report_id AND alert_type = :alert_type',
            [
                'user_id' => $userId,
                'report_id' => $reportId,
                'alert_type' => 'fake-review',
            ]
        );

        if (!$existing) {
            db_run(
                'INSERT INTO alerts (user_id, report_id, alert_type, message, status, created_at)
                 VALUES (:user_id, :report_id, :alert_type, :message, :status, NOW())',
                [
                    'user_id' => $userId,
                    'report_id' => $reportId,
                    'alert_type' => 'fake-review',
                    'message' => 'Fake Report Agent marked this submission for review. Confirm details before escalation.',
                    'status' => 'open',
                ]
            );
        }
    }
}

function get_user_alerts(int $userId, int $limit = 5): array
{
    $limit = max(1, min(20, $limit));

    return db_all(
        "SELECT alerts.*, reports.title AS report_title
         FROM alerts
         LEFT JOIN reports ON reports.id = alerts.report_id
         WHERE alerts.user_id = :user_id
         ORDER BY alerts.created_at DESC
         LIMIT {$limit}",
        ['user_id' => $userId]
    );
}

function get_map_reports(): array
{
    return db_all(
        "SELECT id, title, location_text, latitude, longitude, ai_category, danger_score, incident_time, status
         FROM reports
         WHERE status IN ('new', 'in_review', 'resolved')
         ORDER BY incident_time DESC
         LIMIT 100"
    );
}

function get_chat_history(int $userId, int $limit = 12): array
{
    $limit = max(1, min(30, $limit));

    return db_all(
        "SELECT * FROM chat_logs WHERE user_id = :user_id ORDER BY created_at DESC LIMIT {$limit}",
        ['user_id' => $userId]
    );
}

function save_chat_log(int $userId, string $message, string $response): void
{
    db_run(
        'INSERT INTO chat_logs (user_id, message, response, created_at) VALUES (:user_id, :message, :response, NOW())',
        ['user_id' => $userId, 'message' => $message, 'response' => $response]
    );
}

function get_active_emergency_session(int $userId): ?array
{
    return db_one(
        "SELECT * FROM emergency_sessions WHERE user_id = :user_id AND status = 'active' ORDER BY started_at DESC LIMIT 1",
        ['user_id' => $userId]
    );
}

function extract_location_coordinates(string $locationSummary): ?array
{
    $locationSummary = trim($locationSummary);

    if ($locationSummary === '') {
        return null;
    }

    if (!preg_match('/(-?\d{1,3}(?:\.\d+)?)\s*,\s*(-?\d{1,3}(?:\.\d+)?)/', $locationSummary, $matches)) {
        return null;
    }

    $latitude = (float) $matches[1];
    $longitude = (float) $matches[2];

    if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        return null;
    }

    return [
        'latitude' => $latitude,
        'longitude' => $longitude,
    ];
}

function build_emergency_location_mail_lines(string $locationSummary): array
{
    $locationSummary = trim($locationSummary);

    if ($locationSummary === '') {
        return ['Reported location: Location not provided'];
    }

    $lines = ['Reported location: ' . $locationSummary];
    $coordinates = extract_location_coordinates($locationSummary);

    if ($coordinates !== null) {
        $mapQuery = $coordinates['latitude'] . ',' . $coordinates['longitude'];
        $lines[] = 'Live map link: https://www.google.com/maps?q=' . rawurlencode($mapQuery);

        return $lines;
    }

    $lines[] = 'Map search link: https://www.google.com/maps/search/?api=1&query=' . rawurlencode($locationSummary);

    return $lines;
}

function send_emergency_mode_emails(int $userId, string $locationSummary): array
{
    $user = db_one('SELECT id, name, email, phone FROM users WHERE id = :id LIMIT 1', [
        'id' => $userId,
    ]);

    if (!$user) {
        return ['sent' => 0, 'failed' => 0, 'missing_email' => 0];
    }

    $contacts = get_user_contacts($userId);
    $sent = 0;
    $failed = 0;
    $missingEmail = 0;
    $fromName = (string) app_config('mail.from_name', 'Women Shield');
    $publicSiteUrl = trim((string) app_config('mail.public_site_url', ''));
    $driver = configured_mail_driver();
    $failureReason = '';

    foreach ($contacts as $contact) {
        $contactEmail = trim((string) ($contact['email'] ?? ''));

        if ($contactEmail === '') {
            $missingEmail++;
            continue;
        }

        $subject = $fromName . ' Emergency Alert';
        $messageLines = [
            'Emergency Mode has been activated for ' . $user['name'] . '.',
            '',
            'Priority contact: ' . $contact['name'],
            'Reported time: ' . date('M d, Y h:i A'),
            ...build_emergency_location_mail_lines($locationSummary),
            '',
            'Please contact them immediately and check their safety status.',
            $publicSiteUrl !== '' ? 'Safety portal: ' . $publicSiteUrl : null,
            '',
            'This alert was sent from the Women Shield emergency system.',
        ];

        $mailBody = implode("\n", array_values(array_filter($messageLines, static fn (?string $line): bool => $line !== null)));
        $mailResult = send_app_mail($contactEmail, (string) $contact['name'], $subject, $mailBody);

        if ($mailResult['success']) {
            $sent++;
            continue;
        }

        $failed++;
        $failureReason = $mailResult['details'];
        error_log('Women Shield emergency email failed for contact: ' . $contactEmail . '. ' . $mailResult['details']);
    }

    return [
        'sent' => $sent,
        'failed' => $failed,
        'missing_email' => $missingEmail,
        'driver' => $driver,
        'details' => $failureReason,
    ];
}

function start_emergency_mode(int $userId, string $locationSummary): array
{
    $active = get_active_emergency_session($userId);

    if (!$active) {
        db_run(
            'INSERT INTO emergency_sessions (user_id, status, location_summary, started_at) VALUES (:user_id, :status, :location_summary, NOW())',
            ['user_id' => $userId, 'status' => 'active', 'location_summary' => $locationSummary]
        );
    }

    db_run(
        'INSERT INTO alerts (user_id, report_id, alert_type, message, status, created_at)
         VALUES (:user_id, NULL, :alert_type, :message, :status, NOW())',
        [
            'user_id' => $userId,
            'alert_type' => 'emergency-mode',
            'message' => 'Emergency Mode activated. Reach out to your top contacts and call local emergency services if needed.',
            'status' => 'open',
        ]
    );

    return send_emergency_mode_emails($userId, $locationSummary);
}

function stop_emergency_mode(int $userId): void
{
    db_run(
        "UPDATE emergency_sessions SET status = 'closed', ended_at = NOW() WHERE user_id = :user_id AND status = 'active'",
        ['user_id' => $userId]
    );
}

function get_dashboard_metrics(int $userId): array
{
    $reports = get_user_reports($userId);
    $contacts = get_user_contacts($userId);
    $alerts = get_user_alerts($userId, 6);
    $activeEmergency = get_active_emergency_session($userId);

    $highRiskReports = array_values(array_filter($reports, fn ($report) => (int) $report['danger_score'] >= 70));
    $latestReport = $reports[0] ?? null;

    return [
        'reports' => $reports,
        'contacts' => $contacts,
        'alerts' => $alerts,
        'active_emergency' => $activeEmergency,
        'total_reports' => count($reports),
        'total_contacts' => count($contacts),
        'high_risk_count' => count($highRiskReports),
        'average_danger' => $reports === [] ? 0 : (int) round(array_sum(array_map(fn ($report) => (int) $report['danger_score'], $reports)) / count($reports)),
        'latest_report' => $latestReport,
        'monitoring' => build_monitoring_agent_summary($reports),
        'insights' => build_admin_insights($reports),
    ];
}

function update_report_as_admin(int $reportId, array $payload): void
{
    db_run(
        'UPDATE reports SET status = :status, admin_notes = :admin_notes, updated_at = NOW() WHERE id = :id',
        [
            'id' => $reportId,
            'status' => in_array((string) ($payload['status'] ?? 'new'), ['new', 'in_review', 'resolved', 'rejected'], true)
                ? (string) ($payload['status'] ?? 'new')
                : 'new',
            'admin_notes' => trim((string) ($payload['admin_notes'] ?? '')),
        ]
    );
}
