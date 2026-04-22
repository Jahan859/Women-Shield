<?php

$defaults = [
    'app_name' => 'Women Shield',
    'base_path' => dirname(__DIR__),
    'base_url' => '',
    'timezone' => 'Asia/Dhaka',
    'db' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'name' => 'women_safety_companion',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'mail' => [
        'driver' => 'mail',
        'public_site_url' => '',
        'from_email' => 'noreply@womenshield.local',
        'from_name' => 'Women Shield',
        'smtp' => [
            'host' => '',
            'port' => 587,
            'username' => '',
            'password' => '',
            'encryption' => 'tls',
            'auth' => true,
            'timeout' => 15,
        ],
    ],
];

$localConfig = __DIR__ . '/local.php';

if (is_file($localConfig)) {
    $overrides = require $localConfig;

    if (is_array($overrides)) {
        return array_replace_recursive($defaults, $overrides);
    }
}

return $defaults;
