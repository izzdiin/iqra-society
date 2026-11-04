<?php
/**
 * CALLBACK MIDTRANS - HTTP Notification Handler
 * URL: https://iqrasociety.id/payment/callback_midtrans.php
 * Set di Dashboard Midtrans > Settings > Configuration > Payment Notification URL
 *
 * Verifikasi: signature_key = SHA512(order_id + status_code + gross_amount + server_key)
 */
require_once __DIR__ . '/../includes/auth_middleware.php';

// Log untuk debugging
function cb_log($msg) {
    file_put_contents(__DIR__ . '/callback.log', date('Y-m-d H:i:s') . " [MIDTRANS] $msg\n", FILE_APPEND);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    cb_log("Invalid JSON");
    http_response_code(400);
    exit;
}

$order_id = $data['order_id'] ?? '';
$status_code = $data['status_code'] ?? '';
$gross_amount = $data['gross_amount'] ?? '';
$signature_key = $data['signature_key'] ?? '';
$transaction_status = $data['transaction_status'] ?? '';
$fraud_status = $data['fraud_status'] ?? '';

cb_log("Notif: $order_id | status: $transaction_status | fraud: $fraud_status");

// Ambil server key
$keys = [];
$kq = $conn->query("SELECT key_name, key_value FROM gateway_settings");
while ($r = $kq->fetch_assoc()) $keys[$r['key_name']] = $r['key_value'];
$server_key = $keys['midtrans_server_key'] ?? '';

// Verifikasi signature
$expected = hash('sha512', $order_id . $status_code . $gross_amount . $server_key);
if ($signature_key !== $expected) {
    cb_log("Signature INVALID untuk $order_id");
    http_response_code(403);
    echo "Invalid signature";
    exit;
}

// Tentukan status baru
$new_status = 'UNPAID';
if ($transaction_status === 'capture') {
    $new_status = ($fraud_status === 'challenge') ? 'CHALLENGE' : 'PAID';
} elseif ($transaction_status === 'settlement') {
    $new_status = 'PAID';
} elseif ($transaction_status === 'pending') {
    $new_status = 'UNPAID';
} elseif (in_array($transaction_status, ['deny', 'cancel', 'expire'], true)) {
    $new_status = 'FAILED';
}

// Update transactions (buku/kelas)
$stmt = $conn->prepare("UPDATE transactions SET payment_status = ?, updated_at = NOW() WHERE merchant_ref = ? AND payment_gateway = 'midtrans'");
$stmt->bind_param("ss", $new_status, $order_id);
$stmt->execute();
$affected1 = $stmt->affected_rows;
$stmt->close();

// Update donasi_transactions
$stmt = $conn->prepare("UPDATE donasi_transactions SET transaction_status = ?, updated_at = NOW() WHERE order_id = ?");
$donasi_status = strtolower($new_status) === 'paid' ? 'settlement' : strtolower($new_status);
$stmt->bind_param("ss", $donasi_status, $order_id);
$stmt->execute();
$affected2 = $stmt->affected_rows;
$stmt->close();

cb_log("Update: $order_id -> $new_status (transactions: $affected1, donasi: $affected2)");

// Bagi hasil: catat earning bila transaksi PAID (idempotent)
if ($new_status === 'PAID' && $affected1 > 0) {
    require_once __DIR__ . '/../includes/earnings_helper.php';
    $eid = earn_record($conn, $order_id);
    if ($eid > 0) cb_log("Earning tercatat: id=$eid untuk $order_id");

    // Fulfillment: beri hak akses pembeli (enrollment kelas berbayar)
    require_once __DIR__ . '/../includes/fulfillment_helper.php';
    if (iqra_fulfill_paid($conn, $order_id)) {
        cb_log("Fulfillment selesai untuk $order_id");
    }
}

http_response_code(200);
echo "OK";
