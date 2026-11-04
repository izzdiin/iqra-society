<?php
/**
 * CEK STATUS TRANSAKSI (AJAX polling)
 * GET ?ref=MERCHANT_REF
 */
require_once __DIR__ . '/../includes/auth_middleware.php';
header('Content-Type: application/json');

$ref = sanitize($_GET['ref'] ?? '');
$uid = (int)($_SESSION['user_id'] ?? 0);

$stmt = $conn->prepare("SELECT payment_status FROM transactions WHERE merchant_ref = ? AND user_id = ? LIMIT 1");
$stmt->bind_param("si", $ref, $uid);
$stmt->execute();
$trx = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo json_encode(['status' => $trx['payment_status'] ?? 'NOT_FOUND']);
