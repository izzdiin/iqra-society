<?php

/**
 * ============================================================
 * PROCESS DONASI - MIDTRANS SNAP
 * ============================================================
 *
 * Fungsi:
 * - Menerima request POST JSON
 * - Validasi data donatur
 * - Membuat transaksi pending di database
 * - Membuat Snap Token Midtrans
 * - Menyimpan Snap Token + raw response
 * - Mengembalikan JSON ke frontend
 *
 * Catatan:
 * - Tidak menggunakan mysqli_stmt::get_result()
 * - Menggunakan bind_result() agar kompatibel dengan PHP
 *   tanpa mysqlnd
 * - Status pembayaran final harus diperbarui melalui
 *   Midtrans Notification/Webhook
 *
 * ============================================================
 */


/*
|--------------------------------------------------------------------------
| 1. OUTPUT BUFFERING
|--------------------------------------------------------------------------
|
| Mencegah output tidak sengaja dari config.php atau file lain
| merusak response JSON.
|
*/
ob_start();


/*
|--------------------------------------------------------------------------
| 2. ERROR REPORTING
|--------------------------------------------------------------------------
|
| Error tidak ditampilkan ke browser.
| Detail error masuk ke error_log server.
|
*/
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

error_reporting(E_ALL);


/*
|--------------------------------------------------------------------------
| 3. HEADER DEFAULT
|--------------------------------------------------------------------------
*/
header('Content-Type: application/json; charset=utf-8');


/*
|--------------------------------------------------------------------------
| 4. HELPER JSON RESPONSE
|--------------------------------------------------------------------------
*/
function jsonResponse(array $data, int $httpCode = 200): void
{
    /*
     * Bersihkan output sebelumnya agar response benar-benar JSON.
     */
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($httpCode);

    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| 5. SHUTDOWN HANDLER
|--------------------------------------------------------------------------
|
| Jika terjadi fatal error yang tidak tertangkap oleh try/catch,
| server tetap mengembalikan JSON.
|
*/
register_shutdown_function(function (): void {

    $error = error_get_last();

    if ($error === null) {
        return;
    }

    $fatalTypes = [
        E_ERROR,
        E_PARSE,
        E_CORE_ERROR,
        E_COMPILE_ERROR,
        E_USER_ERROR
    ];

    if (!in_array($error['type'], $fatalTypes, true)) {
        return;
    }

    /*
     * Catat detail error ke server log.
     */
    error_log(
        'process_donasi.php FATAL ERROR: ' .
        ($error['message'] ?? 'Unknown error') .
        ' | File: ' .
        ($error['file'] ?? 'Unknown file') .
        ' | Line: ' .
        ($error['line'] ?? 'Unknown line')
    );

    /*
     * Bersihkan output buffer.
     */
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code(500);

    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        [
            'success' => false,
            'message' => 'Terjadi kesalahan internal pada server.'
        ],
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );
});


/*
|--------------------------------------------------------------------------
| 6. GLOBAL DATABASE CONNECTION
|--------------------------------------------------------------------------
*/
$conn = null;


/*
|--------------------------------------------------------------------------
| 7. LOAD CONFIG
|--------------------------------------------------------------------------
*/
try {

    require_once __DIR__ . '/includes/auth_middleware.php';

} catch (Throwable $e) {

    error_log(
        'process_donasi.php: Gagal memuat config.php. ' .
        $e->getMessage()
    );

    jsonResponse([
        'success' => false,
        'message' => 'Konfigurasi sistem tidak dapat dimuat.'
    ], 500);
}


/*
|--------------------------------------------------------------------------
| 8. VALIDASI DATABASE CONNECTION
|--------------------------------------------------------------------------
*/
if (!isset($conn) || !($conn instanceof mysqli)) {

    error_log(
        'process_donasi.php: Variabel $conn tidak tersedia '
        . 'atau bukan instance mysqli.'
    );

    jsonResponse([
        'success' => false,
        'message' => 'Koneksi database tidak tersedia.'
    ], 500);
}


/*
|--------------------------------------------------------------------------
| 9. SESSION
|--------------------------------------------------------------------------
*/
try {

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

} catch (Throwable $e) {

    error_log(
        'process_donasi.php: Gagal memulai session. ' .
        $e->getMessage()
    );

    /*
     * Session bukan syarat mutlak untuk donasi.
     * Karena user_id boleh NULL, proses tetap dilanjutkan.
     */
}


/*
|--------------------------------------------------------------------------
| 10. HELPER UPDATE TRANSACTION
|--------------------------------------------------------------------------
|
| Digunakan untuk memperbarui status / raw response transaksi.
|
*/
function updateDonationTransaction(
    mysqli $conn,
    string $orderId,
    string $status,
    ?string $rawResponse = null,
    ?string $snapToken = null
): bool {

    $sql = "
        UPDATE donasi_transactions
        SET
            transaction_status = ?,
            snap_token = COALESCE(?, snap_token),
            raw_response = COALESCE(?, raw_response)
        WHERE order_id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {

        error_log(
            'process_donasi.php: Gagal prepare UPDATE transaksi. ' .
            'Order ID: ' . $orderId .
            ' | Error: ' . $conn->error
        );

        return false;
    }

    $stmt->bind_param(
        'ssss',
        $status,
        $snapToken,
        $rawResponse,
        $orderId
    );

    $success = $stmt->execute();

    if (!$success) {

        error_log(
            'process_donasi.php: Gagal execute UPDATE transaksi. ' .
            'Order ID: ' . $orderId .
            ' | Error: ' . $stmt->error
        );
    }

    $stmt->close();

    return $success;
}


/*
|--------------------------------------------------------------------------
| 11. MAIN PROCESS
|--------------------------------------------------------------------------
*/
try {

    /*
    |--------------------------------------------------------------------------
    | 11.1 REQUEST METHOD
    |--------------------------------------------------------------------------
    */

    if (
        !isset($_SERVER['REQUEST_METHOD']) ||
        $_SERVER['REQUEST_METHOD'] !== 'POST'
    ) {

        jsonResponse([
            'success' => false,
            'message' => 'Metode request tidak diizinkan.'
        ], 405);
    }


    /*
    |--------------------------------------------------------------------------
    | 11.2 CONTENT TYPE
    |--------------------------------------------------------------------------
    |
    | Tidak memaksa Content-Type karena beberapa frontend bisa saja
    | mengirim charset.
    |
    */


    /*
    |--------------------------------------------------------------------------
    | 11.3 REQUEST BODY
    |--------------------------------------------------------------------------
    */

    $inputRaw = file_get_contents('php://input');

    if ($inputRaw === false) {

        error_log(
            'process_donasi.php: Gagal membaca php://input.'
        );

        jsonResponse([
            'success' => false,
            'message' => 'Gagal membaca data request.'
        ], 400);
    }

    if (trim($inputRaw) === '') {

        jsonResponse([
            'success' => false,
            'message' => 'Request body kosong.'
        ], 400);
    }


    /*
    |--------------------------------------------------------------------------
    | 11.4 JSON DECODE
    |--------------------------------------------------------------------------
    */

    $data = json_decode(
        $inputRaw,
        true
    );

    if (
        json_last_error() !== JSON_ERROR_NONE ||
        !is_array($data)
    ) {

        error_log(
            'process_donasi.php: JSON request tidak valid. ' .
            'Error: ' . json_last_error_msg()
        );

        jsonResponse([
            'success' => false,
            'message' => 'Format data JSON tidak valid.'
        ], 400);
    }


    /*
    |--------------------------------------------------------------------------
    | 11.5 AMOUNT
    |--------------------------------------------------------------------------
    */

    $amountRaw = $data['amount'] ?? null;

    /*
     * Pastikan amount bukan array/object.
     */
    if (
        is_array($amountRaw) ||
        is_object($amountRaw)
    ) {

        jsonResponse([
            'success' => false,
            'message' => 'Nominal donasi tidak valid.'
        ], 400);
    }

    $amount = filter_var(
        $amountRaw,
        FILTER_VALIDATE_INT
    );

    if (
        $amount === false ||
        $amount < 10000
    ) {

        jsonResponse([
            'success' => false,
            'message' => 'Nominal donasi minimal Rp 10.000.'
        ], 400);
    }


    /*
    |--------------------------------------------------------------------------
    | 11.6 DONOR NAME
    |--------------------------------------------------------------------------
    */

    $donorName = trim(
        (string)($data['name'] ?? '')
    );

    if ($donorName === '') {

        jsonResponse([
            'success' => false,
            'message' => 'Nama donatur wajib diisi.'
        ], 400);
    }


    /*
    |--------------------------------------------------------------------------
    | 11.7 NAME LENGTH
    |--------------------------------------------------------------------------
    |
    | Gunakan mb_strlen jika tersedia.
    | Fallback ke strlen jika extension mbstring tidak tersedia.
    |
    */

    if (function_exists('mb_strlen')) {

        $nameLength = mb_strlen(
            $donorName,
            'UTF-8'
        );

    } else {

        $nameLength = strlen($donorName);
    }

    if ($nameLength > 150) {

        jsonResponse([
            'success' => false,
            'message' => 'Nama donatur maksimal 150 karakter.'
        ], 400);
    }


    /*
    |--------------------------------------------------------------------------
    | 11.8 DONOR EMAIL
    |--------------------------------------------------------------------------
    */

    $donorEmailRaw = trim(
        (string)($data['email'] ?? '')
    );

    $donorEmail = filter_var(
        $donorEmailRaw,
        FILTER_VALIDATE_EMAIL
    );

    if ($donorEmail === false) {

        jsonResponse([
            'success' => false,
            'message' => 'Format email tidak valid.'
        ], 400);
    }

    $donorEmail = trim($donorEmail);

    if (strlen($donorEmail) > 150) {

        jsonResponse([
            'success' => false,
            'message' => 'Email maksimal 150 karakter.'
        ], 400);
    }


    /*
    |--------------------------------------------------------------------------
    | 11.8b CEK GATEWAY AKTIF - Jika Tripay, proses via Tripay
    |--------------------------------------------------------------------------
    */
    $active_gw = 'midtrans';
    $gw_q = $conn->query("SELECT key_value FROM gateway_settings WHERE key_name = 'active_gateway' LIMIT 1");
    if ($gw_q && $gw_q->num_rows > 0) {
        $row_gw = $gw_q->fetch_assoc();
        if (in_array($row_gw['key_value'], ['midtrans', 'tripay'], true)) {
            $active_gw = $row_gw['key_value'];
        }
    }

    // Generate orderId lebih awal agar bisa dipakai blok tripay
    if (!isset($orderId) || empty($orderId)) {
        $randomNumber = random_int(1000, 9999);
        $orderId = 'DONASI-' . date('YmdHis') . '-' . $randomNumber;
    }

    if ($active_gw === 'tripay') {
        // === PROSES VIA TRIPAY ===
        // Simpan donasi dulu agar bayar_donasi.php bisa menemukannya
        $tUid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $stmtT = $conn->prepare("INSERT INTO donasi_transactions (order_id, user_id, donor_name, donor_email, amount, payment_type, transaction_status) VALUES (?, ?, ?, ?, ?, 'tripay', 'pending')");
        $stmtT->bind_param("sissd", $orderId, $tUid, $donorName, $donorEmail, $amount);
        if (!$stmtT->execute()) {
            jsonResponse(['success' => false, 'message' => 'Gagal menyimpan donasi: ' . $stmtT->error], 500);
        }
        $stmtT->close();
        // User pilih metode di halaman IQRA
        jsonResponse(['success' => true, 'gateway' => 'tripay',
            'bayar_url' => '/payment/bayar-donasi/' . $orderId,
            'merchant_ref' => $orderId]);
    }

    /*
    |--------------------------------------------------------------------------
    | 11.9 GET MIDTRANS SERVER KEY
    |--------------------------------------------------------------------------
    |
    | PENTING:
    | Tidak menggunakan get_result().
    | Menggunakan bind_result().
    |
    */

    $serverKey = '';

    $stmtKey = $conn->prepare(
        "
        SELECT key_value
        FROM gateway_settings
        WHERE key_name = 'midtrans_server_key'
        LIMIT 1
        "
    );

    if (!$stmtKey) {

        error_log(
            'process_donasi.php: Gagal prepare query Server Key Midtrans. ' .
            $conn->error
        );

        jsonResponse([
            'success' => false,
            'message' => 'Terjadi kesalahan konfigurasi sistem.'
        ], 500);
    }


    if (!$stmtKey->execute()) {

        error_log(
            'process_donasi.php: Gagal execute query Server Key Midtrans. ' .
            $stmtKey->error
        );

        $stmtKey->close();

        jsonResponse([
            'success' => false,
            'message' => 'Gagal membaca konfigurasi pembayaran.'
        ], 500);
    }


    /*
     * Tidak menggunakan get_result().
     */
    $stmtKey->bind_result($keyValue);

    if ($stmtKey->fetch()) {

        $serverKey = trim(
            (string)$keyValue
        );
    }

    $stmtKey->close();


    /*
    |--------------------------------------------------------------------------
    | 11.10 VALIDATE SERVER KEY
    |--------------------------------------------------------------------------
    */

    if ($serverKey === '') {

        error_log(
            'process_donasi.php: Midtrans Server Key kosong.'
        );

        jsonResponse([
            'success' => false,
            'message' => 'Pengaturan Midtrans Server Key belum terkonfigurasi di sistem.'
        ], 500);
    }


    /*
    |--------------------------------------------------------------------------
    | 11.11 MIDTRANS MODE
    |--------------------------------------------------------------------------
    |
    | true  = Production
    | false = Sandbox
    |
    */

    $isProduction = true;


    $snapApiUrl = $isProduction
        ? 'https://app.midtrans.com/snap/v1/transactions'
        : 'https://app.sandbox.midtrans.com/snap/v1/transactions';


    /*
    |--------------------------------------------------------------------------
    | 11.12 GENERATE ORDER ID
    |--------------------------------------------------------------------------
    */

    try {

        $randomNumber = random_int(
            100000,
            999999
        );

    } catch (Throwable $e) {

        error_log(
            'process_donasi.php: random_int gagal. ' .
            $e->getMessage()
        );

        jsonResponse([
            'success' => false,
            'message' => 'Gagal membuat nomor transaksi.'
        ], 500);
    }


    if (!isset($orderId) || empty($orderId)) {
        $orderId =
            'DONASI-' .
            date('YmdHis') .
            '-' .
            $randomNumber;
    }


    /*
    |--------------------------------------------------------------------------
    | 11.13 USER ID
    |--------------------------------------------------------------------------
    */

    $userId = null;

    if (
        isset($_SESSION['user_id']) &&
        $_SESSION['user_id'] !== ''
    ) {

        $validatedUserId = filter_var(
            $_SESSION['user_id'],
            FILTER_VALIDATE_INT
        );

        if ($validatedUserId !== false) {
            $userId = $validatedUserId;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | 11.14 INITIAL TRANSACTION DATA
    |--------------------------------------------------------------------------
    */

    $transactionStatus = 'pending';

    /*
     * payment_type akan diisi melalui Midtrans Notification/Webhook.
     */
    $paymentType = null;

    $snapToken = null;

    $rawResponseJson = null;


    /*
    |--------------------------------------------------------------------------
    | 11.15 AMOUNT DECIMAL
    |--------------------------------------------------------------------------
    |
    | Database:
    | DECIMAL(12,2)
    |
    | bind_param menggunakan "d".
    |
    */

    $amountDecimal = (float)$amount;


    /*
    |--------------------------------------------------------------------------
    | 11.16 INSERT TRANSACTION PENDING
    |--------------------------------------------------------------------------
    */

    $stmtInsert = $conn->prepare(
        "
        INSERT INTO donasi_transactions
        (
            order_id,
            user_id,
            donor_name,
            donor_email,
            amount,
            payment_type,
            transaction_status,
            snap_token,
            raw_response
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        "
    );


    if (!$stmtInsert) {

        error_log(
            'process_donasi.php: Gagal prepare INSERT transaksi. ' .
            'Order ID: ' . $orderId .
            ' | Error: ' . $conn->error
        );

        jsonResponse([
            'success' => false,
            'message' => 'Gagal menyiapkan transaksi.'
        ], 500);
    }


    /*
     * s = order_id
     * i = user_id
     * s = donor_name
     * s = donor_email
     * d = amount
     * s = payment_type
     * s = transaction_status
     * s = snap_token
     * s = raw_response
     */
    $stmtInsert->bind_param(
        'sissdssss',
        $orderId,
        $userId,
        $donorName,
        $donorEmail,
        $amountDecimal,
        $paymentType,
        $transactionStatus,
        $snapToken,
        $rawResponseJson
    );


    if (!$stmtInsert->execute()) {

        error_log(
            'process_donasi.php: Gagal INSERT transaksi. ' .
            'Order ID: ' . $orderId .
            ' | Error: ' . $stmtInsert->error
        );

        $stmtInsert->close();

        jsonResponse([
            'success' => false,
            'message' => 'Gagal menyimpan transaksi.'
        ], 500);
    }

    $stmtInsert->close();


    /*
    |--------------------------------------------------------------------------
    | 11.17 MIDTRANS PAYLOAD
    |--------------------------------------------------------------------------
    */

    $payload = [
        'transaction_details' => [
            'order_id' => $orderId,
            'gross_amount' => $amount
        ],

        'customer_details' => [
            'first_name' => $donorName,
            'email' => $donorEmail
        ],

        'item_details' => [
            [
                'id' => 'DONASI-IQRA',
                'price' => $amount,
                'quantity' => 1,
                'name' => 'Donasi & Dukungan Dakwah IQRA Society'
            ]
        ]
    ];


    /*
    |--------------------------------------------------------------------------
    | 11.18 ENCODE PAYLOAD
    |--------------------------------------------------------------------------
    */

    $payloadJson = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_INVALID_UTF8_SUBSTITUTE
    );


    if ($payloadJson === false) {

        error_log(
            'process_donasi.php: Gagal encode payload JSON. ' .
            json_last_error_msg() .
            ' | Order ID: ' . $orderId
        );

        updateDonationTransaction(
            $conn,
            $orderId,
            'failure'
        );

        jsonResponse([
            'success' => false,
            'message' => 'Gagal menyiapkan data transaksi.'
        ], 500);
    }


    /*
    |--------------------------------------------------------------------------
    | 11.19 CEK cURL
    |--------------------------------------------------------------------------
    */

    if (!function_exists('curl_init')) {

        error_log(
            'process_donasi.php: PHP cURL extension tidak tersedia.'
        );

        updateDonationTransaction(
            $conn,
            $orderId,
            'failure'
        );

        jsonResponse([
            'success' => false,
            'message' => 'Payment gateway tidak tersedia pada server.'
        ], 500);
    }


    /*
    |--------------------------------------------------------------------------
    | 11.20 CURL INIT
    |--------------------------------------------------------------------------
    */

    $ch = curl_init();


    if ($ch === false) {

        error_log(
            'process_donasi.php: curl_init() gagal. ' .
            'Order ID: ' . $orderId
        );

        updateDonationTransaction(
            $conn,
            $orderId,
            'failure'
        );

        jsonResponse([
            'success' => false,
            'message' => 'Gagal menghubungkan ke payment gateway.'
        ], 502);
    }


    /*
    |--------------------------------------------------------------------------
    | 11.21 CURL CONFIGURATION
    |--------------------------------------------------------------------------
    */

    curl_setopt_array(
        $ch,
        [
            CURLOPT_URL => $snapApiUrl,

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_POST => true,

            CURLOPT_POSTFIELDS => $payloadJson,

            CURLOPT_CONNECTTIMEOUT => 10,

            CURLOPT_TIMEOUT => 30,

            CURLOPT_SSL_VERIFYPEER => true,

            CURLOPT_SSL_VERIFYHOST => 2,

            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Basic ' .
                    base64_encode($serverKey . ':')
            ]
        ]
    );


    /*
    |--------------------------------------------------------------------------
    | 11.22 EXECUTE CURL
    |--------------------------------------------------------------------------
    */

    $response = curl_exec($ch);

    $httpCode = (int)curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    $curlErrorNumber = curl_errno($ch);

    $curlError = curl_error($ch);

    curl_close($ch);


    /*
    |--------------------------------------------------------------------------
    | 11.23 CURL ERROR
    |--------------------------------------------------------------------------
    */

    if (
        $response === false ||
        $curlErrorNumber !== 0
    ) {

        error_log(
            'process_donasi.php: cURL Error. ' .
            'Order ID: ' . $orderId .
            ' | Code: ' . $curlErrorNumber .
            ' | Error: ' . $curlError
        );

        updateDonationTransaction(
            $conn,
            $orderId,
            'failure'
        );

        jsonResponse([
            'success' => false,
            'message' => 'Gagal terhubung ke Midtrans Gateway.'
        ], 502);
    }


    /*
    |--------------------------------------------------------------------------
    | 11.24 EMPTY RESPONSE
    |--------------------------------------------------------------------------
    */

    if (trim((string)$response) === '') {

        error_log(
            'process_donasi.php: Response Midtrans kosong. ' .
            'Order ID: ' . $orderId .
            ' | HTTP Code: ' . $httpCode
        );

        updateDonationTransaction(
            $conn,
            $orderId,
            'failure',
            ''
        );

        jsonResponse([
            'success' => false,
            'message' => 'Payment gateway tidak memberikan response.'
        ], 502);
    }


    /*
    |--------------------------------------------------------------------------
    | 11.25 DECODE MIDTRANS RESPONSE
    |--------------------------------------------------------------------------
    */

    $responseData = json_decode(
        $response,
        true
    );


    if (
        json_last_error() !== JSON_ERROR_NONE ||
        !is_array($responseData)
    ) {

        error_log(
            'process_donasi.php: Response Midtrans bukan JSON valid. ' .
            'Order ID: ' . $orderId .
            ' | HTTP Code: ' . $httpCode .
            ' | JSON Error: ' . json_last_error_msg() .
            ' | Response: ' . $response
        );

        updateDonationTransaction(
            $conn,
            $orderId,
            'failure',
            $response
        );

        jsonResponse([
            'success' => false,
            'message' => 'Response dari payment gateway tidak valid.'
        ], 502);
    }


    /*
    |--------------------------------------------------------------------------
    | 11.26 VALIDATE HTTP STATUS + SNAP TOKEN
    |--------------------------------------------------------------------------
    */

    if (
        $httpCode !== 201 ||
        empty($responseData['token'])
    ) {

        $gatewayErrors = $responseData['error_messages'] ?? [];

        $gatewayError = 'Gagal membuat token transaksi.';


        if (
            is_array($gatewayErrors) &&
            !empty($gatewayErrors)
        ) {

            $gatewayError = implode(
                ', ',
                array_map(
                    'strval',
                    $gatewayErrors
                )
            );
        }


        $failedResponseJson = json_encode(
            $responseData,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_INVALID_UTF8_SUBSTITUTE
        );


        if ($failedResponseJson === false) {
            $failedResponseJson = (string)$response;
        }


        error_log(
            'process_donasi.php: Midtrans gagal membuat transaksi. ' .
            'Order ID: ' . $orderId .
            ' | HTTP Code: ' . $httpCode .
            ' | Error: ' . $gatewayError .
            ' | Response: ' . $failedResponseJson
        );


        updateDonationTransaction(
            $conn,
            $orderId,
            'failure',
            $failedResponseJson
        );


        jsonResponse([
            'success' => false,
            'message' => 'Gagal membuat transaksi pembayaran.'
        ], 502);
    }


    /*
    |--------------------------------------------------------------------------
    | 11.27 GET SNAP TOKEN
    |--------------------------------------------------------------------------
    */

    $snapToken = trim(
        (string)$responseData['token']
    );


    if ($snapToken === '') {

        error_log(
            'process_donasi.php: Snap Token kosong. ' .
            'Order ID: ' . $orderId
        );

        updateDonationTransaction(
            $conn,
            $orderId,
            'failure'
        );

        jsonResponse([
            'success' => false,
            'message' => 'Token pembayaran tidak valid.'
        ], 502);
    }


    /*
    |--------------------------------------------------------------------------
    | 11.28 ENCODE RAW RESPONSE
    |--------------------------------------------------------------------------
    */

    $rawResponseJson = json_encode(
        $responseData,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_INVALID_UTF8_SUBSTITUTE
    );


    if ($rawResponseJson === false) {

        error_log(
            'process_donasi.php: Gagal encode raw response Midtrans. ' .
            'Order ID: ' . $orderId .
            ' | Error: ' . json_last_error_msg()
        );

        /*
         * Midtrans sudah berhasil membuat transaksi.
         * Jangan mengubah status menjadi failure.
         */
        jsonResponse([
            'success' => false,
            'message' => 'Transaksi berhasil dibuat tetapi response gagal disimpan.'
        ], 500);
    }


    /*
    |--------------------------------------------------------------------------
    | 11.29 UPDATE SNAP TOKEN + RAW RESPONSE
    |--------------------------------------------------------------------------
    */

    $stmtUpdate = $conn->prepare(
        "
        UPDATE donasi_transactions
        SET
            snap_token = ?,
            raw_response = ?
        WHERE order_id = ?
        LIMIT 1
        "
    );


    if (!$stmtUpdate) {

        error_log(
            'process_donasi.php: Gagal prepare UPDATE Snap Token. ' .
            'Order ID: ' . $orderId .
            ' | Error: ' . $conn->error
        );

        /*
         * Penting:
         *
         * Midtrans sudah berhasil membuat transaksi.
         * Jadi jangan ubah status menjadi failure.
         *
         * Transaksi lokal tetap pending dan dapat direkonsiliasi
         * melalui webhook/notification Midtrans.
         */

        jsonResponse([
            'success' => false,
            'message' => 'Transaksi berhasil dibuat tetapi gagal menyimpan token pembayaran.'
        ], 500);
    }


    $stmtUpdate->bind_param(
        'sss',
        $snapToken,
        $rawResponseJson,
        $orderId
    );


    if (!$stmtUpdate->execute()) {

        error_log(
            'process_donasi.php: Gagal UPDATE Snap Token. ' .
            'Order ID: ' . $orderId .
            ' | Error: ' . $stmtUpdate->error
        );

        $stmtUpdate->close();

        /*
         * Jangan ubah status menjadi failure.
         * Midtrans sudah berhasil membuat transaksi.
         */

        jsonResponse([
            'success' => false,
            'message' => 'Transaksi berhasil dibuat tetapi gagal menyimpan token pembayaran.'
        ], 500);
    }


    $stmtUpdate->close();


    /*
    |--------------------------------------------------------------------------
    | 11.30 SUCCESS
    |--------------------------------------------------------------------------
    */

    jsonResponse([
        'success' => true,
        'order_id' => $orderId,
        'snap_token' => $snapToken
    ], 200);


} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | 12. GLOBAL EXCEPTION HANDLER
    |--------------------------------------------------------------------------
    */

    error_log(
        'process_donasi.php: UNHANDLED EXCEPTION. ' .
        'Message: ' . $e->getMessage() .
        ' | File: ' . $e->getFile() .
        ' | Line: ' . $e->getLine() .
        ' | Trace: ' . $e->getTraceAsString()
    );


    jsonResponse([
        'success' => false,
        'message' => 'Terjadi kesalahan internal pada server.'
    ], 500);
}