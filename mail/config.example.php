<?php
return [
    'turnstile' => [
        'enabled' => true,
        'site_key' => '0x4AAAAAAC-wIh9U1gGUso6W',
        'secret_key' => 'SET_IN_LOCAL_CONFIG_ONLY',
    ],
    'db' => [
        'host' => 'localhost',
        'name' => 'mat_ieee_landing',
        'user' => 'db_user',
        'pass' => 'db_password',
        'charset' => 'utf8mb4',
    ],
    'smtp' => [
        'host' => 'smtp.example.com',
        'username' => 'notifications@example.com',
        'password' => 'CHANGE_ME',
        'port' => 587,
        'encryption' => 'tls',
    ],
    'from' => [
        'email' => 'notifications@example.com',
        'name' => 'MAT IEEE Landing',
    ],
    'recipients' => [
        'recipient-one@example.com',
        'recipient-two@example.com',
    ],
];
