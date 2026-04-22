<?php
return [
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
