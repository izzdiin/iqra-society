<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/auth_middleware.php';
date_default_timezone_set('Asia/Jakarta');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$phpmailer_dir = __DIR__ . '/PHPMailer/';

if (
    !file_exists($phpmailer_dir . 'Exception.php') ||
    !file_exists($phpmailer_dir . 'PHPMailer.php') ||
    !file_exists($phpmailer_dir . 'SMTP.php')
) {
    die("<div style='font-family:sans-serif; padding:20px; background:#fee2e2; color:#991b1b; border-radius:10px; margin:20px;'>
            <strong>Fatal Error:</strong> File PHPMailer tidak ditemukan di folder <code>PHPMailer/</code>.
         </div>");
}

require_once $phpmailer_dir . 'Exception.php';
require_once $phpmailer_dir . 'PHPMailer.php';
require_once $phpmailer_dir . 'SMTP.php';

if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) {
    header('Location: /');
    exit();
}

$error = '';
$success = '';

function send_reset_email(string $to, string $subject, string $body, string &$error_msg = ''): bool {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(SMTP_USER, "Iqra' Society");
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
        return true;
    } catch (Exception $e) {
        $error_msg = $mail->ErrorInfo;
        error_log("PHPMailer Error: {$error_msg}");
        return false;
    }
}

function send_wa_reset(string $phone, string $caption, &$error_msg = ''): bool {
    $apiUrl = WA_BASE_URL . '/send/image';

    $postData = array(
        'phone'     => $phone,
        'caption'   => $caption,
        'image_url' => 'https://bill.netterhome.net/img/logo/logo.png'
    );

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL            => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING       => '',
        CURLOPT_MAXREDIRS      => 10,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => $postData,
        CURLOPT_HTTPHEADER     => getWaHeaders(),
    ));

    $response = curl_exec($curl);

    if (curl_errno($curl)) {
        $error_msg = curl_error($curl);
        curl_close($curl);
        return false;
    }

    curl_close($curl);
    $resData = json_decode($response, true);

    $statusMsg = $resData['status'] ?? $resData['message'] ?? '';

    if (
        (isset($resData['status']) && $resData['status'] === true) ||
        (is_string($statusMsg) && (stripos($statusMsg, 'sent') !== false || stripos($statusMsg, 'success') !== false))
    ) {
        return true; // Berhasil!
    } else {
        $error_msg = is_string($statusMsg) ? $statusMsg : 'Gagal mengirim pesan WA.';
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Token keamanan CSRF tidak valid.";
    } else {
        $identity = sanitize($_POST['identity'] ?? '');
        $method   = $_POST['method'] ?? 'email';

        if (empty($identity)) {
            $error = "Silakan masukkan Email atau Nomor HP Anda.";
        } else {
            if ($method === 'email') {
                $stmt = $conn->prepare("SELECT id, fullname, email, no_hp FROM users WHERE email = ? LIMIT 1");
                $stmt->bind_param("s", $identity);
            } else {
                $no_hp = preg_replace('/[^0-9]/', '', $identity);
                if (str_starts_with($no_hp, '0')) {
                    $no_hp = '62' . substr($no_hp, 1);
                }
                $stmt = $conn->prepare("SELECT id, fullname, email, no_hp FROM users WHERE no_hp = ? LIMIT 1");
                $stmt->bind_param("s", $no_hp);
            }

            $stmt->execute();
            $result = $stmt->get_result();

            if ($user = $result->fetch_assoc()) {
                // Generate Password Baru (8 Karakter)
                $new_plain_password = substr(bin2hex(random_bytes(4)), 0, 8);
                $hashed_password = password_hash($new_plain_password, PASSWORD_BCRYPT);

                // Update Password di Database
                $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update_stmt->bind_param("si", $hashed_password, $user['id']);

                if ($update_stmt->execute()) {
                    if ($method === 'email') {
                        $subject = "Reset Password - Iqra' Society";
                        $body  = "Halo <b>" . htmlspecialchars($user['fullname']) . "</b>,<br><br>";
                        $body .= "Permintaan reset password Anda berhasil.<br>";
                        $body .= "Password Baru Anda: <b>" . $new_plain_password . "</b><br><br>";
                        $body .= "Silakan masuk dan segera ubah kata sandi Anda di profil.<br><br>Salam,<br>Iqra' Society";

                        $smtp_error = '';
                        if (send_reset_email($user['email'], $subject, $body, $smtp_error)) {
                            $success = "Permintaan reset password berhasil, silakan cek Inbox atau Spam Kamu.";
                        } else {
                            $error = "Gagal mengirim email reset password. <br><small class='font-mono text-[10px]'>Detail SMTP: " . htmlspecialchars($smtp_error) . "</small>";
                        }
                    } else {
                        // OPSI WHATSAPP VIA API GATEWAY
                        $caption  = "Halo *" . $user['fullname'] . "*,\n\n";
                        $caption .= "Permintaan reset password Anda di *Iqra' Society* berhasil.\n\n";
                        $caption .= "Password Baru Anda: *" . $new_plain_password . "*\n\n";
                        $caption .= "Silakan login kembali dan ubah kata sandi Anda di menu profil.";

                        $wa_error = '';
                        if (send_wa_reset($user['no_hp'], $caption, $wa_error)) {
                            $success = "Permintaan reset password berhasil, silakan cek WhatsApp Kamu.";
                        } else {
                            $error = "Gagal mengirim pesan WhatsApp. <br><small class='font-mono text-[10px]'>Detail API WA: " . htmlspecialchars($wa_error) . "</small>";
                        }
                    }
                } else {
                    $error = "Gagal memperbarui password di database.";
                }
                $update_stmt->close();
            } else {
                $error = "Data pengguna tidak ditemukan.";
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - Iqra' Society</title>
    <link rel="icon" type="image/x-icon" href="/asset/img/favicon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/asset/img/favicon.png">
    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
    <style>body{font-family:sans-serif;background-color:#f9fafb}</style>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-white rounded-3xl shadow-xl p-8 border border-gray-100" x-data="{ method: 'email', loading: false }">
        <div class="text-center mb-6">
            <a href="/" class="inline-flex items-center gap-3 mb-2 group">
                <img src="/asset/img/logo_kitab.png" alt="Iqra' Society Logo" class="h-12 w-auto object-contain">
                
                <div class="flex flex-col font-['Zilla_Slab'] text-left leading-tight">
                    <span class="text-xl font-bold text-emerald-900 tracking-wide uppercase leading-none">IQRA'</span>
                    <span class="text-base font-semibold text-lime-500 tracking-normal leading-none mt-0.5">Society</span>
                </div>
            </a>
            <p class="text-xs text-gray-500 mt-1">Pilih metode pemulihan kata sandi Anda</p>
        </div>

        <?php if ($error): ?>
            <div class="mb-4 bg-red-50 border border-red-200 text-red-700 p-3 rounded-xl text-xs leading-relaxed">
                <i class="fa-solid fa-circle-exclamation mr-1"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl text-xs leading-relaxed flex items-start gap-2.5">
                <i class="fa-solid fa-circle-check text-base shrink-0 mt-0.5"></i>
                <div><?= $success ?></div>
            </div>
        <?php endif; ?>

        <form action="" method="POST" @submit="loading = true" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

            <div>
                <label class="block text-xs font-bold text-gray-700 mb-2">Kirim Password Baru Via:</label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="cursor-pointer">
                        <input type="radio" name="method" value="email" x-model="method" class="peer hidden">
                        <div class="text-center p-2.5 rounded-xl border border-gray-200 text-xs font-bold text-gray-600 peer-checked:border-emerald-600 peer-checked:bg-emerald-50 peer-checked:text-emerald-800 transition">
                            <i class="fa-solid fa-envelope mr-1"></i> Email
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="method" value="whatsapp" x-model="method" class="peer hidden">
                        <div class="text-center p-2.5 rounded-xl border border-gray-200 text-xs font-bold text-gray-600 peer-checked:border-emerald-600 peer-checked:bg-emerald-50 peer-checked:text-emerald-800 transition">
                            <i class="fa-brands fa-whatsapp mr-1"></i> WhatsApp
                        </div>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1" x-text="method === 'email' ? 'Email Terdaftar' : 'Nomor WhatsApp'"></label>
                <input type="text" name="identity" :placeholder="method === 'email' ? 'contoh@email.com' : '6281234567890'" required class="w-full text-xs px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-600">
            </div>

            <button type="submit" :disabled="loading" class="w-full bg-emerald-800 hover:bg-emerald-900 text-white font-bold text-xs py-3.5 rounded-xl shadow-md transition flex items-center justify-center gap-2">
                <span x-show="!loading"><i class="fa-solid fa-paper-plane mr-1"></i> Reset Password</span>
                <span x-show="loading" style="display: none;"><i class="fa-solid fa-circle-notch fa-spin"></i> Memproses...</span>
            </button>
        </form>

        <p class="text-center text-xs text-gray-500 mt-6">
            Ingat password Anda? <a href="login" class="text-emerald-800 font-bold hover:underline">Kembali ke Login</a>
        </p>
    </div>
</body>
</html>