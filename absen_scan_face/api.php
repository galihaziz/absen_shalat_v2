<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const FACE_MATCH_THRESHOLD = 0.35;
const FACE_MATCH_MARGIN = 0.10;

function respond(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function readPayload(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || strlen($raw) > 65536) {
        respond(413, ['error' => 'Ukuran permintaan tidak valid.']);
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        respond(400, ['error' => 'Format permintaan tidak valid.']);
    }
    return $data;
}

function getAdminKey(array $payload): string
{
    $headerKey = $_SERVER['HTTP_X_ADMIN_KEY'] ?? '';
    return is_string($headerKey) && $headerKey !== ''
        ? $headerKey
        : (is_string($payload['admin_key'] ?? null) ? $payload['admin_key'] : '');
}

function requireAdmin(array $config, array $payload): void
{
    $configuredKey = (string)($config['registration_key'] ?? '');
    $providedKey = getAdminKey($payload);
    if ($configuredKey === '' || $providedKey === '' || !hash_equals($configuredKey, $providedKey)) {
        respond(403, ['error' => 'Kunci admin tidak valid atau belum dikonfigurasi di server.']);
    }
}

function validateDescriptor(mixed $value): array
{
    if (!is_array($value) || count($value) !== 128) {
        respond(422, ['error' => 'Data wajah tidak valid. Pastikan model deteksi telah dimuat.']);
    }
    $descriptor = [];
    foreach ($value as $number) {
        if ((!is_float($number) && !is_int($number)) || !is_finite((float)$number)) {
            respond(422, ['error' => 'Data wajah berisi nilai yang tidak valid.']);
        }
        $descriptor[] = (float)$number;
    }
    return $descriptor;
}

function descriptorDistance(array $left, array $right): float
{
    $sum = 0.0;
    for ($i = 0; $i < 128; $i++) {
        $difference = $left[$i] - $right[$i];
        $sum += $difference * $difference;
    }
    return sqrt($sum);
}

function identifyEmployee(array $descriptor, array $employees): ?array
{
    $matches = [];
    foreach ($employees as $employee) {
        $savedDescriptor = json_decode($employee['face_descriptor'], true);
        if (!is_array($savedDescriptor) || count($savedDescriptor) !== 128) {
            continue;
        }
        $distance = descriptorDistance($descriptor, array_map('floatval', $savedDescriptor));
        $matches[] = ['employee' => $employee, 'distance' => $distance];
    }

    usort($matches, static fn(array $left, array $right): int => $left['distance'] <=> $right['distance']);
    if ($matches === [] || $matches[0]['distance'] > FACE_MATCH_THRESHOLD) {
        return null;
    }
    if (isset($matches[1]) && ($matches[1]['distance'] - $matches[0]['distance']) < FACE_MATCH_MARGIN) {
        return null;
    }
    return $matches[0]['employee'];
}

try {
    $config = require __DIR__ . '/config.php';
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        respond(405, ['error' => 'Metode permintaan tidak diizinkan.']);
    }

    $payload = readPayload();
    $action = $payload['action'] ?? '';
    if (!in_array($action, ['register', 'attendance', 'history'], true)) {
        respond(400, ['error' => 'Aksi tidak dikenal.']);
    }
    if ($action === 'register' || $action === 'history') {
        requireAdmin($config, $payload);
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        $config['db_host'],
        $config['db_name']
    );
    $pdo = new PDO($dsn, $config['db_user'], $config['db_password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET time_zone = '+07:00'");

    if ($action === 'register') {
        if (!is_string($payload['employee_id'] ?? null) || !is_string($payload['full_name'] ?? null)) {
            respond(422, ['error' => 'ID karyawan atau nama tidak valid.']);
        }
        $employeeId = trim($payload['employee_id']);
        $fullName = trim($payload['full_name']);
        $descriptor = validateDescriptor($payload['descriptor'] ?? null);
        $nameLength = function_exists('mb_strlen') ? mb_strlen($fullName) : strlen($fullName);
        if ($employeeId === '' || strlen($employeeId) > 64 || $fullName === '' || $nameLength > 120) {
            respond(422, ['error' => 'ID karyawan atau nama tidak valid.']);
        }

        $statement = $pdo->prepare(
            'INSERT INTO employees (employee_id, full_name, face_descriptor)
             VALUES (:employee_id, :full_name, :face_descriptor)
             ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), face_descriptor = VALUES(face_descriptor)'
        );
        $statement->execute([
            'employee_id' => $employeeId,
            'full_name' => $fullName,
            'face_descriptor' => json_encode($descriptor, JSON_THROW_ON_ERROR),
        ]);
        respond(201, ['message' => 'Registrasi wajah berhasil disimpan.']);
    }

    if ($action === 'attendance') {
        $submittedDescriptors = $payload['descriptors'] ?? null;
        if (!is_array($submittedDescriptors) || count($submittedDescriptors) !== 3) {
            respond(422, ['error' => 'Verifikasi wajah perlu tiga sampel langsung. Silakan coba lagi.']);
        }
        $descriptors = array_map('validateDescriptor', $submittedDescriptors);
        $employees = $pdo->query('SELECT id, employee_id, full_name, face_descriptor FROM employees')->fetchAll();
        $matches = array_map(
            static fn(array $descriptor): ?array => identifyEmployee($descriptor, $employees),
            $descriptors
        );
        $bestMatch = $matches[0];
        if ($bestMatch === null) {
            respond(404, ['error' => 'Wajah tidak cocok dengan data karyawan terdaftar. Pastikan wajah yang benar sudah diregistrasi, lalu coba lagi.']);
        }
        foreach ($matches as $match) {
            if ($match === null || $match['id'] !== $bestMatch['id']) {
                respond(404, ['error' => 'Tiga sampel tidak cocok secara konsisten dengan satu karyawan. Absensi ditolak.']);
            }
        }

        try {
            $statement = $pdo->prepare(
                'INSERT INTO attendance (employee_id, attendance_date, attendance_time)
                 VALUES (:employee_id, CURDATE(), CURRENT_TIMESTAMP)'
            );
            $statement->execute(['employee_id' => $bestMatch['id']]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                respond(409, ['error' => 'Absensi hari ini sudah tercatat.']);
            }
            throw $exception;
        }

        $count = (int)$pdo->query('SELECT COUNT(*) FROM attendance WHERE attendance_date = CURDATE()')->fetchColumn();
        respond(201, [
            'message' => 'Kehadiran berhasil dicatat.',
            'employee' => ['employee_id' => $bestMatch['employee_id'], 'full_name' => $bestMatch['full_name']],
            'time' => date('H:i:s'),
            'count' => $count,
        ]);
    }

    if ($action === 'history') {
        $statement = $pdo->query(
            'SELECT e.employee_id, e.full_name, a.attendance_date, a.attendance_time
             FROM attendance a
             INNER JOIN employees e ON e.id = a.employee_id
             ORDER BY a.attendance_time DESC
             LIMIT 50'
        );
        $todayCount = (int)$pdo->query('SELECT COUNT(*) FROM attendance WHERE attendance_date = CURDATE()')->fetchColumn();
        respond(200, ['records' => $statement->fetchAll(), 'today_count' => $todayCount]);
    }
} catch (Throwable $exception) {
    error_log('Attendance API error: ' . $exception->getMessage());
    respond(500, ['error' => 'Terjadi kesalahan pada server. Periksa konfigurasi dan log server.']);
}
