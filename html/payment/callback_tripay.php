<?php
/**
 * CALLBACK TRIPAY - Handler
 * URL: https://iqrasociety.id/payment/callback_tripay.php
 * Set di Dashboard TriPay > Merchant > Callback URL
 *
 * Verifikasi: X-Callback-Signature = HMAC-SHA256(raw_body, private_key)
 * Event: X-Callback-Event
 */
require_once __DIR__ . '/../includes/auth_middleware.php';

function cb_log($msg) {
    file_put_contents(__DIR__ . '/callback.log', date('Y-m-d H:i:s') . " [TRIPAY] $msg\n", FILE_APPEND);
}

$raw = file_get_contents('php://input');
$callback_sig = $_SERVER['HTTP_X_CALLBACK_SIGNATURE'] ?? '';
$callback_event = $_SERVER['HTTP_X_CALLBACK_EVENT'] ?? '';

$data = json_decode($raw, true);
if (!$data) {
    cb_log("Invalid JSON");
    http_response_code(400);
    exit;
}

// Ambil private key
$keys = [];
$kq = $conn->query("SELECT key_name, key_value FROM gateway_settings");
while ($r = $kq->fetch_assoc()) $keys[$r['key_name']] = $r['key_value'];
$private_key = $keys['tripay_private_key'] ?? '';

// Verifikasi signature
$expected = hash_hmac('sha256', $raw, $private_key);
if (!hash_equals($expected, $callback_sig)) {
    cb_log("Signature INVALID");
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid signature']);
    exit;
}

$merchant_ref = $data['merchant_ref'] ?? '';
$status = strtoupper($data['status'] ?? '');
$reference = $data['reference'] ?? '';

cb_log("Callback: $merchant_ref | status: $status | event: $callback_event");

// Mapping status Tripay -> internal
// Tripay: UNPAID, PAID, EXPIRED, FAILED, REFUND
// transactions.payment_status enum: UNPAID, PAID, EXPIRED, FAILED
$new_status = 'UNPAID';
if ($status === 'PAID') {
    $new_status = 'PAID';
} elseif ($status === 'EXPIRED') {
    $new_status = 'EXPIRED';
} elseif (in_array($status, ['FAILED', 'REFUND'], true)) {
    $new_status = 'FAILED';
}

// donasi_transactions.transaction_status memakai kosakata Midtrans:
// enum: pending, settlement, capture, deny, cancel, expire, failure
$donasi_map = [
    'PAID'   => 'settlement',
    'UNPAID' => 'pending',
    'EXPIRED' => 'expire',
    'FAILED' => 'failure',
    'REFUND' => 'cancel',
];
$donasi_status = $donasi_map[$status] ?? 'pending';

// Update transactions (buku/kelas)
$stmt = $conn->prepare("UPDATE transactions SET payment_status = ?, updated_at = NOW() WHERE merchant_ref = ? AND payment_gateway = 'tripay'");
$stmt->bind_param("ss", $new_status, $merchant_ref);
$stmt->execute();
$affected1 = $stmt->affected_rows;
$stmt->close();

// Update donasi_transactions (pakai reference atau merchant_ref)
$stmt = $conn->prepare("UPDATE donasi_transactions SET transaction_status = ?, updated_at = NOW() WHERE order_id = ?");
$stmt->bind_param("ss", $donasi_status, $merchant_ref);
$stmt->execute();
$affected2 = $stmt->affected_rows;
$stmt->close();

cb_log("Update: $merchant_ref -> $new_status (transactions: $affected1, donasi: $affected2)");

// Bagi hasil: catat earning bila transaksi PAID (idempotent)
if ($new_status === 'PAID' && $affected1 > 0) {
    require_once __DIR__ . '/../includes/earnings_helper.php';
    $eid = earn_record($conn, $merchant_ref);
    if ($eid > 0) cb_log("Earning tercatat: id=$eid untuk $merchant_ref");

    // Fulfillment: beri hak akses pembeli (enrollment kelas berbayar)
    require_once __DIR__ . '/../includes/fulfillment_helper.php';
    if (iqra_fulfill_paid($conn, $merchant_ref)) {
        cb_log("Fulfillment selesai untuk $merchant_ref");
    }
}

http_response_code(200);
echo json_encode(['success' => true]);
