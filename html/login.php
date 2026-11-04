<?php
require_once __DIR__ . '/includes/auth_middleware.php';

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) {
    header('Location: /');
    exit();
}

$captcha_site_key = '';
$captcha_secret_key = '';
$captcha_active = false;

$stmt_captcha = $conn->prepare("SELECT site_key, secret_key, is_active FROM captcha_settings WHERE provider = 'cloudflare_turnstile' AND is_active = 1 LIMIT 1");
if ($stmt_captcha) {
    $stmt_captcha->execute();
    $res_captcha = $stmt_captcha->get_result();
    if ($row = $res_captcha->fetch_assoc()) {
        $captcha_site_key = $row['site_key'];
        $captcha_secret_key = $row['secret_key'];
        $captcha_active = true;
    }
    $stmt_captcha->close();
}

$max_attempts = 3;   // Maksimal percobaan salah
$lockout_time = 60;  // Durasi blokir (60 detik)

$ip_address   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$user_agent   = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);

$stmt_check = $conn->prepare("SELECT attempts, TIMESTAMPDIFF(SECOND, last_attempt, NOW()) AS seconds_passed FROM login_attempts WHERE ip_address = ? AND user_agent = ? LIMIT 1");
$stmt_check->bind_param("ss", $ip_address, $user_agent);
$stmt_check->execute();
$res_check = $stmt_check->get_result();

$db_attempts    = 0;
$seconds_passed = 0;

if ($row = $res_check->fetch_assoc()) {
    $db_attempts    = (int)$row['attempts'];
    $seconds_passed = (int)$row['seconds_passed'];
}
$stmt_check->close();

$remaining_time = $lockout_time - $seconds_passed;

$is_locked_out = ($db_attempts >= $max_attempts) && ($remaining_time > 0);

if ($db_attempts >= $max_attempts && $remaining_time <= 0) {
    $stmt_reset = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND user_agent = ?");
    $stmt_reset->bind_param("ss", $ip_address, $user_agent);
    $stmt_reset->execute();
    $stmt_reset->close();

    $is_locked_out  = false;
    $remaining_time = 0;
    $db_attempts    = 0;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($is_locked_out) {
        $error = "Terlalu banyak percobaan gagal. Silakan tunggu hingga waktu habis.";
    } elseif (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Token keamanan CSRF tidak valid.";
    } else {

        if ($captcha_active) {
            $cf_response = $_POST['cf-turnstile-response'] ?? '';

            if (empty($cf_response)) {
                $error = "Silakan selesaikan verifikasi Captcha.";
            } else {
                $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => http_build_query([
                        'secret'   => $captcha_secret_key,
                        'response' => $cf_response,
                        'remoteip' => $ip_address
                    ])
                ]);
                $api_response = curl_exec($ch);
                curl_close($ch);

                $result = json_decode($api_response, true);
                if (!($result['success'] ?? false)) {
                    $error = "Verifikasi Captcha gagal atau kadaluwarsa. Silakan coba lagi.";
                }
            }
        }

        if (empty($error)) {
            $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
            $password = $_POST['password'] ?? '';

            if (empty($email) && empty($password)) {
                $error = "Email dan kata sandi wajib diisi.";
            } elseif (empty($email)) {
                $error = "Email wajib diisi.";
            } elseif (empty($password)) {
                $error = "Kata sandi wajib diisi.";
            } else {
                $stmt = $conn->prepare("SELECT id, username, fullname, password, role, avatar FROM users WHERE email = ? LIMIT 1");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($user = $result->fetch_assoc()) {
                    if (password_verify($password, $user['password'])) {
                        
                        $stmt_del = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND user_agent = ?");
                        $stmt_del->bind_param("ss", $ip_address, $user_agent);
                        $stmt_del->execute();
                        $stmt_del->close();

                        session_regenerate_id(true);
                        
                        $_SESSION['is_logged_in'] = true;
                        $_SESSION['user_id']      = $user['id'];
                        $_SESSION['username']     = $user['username'];
                        $_SESSION['fullname']     = $user['fullname'];
                        $_SESSION['email']        = $email;
                        $_SESSION['role']         = $user['role'];
                        $_SESSION['avatar']       = $user['avatar'];
                        $_SESSION['user_agent']   = $user_agent;
                        $_SESSION['ip_address']   = $ip_address;
                        
                        session_write_close();

                        switch ($user['role']) {
                            case 'super_administrator':
                            case 'administrator':
                                header('Location: admin/');
                                break;
                            case 'pengajar':
                                header('Location: pengajar/');
                                break;
                            case 'donatur':
                                header('Location: donatur/');
                                break;
                            case 'pembelajar':
                                header('Location: pembelajar/');
                                break;
                            default:
                                header('Location: /');
                                break;
                        }
                        exit();

                    } else {
                        $error_type = 'password_salah';
                    }
                } else {
                    $error_type = 'email_salah';
                }
                $stmt->close();

                if (isset($error_type)) {
                    $stmt_log = $conn->prepare("
                        INSERT INTO login_attempts (ip_address, user_agent, attempts, last_attempt) 
                        VALUES (?, ?, 1, NOW()) 
                        ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = NOW()
                    ");
                    $stmt_log->bind_param("ss", $ip_address, $user_agent);
                    $stmt_log->execute();
                    $stmt_log->close();

                    $db_attempts += 1;
                    $sisa_kesempatan = $max_attempts - $db_attempts;

                    if ($sisa_kesempatan > 0) {
                        $pesan_spesifik = ($error_type === 'password_salah') ? "Kata sandi salah." : "Email tidak terdaftar.";
                        $error = "{$pesan_spesifik} Sisa percobaan: {$sisa_kesempatan}x.";
                    } else {
                        $is_locked_out  = true;
                        $remaining_time = $lockout_time;
                        $error = "Terlalu banyak percobaan gagal. Silakan tunggu 1 menit.";
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - Iqra' Society</title>
    <link rel="icon" type="image/x-icon" href="/asset/img/favicon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/asset/img/favicon.png">
    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
    
    <!-- Script API Cloudflare Turnstile -->
    <?php if ($captcha_active): ?>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php endif; ?>

    <style>body{font-family:sans-serif;background-color:#f9fafb}</style>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>

<body class="min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-sm bg-white rounded-3xl shadow-xl p-8 border border-gray-100" 
         x-data="{ 
            showPass: false, 
            loading: false,
            timeLeft: <?= max(0, (int)$remaining_time) ?>,
            isLocked: <?= $is_locked_out ? 'true' : 'false' ?>,
            timer: null,
            startCountdown() {
                if (this.isLocked && this.timeLeft > 0) {
                    this.timer = setInterval(() => {
                        this.timeLeft--;
                        if (this.timeLeft <= 0) {
                            clearInterval(this.timer);
                            this.isLocked = false;
                        }
                    }, 1000);
                }
            }
         }"
         x-init="startCountdown()">

        <div class="text-center mb-6">
            <a href="/" class="inline-flex items-center gap-3 mb-2 group">
                <img src="/asset/img/logo_kitab.png" alt="Iqra' Society Logo" class="h-12 w-auto object-contain">        
                <div class="flex flex-col font-['Zilla_Slab'] text-left leading-tight">
                    <span class="text-xl font-bold text-emerald-900 tracking-wide uppercase leading-none">IQRA'</span>
                    <span class="text-base font-semibold text-lime-500 tracking-normal leading-none mt-0.5">Society</span>
                </div>
            </a>
            <p class="text-xs text-gray-500 mt-1">Masuk ke akun Iqra' Society Anda</p>
        </div>

        <!-- ALERT JIKA TERKUNCI -->
        <div x-show="isLocked" class="mb-4 bg-amber-50 border border-amber-200 text-amber-800 p-3 rounded-xl text-xs flex items-center gap-2">
            <i class="fa-solid fa-clock-rotate-left shrink-0 text-amber-600 text-sm"></i>
            <div>
                <p class="font-bold">Akses Login Dibatasi</p>
                <p>Coba lagi dalam <span class="font-mono font-bold text-red-600" x-text="timeLeft"></span> detik.</p>
            </div>
        </div>

        <!-- ALERT ERROR REGULER -->
        <?php if ($error && !$is_locked_out): ?>
            <div class="mb-4 bg-red-50 border border-red-200 text-red-700 p-3 rounded-xl text-xs flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation shrink-0"></i> 
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form action="" method="POST" @submit="loading = true" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1">Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required :disabled="isLocked" class="w-full text-xs px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-600 disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed">
            </div>

            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-xs font-bold text-gray-700">Kata Sandi</label>
                    <a href="forgot-password" class="text-[11px] font-bold text-emerald-800 hover:underline">Lupa Password?</a>
                </div>
                <div class="relative">
                    <input :type="showPass ? 'text' : 'password'" name="password" required :disabled="isLocked" class="w-full text-xs px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-600 disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed">
                    <button type="button" @click="showPass = !showPass" :disabled="isLocked" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs">
                        <i class="fa-solid" :class="showPass ? 'fa-eye-slash' : 'fa-eye'"></i>
                    </button>
                </div>
            </div>

            <!-- RENDER CLOUDFLARE TURNSTILE CAPTCHA -->
            <?php if ($captcha_active): ?>
                <div class="flex justify-center my-3">
                    <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars($captcha_site_key) ?>" data-theme="light"></div>
                </div>
            <?php endif; ?>

            <button type="submit" :disabled="loading || isLocked" class="w-full bg-emerald-800 hover:bg-emerald-900 text-white font-bold text-xs py-3.5 rounded-xl shadow-md transition flex items-center justify-center gap-2 disabled:bg-gray-300 disabled:cursor-not-allowed">
                <span x-show="!loading && !isLocked"><i class="fa-solid fa-right-to-bracket mr-1"></i> Masuk</span>
                <span x-show="loading && !isLocked" style="display: none;"><i class="fa-solid fa-circle-notch fa-spin"></i> Memproses...</span>
                <span x-show="isLocked" style="display: none;"><i class="fa-solid fa-lock mr-1"></i> Terkunci (<span x-text="timeLeft"></span>s)</span>
            </button>
        </form>

        <div class="flex items-center gap-3 my-5">
            <div class="flex-1 border-t border-gray-200"></div>
            <span class="text-[10px] font-bold text-gray-400 uppercase">atau</span>
            <div class="flex-1 border-t border-gray-200"></div>
        </div>

        <a href="/auth/google_login.php" class="w-full bg-white hover:bg-gray-50 border border-gray-300 text-gray-700 font-bold text-xs py-3.5 rounded-xl shadow-sm transition flex items-center justify-center gap-2">
            <svg class="w-4 h-4" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.1c-.22-.66-.35-1.36-.35-2.1s.13-1.44.35-2.1V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>
            Masuk dengan Google
        </a>

        <p class="text-center text-xs text-gray-500 mt-6">
            Belum punya akun? <a href="register" class="text-emerald-800 font-bold hover:underline">Daftar disini</a>
        </p>
    </div>

</body>
</html>