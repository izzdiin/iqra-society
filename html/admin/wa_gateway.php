<?php
require_once __DIR__ . '/../includes/auth_middleware.php';

check_access(['super_administrator', 'administrator']);

if (!function_exists('getWaHeaders')) {
    function getWaHeaders($deviceId) {
        // Basic auth gateway wa-netter (2026-10-18)
        $basic = 'Basic ' . base64_encode('wa:wa-netter');
        $headers = [
            'Authorization: ' . $basic,
            'Content-Type: application/json',
            'X-Device-ID: ' . $deviceId
        ];
        // Bearer token tetap dikirim bila ada (untuk endpoint yg butuh sesi login)
        if (!empty($_SESSION['wa_token'])) {
            $headers[] = 'X-WA-Token: Bearer ' . $_SESSION['wa_token'];
        }
        return $headers;
    }
}

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $deviceId = $_SESSION['device_id'] ?? 'default_device';

    if ($_GET['action'] === 'get_qr') {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => WA_BASE_URL . '/app/login',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_HTTPHEADER     => getWaHeaders($deviceId),
        ]);

        $response = curl_exec($curl);
        $curlErr  = curl_error($curl);
        curl_close($curl);

        if ($curlErr) {
            echo json_encode(['success' => false, 'message' => 'cURL Error: ' . $curlErr]);
            exit;
        }

        $res = json_decode($response, true);

        if (isset($res['code']) && $res['code'] === 'SUCCESS') {
            $qr_duration = $res['results']['qr_duration'] ?? 30;
            $qr_link     = $res['results']['qr_link'];

            if (filter_var($qr_link, FILTER_VALIDATE_URL)) {
                $imageData = @file_get_contents($qr_link);
                $qrBase64 = ($imageData !== false) ? 'data:image/png;base64,' . base64_encode($imageData) : $qr_link;
            } else {
                $qrBase64 = strpos($qr_link, 'data:image') === 0 ? $qr_link : 'data:image/png;base64,' . base64_encode($qr_link);
            }

            echo json_encode([
                'success'     => true,
                'is_logged_in'=> false,
                'qr_image'    => $qrBase64,
                'qr_duration' => (int)$qr_duration
            ]);
        } 

        elseif (isset($res['message']) && stripos($res['message'], 'already logged in') !== false) {
            echo json_encode([
                'success'      => false,
                'is_logged_in' => true,
                'message'      => 'Perangkat WhatsApp Anda sudah terhubung!'
            ]);
        } 
        else {
            echo json_encode([
                'success'      => false,
                'is_logged_in' => false,
                'message'      => $res['message'] ?? 'Gagal mengambil QR Code.'
            ]);
        }
        exit;
    }

    if ($_GET['action'] === 'get_status') {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => WA_BASE_URL . '/app/status',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_HTTPHEADER     => getWaHeaders($deviceId),
        ]);
        $response = curl_exec($curl);
        $curlErr  = curl_error($curl);
        curl_close($curl);
        if ($curlErr) {
            echo json_encode(['success' => false, 'message' => 'cURL Error: ' . $curlErr]);
            exit;
        }
        $res = json_decode($response, true);
        $logged = !empty($res['results']['is_logged_in']);
        echo json_encode([
            'success'      => true,
            'is_logged_in' => $logged,
            'jid'          => $res['results']['jid'] ?? null,
            'device'       => $deviceId,
        ]);
        exit;
    }

    if ($_GET['action'] === 'login_code') {
        $phone = preg_replace('/[^0-9]/', '', $_GET['phone'] ?? '');
        if ($phone === '') {
            echo json_encode(['success' => false, 'message' => 'Nomor HP wajib diisi.']);
            exit;
        }
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => WA_BASE_URL . '/app/login-with-code?phone=' . urlencode($phone),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_HTTPHEADER     => getWaHeaders($deviceId),
        ]);
        $response = curl_exec($curl);
        $curlErr  = curl_error($curl);
        curl_close($curl);
        if ($curlErr) {
            echo json_encode(['success' => false, 'message' => 'cURL Error: ' . $curlErr]);
            exit;
        }
        $res = json_decode($response, true);
        if (!empty($res['results']['pairing_code'])) {
            echo json_encode(['success' => true, 'pairing_code' => $res['results']['pairing_code']]);
        } elseif (($res['code'] ?? '') === 'ALREADY_LOGGED_IN') {
            echo json_encode(['success' => false, 'is_logged_in' => true, 'message' => 'Perangkat sudah login.']);
        } else {
            echo json_encode(['success' => false, 'message' => $res['message'] ?? 'Gagal meminta kode pairing.']);
        }
        exit;
    }

    if ($_GET['action'] === 'logout_wa') {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL            => WA_BASE_URL . '/app/logout',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 15, // Diberi timeout 15s agar tidak gantung
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_HTTPHEADER     => getWaHeaders($deviceId),
        ));

        $response = curl_exec($curl);
        $curlErr  = curl_error($curl);
        curl_close($curl);

        if ($curlErr) {
            echo json_encode(['success' => false, 'message' => 'cURL Error: ' . $curlErr]);
            exit;
        }

        $res = json_decode($response, true);

        if (isset($res['code']) && $res['code'] === 'SUCCESS') {
            echo json_encode([
                'success' => true, 
                'message' => $res['message'] ?? 'Logout berhasil'
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => $res['message'] ?? 'Gagal logout dari WA Gateway.'
            ]);
        }
        exit;
    }
} 

$pending_count_query = $conn->query("SELECT COUNT(id) FROM section_kelas WHERE status = 'pending'");
$pending_notifications = $pending_count_query ? (int) $pending_count_query->fetch_row()[0] : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Iqra' Society</title>
    <link rel="icon" type="image/x-icon" href="../asset/img/favicon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../asset/img/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Zilla+Slab:wght@600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>

<body class="bg-gray-50 font-sans antialiased" x-data="{ sidebarOpen: false }">

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
        <h1 class="text-base md:text-lg font-extrabold text-gray-800">WA Gateway</h1>
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

            <!-- Dashboard Body -->
            <main class="p-6 space-y-6 flex justify-center">
<!-- Card Container dengan Alpine Component -->
<div x-data="qrScanner()" x-init="init()" class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 sm:p-8 border border-slate-200">
    
    <!-- Header -->
    <div class="text-center mb-6">
        <p class="text-sm text-slate-500 mt-1" x-text="isLoggedIn ? 'Status koneksi perangkat saat ini.' : 'Pindai kode QR di bawah ini menggunakan aplikasi WhatsApp Anda.'"></p>
    </div>

    <!-- Status Koneksi -->
    <div class="flex items-center justify-between mb-4">
        <div>
            <span x-show="statusChecked && isLoggedIn" class="inline-flex items-center gap-2 bg-green-100 text-green-800 px-4 py-2 rounded-xl text-xs font-bold"><i class="fa-solid fa-circle-check"></i> Logged in <span x-show="statusJid" x-text="'(' + statusJid + ')'" class="font-normal"></span></span>
            <span x-show="statusChecked && !isLoggedIn" class="inline-flex items-center gap-2 bg-red-100 text-red-800 px-4 py-2 rounded-xl text-xs font-bold"><i class="fa-solid fa-circle-xmark"></i> Disconnected</span>
            <span x-show="!statusChecked" class="inline-flex items-center gap-2 bg-slate-100 text-slate-500 px-4 py-2 rounded-xl text-xs font-bold"><i class="fa-solid fa-circle-notch animate-spin"></i> Mengecek status...</span>
        </div>
        <button @click="checkStatus()" :disabled="checkingStatus" class="text-xs font-bold text-emerald-700 hover:text-emerald-800 px-3 py-2 rounded-xl hover:bg-emerald-50 transition disabled:opacity-50">
            <i class="fa-solid fa-rotate" :class="checkingStatus && 'animate-spin'"></i> Refresh
        </button>
    </div>

    <!-- Login dengan Kode Pairing -->
    <div x-show="!isLoggedIn" class="mb-4 bg-white border border-slate-200 rounded-xl p-4">
        <p class="text-xs font-bold text-slate-700 mb-2"><i class="fa-solid fa-keyboard text-emerald-600 mr-1"></i> Login dengan Kode</p>
        <div class="flex gap-2">
            <input type="tel" x-model="pairPhone" placeholder="6281234567890" class="flex-1 text-sm border border-slate-200 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500">
            <button @click="requestCode()" :disabled="requestingCode || !pairPhone" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white text-xs font-bold rounded-xl transition">
                <span x-show="!requestingCode">Minta Kode</span>
                <span x-show="requestingCode"><i class="fa-solid fa-circle-notch animate-spin"></i></span>
            </button>
        </div>
        <div x-show="pairingCode" class="mt-3 text-center">
            <p class="text-[11px] text-slate-500 mb-1">Masukkan kode ini di WhatsApp HP:</p>
            <p x-text="pairingCode" class="text-3xl font-bold tracking-widest text-slate-900 bg-slate-100 rounded-xl px-4 py-3 select-all"></p>
            <p class="mt-2 text-[11px] text-slate-400">WhatsApp &gt; Perangkat Tertaut &gt; Tautkan Perangkat &gt; Tautkan dengan nomor telepon</p>
        </div>
        <p x-show="pairError" x-text="pairError" class="mt-2 text-xs text-red-600"></p>
    </div>

    <!-- Box Display QR Code / Logged In State -->
    <div class="relative flex flex-col items-center justify-center bg-slate-50 p-6 rounded-xl border border-dashed border-slate-300 min-h-[280px]">
        
        <!-- State 1: Skeleton Loading -->
        <template x-if="loading">
            <div class="flex flex-col items-center space-y-3 animate-pulse">
                <div class="w-48 h-48 bg-slate-300 rounded-lg"></div>
                <div class="h-4 w-32 bg-slate-300 rounded"></div>
            </div>
        </template>

        <!-- State 2: Display QR Code (Jika belum login) -->
        <template x-if="!loading && qrImage && !expired && !error && !isLoggedIn">
            <div class="flex flex-col items-center">
                <div class="p-2 bg-white rounded-lg shadow-sm border border-slate-200">
                    <img :src="qrImage" alt="WhatsApp QR Code" class="w-48 h-48 object-contain">
                </div>
                
                <!-- Timer Count Down -->
                <div class="mt-4 flex items-center space-x-2 text-xs font-medium text-slate-600 bg-slate-200/60 px-3 py-1.5 rounded-full">
                    <svg class="w-4 h-4 text-emerald-600 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Kadaluarsa dalam <strong class="text-emerald-700" x-text="timer"></strong> detik</span>
                </div>
            </div>
        </template>

        <!-- State 3: Logged In State (Jika sudah terhubung WA) -->
        <template x-if="!loading && isLoggedIn">
            <div class="flex flex-col items-center text-center p-4 space-y-4">
                <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center shadow-inner">
                    <i class="fa-solid fa-circle-check text-3xl"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-800">Perangkat Terhubung</h3>
                    <p class="text-xs text-slate-500 mt-1">WhatsApp Gateway siap digunakan untuk mengirim notifikasi.</p>
                </div>
                
                <!-- Button Logout WA -->
                <button @click="logoutWA()" 
                        :disabled="disconnecting"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 text-xs font-bold rounded-xl transition shadow-sm hover:shadow active:scale-95 disabled:opacity-50">
                    <i x-show="!disconnecting" class="fa-solid fa-right-from-bracket"></i>
                    <i x-show="disconnecting" class="fa-solid fa-circle-notch animate-spin"></i>
                    <span x-text="disconnecting ? 'Memutuskan...' : 'Logout / Putuskan WA'"></span>
                </button>
            </div>
        </template>

        <!-- State 4: Expired Overlay -->
        <template x-if="!loading && expired && !error && !isLoggedIn">
            <div class="text-center p-4">
                <p class="text-sm text-slate-600 mb-3">Kode QR telah kadaluarsa demi keamanan session.</p>
                <button @click="fetchQR()" class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Muat Ulang QR Code
                </button>
            </div>
        </template>

        <!-- State 5: Error State Biasa -->
        <template x-if="!loading && error && !isLoggedIn">
            <div class="text-center p-4">
                <p class="text-sm text-red-500 mb-3" x-text="errorMessage"></p>
                <button @click="fetchQR()" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-sm font-medium rounded-lg shadow-sm transition-colors">
                    Coba Lagi
                </button>
            </div>
        </template>

    </div>

    <!-- Instruksi Penggunaan (Hanya muncul jika belum login) -->
    <div x-show="!isLoggedIn" class="mt-6 border-t border-slate-100 pt-4">
        <ol class="text-xs text-slate-500 space-y-2 list-decimal list-inside">
            <li>Buka <strong>WhatsApp</strong> di HP Anda.</li>
            <li>Ketuk <strong>Setelan</strong> atau <strong>Menu (?)</strong> dan pilih <strong>Perangkat Tertaut</strong>.</li>
            <li>Ketuk <strong>Tautkan Perangkat</strong> lalu arahkan kamera ke layar ini.</li>
        </ol>
    </div>

</div>

<!-- Script Alpine logic -->
<script>
    function qrScanner() {
        return {
            loading: true,
            isLoggedIn: false,
            disconnecting: false,
            statusChecked: false,
            checkingStatus: false,
            statusJid: '',
            pairPhone: '',
            pairingCode: '',
            pairError: '',
            requestingCode: false,
            qrImage: null,
            timer: 0,
            interval: null,
            expired: false,
            error: false,
            errorMessage: '',

            init() {
                this.checkStatus();
                this.fetchQR();
            },

            async checkStatus() {
                this.checkingStatus = true;
                try {
                    const r = await fetch('?action=get_status', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    const d = await r.json();
                    this.statusChecked = true;
                    this.isLoggedIn = !!d.is_logged_in;
                    this.statusJid = d.jid || '';
                    if (d.is_logged_in) { this.loading = false; }
                } catch (e) { this.statusChecked = true; }
                this.checkingStatus = false;
            },

            async requestCode() {
                this.requestingCode = true;
                this.pairError = '';
                this.pairingCode = '';
                try {
                    const r = await fetch('?action=login_code&phone=' + encodeURIComponent(this.pairPhone), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    const d = await r.json();
                    if (d.success && d.pairing_code) {
                        this.pairingCode = d.pairing_code;
                    } else if (d.is_logged_in) {
                        this.isLoggedIn = true;
                        this.statusChecked = true;
                    } else {
                        this.pairError = d.message || 'Gagal meminta kode.';
                    }
                } catch (e) { this.pairError = 'Koneksi gagal.'; }
                this.requestingCode = false;
            },

            async fetchQR() {
                this.loading = true;
                this.error = false;
                this.expired = false;
                this.isLoggedIn = false;
                clearInterval(this.interval);

                try {
                    const response = await fetch('?action=get_qr', {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await response.json();

                    if (data.is_logged_in) {
                        this.isLoggedIn = true;
                    } else if (data.success) {
                        this.qrImage = data.qr_image;
                        this.timer = data.qr_duration;
                        this.startCountdown();
                    } else {
                        this.error = true;
                        this.errorMessage = data.message || 'Gagal memuat QR Code';
                    }
                } catch (err) {
                    this.error = true;
                    this.errorMessage = 'Terjadi kesalahan jaringan.';
                } finally {
                    this.loading = false;
                }
            },

async logoutWA() {
    if (!confirm('Apakah Kamu yakin ingin logout akun WhatsApp dari aplikasi ini?')) return;
    
    this.disconnecting = true;
    try {
        const response = await fetch('?action=logout_wa', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await response.json();

        if (data.success) {
            // Jika berhasil logout dari WA Gateway, ambil QR Code baru
            await this.fetchQR();
        } else {
            // Tampilkan pesan error dari API jika gagal
            alert('Gagal Logout: ' + (data.message || 'Terjadi kesalahan pada server.'));
        }
    } catch (err) {
        alert('Terjadi kesalahan jaringan saat mencoba logout.');
    } finally {
        this.disconnecting = false;
    }
},

            startCountdown() {
                this.interval = setInterval(() => {
                    if (this.timer > 1) {
                        this.timer--;
                    } else {
                        this.timer = 0;
                        this.expired = true;
                        clearInterval(this.interval);
                    }
                }, 1000);
            }
        }
    }
</script>
            </main>
        </div>
    </div>

</body>
</html>