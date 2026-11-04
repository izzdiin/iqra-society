<?php
/**
 * Google OAuth Callback
 * Terima code dari Google, tukar dengan access token, ambil profil user
 * Login atau buat akun baru otomatis
 */
require_once __DIR__ . '/../includes/auth_middleware.php';

// Verifikasi state (CSRF protection)
if (!isset($_GET['state']) || !isset($_SESSION['oauth_state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
    die('Invalid state parameter. Kemungkinan serangan CSRF.');
}
unset($_SESSION['oauth_state']);

if (!isset($_GET['code'])) {
    die('Otorisasi gagal. Tidak ada code dari Google.');
}

// Ambil kredensial
$keys = [];
$kq = $conn->query("SELECT key_name, key_value FROM gateway_settings WHERE key_name IN ('google_client_id','google_client_secret')");
while ($r = $kq->fetch_assoc()) $keys[$r['key_name']] = $r['key_value'];

$client_id = $keys['google_client_id'] ?? '';
$client_secret = $keys['google_client_secret'] ?? '';
$redirect_uri = 'https://iqrasociety.id/auth/google_callback.php';

// Tukar code dengan access token
$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'code' => $_GET['code'],
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'redirect_uri' => $redirect_uri,
        'grant_type' => 'authorization_code',
    ]),
    CURLOPT_TIMEOUT => 15,
]);
$token_resp = curl_exec($ch);
$curl_err = curl_error($ch);
$curl_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$token_data = json_decode($token_resp, true);

if (!isset($token_data['access_token'])) {
    $err = $token_data['error_description'] ?? $token_data['error'] ?? 'Unknown error';
    $debug = 'HTTP:' . $curl_http . ' | CurlErr:' . ($curl_err ?: 'none') . ' | Resp:' . htmlspecialchars(substr($token_resp ?: '(empty)', 0, 300));
    die('Gagal mendapatkan access token: ' . htmlspecialchars($err) . '<br><br><small>Debug: ' . $debug . '</small>');
}

// Ambil profil user dari Google
$ch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token_data['access_token']],
    CURLOPT_TIMEOUT => 15,
]);
$user_resp = curl_exec($ch);
curl_close($ch);
$google_user = json_decode($user_resp, true);

if (!isset($google_user['email'])) {
    die('Gagal mendapatkan data user dari Google.');
}

$google_id = $google_user['sub'] ?? '';
$email = $google_user['email'] ?? '';
$name = $google_user['name'] ?? '';
$picture = $google_user['picture'] ?? '';

// Cek apakah user sudah ada (by google_id atau email)
$stmt = $conn->prepare("SELECT id, username, fullname, role FROM users WHERE google_id = ? OR email = ? LIMIT 1");
$stmt->bind_param("ss", $google_id, $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$is_new_user = false;
if ($user) {
    // User sudah ada - update google_id jika belum ada
    if (empty($user['google_id'])) {
        $stmt = $conn->prepare("UPDATE users SET google_id = ?, auth_provider = 'google', avatar_url = ? WHERE id = ?");
        $stmt->bind_param("ssi", $google_id, $picture, $user['id']);
        $stmt->execute();
        $stmt->close();
    }
    $user_id = $user['id'];
    $username = $user['username'];
    $fullname = $user['fullname'];
    $role = $user['role'];
} else {
    // Buat akun baru otomatis
    $username = strtolower(preg_replace('/[^a-z0-9]/i', '', explode('@', $email)[0]));
    // Pastikan username unik
    $base_username = $username;
    $i = 1;
    while (true) {
        $chk = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $chk->bind_param("s", $username);
        $chk->execute();
        if ($chk->get_result()->num_rows === 0) { $chk->close(); break; }
        $chk->close();
        $username = $base_username . $i++;
    }

    $random_pass = bin2hex(random_bytes(16));
    $hashed = password_hash($random_pass, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (username, email, password, fullname, no_hp, role, google_id, auth_provider, avatar_url, created_at) VALUES (?, ?, ?, ?, '', 'pembelajar', ?, 'google', ?, NOW())");
    $stmt->bind_param("ssssss", $username, $email, $hashed, $name, $google_id, $picture);
    $stmt->execute();
    $user_id = $stmt->insert_id;
    $stmt->close();

    $fullname = $name;
    $role = 'pembelajar';
    $is_new_user = true;
}

// Set session seperti login biasa
$_SESSION['user_id'] = $user_id;
$_SESSION['username'] = $username;
$_SESSION['fullname'] = $fullname;
$_SESSION['role'] = $role;
$_SESSION['is_logged_in'] = true;
$_SESSION['email'] = $email;
$_SESSION['avatar'] = $picture;

// Log aktivitas
if (function_exists('log_activity')) {
    log_activity('login', 'auth', "Login via Google: $email", $user_id);
}

// User baru via Google -> lengkapi profil dulu
if ($is_new_user && $role === 'pembelajar') {
    header('Location: /pembelajar/lengkapi-profil.php');
    exit;
}

// Redirect ke dashboard sesuai role
$redirect = '/';
if ($role === 'pembelajar') $redirect = '/pembelajar/';
elseif ($role === 'pengajar') $redirect = '/pengajar/';
elseif (in_array($role, ['admin', 'super_admin', 'super_administrator'])) $redirect = '/admin/';

header('Location: ' . $redirect);
exit;
