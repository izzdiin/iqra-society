<?php
require_once __DIR__ . '/../config.php';

$json_str = file_get_contents('php://input');
$json = json_decode($json_str, true);

if (!$json) {
    http_response_code(400);
    exit('Invalid JSON');
}

$order_id     = $json['order_id'] ?? '';
$status_code  = $json['status_code'] ?? '';
$gross_amount = $json['gross_amount'] ?? '';
$server_key   = MIDTRANS_SERVER_KEY;
$input_sig    = $json['signature_key'] ?? '';

// Verifikasi Signature SHA512
$my_sig = hash("sha512", $order_id . $status_code . $gross_amount . $server_key);

if ($input_sig !== $my_sig) {
    http_response_code(403);
    exit('Invalid Signature');
}

$transaction_status = $json['transaction_status'] ?? '';

if ($transaction_status === 'settlement' || $transaction_status === 'capture') {
    $conn->begin_transaction();
    try {
        // 1. Ambil data transaksi
        $stmt = $conn->prepare("SELECT user_id, amount, payment_status FROM transactions WHERE merchant_ref = ? LIMIT 1 FOR UPDATE");
        $stmt->bind_param("s", $order_id);
        $stmt->execute();
        $trx = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($trx && $trx['payment_status'] !== 'PAID') {
            // Update Status Transaksi
            $stmt_up = $conn->prepare("UPDATE transactions SET payment_status = 'PAID' WHERE merchant_ref = ?");
            $stmt_up->bind_param("s", $order_id);
            $stmt_up->execute();
            $stmt_up->close();

            // Tambah Saldo Wallet User
            $stmt_w = $conn->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, ?) ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance)");
            $stmt_w->bind_param("id", $trx['user_id'], $trx['amount']);
            $stmt_w->execute();
            $stmt_w->close();
        }

        $conn->commit();
        echo "Success";
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo "Error: " . $e->getMessage();
    }
}