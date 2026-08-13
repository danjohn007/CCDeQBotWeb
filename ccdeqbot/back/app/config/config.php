<?php

declare(strict_types=1);

// Calcula dinámicamente la ruta base de la API según la carpeta donde se aloje el proyecto.
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$basePath = preg_replace('#/index\.php$#', '', $scriptName) ?: '/api';

return [
    'app_env' => 'production',
    'timezone' => 'America/Mexico_City',
    'base_path' => $basePath,
    'db' => [
        'host' => 'localhost',
        'name' => 'crmcamar_allunay',
        'user' => 'crmcamar_allunayadmin',
        'password' => 'cnhmYoIC[O^v',
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
