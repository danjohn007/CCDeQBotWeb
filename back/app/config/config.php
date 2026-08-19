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
    'messaging' => [
        // Cola programada de producción: la web encola y Firebase procesa cada 5 minutos.
        'use_scheduled_queue' => true,

        // Envíos por grupos (Prospectos, Clientes, etc.).
        'firebase_broadcast_url' => 'https://us-central1-ccdeqbot.cloudfunctions.net/crmBroadcastCCdeQbot',
        // Debe coincidir con CRM_BROADCAST_TOKEN_CCDEQBOT en Firebase.
        'broadcast_token' => 'cY7Kk4owwMe-j5aPJDdd0fWbQDc94VhCV3f21q3JNU5xxryM',

        // Envío individual de agente, equivalente al flujo usado en LaptopFix.
        'firebase_agent_url' => 'https://us-central1-ccdeqbot.cloudfunctions.net/sendAgentMessageCCdeQbot',
        // Para que solo tengas que trabajar desde terminal, usa el mismo valor anterior
        // al crear AGENT_API_TOKEN_CCDEQBOT en Firebase. Después puedes separarlos si deseas.
        'agent_token' => 'cY7Kk4owwMe-j5aPJDdd0fWbQDc94VhCV3f21q3JNU5xxryM',
    ],
    'setup_key' => 'qYg14JF6461ZBn9uYEKgWAmYupP40_9T',
];
