<?php

return [
    'base_url' => '/Logno',
    'timezone' => 'Asia/Dhaka',
    'db' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'name' => 'women_safety_companion',
        'user' => 'root',
        'pass' => '',
    ],
    'mail' => [
        'driver' => 'phpmailer',
        'public_site_url' => 'http://localhost/Logno',
        'from_email' => 'your-email@gmail.com',
        'from_name' => 'Women Shield',
        'smtp' => [
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'username' => 'your-email@gmail.com',
            'password' => 'your-app-password',
            'encryption' => 'tls',
            'auth' => true,
            'timeout' => 15,
        ],
    ],
];
