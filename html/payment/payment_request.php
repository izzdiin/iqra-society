<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
check_access(['super_admin', 'admin', 'pengajar', 'pembelajar', 'donatur']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die(json_encode(['status' => false, 'message' => 'CSRF Token Invalid']));
    }

    $user_id = $_SESSION['user_id'];
    $amount  = (float) ($_POST['amount'] ?? 0);
    // Gunakan gateway aktif dari pengaturan (fallback: midtrans)
    $gw_q = $conn->query("SELECT key_value FROM gateway_settings WHERE key_name = 'active_gateway' LIMIT 1");
    $gateway = ($gw_q && $gw_q->num_rows > 0) ? $gw_q->fetch_assoc()['key_value'] : 'midtrans';
    if (!in_array($gateway, ['midtrans', 'tripay'], true)) { $gateway = 'midtrans'; }

    if ($amount < 10000) {
        die(json_encode(['status' => false, 'message' => 'Nominal minimal Rp 10.000']));
    }

    $merchant_ref = 'INV-' . time() . '-' . rand(1000, 9999);

    if ($gateway === 'midtrans') {
        $url = MIDTRANS_IS_PRODUCTION 
            ? 'https://app.midtrans.com/snap/v1/transactions' 
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';

        $payload = [
            'transaction_details' => [
                'order_id'     => $merchant_ref,
                'gross_amount' => (int) $amount,
            ],
            'customer_details' => [
                'first_name' => $_SESSION['username'],
            ]
        ];

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode(MIDTRANS_SERVER_KEY . ':')
            ]
        ]);

        $response = json_decode(curl_exec($curl), true);
        curl_close($curl);

        if (isset($response['token'])) {
            $snap_token = $response['token'];
            
            $stmt = $conn->prepare("INSERT INTO transactions (user_id, payment_gateway, merchant_ref, snap_token, amount, payment_status) VALUES (?, 'midtrans', ?, ?, ?, 'UNPAID')");
            $stmt->bind_param("issd", $user_id, $merchant_ref, $snap_token, $amount);
            $stmt->execute();
            $stmt->close();

            echo json_encode(['status' => true, 'snap_token' => $snap_token, 'merchant_ref' => $merchant_ref]);
            exit();
        }
    } elseif ($gateway === 'tripay') {
        $url = TRIPAY_IS_PRODUCTION
            ? 'https://tripay.co.id/api/transaction/create'
            : 'https://tripay.co.id/api-sandbox/transaction/create';

        $method     = sanitize($_POST['method'] ?? 'BRIVA');
        $signature  = hash_hmac('sha256', TRIPAY_MERCHANT_CODE . $merchant_ref . (int)$amount, TRIPAY_PRIVATE_KEY);

        $payload = [
            'method'         => $method,
            'merchant_ref'   => $merchant_ref,
            'amount'         => (int) $amount,
            'customer_name'  => $_SESSION['username'],
            'customer_email' => 'user@iqrasociety.online',
            'order_items'    => [
                ['name' => 'Topup Salvo / Donasi', 'price' => (int)$amount, 'quantity' => 1]
            ],
            'signature'      => $signature
        ];

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . TRIPAY_API_KEY,
                'Content-Type: application/json'
            ]
        ]);

        $response = json_decode(curl_exec($curl), true);
        curl_close($curl);

        if (isset($response['success']) && $response['success'] === true) {
            $checkout_url = $response['data']['checkout_url'];
            $reference    = $response['data']['reference'];

            $stmt = $conn->prepare("INSERT INTO transactions (user_id, payment_gateway, merchant_ref, reference_id, amount, payment_status, signature) VALUES (?, 'tripay', ?, ?, ?, 'UNPAID', ?)");
            $stmt->bind_param("issds", $user_id, $merchant_ref, $reference, $amount, $signature);
            $stmt->execute();
            $stmt->close();

            echo json_encode(['status' => true, 'checkout_url' => $checkout_url]);
            exit();
        }
    }

    echo json_encode(['status' => false, 'message' => 'Gagal membuat transaksi.']);
}