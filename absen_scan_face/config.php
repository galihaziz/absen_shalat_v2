<?php
declare(strict_types=1);

$localConfigPath = __DIR__ . '/config.local.php';
$localConfig = is_file($localConfigPath) ? require $localConfigPath : [];
$localConfig = is_array($localConfig) ? $localConfig : [];

return [
    'db_host' => getenv('ATTENDANCE_DB_HOST') ?: ($localConfig['db_host'] ?? '127.0.0.1'),
    'db_name' => getenv('ATTENDANCE_DB_NAME') ?: ($localConfig['db_name'] ?? 'absensi_wajah'),
    'db_user' => getenv('ATTENDANCE_DB_USER') ?: ($localConfig['db_user'] ?? 'root'),
    'db_password' => getenv('ATTENDANCE_DB_PASSWORD') ?: ($localConfig['db_password'] ?? ''),
    'registration_key' => getenv('ATTENDANCE_REGISTRATION_KEY') ?: ($localConfig['registration_key'] ?? ''),
];
