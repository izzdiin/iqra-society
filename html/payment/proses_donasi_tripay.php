<?php
/**
 * PROSES DONASI TRIPAY - Buat transaksi dengan metode pilihan
 */
require_once __DIR__ . '/../includes/auth_middleware.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$order_id = sanitize($input['order_id'] ?? '');
$method = sanitize($input['method'] ?? '');

$stmt = $conn->prepare("SELECT * FROM donasi_transactions WHERE order_id = ? LIMIT 1");
$stmt->bind_param("s", $order_id);
$stmt->execute();
$trx = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$trx) { echo json_encode(['success' => false, 'message' => 'Tidak ditemukan.']); exit; }

$keys = [];
$kq = $conn->query("SELECT key_name, key_value FROM gateway_settings WHERE key_name IN ('tripay_merchant_code','tripay_api_key','tripay_private_key')");
while ($r = $kq->fetch_assoc()) $keys[$r['key_name']] = $r['key_value'];

$amount = (int)$trx['amount'];
$sig = hash_hmac('sha256', $keys['tripay_merchant_code'] . $order_id . $amount, $keys['tripay_private_key']);
$is_prod = (strpos($keys['tripay_api_key'], 'DEV-') !== 0);
$url = ($is_prod ? 'https://tripay.co.id/api/' : 'https://tripay.co.id/api-sandbox/') . 'transaction/create';

$payload = json_encode([
    'method' => $method, 'merchant_ref' => $order_id, 'amount' => $amount,
    'customer_name' => $trx['donor_name'], 'customer_email' => $trx['donor_email'],
    'order_items' => [['name' => 'Donasi Dakwah', 'price' => $amount, 'quantity' => 1]],
    'expired_time' => time() + 86400, 'signature' => $sig
]);
$ch = curl_init($url);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $keys['tripay_api_key'], 'Content-Type: application/json'], CURLOPT_TIMEOUT => 20]);
$resp = curl_exec($ch); $http = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
$data = json_decode($resp, true);

if ($http === 200 && ($data['success'] ?? false)) {
    $d = $data['data'];
    echo json_encode(['success' => true, 'data' => [
        'pay_code' => $d['pay_code'] ?? '', 'qr_url' => $d['qr_url'] ?? '',
        'reference' => $d['reference'] ?? ''
    ]]);
} else {
    echo json_encode(['success' => false, 'message' => $data['message'] ?? 'Gagal.']);
}
