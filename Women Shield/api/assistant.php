<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_authenticated()) {
    http_response_code(401);
    echo json_encode(['reply' => 'Please log in first.']);
    exit;
}

if (!is_post()) {
    http_response_code(405);
    echo json_encode(['reply' => 'Method not allowed.']);
    exit;
}

verify_csrf_token();

$message = trim((string) ($_POST['message'] ?? ''));

if ($message === '') {
    http_response_code(422);
    echo json_encode(['reply' => 'Please enter a message.']);
    exit;
}

$user = current_user();
$metrics = get_dashboard_metrics((int) $user['id']);
$reply = generate_assistant_reply($message, [
    'contact_count' => $metrics['total_contacts'],
    'high_risk_reports' => $metrics['high_risk_count'],
    'active_emergency' => (bool) $metrics['active_emergency'],
    'reports' => $metrics['reports'],
]);

save_chat_log((int) $user['id'], $message, $reply);

echo json_encode(['reply' => $reply]);