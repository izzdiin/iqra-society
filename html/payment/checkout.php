<?php
/**
 * CHECKOUT - Pembayaran Buku & Kelas
 * Menerima: item_type (buku/kelas), item_id
 * Menggunakan gateway aktif dari settings
 * Response: {success, gateway, snap_token|checkout_url, merchant_ref}
 */
require_once __DIR__ . '/../includes/auth_middleware.php';

header('Content-Type: application/json');

// Hanya user login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$item_type = sanitize($input['item_type'] ?? '');
$item_id = (int)($input['item_id'] ?? 0);

if (!in_array($item_type, ['buku', 'kelas'], true) || $item_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Item tidak valid.']);
    exit;
}

// Ambil data item & harga
if ($item_type === 'buku') {
    $stmt = $conn->prepare("SELECT id, title, price FROM section_buku WHERE id = ? AND status = 'approved' LIMIT 1");
} else {
    $stmt = $conn->prepare("SELECT id, title, price FROM section_kelas WHERE id = ? AND status = 'approved' LIMIT 1");
}
$stmt->bind_param("i", $item_id);
$stmt->execute();
$res = $stmt->get_result();
$item = $res->fetch_assoc();
$stmt->close();

if (!$item) {
    echo json_encode(['success' => false, 'message' => 'Item tidak ditemukan.']);
    exit;
}

$amount = (float)$item['price'];
if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Item ini gratis, tidak perlu pembayaran.']);
    exit;
}

// Gateway aktif
$gw_q = $conn->query("SELECT key_value FROM gateway_settings WHERE key_name = 'active_gateway' LIMIT 1");
$gateway = ($gw_q && $gw_q->num_rows > 0) ? $gw_q->fetch_assoc()['key_value'] : 'midtrans';
if (!in_array($gateway, ['midtrans', 'tripay'], true)) $gateway = 'midtrans';

// Ambil kredensial
$keys = [];
$kq = $conn->query("SELECT key_name, key_value FROM gateway_settings");
while ($r = $kq->fetch_assoc()) $keys[$r['key_name']] = $r['key_value'];

$user_id = (int)$_SESSION['user_id'];
$merchant_ref = strtoupper($item_type) . '-' . time() . '-' . rand(1000, 9999);
$item_name = $item['title'];

if ($gateway === 'midtrans') {
    $server_key = $keys['midtrans_server_key'] ?? '';
    if (empty($server_key)) {
        echo json_encode(['success' => false, 'message' => 'Midtrans belum dikonfigurasi.']);
        exit;
    }
    $is_prod = (strpos($server_key, 'SB-Mid-server') !== 0);
    $url = $is_prod ? 'https://app.midtrans.com/snap/v1/transactions' : 'https://app.sandbox.midtrans.com/snap/v1/transactions';

    $payload = json_encode([
        'transaction_details' => ['order_id' => $merchant_ref, 'gross_amount' => (int)$amount],
        'item_details' => [['id' => $item_type . '-' . $item_id, 'price' => (int)$amount, 'quantity' => 1, 'name' => substr($item_name, 0, 50)]],
        'customer_details' => ['first_name' => $_SESSION['username'] ?? 'User']
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json', 'Authorization: Basic ' . base64_encode($server_key . ':')],
        CURLOPT_TIMEOUT => 20]);
    $resp = curl_exec($ch); $http = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $data = json_decode($resp, true);

    if ($http === 201 && isset($data['token'])) {
        $stmt = $conn->prepare("INSERT INTO transactions (user_id, item_type, item_id, item_name, payment_gateway, merchant_ref, snap_token, amount, payment_status) VALUES (?, ?, ?, ?, 'midtrans', ?, ?, ?, 'UNPAID')");
        $stmt->bind_param("isisssd", $user_id, $item_type, $item_id, $item_name, $merchant_ref, $data['token'], $amount);
        $stmt->execute(); $stmt->close();
        echo json_encode(['success' => true, 'gateway' => 'midtrans', 'snap_token' => $data['token'], 'merchant_ref' => $merchant_ref]);
    } else {
        $msg = $data['error_messages'][0] ?? 'Gagal membuat transaksi Midtrans';
        echo json_encode(['success' => false, 'message' => $msg]);
    }
} else {
    // Tripay - simpan transaksi, user pilih metode di halaman IQRA
    $signature = hash_hmac('sha256', $merchant_ref, 'iqra');
    $stmt = $conn->prepare("INSERT INTO transactions (user_id, item_type, item_id, item_name, payment_gateway, merchant_ref, amount, payment_status, signature) VALUES (?, ?, ?, ?, 'tripay', ?, ?, 'UNPAID', ?)");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'DB prepare gagal: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("isissds", $user_id, $item_type, $item_id, $item_name, $merchant_ref, $amount, $signature);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'DB execute gagal: ' . $stmt->error]);
        exit;
    }
    $stmt->close();
    echo json_encode(['success' => true, 'gateway' => 'tripay', 'bayar_url' => '/payment/bayar/' . $merchant_ref, 'merchant_ref' => $merchant_ref]);
}
