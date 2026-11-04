<?php
require_once __DIR__ . '/includes/auth_middleware.php';

if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) {
    header('Location: /');
    exit();
}

/*
|--------------------------------------------------------------------------
| Cloudflare Turnstile
|--------------------------------------------------------------------------
*/

$captcha_site_key = '';
$captcha_secret_key = '';
$captcha_active = false;

$stmt_captcha = $conn->prepare("
    SELECT site_key, secret_key, is_active
    FROM captcha_settings
    WHERE provider = 'cloudflare_turnstile'
      AND is_active = 1
    LIMIT 1
");

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

/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$errors = [];
$success = '';

$max_attempts = 5;
$decay_seconds = 900;

/*
|--------------------------------------------------------------------------
| Rate Limit
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['reg_attempts'])) {
    $_SESSION['reg_attempts'] = 0;
    $_SESSION['reg_last_attempt'] = time();
}

if (time() - $_SESSION['reg_last_attempt'] > $decay_seconds) {
    $_SESSION['reg_attempts'] = 0;
    $_SESSION['reg_last_attempt'] = time();
}

/*
|--------------------------------------------------------------------------
| Username Normalizer
|--------------------------------------------------------------------------
*/

function normalize_username(string $username): string
{
    $username = trim(strtolower($username));
    $username = preg_replace('/[^a-z0-9]+/', '-', $username);
    $username = trim($username, '-');
    $username = substr($username, 0, 50);
    $username = rtrim($username, '-');

    return $username;
}

/*
|--------------------------------------------------------------------------
| POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($_SESSION['reg_attempts'] >= $max_attempts) {
        $errors[] = "Terlalu banyak percobaan registrasi. Silakan coba lagi dalam 15 menit.";
    } elseif (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = "Token keamanan CSRF tidak valid.";
    } else {

        if ($captcha_active) {
            $cf_response = $_POST['cf-turnstile-response'] ?? '';
            $remote_ip = $_SERVER['REMOTE_ADDR'] ?? '';

            if (empty($cf_response)) {
                $errors[] = "Silakan selesaikan verifikasi Captcha.";
            } else {
                $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');

                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => http_build_query([
                        'secret'   => $captcha_secret_key,
                        'response' => $cf_response,
                        'remoteip' => $remote_ip
                    ]),
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_CONNECTTIMEOUT => 5
                ]);

                $api_response = curl_exec($ch);

                if ($api_response === false) {
                    $errors[] = "Tidak dapat memverifikasi Captcha. Silakan coba lagi.";
                }

                curl_close($ch);

                if (empty($errors)) {
                    $result = json_decode($api_response, true);

                    if (!($result['success'] ?? false)) {
                        $errors[] = "Verifikasi Captcha gagal atau kadaluwarsa. Silakan coba lagi.";
                    }
                }
            }
        }

        if (empty($errors)) {
            $_SESSION['reg_attempts']++;
            $_SESSION['reg_last_attempt'] = time();

            $fullname = trim($_POST['fullname'] ?? '');
            $username = normalize_username($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $no_hp = $_POST['no_hp'] ?? '';
            $password = $_POST['password'] ?? '';
            $role = 'pembelajar';

            $no_hp = preg_replace('/[^0-9]/', '', $no_hp);

            if (substr($no_hp, 0, 1) === '0') {
                $no_hp = '62' . substr($no_hp, 1);
            }

            if (
                empty($fullname) ||
                empty($username) ||
                empty($email) ||
                empty($no_hp) ||
                empty($password)
            ) {
                $errors[] = "Semua bidang wajib diisi.";
            }

            if (!empty($username)) {
                if (strlen($username) < 3) {
                    $errors[] = "Username minimal 3 karakter.";
                }

                if (strlen($username) > 50) {
                    $errors[] = "Username maksimal 50 karakter.";
                }

                if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{1,48}[a-z0-9])?$/', $username)) {
                    $errors[] = "Username hanya boleh menggunakan huruf kecil, angka, dan tanda hubung (-), serta tidak boleh diawali atau diakhiri tanda hubung.";
                }
            }

            if (empty($errors) && !empty($username)) {
                $stmt_reserved = $conn->prepare("
                    SELECT username
                    FROM reserved_usernames
                    WHERE username = ?
                    LIMIT 1
                ");

                if ($stmt_reserved) {
                    $stmt_reserved->bind_param("s", $username);
                    $stmt_reserved->execute();
                    $stmt_reserved->store_result();

                    if ($stmt_reserved->num_rows > 0) {
                        $errors[] = "Username tersebut tidak dapat digunakan.";
                    }

                    $stmt_reserved->close();
                } else {
                    $errors[] = "Terjadi kesalahan sistem saat memeriksa username.";
                }
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Format email tidak valid.";
            }

            if (strlen($no_hp) < 10 || strlen($no_hp) > 15) {
                $errors[] = "Nomor HP tidak valid (minimal 10 angka dan maksimal 15 angka).";
            }

            if (strlen($password) < 8) {
                $errors[] = "Password minimal 8 karakter.";
            } elseif (!preg_match('/[A-Z]/', $password)) {
                $errors[] = "Password harus mengandung minimal satu huruf kapital.";
            } elseif (!preg_match('/[a-z]/', $password)) {
                $errors[] = "Password harus mengandung minimal satu huruf kecil.";
            } elseif (!preg_match('/[0-9]/', $password)) {
                $errors[] = "Password harus mengandung minimal satu angka.";
            }

            if (empty($errors)) {
                $stmt = $conn->prepare("
                    SELECT id, username, email, no_hp
                    FROM users
                    WHERE username = ?
                       OR email = ?
                       OR no_hp = ?
                    LIMIT 1
                ");

                if (!$stmt) {
                    $errors[] = "Terjadi kesalahan sistem. Silakan coba lagi.";
                } else {
                    $stmt->bind_param("sss", $username, $email, $no_hp);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($existing = $result->fetch_assoc()) {
                        if (isset($existing['username']) && strtolower($existing['username']) === $username) {
                            $errors[] = "Username tersebut sudah digunakan.";
                        }
                        if (isset($existing['email']) && strtolower($existing['email']) === strtolower($email)) {
                            $errors[] = "Email tersebut sudah terdaftar.";
                        }
                        if (isset($existing['no_hp']) && $existing['no_hp'] === $no_hp) {
                            $errors[] = "Nomor HP tersebut sudah terdaftar.";
                        }
                    }

                    $stmt->close();
                }
            }

            if (empty($errors)) {
                $hashed_password = password_hash($password, PASSWORD_ARGON2ID);

                if (!$hashed_password) {
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                }

                if (!$hashed_password) {
                    $errors[] = "Gagal mengamankan password. Silakan coba lagi.";
                } else {
                    $stmt = $conn->prepare("
                        INSERT INTO users (username, fullname, email, no_hp, password, role)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");

                    if (!$stmt) {
                        $errors[] = "Terjadi kesalahan sistem. Silakan coba lagi.";
                    } else {
                        $stmt->bind_param("ssssss", $username, $fullname, $email, $no_hp, $hashed_password, $role);

                        if ($stmt->execute()) {
                            unset($_SESSION['reg_attempts']);
                            $success = "Pendaftaran berhasil! Silakan masuk.";
                        } else {
                            if ($stmt->errno === 1062) {
                                $errors[] = "Username, email, atau nomor HP sudah digunakan.";
                            } else {
                                $errors[] = "Terjadi kesalahan sistem. Coba lagi.";
                            }
                        }

                        $stmt->close();
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
    <title>Daftar Akun - IQRA' Society</title>
    <link rel="icon" type="image/x-icon" href="/asset/img/favicon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/asset/img/favicon.png">
    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Zilla+Slab:wght@600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
    
    <?php if ($captcha_active): ?>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php endif; ?>

    <style>
        body {
            font-family: sans-serif;
            background-color: #f9fafb;
        }
    </style>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

<div class="w-full max-w-md bg-white rounded-3xl shadow-xl p-8 border border-gray-100"
     x-data="{
         showPass: false,
         loading: false,
         username: '<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>'
     }">

    <!-- Header -->
    <div class="text-center mb-6">
        <a href="/" class="inline-flex items-center gap-3 mb-2 group">
            <img src="/asset/img/logo_kitab.png" alt="Iqra' Society Logo" class="h-12 w-auto object-contain">
            <div class="flex flex-col font-['Zilla_Slab'] text-left leading-tight">
                <span class="text-xl font-bold text-emerald-900 tracking-wide uppercase leading-none">IQRA'</span>
                <span class="text-base font-semibold text-lime-500 tracking-normal leading-none mt-0.5">Society</span>
            </div>
        </a>
        <p class="text-xs text-gray-500 mt-1">Gabung bersama komunitas IQRA' Society</p>
    </div>

    <!-- Error -->
    <?php if (!empty($errors)): ?>
        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 p-3 rounded-xl text-xs space-y-1">
            <?php foreach ($errors as $err): ?>
                <p><i class="fa-solid fa-circle-exclamation mr-1"></i><?= htmlspecialchars($err) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Success -->
    <?php if ($success): ?>
        <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 p-3 rounded-xl text-xs font-semibold">
            <i class="fa-solid fa-circle-check mr-1"></i>
            <?= htmlspecialchars($success) ?>
            <a href="login" class="underline ml-1">Masuk sekarang</a>
        </div>
    <?php endif; ?>

    <!-- Form -->
    <form action="" method="POST" @submit="loading = true" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

        <!-- Fullname -->
        <div>
            <label class="block text-xs font-bold text-gray-700 mb-1">Nama Lengkap</label>
            <input type="text" name="fullname" value="<?= htmlspecialchars($_POST['fullname'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required autocomplete="name" class="w-full text-xs px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-600">
        </div>

        <!-- Username -->
        <div>
            <label class="block text-xs font-bold text-gray-700 mb-1">Username</label>
            <div class="relative">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-xs">@</span>
                <input type="text" name="username" x-model="username"
                       @input="username = username.toLowerCase().replace(/[^a-z0-9-]/g, '').replace(/^-+/, '').replace(/-+$/, '')"
                       minlength="3" maxlength="50" pattern="[a-z0-9][a-z0-9-]{1,48}[a-z0-9]"
                       autocomplete="username" autocapitalize="none" spellcheck="false" required
                       placeholder="username"
                       class="w-full text-xs pl-8 pr-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-600">
            </div>
            <p class="text-[10px] text-gray-400 mt-0.5">3–50 karakter. Gunakan huruf kecil, angka, dan tanda hubung (-).</p>
        </div>

        <!-- Email -->
        <div>
            <label class="block text-xs font-bold text-gray-700 mb-1">Alamat Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required autocomplete="email" class="w-full text-xs px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-600">
        </div>

        <!-- Phone -->
        <div>
            <label class="block text-xs font-bold text-gray-700 mb-1">Nomor WhatsApp / HP</label>
            <input type="tel" name="no_hp" placeholder="Contoh: 6281234567890" value="<?= htmlspecialchars($_POST['no_hp'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required autocomplete="tel" class="w-full text-xs px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-600">
        </div>

        <!-- Password -->
        <div>
            <label class="block text-xs font-bold text-gray-700 mb-1">Kata Sandi</label>
            <div class="relative">
                <input :type="showPass ? 'text' : 'password'" name="password" required autocomplete="new-password" class="w-full text-xs px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                <button type="button" @click="showPass = !showPass" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs">
                    <i class="fa-solid" :class="showPass ? 'fa-eye-slash' : 'fa-eye'"></i>
                </button>
            </div>
            <p class="text-[10px] text-gray-400 mt-1">Minimal 8 karakter, kombinasi huruf besar, huruf kecil, & angka.</p>
        </div>

        <!-- Cloudflare Turnstile -->
        <?php if ($captcha_active): ?>
            <div class="flex justify-center my-3">
                <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars($captcha_site_key, ENT_QUOTES, 'UTF-8') ?>" data-theme="light"></div>
            </div>
        <?php endif; ?>

        <!-- Submit -->
        <button type="submit" :disabled="loading" class="w-full bg-emerald-800 hover:bg-emerald-900 text-white font-bold text-xs py-3.5 rounded-xl shadow-md transition flex items-center justify-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed">
            <span x-show="!loading">
                <i class="fa-solid fa-user-plus mr-1"></i> Daftar Sekarang
            </span>
            <span x-show="loading" style="display: none;">
                <i class="fa-solid fa-circle-notch fa-spin"></i> Memproses...
            </span>
        </button>
    </form>

    <!-- Login -->
    <p class="text-center text-xs text-gray-500 mt-6">
        Sudah punya akun?
        <a href="login" class="text-emerald-800 font-bold hover:underline">Masuk disini</a>
    </p>
</div>

</body>
</html>