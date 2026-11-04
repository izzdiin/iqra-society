<?php
/**
 * Google OAuth Login - Initiate
 * Redirect user ke Google untuk otorisasi
 */
require_once __DIR__ . '/../includes/auth_middleware.php';

// Ambil kredensial dari database
$keys = [];
$kq = $conn->query("SELECT key_name, key_value FROM gateway_settings WHERE key_name IN ('google_client_id','google_client_secret')");
while ($r = $kq->fetch_assoc()) $keys[$r['key_name']] = $r['key_value'];

$client_id = $keys['google_client_id'] ?? '';
if (empty($client_id)) {
    die('Google Client ID belum dikonfigurasi.');
}

$redirect_uri = 'https://iqrasociety.id/auth/google_callback.php';

// Generate state untuk CSRF protection
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

$params = http_build_query([
    'client_id' => $client_id,
    'redirect_uri' => $redirect_uri,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'access_type' => 'online',
    'prompt' => 'select_account',
    'state' => $state,
]);

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
exit;
