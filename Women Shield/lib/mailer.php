<?php

declare(strict_types=1);

function configured_mail_driver(): string
{
    $driver = strtolower(trim((string) app_config('mail.driver', 'mail')));

    return in_array($driver, ['mail', 'phpmailer'], true) ? $driver : 'mail';
}

function configured_mail_driver_label(): string
{
    if (configured_mail_driver() === 'mail') {
        return 'PHP mail()';
    }

    return is_phpmailer_available() ? 'PHPMailer SMTP' : 'Built-in SMTP';
}

function mail_local_config_path(): string
{
    return __DIR__ . '/../config/local.php';
}

function is_local_mail_config_present(): bool
{
    return is_file(mail_local_config_path());
}

function load_local_config_overrides(): array
{
    $path = mail_local_config_path();

    if (!is_file($path)) {
        return [];
    }

    $overrides = require $path;

    return is_array($overrides) ? $overrides : [];
}

function write_local_config_overrides(array $overrides): bool
{
    $path = mail_local_config_path();
    $contents = "<?php\n\nreturn " . var_export($overrides, true) . ";\n";

    return file_put_contents($path, $contents, LOCK_EX) !== false;
}

function reload_app_config(): void
{
    set_app_config(require __DIR__ . '/../config/app.php');
}

function is_phpmailer_available(): bool
{
    return class_exists(\PHPMailer\PHPMailer\PHPMailer::class);
}

function smtp_config_errors(): array
{
    $errors = [];

    if (!filter_var((string) app_config('mail.from_email', ''), FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'mail.from_email is not a valid email address.';
    }

    if (trim((string) app_config('mail.smtp.host', '')) === '') {
        $errors[] = 'mail.smtp.host is required for SMTP delivery.';
    }

    if ((int) app_config('mail.smtp.port', 0) <= 0) {
        $errors[] = 'mail.smtp.port must be a valid SMTP port.';
    }

    $encryption = strtolower(trim((string) app_config('mail.smtp.encryption', 'tls')));

    if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
        $errors[] = 'mail.smtp.encryption must be tls, ssl, or none.';
    }

    if (($encryption === 'tls' || $encryption === 'ssl') && !extension_loaded('openssl')) {
        $errors[] = 'The PHP OpenSSL extension is required for encrypted SMTP connections.';
    }

    if ((bool) app_config('mail.smtp.auth', true)) {
        if (trim((string) app_config('mail.smtp.username', '')) === '') {
            $errors[] = 'mail.smtp.username is required when SMTP auth is enabled.';
        }

        if (trim((string) app_config('mail.smtp.password', '')) === '') {
            $errors[] = 'mail.smtp.password is required when SMTP auth is enabled.';
        }
    }

    $publicSiteUrl = trim((string) app_config('mail.public_site_url', ''));

    if ($publicSiteUrl !== '' && !filter_var($publicSiteUrl, FILTER_VALIDATE_URL)) {
        $errors[] = 'mail.public_site_url must be a valid URL when provided.';
    }

    return $errors;
}

function phpmailer_config_errors(): array
{
    return smtp_config_errors();
}

function mask_mail_secret(string $value): string
{
    $length = strlen($value);

    if ($length <= 4) {
        return str_repeat('*', max(8, $length));
    }

    return str_repeat('*', max(8, $length - 4)) . substr($value, -4);
}

function normalized_mail_settings(array $input, bool $hasSavedPassword = false): array
{
    $encryption = strtolower(trim((string) ($input['encryption'] ?? 'tls')));

    if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
        $encryption = 'tls';
    }

    $smtpPort = trim((string) ($input['smtp_port'] ?? '587'));
    $smtpPort = $smtpPort === '' ? '587' : $smtpPort;

    return [
        'smtp_host' => trim((string) ($input['smtp_host'] ?? '')),
        'public_site_url' => rtrim(trim((string) ($input['public_site_url'] ?? '')), '/'),
        'smtp_port' => $smtpPort,
        'encryption' => $encryption,
        'smtp_username' => trim((string) ($input['smtp_username'] ?? '')),
        'smtp_password' => (string) ($input['smtp_password'] ?? ''),
        'smtp_password_saved' => $hasSavedPassword,
        'clear_saved_password' => !empty($input['clear_saved_password']),
        'from_email' => strtolower(trim((string) ($input['from_email'] ?? ''))),
        'from_name' => trim((string) ($input['from_name'] ?? '')),
    ];
}

function current_mail_settings(): array
{
    $storedPassword = trim((string) app_config('mail.smtp.password', ''));

    return normalized_mail_settings([
        'smtp_host' => app_config('mail.smtp.host', ''),
        'public_site_url' => app_config('mail.public_site_url', ''),
        'smtp_port' => (string) app_config('mail.smtp.port', 587),
        'encryption' => app_config('mail.smtp.encryption', 'tls'),
        'smtp_username' => app_config('mail.smtp.username', ''),
        'smtp_password' => '',
        'from_email' => app_config('mail.from_email', ''),
        'from_name' => app_config('mail.from_name', 'Women Shield'),
    ], $storedPassword !== '') + [
        'smtp_password_mask' => $storedPassword !== '' ? mask_mail_secret($storedPassword) : 'Not saved yet',
    ];
}

function validate_mail_settings(array $settings, string $resolvedPassword): array
{
    $errors = [];

    if ($settings['smtp_host'] === '') {
        $errors[] = 'SMTP host is required.';
    }

    if (!ctype_digit($settings['smtp_port']) || (int) $settings['smtp_port'] <= 0) {
        $errors[] = 'SMTP port must be a valid number.';
    }

    if ($settings['smtp_username'] === '') {
        $errors[] = 'SMTP username is required.';
    }

    if ($resolvedPassword === '') {
        $errors[] = 'SMTP password or app password is required.';
    }

    if (!filter_var($settings['from_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'From email must be a valid email address.';
    }

    if ($settings['from_name'] === '') {
        $errors[] = 'From name is required.';
    }

    if ($settings['public_site_url'] !== '' && !filter_var($settings['public_site_url'], FILTER_VALIDATE_URL)) {
        $errors[] = 'Public site URL must be a valid full URL.';
    }

    return $errors;
}

function save_mail_settings(array $input): array
{
    $existingPassword = trim((string) app_config('mail.smtp.password', ''));
    $settings = normalized_mail_settings($input, $existingPassword !== '');
    $resolvedPassword = trim($settings['smtp_password']);

    if ($resolvedPassword === '') {
        if ($settings['clear_saved_password']) {
            $resolvedPassword = '';
        } else {
            $resolvedPassword = $existingPassword;
        }
    }

    if ($resolvedPassword !== '') {
        $settings['clear_saved_password'] = false;
    }

    $errors = validate_mail_settings($settings, $resolvedPassword);

    if ($errors !== []) {
        $settings['smtp_password'] = '';
        $settings['smtp_password_mask'] = $existingPassword !== '' ? mask_mail_secret($existingPassword) : 'Not saved yet';
        $settings['smtp_password_saved'] = $existingPassword !== '';

        return [
            'success' => false,
            'errors' => $errors,
            'settings' => $settings,
        ];
    }

    $overrides = load_local_config_overrides();
    $overrides['mail'] = [
        'driver' => 'phpmailer',
        'public_site_url' => $settings['public_site_url'],
        'from_email' => $settings['from_email'],
        'from_name' => $settings['from_name'],
        'smtp' => [
            'host' => $settings['smtp_host'],
            'port' => (int) $settings['smtp_port'],
            'username' => $settings['smtp_username'],
            'password' => $resolvedPassword,
            'encryption' => $settings['encryption'],
            'auth' => true,
            'timeout' => 15,
        ],
    ];

    if (!write_local_config_overrides($overrides)) {
        $settings['smtp_password'] = '';
        $settings['smtp_password_mask'] = $existingPassword !== '' ? mask_mail_secret($existingPassword) : 'Not saved yet';
        $settings['smtp_password_saved'] = $existingPassword !== '';

        return [
            'success' => false,
            'errors' => ['Could not save config/local.php. Check file permissions and try again.'],
            'settings' => $settings,
        ];
    }

    reload_app_config();

    return [
        'success' => true,
        'errors' => [],
        'settings' => current_mail_settings(),
    ];
}

function send_mail_setup_test_email(array $user): array
{
    $toEmail = strtolower(trim((string) ($user['email'] ?? '')));
    $toName = trim((string) ($user['name'] ?? 'User'));

    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'message' => 'Your account does not have a valid email address for the test message.',
        ];
    }

    $publicSiteUrl = trim((string) app_config('mail.public_site_url', ''));
    $bodyLines = [
        'This is a Women Shield SMTP test email.',
        '',
        'If you received this message, your email setup page is working.',
        'Mail driver: ' . configured_mail_driver_label(),
        'Sent at: ' . date('M d, Y h:i A'),
        $publicSiteUrl !== '' ? 'Public site URL: ' . $publicSiteUrl : null,
        '',
        'Emergency Mode will use this same mail setup for emergency contact alerts.',
    ];

    $body = implode("\n", array_values(array_filter($bodyLines, static fn (?string $line): bool => $line !== null)));
    $result = send_app_mail($toEmail, $toName, 'Women Shield test email', $body);

    if ($result['success']) {
        return [
            'success' => true,
            'message' => 'A test email was sent to ' . $toEmail . '.',
        ];
    }

    return [
        'success' => false,
        'message' => $result['details'] !== '' ? $result['details'] : 'The test email could not be sent.',
    ];
}

function mail_header_encode(string $value): string
{
    $value = trim(str_replace(["\r", "\n"], '', $value));

    if ($value === '') {
        return '';
    }

    if (!preg_match('/[^\x20-\x7E]/', $value)) {
        return $value;
    }

    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function format_mailbox(string $email, string $name = ''): string
{
    $email = trim(str_replace(["\r", "\n"], '', $email));
    $name = trim(str_replace(["\r", "\n"], '', $name));

    if ($name === '') {
        return '<' . $email . '>';
    }

    return '"' . addcslashes($name, "\\\"") . '" <' . $email . '>';
}

function smtp_crypto_method(): ?int
{
    if (defined('STREAM_CRYPTO_METHOD_TLS_CLIENT')) {
        return STREAM_CRYPTO_METHOD_TLS_CLIENT;
    }

    $methods = [];

    foreach ([
        'STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT',
        'STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT',
        'STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT',
        'STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT',
    ] as $constant) {
        if (defined($constant)) {
            $methods[] = constant($constant);
        }
    }

    if ($methods === []) {
        return null;
    }

    return array_reduce($methods, static fn (int $carry, int $method): int => $carry | $method, 0);
}

function smtp_read_response($socket): string
{
    $response = '';

    while (!feof($socket)) {
        $line = fgets($socket, 515);

        if ($line === false) {
            break;
        }

        $response .= $line;

        if (strlen($line) < 4 || $line[3] === ' ') {
            break;
        }
    }

    if ($response === '') {
        throw new RuntimeException('SMTP server did not return a response.');
    }

    return $response;
}

function smtp_expect($socket, array $expectedCodes, string $step): string
{
    $response = smtp_read_response($socket);
    $code = (int) substr($response, 0, 3);

    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException($step . ' failed: ' . trim($response));
    }

    return $response;
}

function smtp_command($socket, string $command, array $expectedCodes, string $step): string
{
    $written = fwrite($socket, $command . "\r\n");

    if ($written === false) {
        throw new RuntimeException($step . ' failed: could not write to the SMTP socket.');
    }

    return smtp_expect($socket, $expectedCodes, $step);
}

function smtp_normalize_body(string $body): string
{
    $normalized = str_replace(["\r\n", "\r"], "\n", $body);
    $normalized = preg_replace('/^\./m', '..', $normalized) ?? $normalized;

    return str_replace("\n", "\r\n", $normalized);
}

function smtp_message_headers(string $toEmail, string $toName, string $subject): string
{
    $fromEmail = (string) app_config('mail.from_email', 'noreply@womenshield.local');
    $fromName = (string) app_config('mail.from_name', 'Women Shield');
    $host = gethostname() ?: 'localhost';

    $headers = [
        'Date: ' . date(DATE_RFC2822),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . preg_replace('/[^a-z0-9.\-]/i', '', $host) . '>',
        'From: ' . format_mailbox($fromEmail, $fromName),
        'To: ' . format_mailbox($toEmail, $toName),
        'Subject: ' . mail_header_encode($subject),
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];

    return implode("\r\n", $headers);
}

function send_via_native_smtp(string $toEmail, string $toName, string $subject, string $body): array
{
    $errors = smtp_config_errors();

    if ($errors !== []) {
        return [
            'success' => false,
            'driver' => 'smtp',
            'details' => implode(' ', $errors),
        ];
    }

    $host = trim((string) app_config('mail.smtp.host', ''));
    $port = (int) app_config('mail.smtp.port', 587);
    $timeout = max(5, (int) app_config('mail.smtp.timeout', 15));
    $encryption = strtolower(trim((string) app_config('mail.smtp.encryption', 'tls')));
    $username = (string) app_config('mail.smtp.username', '');
    $password = (string) app_config('mail.smtp.password', '');
    $socket = null;

    try {
        $transport = $encryption === 'ssl' ? 'ssl://' . $host : 'tcp://' . $host;
        $socket = @stream_socket_client($transport . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);

        if (!is_resource($socket)) {
            throw new RuntimeException('SMTP connection failed: ' . trim($errstr) . ' (' . $errno . ').');
        }

        stream_set_timeout($socket, $timeout);
        smtp_expect($socket, [220], 'SMTP greeting');

        $clientName = gethostname() ?: 'localhost';
        smtp_command($socket, 'EHLO ' . $clientName, [250], 'EHLO');

        if ($encryption === 'tls') {
            smtp_command($socket, 'STARTTLS', [220], 'STARTTLS');
            $cryptoMethod = smtp_crypto_method();

            if ($cryptoMethod === null || @stream_socket_enable_crypto($socket, true, $cryptoMethod) !== true) {
                throw new RuntimeException('STARTTLS negotiation failed.');
            }

            smtp_command($socket, 'EHLO ' . $clientName, [250], 'EHLO after STARTTLS');
        }

        if ((bool) app_config('mail.smtp.auth', true)) {
            smtp_command($socket, 'AUTH LOGIN', [334], 'AUTH LOGIN');
            smtp_command($socket, base64_encode($username), [334], 'SMTP username');
            smtp_command($socket, base64_encode($password), [235], 'SMTP password');
        }

        $fromEmail = (string) app_config('mail.from_email', 'noreply@womenshield.local');
        smtp_command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250], 'MAIL FROM');
        smtp_command($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251], 'RCPT TO');
        smtp_command($socket, 'DATA', [354], 'DATA');

        $payload = smtp_message_headers($toEmail, $toName, $subject)
            . "\r\n\r\n"
            . smtp_normalize_body($body)
            . "\r\n.\r\n";

        if (fwrite($socket, $payload) === false) {
            throw new RuntimeException('SMTP message body could not be written.');
        }

        smtp_expect($socket, [250], 'SMTP message delivery');
        @fwrite($socket, "QUIT\r\n");
        @fclose($socket);

        return [
            'success' => true,
            'driver' => 'smtp',
            'details' => '',
        ];
    } catch (Throwable $exception) {
        if (is_resource($socket)) {
            @fwrite($socket, "QUIT\r\n");
            @fclose($socket);
        }

        error_log('Women Shield SMTP error: ' . $exception->getMessage());

        return [
            'success' => false,
            'driver' => 'smtp',
            'details' => 'SMTP send failed: ' . $exception->getMessage(),
        ];
    }
}

function send_via_phpmailer(string $toEmail, string $toName, string $subject, string $body): array
{
    $errors = smtp_config_errors();

    if ($errors !== []) {
        return [
            'success' => false,
            'driver' => 'phpmailer',
            'details' => implode(' ', $errors),
        ];
    }

    if (!is_phpmailer_available()) {
        return send_via_native_smtp($toEmail, $toName, $subject, $body);
    }

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = (string) app_config('mail.smtp.host');
        $mail->Port = (int) app_config('mail.smtp.port', 587);
        $mail->SMTPAuth = (bool) app_config('mail.smtp.auth', true);

        $encryption = strtolower(trim((string) app_config('mail.smtp.encryption', 'tls')));

        if ($encryption === 'ssl') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'tls') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->Username = (string) app_config('mail.smtp.username', '');
        $mail->Password = (string) app_config('mail.smtp.password', '');
        $mail->Timeout = (int) app_config('mail.smtp.timeout', 15);
        $mail->CharSet = 'UTF-8';
        $mail->setFrom((string) app_config('mail.from_email'), (string) app_config('mail.from_name', 'Women Shield'));
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = $body;
        $mail->send();

        return [
            'success' => true,
            'driver' => 'phpmailer',
            'details' => '',
        ];
    } catch (Throwable $exception) {
        error_log('Women Shield PHPMailer error: ' . $exception->getMessage());

        return [
            'success' => false,
            'driver' => 'phpmailer',
            'details' => 'PHPMailer send failed: ' . $exception->getMessage(),
        ];
    }
}

function send_via_php_mail(string $toEmail, string $toName, string $subject, string $body): array
{
    $fromName = (string) app_config('mail.from_name', 'Women Shield');
    $fromEmail = (string) app_config('mail.from_email', 'noreply@womenshield.local');
    $headers = 'From: ' . $fromName . ' <' . $fromEmail . '>';
    $mailSent = @mail($toEmail, $subject, $body, $headers);

    if ($mailSent) {
        return [
            'success' => true,
            'driver' => 'mail',
            'details' => '',
        ];
    }

    return [
        'success' => false,
        'driver' => 'mail',
        'details' => 'PHP mail() send failed. Configure XAMPP sendmail or switch to SMTP.',
    ];
}

function send_app_mail(string $toEmail, string $toName, string $subject, string $body): array
{
    return configured_mail_driver() === 'phpmailer'
        ? send_via_phpmailer($toEmail, $toName, $subject, $body)
        : send_via_php_mail($toEmail, $toName, $subject, $body);
}

function mail_setup_status(?int $userId = null): array
{
    $contacts = $userId !== null && $userId > 0
        ? get_user_contacts($userId)
        : db_all('SELECT email FROM emergency_contacts');
    $contactsWithEmail = 0;

    foreach ($contacts as $contact) {
        if (trim((string) ($contact['email'] ?? '')) !== '') {
            $contactsWithEmail++;
        }
    }

    $driver = configured_mail_driver();
    $transportIssues = [];
    $contactIssues = [];

    if (!is_local_mail_config_present()) {
        $transportIssues[] = 'config/local.php is missing.';
    }

    if ($driver === 'mail') {
        $transportIssues[] = 'Current driver is PHP mail(). On local XAMPP this usually needs extra server mail setup.';
    }

    if ($driver === 'phpmailer') {
        foreach (smtp_config_errors() as $error) {
            $transportIssues[] = $error;
        }
    }

    if ($contactsWithEmail === 0) {
        $contactIssues[] = 'No emergency contact has an email address saved.';
    }

    return [
        'driver' => $driver,
        'driver_label' => configured_mail_driver_label(),
        'local_config' => is_local_mail_config_present(),
        'phpmailer_available' => is_phpmailer_available(),
        'contacts_total' => count($contacts),
        'contacts_with_email' => $contactsWithEmail,
        'transport_ready' => $transportIssues === [],
        'contact_ready' => $contactIssues === [],
        'ready' => $transportIssues === [] && $contactIssues === [],
        'transport_issues' => $transportIssues,
        'contact_issues' => $contactIssues,
        'issues' => array_merge($transportIssues, $contactIssues),
    ];
}
