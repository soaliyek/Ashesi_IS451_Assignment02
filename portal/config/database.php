<?php
declare(strict_types=1);

$env = parse_ini_file(__DIR__ . '/../../.env');
if ($env === false) {
    throw new RuntimeException('Database configuration could not be loaded.');
}

foreach (['server', 'user', 'password', 'database', 'port'] as $key) {
    if (!array_key_exists($key, $env)) {
        throw new RuntimeException('Database configuration is incomplete.');
    }
}

$connection = new mysqli(
    $env['server'],
    $env['user'],
    $env['password'],
    $env['database'],
    (int) $env['port']
);
$connection->set_charset('utf8mb4');

?>