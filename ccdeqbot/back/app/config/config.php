<?php

declare(strict_types=1);

return [
    'app_env' => 'production',
    'timezone' => 'America/Mexico_City',
    'base_path' => '/ccdeqbot/back/api',
    'db' => [
        'host' => 'localhost',
        'name' => 'crmcamar_ccdeqbot',
        'user' => 'crmcamar_ccdeqbotuser',
        'password' => 'Danjohn007!',
        'charset' => 'utf8mb4',
    ],
    'session' => [
        'name' => 'ccdeqcrm_session',
        'lifetime' => 28800,
    ],
    'allowed_origins' => [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
    ],
    'setup_key' => 'qYg14JF6461ZBn9uYEKgWAmYupP40_9T',
];
