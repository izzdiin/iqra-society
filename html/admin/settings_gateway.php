<?php
require_once __DIR__ . '/../includes/auth_middleware.php';

check_access(['super_administrator', 'super_admin']);

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Token keamanan CSRF tidak valid.";
    } else {
        $action = $_POST['action'] ?? '';
        $settings = [];

        if ($action === 'update_midtrans') {
            $settings = [
                'midtrans_merchant_id' => sanitize($_POST['midtrans_merchant_id'] ?? ''),
                'midtrans_client_key'  => sanitize($_POST['midtrans_client_key'] ?? ''),
                'midtrans_server_key'  => sanitize($_POST['midtrans_server_key'] ?? ''),
            ];
            $success = "Kredensial Midtrans berhasil disimpan!";
        } elseif ($action === 'update_tripay') {
            $settings = [
                'tripay_merchant_code' => sanitize($_POST['tripay_merchant_code'] ?? ''),
                'tripay_api_key'       => sanitize($_POST['tripay_api_key'] ?? ''),
                'tripay_private_key'   => sanitize($_POST['tripay_private_key'] ?? ''),
            ];
            $success = "Kredensial TriPay berhasil disimpan!";
        } elseif ($action === 'set_active_gateway') {
            $gw = sanitize($_POST['active_gateway'] ?? '');
            if (in_array($gw, ['midtrans', 'tripay'], true)) {
                $settings = ['active_gateway' => $gw];
                $success = "Payment gateway aktif: <strong>" . ucfirst($gw) . "</strong>. Seluruh transaksi kini menggunakan " . ucfirst($gw) . ".";
            } else {
                $error = "Pilihan gateway tidak valid.";
            }
        }

        if (!empty($settings)) {
            foreach ($settings as $key => $val) {
                $stmt = $conn->prepare("INSERT INTO gateway_settings (key_name, key_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");
                $stmt->bind_param("ss", $key, $val);
                $stmt->execute();
                $stmt->close();
            }
        }

        // === TEST KONEKSI MIDTRANS ===
        if ($action === 'test_midtrans') {
            $server_key = trim($_POST['midtrans_server_key'] ?? '');
            $is_prod = (strpos($server_key, 'SB-Mid-server') === 0) ? false : true;
            $url = $is_prod ? 'https://app.midtrans.com/snap/v1/transactions' : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
            $test_ref = 'TEST-' . time();
            $payload = json_encode([
                'transaction_details' => ['order_id' => $test_ref, 'gross_amount' => 10000],
                'customer_details' => ['first_name' => 'Test', 'email' => 'test@iqrasociety.id']
            ]);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json', 'Authorization: Basic ' . base64_encode($server_key . ':')],
                CURLOPT_TIMEOUT => 15
            ]);
            $resp = curl_exec($ch); $http = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
            $data = json_decode($resp, true);
            if ($http === 201 && isset($data['token'])) {
                $success = "Tes Midtrans BERHASIL! Token: <code class='bg-gray-100 px-1 rounded'>" . htmlspecialchars(substr($data['token'], 0, 20)) . "...</code> (Order: $test_ref, Rp 10.000)";
            } else {
                $err_msg = $data['error_messages'][0] ?? $resp;
                $error = "Tes Midtrans GAGAL (HTTP $http): " . htmlspecialchars(substr($err_msg, 0, 200));
            }
        }

        // === TEST KONEKSI TRIPAY ===
        if ($action === 'test_tripay') {
            $merchant_code = trim($_POST['tripay_merchant_code'] ?? '');
            $api_key = trim($_POST['tripay_api_key'] ?? '');
            $is_prod = (strpos($api_key, 'DEV-') === 0) ? false : true;
            $url = $is_prod ? 'https://tripay.co.id/api/merchant/payment-channel' : 'https://tripay.co.id/api-sandbox/merchant/payment-channel';
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $api_key],
                CURLOPT_TIMEOUT => 15
            ]);
            $resp = curl_exec($ch); $http = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
            $data = json_decode($resp, true);
            if ($http === 200 && isset($data['success']) && $data['success'] === true) {
                $count = count($data['data'] ?? []);
                $success = "Tes TriPay BERHASIL! $count channel pembayaran aktif (Merchant: " . htmlspecialchars($merchant_code) . ")";
            } else {
                $err_msg = $data['message'] ?? $resp;
                $error = "Tes TriPay GAGAL (HTTP $http): " . htmlspecialchars(substr($err_msg, 0, 200));
            }
        }

        // === SYNC CHANNEL TRIPAY ===
        if ($action === 'sync_tripay_channels') {
            $api_key = trim($_POST['tripay_api_key'] ?? '');
            $is_prod = (strpos($api_key, 'DEV-') === 0) ? false : true;
            $url = $is_prod ? 'https://tripay.co.id/api/merchant/payment-channel' : 'https://tripay.co.id/api-sandbox/merchant/payment-channel';
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $api_key],
                CURLOPT_TIMEOUT => 20
            ]);
            $resp = curl_exec($ch); $http = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
            $data = json_decode($resp, true);
            if ($http === 200 && isset($data['success']) && $data['success'] === true) {
                $st = $conn->prepare("INSERT INTO tripay_channels (code, `group`, name, icon_url, fee_flat, fee_percent, min_amount, max_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE `group`=VALUES(`group`), name=VALUES(name), icon_url=VALUES(icon_url), fee_flat=VALUES(fee_flat), fee_percent=VALUES(fee_percent), min_amount=VALUES(min_amount), max_amount=VALUES(max_amount)");
                $n = 0;
                foreach (($data['data'] ?? []) as $chd) {
                    $code = $chd['code'] ?? ''; if (!$code) continue;
                    $grp = $chd['group'] ?? ''; $nm = $chd['name'] ?? $code;
                    $icon = $chd['icon_url'] ?? '';
                    $ff = (float)($chd['fee_merchant']['flat'] ?? 0);
                    $fp = (float)($chd['fee_merchant']['percent'] ?? 0);
                    $mn = (int)($chd['minimum_amount'] ?? 0);
                    $mx = (int)($chd['maximum_amount'] ?? 0);
                    $st->bind_param("ssssddii", $code, $grp, $nm, $icon, $ff, $fp, $mn, $mx);
                    if ($st->execute()) $n++;
                }
                $st->close();
                $success = "Sinkronisasi BERHASIL! $n channel pembayaran diperbarui dari TriPay.";
                log_activity('update', 'payment_gateway', "Sinkronisasi $n channel TriPay");
            } else {
                $err_msg = $data['message'] ?? $resp;
                $error = "Sinkronisasi GAGAL (HTTP $http): " . htmlspecialchars(substr($err_msg, 0, 200));
            }
        }

        // === TOGGLE CHANNEL TRIPAY ===
        if ($action === 'toggle_tripay_channel') {
            $code = trim($_POST['code'] ?? '');
            $act = isset($_POST['active']) && $_POST['active'] === '1' ? 1 : 0;
            $st = $conn->prepare("UPDATE tripay_channels SET active = ? WHERE code = ?");
            $st->bind_param("is", $act, $code);
            if ($st->execute() && $st->affected_rows > 0) {
                $success = "Channel $code " . ($act ? "DIAKTIFKAN" : "DINONAKTIFKAN") . ".";
                log_activity('update', 'payment_gateway', "Channel TriPay $code " . ($act ? 'aktif' : 'nonaktif'));
            } else {
                $error = "Gagal mengubah status channel.";
            }
            $st->close();
        }
    }
}

$keys_data = [];
$res = $conn->query("SELECT key_name, key_value FROM gateway_settings");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $keys_data[$r['key_name']] = $r['key_value'];
    }
}
$active_gateway = $keys_data['active_gateway'] ?? 'midtrans';
$tripay_channels = [];
$rch = $conn->query("SELECT * FROM tripay_channels ORDER BY `group`, name");
if ($rch) while ($r = $rch->fetch_assoc()) $tripay_channels[] = $r;

$pending_count_query = $conn->query("SELECT COUNT(id) FROM section_kelas WHERE status = 'pending'");
$pending_notifications = $pending_count_query ? (int) $pending_count_query->fetch_row()[0] : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfigurasi Payment Gateway - Iqra' Society</title>
    <link rel="icon" type="image/x-icon" href="../asset/img/favicon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../asset/img/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Zilla+Slab:wght@600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50 font-sans antialiased" x-data="{ sidebarOpen: false, activeTab: 'midtrans', showMidServer: false, showTriPrivate: false  }">

<!-- ================= MOBILE NAVIGATION DRAWER ================= -->
    <div x-show="sidebarOpen" 
         x-transition:enter="transition-opacity ease-linear duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="sidebarOpen = false" 
         class="fixed inset-0 bg-gray-900/80 z-40 md:hidden" 
         style="display: none;">
    </div>

    <aside x-show="sidebarOpen"
           x-transition:enter="transition ease-in-out duration-300 transform"
           x-transition:enter-start="-translate-x-full"
           x-transition:enter-end="translate-x-0"
           x-transition:leave="transition ease-in-out duration-300 transform"
           x-transition:leave-start="translate-x-0"
           x-transition:leave-end="-translate-x-full"
           class="fixed inset-y-0 left-0 w-64 bg-emerald-950 text-white z-50 flex flex-col justify-between md:hidden"
           style="display: none;">
        
        <div class="p-6">
            <!-- Header Mobile Drawer: PROFIL / AVATAR USER -->
            <div class="flex items-center justify-between pb-6 mb-6 border-b border-emerald-900">
                <div class="flex items-center gap-3 overflow-hidden">
                    <?php if (!empty($_SESSION['avatar']) && file_exists(__DIR__ . '/../uploads/avatars/' . $_SESSION['avatar'])): ?>
                        <img src="../uploads/avatars/<?= htmlspecialchars($_SESSION['avatar']) ?>" alt="Avatar" class="w-10 h-10 rounded-full object-cover border border-emerald-600 shrink-0">
                    <?php else: ?>
                        <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold text-sm shrink-0">
                            <?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <div class="flex flex-col truncate">
                        <span class="text-xs font-bold text-white truncate"><?= htmlspecialchars($_SESSION['username'] ?? 'Pengguna') ?></span>
                        <span class="text-[10px] text-lime-400 font-semibold uppercase tracking-wider truncate"><?= htmlspecialchars($_SESSION['role'] ?? 'Guest') ?></span>
                    </div>
                </div>
                <button @click="sidebarOpen = false" class="text-emerald-400 hover:text-white p-1">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <?php include 'menu.php'; ?>
        </div>

        <div class="p-6 border-t border-emerald-900">
            <a href="../logout.php" class="flex items-center gap-2 text-xs font-bold text-red-400 hover:text-red-300">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a>
        </div>
    </aside>

    <!-- ================= MAIN DESKTOP LAYOUT ================= -->
    <div class="min-h-screen flex">
        
        <!-- Desktop Sidebar -->
        <aside class="w-64 bg-emerald-950 text-white flex-shrink-0 flex flex-col justify-between hidden md:flex">
            <div class="p-6">
                <!-- INFORMASI PROFIL USER (Pengganti Logo) -->
                <div class="flex items-center gap-3 pb-6 mb-6 border-b border-emerald-900/80">
                    <?php if (!empty($_SESSION['avatar']) && file_exists(__DIR__ . '/../uploads/avatars/' . $_SESSION['avatar'])): ?>
                        <img src="../uploads/avatars/<?= htmlspecialchars($_SESSION['avatar']) ?>" alt="Avatar" class="w-10 h-10 rounded-full object-cover border-2 border-emerald-500 shrink-0">
                    <?php else: ?>
                        <div class="w-10 h-10 rounded-full bg-emerald-800 text-lime-400 border border-emerald-600 flex items-center justify-center font-bold text-sm shrink-0 shadow-inner">
                            <?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <div class="flex flex-col min-w-0">
                        <span class="text-xs font-bold text-white truncate" title="<?= htmlspecialchars($_SESSION['username'] ?? '') ?>">
                            <?= htmlspecialchars($_SESSION['username'] ?? 'Pengguna') ?>
                        </span>
                        <span class="text-[10px] text-lime-400 font-semibold uppercase tracking-wider truncate">
                            <?= htmlspecialchars($_SESSION['role'] ?? 'Guest') ?>
                        </span>
                    </div>
                </div>

                <?php include 'menu.php'; ?>
            </div>

            <div class="p-6 border-t border-emerald-900">
                <a href="../logout.php" class="flex items-center gap-2 text-xs font-bold text-red-400 hover:text-red-300">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a>
            </div>
        </aside>

<!-- Main Content Area -->
<div class="flex-1 flex flex-col min-w-0">
<header class="sticky top-0 z-30 bg-white/95 backdrop-blur-md border-b border-gray-100 px-4 md:px-6 py-4 flex items-center justify-between shadow-sm">
    <div class="flex items-center gap-3">
        <button @click="sidebarOpen = true" class="md:hidden text-gray-600 hover:text-gray-900 p-2 rounded-lg hover:bg-gray-100">
            <i class="fa-solid fa-bars text-lg"></i>
        </button>
        <h1 class="text-base md:text-lg font-extrabold text-gray-800">Payment Gateway</h1>
    </div>

    <!-- POJOK KANAN HEADER: DROPDOWN LINK & NOTIFIKASI LONCENG -->
    <div class="flex items-center gap-2 md:gap-3">

        <!-- DROPDOWN LINK QUICK ACCESS (IKON RANTAI) -->
        <div class="relative" x-data="{ linkDropdown: false }">
            <button @click="linkDropdown = !linkDropdown" class="relative p-2.5 rounded-xl text-gray-500 hover:text-gray-800 hover:bg-gray-100 transition focus:outline-none" title="Tautan Web">
                <i class="fa-solid fa-link text-lg"></i>
            </button>

            <!-- Dropdown Menu Link -->
            <div x-show="linkDropdown" 
                 @click.outside="linkDropdown = false"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="absolute right-0 mt-2 w-52 bg-white rounded-2xl shadow-xl border border-gray-100 py-2 z-50"
                 style="display: none;">
                
                <div class="px-4 py-1.5 border-b border-gray-100">
                    <span class="text-xs font-bold text-gray-800">Akses Cepat Web</span>
                </div>

                <div class="py-1">
                    <a href="../index.php" target="_blank" class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-gray-700 hover:bg-emerald-50 hover:text-emerald-600 transition">
                        <i class="fa-solid fa-globe w-4 text-center text-gray-400"></i>
                        Web Iqra
                    </a>
                    <a href="../pengajar" target="_blank" class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-gray-700 hover:bg-emerald-50 hover:text-emerald-600 transition">
                        <i class="fa-solid fa-chalkboard-user w-4 text-center text-gray-400"></i>
                        Panel Pengajar
                    </a>
                    <a href="../donatur" target="_blank" class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-gray-700 hover:bg-emerald-50 hover:text-emerald-600 transition">
                        <i class="fa-solid fa-hand-holding-heart w-4 text-center text-gray-400"></i>
                        Panel Donatur
                    </a>
                    <a href="../pembelajar" target="_blank" class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-gray-700 hover:bg-emerald-50 hover:text-emerald-600 transition">
                        <i class="fa-solid fa-users w-4 text-center text-gray-400"></i>
                        Panel Member
                    </a>
                </div>
            </div>
        </div>

        <!-- NOTIFIKASI LONCENG -->
        <?php include __DIR__ . '/notif_dropdown.php'; ?>

    </div>
</header>

            <main class="p-4 md:p-6 w-full space-y-6">
            
                <!-- Alert Error -->
                <?php if ($error): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl text-xs flex items-center gap-2">
                        <i class="fa-solid fa-circle-exclamation text-base"></i>
                        <span><?= $error ?></span>
                    </div>
                <?php endif; ?>
            
                <!-- Alert Sukses -->
                <?php if ($success): ?>
                    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl text-xs flex items-center gap-2">
                        <i class="fa-solid fa-circle-check text-base"></i>
                        <span><?= $success ?></span>
                    </div>
                <?php endif; ?>
            
                <!-- PEMILIH GATEWAY AKTIF -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-4 py-3 w-full mb-2">
                    <form action="" method="POST" class="flex flex-wrap items-center gap-2">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="action" value="set_active_gateway">
                        <span class="text-xs font-bold text-gray-700 flex items-center gap-1.5 mr-1" title="Gateway yang digunakan untuk seluruh transaksi">
                            <i class="fa-solid fa-toggle-on text-emerald-700"></i> Gateway Aktif
                        </span>
                        <label class="flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-xl border cursor-pointer transition <?= $active_gateway === 'midtrans' ? 'border-emerald-600 bg-emerald-50 text-emerald-800 font-bold' : 'border-gray-200 text-gray-600 hover:border-gray-300' ?>">
                            <input type="radio" name="active_gateway" value="midtrans" <?= $active_gateway === 'midtrans' ? 'checked' : '' ?> class="accent-emerald-600">
                            <i class="fa-solid fa-shield-halved"></i> Midtrans
                            <?php if ($active_gateway === 'midtrans'): ?><span class="text-[9px] bg-emerald-600 text-white px-1.5 py-0.5 rounded-full font-bold">AKTIF</span><?php endif; ?>
                        </label>
                        <label class="flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-xl border cursor-pointer transition <?= $active_gateway === 'tripay' ? 'border-emerald-600 bg-emerald-50 text-emerald-800 font-bold' : 'border-gray-200 text-gray-600 hover:border-gray-300' ?>">
                            <input type="radio" name="active_gateway" value="tripay" <?= $active_gateway === 'tripay' ? 'checked' : '' ?> class="accent-emerald-600">
                            <i class="fa-solid fa-wallet"></i> TriPay
                            <?php if ($active_gateway === 'tripay'): ?><span class="text-[9px] bg-emerald-600 text-white px-1.5 py-0.5 rounded-full font-bold">AKTIF</span><?php endif; ?>
                        </label>
                        <button type="submit" title="Aktifkan" class="bg-emerald-800 hover:bg-emerald-900 text-white font-bold text-xs px-3.5 py-1.5 rounded-xl shadow transition flex items-center gap-1.5">
                            <i class="fa-solid fa-check"></i> Aktifkan
                        </button>
                    </form>
                </div>

                <!-- TAB HEADERS -->
                <div class="flex border-b border-gray-200 gap-6 text-xs font-bold w-full">
                    <button type="button" @click="activeTab = 'midtrans'" :class="activeTab === 'midtrans' ? 'border-emerald-600 text-emerald-800 border-b-2 pb-3' : 'text-gray-400 pb-3 hover:text-gray-600 transition'">
                        <i class="fa-solid fa-shield-halved mr-1"></i> Midtrans 
                    </button>
                    <button type="button" @click="activeTab = 'tripay'" :class="activeTab === 'tripay' ? 'border-emerald-600 text-emerald-800 border-b-2 pb-3' : 'text-gray-400 pb-3 hover:text-gray-600 transition'">
                        <i class="fa-solid fa-wallet mr-1"></i> TriPay 
                    </button>
                </div>
            
                <!-- TAB CONTENT 1: FORM MIDTRANS -->
                <div x-show="activeTab === 'midtrans'" x-cloak class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 w-full">
                    <form action="" method="POST" class="space-y-6 w-full">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="action" value="update_midtrans">
            
                        <div class="border-b border-gray-100 pb-3">
                            <h2 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                                <i class="fa-solid fa-shield-halved text-emerald-700"></i> Midtrans Snap Credentials
                            </h2>
                            <p class="text-[11px] text-gray-400 mt-0.5">Atur Merchant ID, Client Key, dan Server Key untuk integrasi pembayaran Midtrans.</p>
                        </div>
            
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">Merchant ID</label>
                                <input type="text" name="midtrans_merchant_id" value="<?= htmlspecialchars($keys_data['midtrans_merchant_id'] ?? '') ?>" placeholder="G123456789" class="w-full text-xs px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                            </div>
            
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">Client Key</label>
                                <input type="text" name="midtrans_client_key" value="<?= htmlspecialchars($keys_data['midtrans_client_key'] ?? '') ?>" placeholder="SB-Mid-client-..." class="w-full text-xs px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                            </div>
            
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">Server Key</label>
                                <div class="relative">
                                    <input :type="showMidServer ? 'text' : 'password'" name="midtrans_server_key" value="<?= htmlspecialchars($keys_data['midtrans_server_key'] ?? '') ?>" placeholder="SB-Mid-server-..." class="w-full text-xs px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-600 pr-10">
                                    <button type="button" @click="showMidServer = !showMidServer" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs">
                                        <i class="fa-solid" :class="showMidServer ? 'fa-eye-slash' : 'fa-eye'"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
            
                        <div class="pt-2 flex flex-wrap gap-3">
                            <button type="submit" class="bg-emerald-800 hover:bg-emerald-900 text-white font-bold text-xs px-6 py-3 rounded-xl shadow-md transition flex items-center gap-2">
                                <i class="fa-solid fa-floppy-disk"></i> Simpan 
                            </button>
                            <button type="submit" formnovalidate onclick="this.form.action.value='test_midtrans'" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-6 py-3 rounded-xl shadow-md transition flex items-center gap-2">
                                <i class="fa-solid fa-vial-circle-check"></i> Tes Koneksi (Rp 10.000)
                            </button>
                        </div>
                        <p class="text-[10px] text-gray-400 italic">Tes membuat transaksi Rp 10.000 via Snap API untuk memverifikasi Server Key. Tidak ada dana yang ditarik.</p>
                    </form>
                </div>
            
                <!-- TAB CONTENT 2: FORM TRIPAY -->
                <div x-show="activeTab === 'tripay'" x-cloak class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 w-full">
                    <form action="" method="POST" class="space-y-6 w-full">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="action" value="update_tripay">
            
                        <div class="border-b border-gray-100 pb-3">
                            <h2 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                                <i class="fa-solid fa-wallet text-emerald-700"></i> TriPay Payment Channel Credentials
                            </h2>
                            <p class="text-[11px] text-gray-400 mt-0.5">Atur Merchant Code, API Key, dan Private Key untuk integrasi TriPay.</p>
                        </div>
            
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">Merchant Code</label>
                                <input type="text" name="tripay_merchant_code" value="<?= htmlspecialchars($keys_data['tripay_merchant_code'] ?? '') ?>" placeholder="T12345" class="w-full text-xs px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                            </div>
            
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">API Key</label>
                                <input type="text" name="tripay_api_key" value="<?= htmlspecialchars($keys_data['tripay_api_key'] ?? '') ?>" placeholder="DEV-..." class="w-full text-xs px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                            </div>
            
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">Private Key</label>
                                <div class="relative">
                                    <input :type="showTriPrivate ? 'text' : 'password'" name="tripay_private_key" value="<?= htmlspecialchars($keys_data['tripay_private_key'] ?? '') ?>" placeholder="DEV-..." class="w-full text-xs px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-600 pr-10">
                                    <button type="button" @click="showTriPrivate = !showTriPrivate" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs">
                                        <i class="fa-solid" :class="showTriPrivate ? 'fa-eye-slash' : 'fa-eye'"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
            
                        <div class="pt-2 flex flex-wrap gap-3">
                            <button type="submit" class="bg-emerald-800 hover:bg-emerald-900 text-white font-bold text-xs px-6 py-3 rounded-xl shadow-md transition flex items-center gap-2">
                                <i class="fa-solid fa-floppy-disk"></i> Simpan 
                            </button>
                            <button type="submit" formnovalidate onclick="this.form.action.value='test_tripay'" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-6 py-3 rounded-xl shadow-md transition flex items-center gap-2">
                                <i class="fa-solid fa-vial-circle-check"></i> Tes Koneksi
                            </button>
                        </div>
                        <p class="text-[10px] text-gray-400 italic">Tes mengambil daftar channel pembayaran untuk memverifikasi API Key & Merchant Code.</p>
                    </form>

                    <!-- CHANNEL PEMBAYARAN TRIPAY -->
                    <div class="border-t border-gray-100 mt-2 pt-5">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                            <h3 class="text-xs font-bold text-gray-800"><i class="fa-solid fa-list-check text-emerald-600 mr-1.5"></i>Channel Pembayaran</h3>
                            <form method="POST" class="flex items-center gap-2">
                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                <input type="hidden" name="action" value="sync_tripay_channels">
                                <input type="hidden" name="tripay_api_key" value="<?= htmlspecialchars($keys_data['tripay_api_key'] ?? '') ?>">
                                <button type="submit" class="bg-gray-800 hover:bg-gray-900 text-white font-bold text-[11px] px-3.5 py-2 rounded-xl transition flex items-center gap-1.5">
                                    <i class="fa-solid fa-rotate"></i> Sinkronkan dari TriPay
                                </button>
                            </form>
                        </div>
                        <p class="text-[10px] text-gray-400 mb-3">Channel yang aktif tampil di halaman pembayaran donasi, buku, dan kelas (bila gateway TriPay dipakai).</p>
                        <?php if (!$tripay_channels): ?>
                            <p class="text-xs text-gray-400 italic py-4 text-center">Belum ada data channel. Klik "Sinkronkan dari TriPay".</p>
                        <?php else: ?>
                        <div class="overflow-x-auto -mx-6 px-6">
                        <table class="w-full text-xs min-w-[560px]">
                            <thead><tr class="text-left text-[10px] uppercase text-gray-400 border-b">
                                <th class="py-2 pr-3">Channel</th><th class="py-2 pr-3">Grup</th><th class="py-2 pr-3">Biaya Admin</th><th class="py-2 text-right">Aktif</th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($tripay_channels as $tc): ?>
                                <tr class="border-b border-gray-50 last:border-0">
                                    <td class="py-2.5 pr-3">
                                        <div class="flex items-center gap-2">
                                            <?php if ($tc['icon_url']): ?><img src="<?= htmlspecialchars($tc['icon_url']) ?>" alt="" class="w-7 h-7 rounded-lg object-contain bg-gray-50"><?php endif; ?>
                                            <div><p class="font-bold text-gray-800"><?= htmlspecialchars($tc['name']) ?></p><p class="text-[10px] text-gray-400"><?= htmlspecialchars($tc['code']) ?></p></div>
                                        </div>
                                    </td>
                                    <td class="py-2.5 pr-3 text-gray-500"><?= htmlspecialchars($tc['group']) ?></td>
                                    <td class="py-2.5 pr-3 text-gray-600">
                                        <?php
                                        $fee_txt = [];
                                        if ((float)$tc['fee_flat'] > 0) $fee_txt[] = 'Rp ' . number_format((float)$tc['fee_flat'], 0, ',', '.');
                                        if ((float)$tc['fee_percent'] > 0) $fee_txt[] = rtrim(rtrim(number_format((float)$tc['fee_percent'], 2), '0'), '.') . '%';
                                        echo $fee_txt ? implode(' + ', $fee_txt) : '<span class="text-gray-300">-</span>';
                                        ?>
                                    </td>
                                    <td class="py-2.5 text-right">
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                            <input type="hidden" name="action" value="toggle_tripay_channel">
                                            <input type="hidden" name="code" value="<?= htmlspecialchars($tc['code']) ?>">
                                            <input type="hidden" name="active" value="<?= $tc['active'] ? '0' : '1' ?>">
                                            <button type="submit" title="<?= $tc['active'] ? 'Nonaktifkan' : 'Aktifkan' ?>" class="w-11 h-6 rounded-full relative transition <?= $tc['active'] ? 'bg-emerald-600' : 'bg-gray-300' ?>">
                                                <span class="absolute top-0.5 <?= $tc['active'] ? 'right-0.5' : 'left-0.5' ?> w-5 h-5 bg-white rounded-full shadow transition-all"></span>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            
            </main>
        </div>
    </div>

</body>
</html>