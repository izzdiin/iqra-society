<?php
define('DB_HOST', 'localhost');
define('DB_USERNAME', '');
define('DB_PASSWORD', '');
define('DB_NAME', '');
define('SMTP_HOST', '');
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_PORT', 465);
define('SMTP_SECURE', 'ssl');
define('WA_BASE_URL', '');
define('WA_DEVICE_ID', '');

function getWaHeaders()
{
    return [
        'Authorization: Basic ' . base64_encode('admin:password'),
        'X-Device-Id: ' . WA_DEVICE_ID
    ];
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', 1); // Diaktifkan karena domain menggunakan HTTPS
    session_start();
}

$conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    error_log("Koneksi Database Gagal: " . $conn->connect_error);
    die("Maaf, terjadi gangguan koneksi database.");
}
$conn->set_charset("utf8mb4");

function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
}


/**
 * Catat aktivitas CRUD ke activity_logs.
 * @param string $action   CREATE|UPDATE|DELETE|APPROVE|REJECT|LOGIN|LOGOUT|UPLOAD|SETTING
 * @param string $module   modul: kelas, user, post, quotes, landing, profil, gateway, dll
 * @param string $desc     deskripsi human-readable
 * @param int|null $target_id  ID record yang terpengaruh
 */
function log_activity(string $action, string $module, string $desc, $target_id = null): void {
    global $conn;
    if (!isset($conn) || !($conn instanceof mysqli)) return;
    if (empty($_SESSION['user_id'])) return;
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
    if (is_string($ip) && strpos($ip, ',') !== false) $ip = trim(explode(',', $ip)[0]);
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, username, fullname, role, action, module, description, target_id, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) return;
    $uid = (int)$_SESSION['user_id'];
    $un  = (string)($_SESSION['username'] ?? '');
    $fn  = (string)($_SESSION['fullname'] ?? '');
    $rl  = (string)($_SESSION['role'] ?? '');
    $tid = $target_id === null ? null : (int)$target_id;
    $stmt->bind_param("issssssis", $uid, $un, $fn, $rl, $action, $module, $desc, $tid, $ip);
    $stmt->execute();
    $stmt->close();
}

function sanitize(string $data): string {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}
