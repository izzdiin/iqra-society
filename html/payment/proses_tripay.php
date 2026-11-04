<?php
/**
 * PROSES TRIPAY - Buat transaksi dengan metode yang dipilih user
 * POST: {merchant_ref, method}
 * Response: {success, data: {pay_code, qr_url, instructions, amount, expired_time, reference}}
 */
require_once __DIR__ . '/../includes/auth_middleware.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$merchant_ref = sanitize($input['merchant_ref'] ?? '');
$method = sanitize($input['method'] ?? '');

if (empty($merchant_ref) || empty($method)) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap.']);
    exit;
}

// Ambil transaksi
$stmt = $conn->prepare("SELECT * FROM transactions WHERE merchant_ref = ? AND user_id = ? AND payment_gateway = 'tripay' LIMIT 1");
$uid = (int)$_SESSION['user_id'];
$stmt->bind_param("si", $merchant_ref, $uid);
$stmt->execute();
$trx = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$trx) {
    echo json_encode(['success' => false, 'message' => 'Transaksi tidak ditemukan.']);
    exit;
}

// Jika sudah ada reference (sudah dibuat sebelumnya), ambil detailnya
if (!empty($trx['reference_id'])) {
    // Ambil kredensial untuk cek detail
    $keys = [];
    $kq = $conn->query("SELECT key_name, key_value FROM gateway_settings WHERE key_name IN ('tripay_api_key')");
    while ($r = $kq->fetch_assoc()) $keys[$r['key_name']] = $r['key_value'];
    $api_key = $keys['tripay_api_key'] ?? '';
    $is_prod = (strpos($api_key, 'DEV-') !== 0);
    $url = ($is_prod ? 'https://tripay.co.id/api/' : 'https://tripay.co.id/api-sandbox/') . 'transaction/detail?reference=' . urlencode($trx['reference_id']);

    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $api_key], CURLOPT_TIMEOUT => 15]);
    $resp = curl_exec($ch); curl_close($ch);
    $data = json_decode($resp, true);
    if (($data['success'] ?? false) && isset($data['data'])) {
        $d = $data['data'];
        echo json_encode(['success' => true, 'data' => [
            'pay_code' => $d['pay_code'] ?? '',
            'qr_url' => $d['qr_url'] ?? '',
            'amount' => $d['amount'] ?? $trx['amount'],
            'expired_time' => $d['expired_time'] ?? null,
            'instructions' => $d['instructions'] ?? [],
            'reference' => $d['reference'] ?? '',
        ]]);
        exit;
    }
}

// Buat transaksi baru di Tripay
$keys = [];
$kq = $conn->query("SELECT key_name, key_value FROM gateway_settings WHERE key_name IN ('tripay_merchant_code','tripay_api_key','tripay_private_key')");
while ($r = $kq->fetch_assoc()) $keys[$r['key_name']] = $r['key_value'];

$tMerchant = $keys['tripay_merchant_code'] ?? '';
$tApiKey = $keys['tripay_api_key'] ?? '';
$tPrivate = $keys['tripay_private_key'] ?? '';
$amount = (int)$trx['amount'];

$signature = hash_hmac('sha256', $tMerchant . $merchant_ref . $amount, $tPrivate);
$is_prod = (strpos($tApiKey, 'DEV-') !== 0);
$url = $is_prod ? 'https://tripay.co.id/api/transaction/create' : 'https://tripay.co.id/api-sandbox/transaction/create';

$payload = json_encode([
    'method' => $method,
    'merchant_ref' => $merchant_ref,
    'amount' => $amount,
    'customer_name' => $_SESSION['username'] ?? 'User',
    'customer_email' => $_SESSION['email'] ?? 'user@iqrasociety.id',
    'order_items' => [['name' => substr($trx['item_name'], 0, 50), 'price' => $amount, 'quantity' => 1]],
    'expired_time' => time() + (24 * 60 * 60),
    'signature' => $signature,
]);

$ch = curl_init($url);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $tApiKey, 'Content-Type: application/json'], CURLOPT_TIMEOUT => 20]);
$resp = curl_exec($ch); $http = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
$data = json_decode($resp, true);

if ($http === 200 && ($data['success'] ?? false)) {
    $d = $data['data'];
    // Simpan reference
    $stmt = $conn->prepare("UPDATE transactions SET reference_id = ?, updated_at = NOW() WHERE merchant_ref = ?");
    $stmt->bind_param("ss", $d['reference'], $merchant_ref);
    $stmt->execute(); $stmt->close();

    echo json_encode(['success' => true, 'data' => [
        'pay_code' => $d['pay_code'] ?? '',
        'qr_url' => $d['qr_url'] ?? '',
        'amount' => $d['amount'] ?? $amount,
        'expired_time' => $d['expired_time'] ?? null,
        'instructions' => $d['instructions'] ?? [],
        'reference' => $d['reference'] ?? '',
    ]]);
} else {
    echo json_encode(['success' => false, 'message' => $data['message'] ?? 'Gagal membuat transaksi.']);
}
