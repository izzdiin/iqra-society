<?php
require_once __DIR__ . '/../config.php';

$json_raw = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_CALLBACK_SIGNATURE'] ?? '';

// Verifikasi Signature HMAC-SHA256
$calc_signature = hash_hmac('sha256', $json_raw, TRIPAY_PRIVATE_KEY);

if ($signature !== $calc_signature) {
    http_response_code(403);
    exit('Invalid Signature Callback');
}

$data = json_decode($json_raw, true);
$status = $data['status'] ?? '';
$merchant_ref = $data['merchant_ref'] ?? '';

if ($status === 'PAID') {
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT user_id, amount, payment_status FROM transactions WHERE merchant_ref = ? LIMIT 1 FOR UPDATE");
        $stmt->bind_param("s", $merchant_ref);
        $stmt->execute();
        $trx = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($trx && $trx['payment_status'] !== 'PAID') {
            $stmt_up = $conn->prepare("UPDATE transactions SET payment_status = 'PAID' WHERE merchant_ref = ?");
            $stmt_up->bind_param("s", $merchant_ref);
            $stmt_up->execute();
            $stmt_up->close();

            $stmt_w = $conn->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, ?) ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance)");
            $stmt_w->bind_param("id", $trx['user_id'], $trx['amount']);
            $stmt_w->execute();
            $stmt_w->close();
        }

        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}