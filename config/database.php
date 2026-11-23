<?php
/**
 * Configuración MySQL compatible con InfinityFree y entornos locales.
 * En InfinityFree usa los datos de MySQL Accounts del panel:
 * hostname, database name, username y password.
 */
$localConfig = __DIR__ . '/database.local.php';
if (is_file($localConfig)) {
    require $localConfig;
}

$host = getenv('DB_HOST') ?: ($DB_LOCAL_HOST ?? DB_HOST ?? 'localhost');
$name = getenv('DB_NAME') ?: ($DB_LOCAL_NAME ?? DB_NAME ?? '');
$user = getenv('DB_USER') ?: ($DB_LOCAL_USER ?? DB_USER ?? '');
$pass = getenv('DB_PASS') ?: ($DB_LOCAL_PASS ?? DB_PASS ?? '');

if ($name === '' || $user === '') {
    throw new RuntimeException('Configura DB_HOST, DB_NAME, DB_USER y DB_PASS en config/database.local.php.');
}

$pdo = new PDO(
    'mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8mb4',
    $user,
    $pass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]
);
