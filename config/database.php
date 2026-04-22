<?php

require_once __DIR__ . '/env.php';

return [
    'host' => $_ENV['DB_HOST'],
    'user' => $_ENV['DB_USER'],
    'pass' => $_ENV['DB_PASS'],
    'name' => $_ENV['DB_NAME'],
    'port' => (int) $_ENV['DB_PORT'],
];
