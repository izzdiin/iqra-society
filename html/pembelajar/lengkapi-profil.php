<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
check_access(['pembelajar']);

$user_id = (int)($_SESSION['user_id'] ?? 0);
if (!$user_id) { header('Location: /login.php'); exit; }

$err = '';
$ok = '';

// Ambil data saat ini
$stmt = $conn->prepare("SELECT username, email, fullname, no_hp FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');

    if ($fullname === '') {
        $err = 'Nama lengkap wajib diisi.';
    } elseif ($no_hp === '') {
        $err = 'Nomor HP wajib diisi.';
    } elseif (!preg_match('/^[0-9+\-\s]{9,16}$/', $no_hp)) {
        $err = 'Nomor HP tidak valid.';
    } else {
        $stmt = $conn->prepare("UPDATE users SET fullname = ?, no_hp = ? WHERE id = ?");
        $stmt->bind_param('ssi', $fullname, $no_hp, $user_id);
        if ($stmt->execute()) {
            $_SESSION['fullname'] = $fullname;
            if (function_exists('log_activity')) {
                log_activity('update', 'profile', 'Melengkapi profil setelah login Google', $user_id);
            }
            header('Location: /pembelajar/');
            exit;
        } else {
            $err = 'Gagal menyimpan. Coba lagi.';
        }
        $stmt->close();
        $user['fullname'] = $fullname;
        $user['no_hp'] = $no_hp;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lengkapi Profil - IQRA Society</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-emerald-50 min-h-screen flex items-center justify-center p-4">
<div class="bg-white rounded-2xl shadow-xl p-8 w-full max-w-md">
    <div class="text-center mb-6">
        <h1 class="text-2xl font-bold text-emerald-800">Lengkapi Profil</h1>
        <p class="text-gray-500 text-sm mt-1">Satu langkah lagi sebelum mulai belajar</p>
    </div>

    <?php if ($err): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-4 text-sm"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input type="text" value="<?= htmlspecialchars($user['email'] ?? '') ?>" disabled
                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 text-gray-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap <span class="text-red-500">*</span></label>
            <input type="text" name="fullname" required value="<?= htmlspecialchars($user['fullname'] ?? '') ?>"
                placeholder="Nama lengkap Anda"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nomor HP / WhatsApp <span class="text-red-500">*</span></label>
            <input type="tel" name="no_hp" required value="<?= htmlspecialchars($user['no_hp'] ?? '') ?>"
                placeholder="cth: 08123456789"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 outline-none">
        </div>
        <button type="submit"
            class="w-full bg-emerald-700 hover:bg-emerald-800 text-white font-semibold py-3 rounded-xl transition">
            Simpan & Mulai Belajar
        </button>
    </form>
</div>
</body>
</html>
