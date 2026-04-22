<?php

declare(strict_types=1);

function current_user(): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    static $cachedUserId = null;
    static $cachedUser = null;
    $sessionUserId = (int) $_SESSION['user_id'];

    if ($cachedUserId === $sessionUserId) {
        return $cachedUser;
    }

    $cachedUser = db_one('SELECT id, name, email, phone, role, created_at FROM users WHERE id = :id', [
        'id' => $sessionUserId,
    ]);

    if (!$cachedUser) {
        unset($_SESSION['user_id']);
        $cachedUserId = null;
        $cachedUser = null;
        return null;
    }

    $cachedUserId = $sessionUserId;

    return $cachedUser;
}

function current_admin(): ?array
{
    if (!isset($_SESSION['admin_id'])) {
        return null;
    }

    static $cachedAdminId = null;
    static $cachedAdmin = null;
    $sessionAdminId = (int) $_SESSION['admin_id'];

    if ($cachedAdminId === $sessionAdminId) {
        return $cachedAdmin;
    }

    $cachedAdmin = db_one('SELECT id, username FROM admin WHERE id = :id', [
        'id' => $sessionAdminId,
    ]);

    if (!$cachedAdmin) {
        unset($_SESSION['admin_id']);
        $cachedAdminId = null;
        $cachedAdmin = null;
        return null;
    }

    $cachedAdminId = $sessionAdminId;

    return $cachedAdmin;
}

function current_account(): ?array
{
    $admin = current_admin();

    if ($admin !== null) {
        return [
            'id' => (int) $admin['id'],
            'name' => (string) $admin['username'],
            'username' => (string) $admin['username'],
            'type' => 'admin',
        ];
    }

    $user = current_user();

    if ($user === null) {
        return null;
    }

    return array_merge($user, [
        'type' => (($user['role'] ?? 'user') === 'admin') ? 'admin' : 'user',
    ]);
}

function is_authenticated(): bool
{
    return current_account() !== null;
}

function is_admin(): bool
{
    if (current_admin() !== null) {
        return true;
    }

    $user = current_user();

    return $user !== null && (($user['role'] ?? 'user') === 'admin');
}

function authenticated_home_path(): string
{
    return is_admin() ? 'admin/index.php' : 'dashboard.php';
}

function require_login(): void
{
    if (is_admin()) {
        flash_message('error', 'This page is only available for user accounts.');
        redirect_to('admin/index.php');
    }

    if (current_user() !== null) {
        return;
    }

    flash_message('error', 'Please log in to continue.');
    redirect_to('login.php');
}

function require_admin(): void
{
    if (is_admin()) {
        return;
    }

    flash_message('error', 'Admin access is required for that page.');

    if (current_user() !== null) {
        redirect_to('dashboard.php');
    }

    redirect_to('login.php?mode=admin');
}

function attempt_login(string $email, string $password): bool
{
    $user = db_one('SELECT * FROM users WHERE email = :email LIMIT 1', [
        'email' => strtolower(trim($email)),
    ]);

    if (
        !$user
        || (($user['role'] ?? 'user') === 'admin')
        || !password_verify($password, $user['password_hash'])
    ) {
        return false;
    }

    unset($_SESSION['admin_id']);
    $_SESSION['user_id'] = (int) $user['id'];
    session_regenerate_id(true);

    return true;
}

function attempt_admin_login(string $username, string $password): bool
{
    $username = trim($username);
    $admin = db_one('SELECT id, username, password_hash FROM admin WHERE username = :username LIMIT 1', [
        'username' => $username,
    ]);

    if ($admin && password_verify($password, (string) $admin['password_hash'])) {
        unset($_SESSION['user_id']);
        $_SESSION['admin_id'] = (int) $admin['id'];
        session_regenerate_id(true);

        return true;
    }

    $legacyAdmin = db_one('SELECT * FROM users WHERE email = :email AND role = :role LIMIT 1', [
        'email' => strtolower($username),
        'role' => 'admin',
    ]);

    if (!$legacyAdmin || !password_verify($password, (string) $legacyAdmin['password_hash'])) {
        return false;
    }

    unset($_SESSION['admin_id']);
    $_SESSION['user_id'] = (int) $legacyAdmin['id'];
    session_regenerate_id(true);

    return true;
}

function phone_digits_only(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

function is_valid_bangladesh_phone_local(string $value): bool
{
    return preg_match('/^01\d{9}$/', phone_digits_only($value)) === 1;
}

function normalize_bangladesh_phone_for_storage(string $value): string
{
    return phone_digits_only($value);
}

function bangladesh_phone_validation_message(): string
{
    return 'Use a valid Bangladesh mobile number with exactly 11 digits, like 01712345678.';
}

function register_account(array $payload): array
{
    $name = trim((string) ($payload['name'] ?? ''));
    $email = strtolower(trim((string) ($payload['email'] ?? '')));
    $phone = trim((string) ($payload['phone'] ?? ''));
    $password = (string) ($payload['password'] ?? '');

    $errors = [];

    if ($name === '') {
        $errors[] = 'Full name is required.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }

    if ($phone !== '' && !is_valid_bangladesh_phone_local($phone)) {
        $errors[] = bangladesh_phone_validation_message();
    }

    if (mb_strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    if (db_one('SELECT id FROM users WHERE email = :email', ['email' => $email])) {
        $errors[] = 'That email address is already registered.';
    }

    if ($errors !== []) {
        return $errors;
    }

    $phone = $phone === '' ? '' : normalize_bangladesh_phone_for_storage($phone);

    db_run(
        'INSERT INTO users (name, email, phone, password_hash, role, created_at) VALUES (:name, :email, :phone, :password_hash, :role, NOW())',
        [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'user',
        ]
    );

    $_SESSION['user_id'] = (int) db()->lastInsertId();

    return [];
}

function normalize_phone_for_match(string $value): string
{
    $digits = phone_digits_only($value);

    if (preg_match('/^8801\d{9}$/', $digits) === 1) {
        return '0' . substr($digits, 3);
    }

    return $digits;
}

function reset_account_password(string $email, string $phone, string $password, string $passwordConfirmation): array
{
    $email = strtolower(trim($email));
    $phone = trim($phone);
    $password = (string) $password;
    $passwordConfirmation = (string) $passwordConfirmation;
    $errors = [];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter the email address you used to register.';
    }

    if ($phone !== '' && !is_valid_bangladesh_phone_local($phone)) {
        $errors[] = bangladesh_phone_validation_message();
    }

    if (mb_strlen($password) < 8) {
        $errors[] = 'New password must be at least 8 characters long.';
    }

    if ($password !== $passwordConfirmation) {
        $errors[] = 'Password confirmation does not match.';
    }

    if ($errors !== []) {
        return $errors;
    }

    $user = db_one('SELECT id, phone FROM users WHERE email = :email LIMIT 1', [
        'email' => $email,
    ]);

    if (!$user) {
        return ['No account was found with that email address.'];
    }

    $storedPhone = trim((string) ($user['phone'] ?? ''));

    if ($storedPhone !== '') {
        if (normalize_phone_for_match($phone) === '' || normalize_phone_for_match($storedPhone) !== normalize_phone_for_match($phone)) {
            return ['Phone number does not match the account record.'];
        }
    }

    db_run(
        'UPDATE users SET password_hash = :password_hash WHERE id = :id',
        [
            'id' => (int) $user['id'],
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]
    );

    return [];
}

function logout_account(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}
