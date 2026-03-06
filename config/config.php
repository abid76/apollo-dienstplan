<?php

return [
    // Bei Deployment in Unterverzeichnis setzen (z. B. '/apollo-dienstplan'). Leer = im Root.
    'base_path' => rtrim((string) (getenv('BASE_PATH') ?: ''), '/'),
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('DB_PORT') ?: '3306'),
        'database' => getenv('DB_NAME') ?: 'dienstplan',
        'user' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: 'root',
        'charset' => 'utf8mb4',
    ],
];

