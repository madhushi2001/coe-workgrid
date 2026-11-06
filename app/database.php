<?php
declare(strict_types=1);

function database(): PDO
{
    static $connection;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $localConfigPath = __DIR__ . '/config.local.php';
    $localConfig = is_file($localConfigPath) ? require $localConfigPath : [];
    if (!is_array($localConfig)) {
        $localConfig = [];
    }

    $host = getenv('DB_HOST') ?: ($localConfig['host'] ?? '127.0.0.1');
    $port = getenv('DB_PORT') ?: ($localConfig['port'] ?? '3306');
    $name = getenv('DB_NAME') ?: ($localConfig['name'] ?? 'coe_workgrid');
    $username = getenv('DB_USER') ?: ($localConfig['username'] ?? 'root');
    $password = getenv('DB_PASSWORD') ?: ($localConfig['password'] ?? '');

    $connection = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    return $connection;
}
