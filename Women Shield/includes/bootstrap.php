<?php

declare(strict_types=1);

$config = require __DIR__ . '/../config/app.php';

$autoload = __DIR__ . '/../vendor/autoload.php';

if (is_file($autoload)) {
    require_once $autoload;
}

date_default_timezone_set($config['timezone'] ?? 'UTC');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../lib/helpers.php';
set_app_config($config);
require_once __DIR__ . '/../lib/database.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/ai.php';
require_once __DIR__ . '/../lib/mailer.php';
require_once __DIR__ . '/../lib/services.php';
require_once __DIR__ . '/../includes/layout.php';
