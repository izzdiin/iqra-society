<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
check_access(['super_administrator', 'administrator', 'donatur']);

$user_id  = (int)($_SESSION['user_id'] ?? 0);
$fullname = $_SESSION['fullname'] ?? '';
$email    = $_SESSION['email'] ?? '';

// Midtrans Snap (mengikuti includes/header.php)
$midtrans_client_key = '';
$stmt_gt = $conn->prepare("SELECT key_value FROM gateway_settings WHERE key_name = 'midtrans_client_key' LIMIT 1");
if ($stmt_gt) {
    $stmt_gt->execute();
    $r = $stmt_gt->get_result()->fetch_assoc();
    $midtrans_client_key = $r['key_value'] ?? '';
    $stmt_gt->close();
}
$is_production = true; // Ubah ke false jika masih mode sandbox
$snap_url = $is_production
    ? "https://app.midtrans.com/snap/snap.js"
    : "https://app.sandbox.midtrans.com/snap/snap.js";

$stats = ['total' => 0, 'count' => 0, 'month' => 0, 'last' => null];
$riwayat = [];

if ($user_id > 0) {
    // Total & jumlah donasi berhasil
    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(amount),0) AS total, COUNT(*) AS cnt
         FROM donasi_transactions
         WHERE user_id = ? AND transaction_status IN ('settlement','capture')"
    );
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stats['total'] = (float)($row['total'] ?? 0);
    $stats['count'] = (int)($row['cnt'] ?? 0);
    $stmt->close();

    // Donasi bulan ini (berhasil)
    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(amount),0) AS mtotal
         FROM donasi_transactions
         WHERE user_id = ? AND transaction_status IN ('settlement','capture')
           AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"
    );
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stats['month'] = (float)($stmt->get_result()->fetch_assoc()['mtotal'] ?? 0);
    $stmt->close();

    // Donasi terakhir (berhasil)
    $stmt = $conn->prepare(
        "SELECT created_at FROM donasi_transactions
         WHERE user_id = ? AND transaction_status IN ('settlement','capture')
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stats['last'] = $r['created_at'] ?? null;
    $stmt->close();

    // Riwayat donasi
    $stmt = $conn->prepare(
        "SELECT order_id, amount, payment_type, transaction_status, created_at
         FROM donasi_transactions
         WHERE user_id = ?
         ORDER BY id DESC LIMIT 50"
    );
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($d = $res->fetch_assoc()) { $riwayat[] = $d; }
    $stmt->close();
}

function rupiah($n) {
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

function status_badge($s) {
    $map = [
        'settlement' => ['Berhasil', 'bg-emerald-100 text-emerald-800'],
        'capture'    => ['Berhasil', 'bg-emerald-100 text-emerald-800'],
        'pending'    => ['Menunggu', 'bg-amber-100 text-amber-800'],
        'expire'     => ['Kadaluarsa', 'bg-gray-100 text-gray-600'],
        'cancel'     => ['Dibatalkan', 'bg-gray-100 text-gray-600'],
        'deny'       => ['Ditolak', 'bg-red-100 text-red-700'],
        'failure'    => ['Gagal', 'bg-red-100 text-red-700'],
    ];
    [$label, $cls] = $map[$s] ?? [ucfirst($s), 'bg-gray-100 text-gray-600'];
    return '<span class="px-2.5 py-1 rounded-full text-[11px] font-bold ' . $cls . '">' . htmlspecialchars($label) . '</span>';
}

function tgl_id($dt) {
    if (!$dt) return '-';
    $bulan = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];
    $t = strtotime($dt);
    return date('j', $t) . ' ' . $bulan[(int)date('n', $t)] . ' ' . date('Y', $t);
}
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Donatur - IQRA' Society</title>
    <meta name="robots" content="noindex, nofollow">

    <!-- Tailwind CSS & FontAwesome -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="<?= $snap_url ?>" data-client-key="<?= htmlspecialchars($midtrans_client_key) ?>"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Zilla+Slab:wght@600;700&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            DEFAULT: '#065f46',
                            dark: '#044e39',
                            light: '#047857',
                            soft: '#ecfdf5',
                            accent: '#10b981'
                        }
                    },
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; color: #1f2937; }
        .scrollbar-none::-webkit-scrollbar { display: none; }
        .scrollbar-none { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="antialiased flex flex-col min-h-screen">

    <!-- HEADER / NAVBAR -->
    <header class="bg-white/90 backdrop-blur-md sticky top-0 z-50 border-b border-gray-100 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 sm:h-20">
                <a href="../index.php" class="flex items-center gap-2.5" aria-label="Beranda Iqra Society">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-xl shadow-sm">
                        <img src="../asset/img/logo_kitab.png" alt="Logo" width="40" height="40">
                    </div>
                    <div class="flex flex-col font-['Zilla_Slab'] leading-tight">
                        <span class="text-lg font-bold text-emerald-900 tracking-wide uppercase leading-none">IQRA'</span>
                        <span class="text-base font-semibold text-lime-500 tracking-normal leading-none mt-0.5">Society</span>
                    </div>
                </a>

                <div class="flex items-center gap-3" x-data="{ userMenuOpen: false }">
                    <div class="hidden sm:flex flex-col text-right">
                        <span class="text-xs font-bold text-gray-800"><?= htmlspecialchars($fullname) ?></span>
                        <span class="text-[10px] text-emerald-700 font-medium capitalize">Donatur</span>
                    </div>
                    <div class="relative">
                        <button @click="userMenuOpen = !userMenuOpen" class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold text-sm border-2 border-emerald-500 focus:outline-none">
                            <i class="fa-solid fa-hand-holding-heart"></i>
                        </button>
                        <div x-show="userMenuOpen" @click.away="userMenuOpen = false" x-cloak
                             class="absolute right-0 mt-2 w-48 bg-white rounded-2xl shadow-xl border border-gray-100 py-2 z-50 text-xs font-medium">
                            <div class="px-4 py-2 border-b border-gray-100 sm:hidden">
                                <p class="font-bold text-gray-800"><?= htmlspecialchars($fullname) ?></p>
                                <p class="text-[10px] text-gray-500 truncate"><?= htmlspecialchars($email) ?></p>
                            </div>
                            <a href="#" @click="userMenuOpen = false" class="block px-4 py-2 text-gray-700 hover:bg-emerald-50 hover:text-emerald-800">Edit Profil</a>
                            <div class="border-t border-gray-100 my-1"></div>
                            <a href="../logout.php" class="block px-4 py-2 text-red-600 hover:bg-red-50 font-bold">Keluar</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- MAIN -->
    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6" x-data="{ activeTab: 'beranda' }">

        <!-- WELCOME BANNER -->
        <div class="bg-gradient-to-r from-emerald-900 to-emerald-700 rounded-3xl p-6 text-white shadow-lg relative overflow-hidden">
            <div class="absolute right-0 bottom-0 opacity-10 pointer-events-none translate-x-6 translate-y-6">
                <i class="fa-solid fa-hand-holding-heart text-[180px]"></i>
            </div>
            <div class="relative z-10">
                <span class="bg-emerald-800/80 text-emerald-200 text-[10px] sm:text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider">Panel Donatur</span>
                <h1 class="text-xl sm:text-3xl font-extrabold mt-2">Jazakumullah khairan, <?= htmlspecialchars($fullname) ?>!</h1>
                <p class="text-xs sm:text-sm text-emerald-100/90 mt-1 max-w-xl">
                    Setiap donasi Anda menjadi amal jariyah untuk dakwah ilmu. Berikut ringkasan kebaikan Anda bersama Iqra' Society.
                </p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-6 pt-6 border-t border-emerald-800/60">
                    <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-3 border border-white/10">
                        <span class="block text-lg sm:text-2xl font-black text-white"><?= rupiah($stats['total']) ?></span>
                        <span class="text-[11px] text-emerald-200 font-medium">Total Donasi</span>
                    </div>
                    <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-3 border border-white/10">
                        <span class="block text-2xl font-black text-white"><?= $stats['count'] ?>x</span>
                        <span class="text-[11px] text-emerald-200 font-medium">Jumlah Donasi</span>
                    </div>
                    <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-3 border border-white/10">
                        <span class="block text-lg sm:text-2xl font-black text-white"><?= rupiah($stats['month']) ?></span>
                        <span class="text-[11px] text-emerald-200 font-medium">Bulan Ini</span>
                    </div>
                    <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-3 border border-white/10">
                        <span class="block text-lg sm:text-2xl font-black text-white"><?= tgl_id($stats['last']) ?></span>
                        <span class="text-[11px] text-emerald-200 font-medium">Donasi Terakhir</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABS -->
        <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none">
            <button @click="activeTab = 'beranda'" :class="activeTab === 'beranda' ? 'bg-emerald-800 text-white shadow-md' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'" class="px-4 py-2.5 rounded-2xl text-xs font-bold transition shrink-0">
                <i class="fa-solid fa-house mr-1.5"></i> Beranda
            </button>
            <button @click="activeTab = 'riwayat'" :class="activeTab === 'riwayat' ? 'bg-emerald-800 text-white shadow-md' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'" class="px-4 py-2.5 rounded-2xl text-xs font-bold transition shrink-0">
                <i class="fa-solid fa-clock-rotate-left mr-1.5"></i> Riwayat Donasi
            </button>
            <button @click="activeTab = 'donasi'" :class="activeTab === 'donasi' ? 'bg-emerald-800 text-white shadow-md' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'" class="px-4 py-2.5 rounded-2xl text-xs font-bold transition shrink-0">
                <i class="fa-solid fa-hand-holding-heart mr-1.5"></i> Donasi Baru
            </button>
        </div>

        <!-- TAB: BERANDA -->
        <div x-show="activeTab === 'beranda'" x-cloak class="space-y-6">
            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-extrabold text-gray-800"><i class="fa-solid fa-clock-rotate-left text-emerald-600 mr-2"></i>Donasi Terakhir Anda</h2>
                    <button @click="activeTab = 'riwayat'" class="text-xs font-bold text-emerald-700 hover:text-emerald-900">Lihat Semua <i class="fa-solid fa-arrow-right ml-1"></i></button>
                </div>
                <?php if (empty($riwayat)): ?>
                    <div class="text-center py-10">
                        <i class="fa-solid fa-hand-holding-heart text-5xl text-emerald-200 mb-4"></i>
                        <p class="text-sm text-gray-500 font-medium">Belum ada riwayat donasi.</p>
                        <button @click="activeTab = 'donasi'" class="mt-4 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-5 py-3 rounded-xl transition shadow-md">
                            <i class="fa-solid fa-heart mr-1.5"></i> Mulai Berdonasi
                        </button>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach (array_slice($riwayat, 0, 5) as $d): ?>
                        <div class="flex items-center justify-between gap-3 bg-gray-50 rounded-2xl px-4 py-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-hand-holding-dollar"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-gray-800 truncate"><?= htmlspecialchars($d['order_id']) ?></p>
                                    <p class="text-[11px] text-gray-500"><?= tgl_id($d['created_at']) ?> &bull; <?= htmlspecialchars(ucfirst($d['payment_type'] ?? '-')) ?></p>
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-xs font-extrabold text-gray-800"><?= rupiah($d['amount']) ?></p>
                                <div class="mt-1"><?= status_badge($d['transaction_status']) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="bg-emerald-50 border border-emerald-100 rounded-3xl p-6 flex flex-col sm:flex-row items-start sm:items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-emerald-600 text-white flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-quote-left"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm text-emerald-900 font-medium leading-relaxed">"Perumpamaan orang yang menginfakkan hartanya di jalan Allah seperti sebutir biji yang menumbuhkan tujuh tangkai, pada setiap tangkai ada seratus biji."</p>
                    <p class="text-xs text-emerald-700 mt-1 font-bold">QS. Al-Baqarah: 261</p>
                </div>
            </div>
        </div>

        <!-- TAB: RIWAYAT -->
        <div x-show="activeTab === 'riwayat'" x-cloak>
            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6">
                <h2 class="text-sm font-extrabold text-gray-800 mb-4"><i class="fa-solid fa-clock-rotate-left text-emerald-600 mr-2"></i>Riwayat Donasi</h2>
                <?php if (empty($riwayat)): ?>
                    <p class="text-sm text-gray-500 text-center py-8">Belum ada riwayat donasi.</p>
                <?php else: ?>
                    <div class="overflow-x-auto -mx-6 px-6">
                        <table class="w-full text-xs min-w-[560px]">
                            <thead>
                                <tr class="text-left text-gray-500 border-b border-gray-100">
                                    <th class="py-2.5 pr-3 font-bold">Tanggal</th>
                                    <th class="py-2.5 pr-3 font-bold">Order ID</th>
                                    <th class="py-2.5 pr-3 font-bold">Metode</th>
                                    <th class="py-2.5 pr-3 font-bold text-right">Nominal</th>
                                    <th class="py-2.5 font-bold text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($riwayat as $d): ?>
                                <tr class="border-b border-gray-50 hover:bg-gray-50/60">
                                    <td class="py-3 pr-3 text-gray-600 whitespace-nowrap"><?= tgl_id($d['created_at']) ?></td>
                                    <td class="py-3 pr-3 font-bold text-gray-800"><?= htmlspecialchars($d['order_id']) ?></td>
                                    <td class="py-3 pr-3 text-gray-600"><?= htmlspecialchars(ucfirst($d['payment_type'] ?? '-')) ?></td>
                                    <td class="py-3 pr-3 font-extrabold text-gray-800 text-right whitespace-nowrap"><?= rupiah($d['amount']) ?></td>
                                    <td class="py-3 text-right"><?= status_badge($d['transaction_status']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- TAB: DONASI BARU -->
        <div x-show="activeTab === 'donasi'" x-cloak x-data="donasiHandler()">
            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 max-w-2xl mx-auto">
                <h2 class="text-sm font-extrabold text-gray-800 mb-1"><i class="fa-solid fa-hand-holding-heart text-emerald-600 mr-2"></i>Donasi Baru</h2>
                <p class="text-xs text-gray-500 mb-5">Pilih nominal dan lanjutkan ke pembayaran yang aman via Midtrans.</p>

                <div class="grid grid-cols-3 gap-2.5 mb-4">
                    <template x-for="n in presetNominals" :key="n">
                        <button type="button" @click="amount = n"
                                :class="amount === n ? 'bg-emerald-700 text-white border-emerald-700 shadow-md' : 'bg-white text-gray-700 border-gray-200 hover:border-emerald-400'"
                                class="border rounded-xl py-3 text-xs font-extrabold transition"
                                x-text="'Rp ' + n.toLocaleString('id-ID')"></button>
                    </template>
                </div>

                <div class="mb-4">
                    <label class="text-xs font-bold text-gray-700 block mb-1.5">Nominal Lainnya</label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-xs font-bold text-gray-400">Rp</span>
                        <input type="number" x-model.number="amount" min="10000" step="1000"
                               class="w-full text-sm font-bold pl-11 pr-3 py-3 rounded-xl border border-gray-200 focus:outline-none focus:border-emerald-600">
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1">Minimal Rp 10.000</p>
                </div>

                <div class="grid sm:grid-cols-2 gap-4 mb-5">
                    <div>
                        <label class="text-xs font-bold text-gray-700 block mb-1.5">Nama Donatur</label>
                        <input type="text" x-model="donorName" required
                               class="w-full text-xs px-3 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-emerald-600 bg-gray-50">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-700 block mb-1.5">Email</label>
                        <input type="email" x-model="donorEmail" placeholder="email@contoh.com" required
                               class="w-full text-xs px-3 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-emerald-600 bg-gray-50">
                    </div>
                </div>

                <button type="button" @click="processDonation()" :disabled="loading"
                        class="w-full bg-emerald-700 hover:bg-emerald-800 active:bg-emerald-900 disabled:opacity-50 text-white font-bold text-sm py-3.5 rounded-xl transition shadow-md flex items-center justify-center gap-2">
                    <template x-if="!loading"><span><i class="fa-solid fa-shield-halved mr-1"></i> Lanjut ke Pembayaran</span></template>
                    <template x-if="loading"><span><i class="fa-solid fa-spinner fa-spin mr-1"></i> Memproses...</span></template>
                </button>
                <p class="text-[11px] text-gray-400 text-center mt-3"><i class="fa-solid fa-lock mr-1"></i>Pembayaran aman via Midtrans (QRIS, VA, e-wallet, kartu)</p>
            </div>
        </div>

    </main>

    <!-- FOOTER -->
    <footer class="bg-emerald-950 text-emerald-100/80 mt-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-8">
                <div class="lg:col-span-6">
                    <div class="flex items-center gap-2.5 mb-4">
                        <img src="../asset/img/logo_kitab.png" alt="Logo" width="36" height="36">
                        <div class="flex flex-col font-['Zilla_Slab'] leading-tight">
                            <span class="text-base font-bold text-white tracking-wide uppercase leading-none">IQRA'</span>
                            <span class="text-sm font-semibold text-lime-400 tracking-normal leading-none mt-0.5">Society</span>
                        </div>
                    </div>
                    <p class="text-xs leading-relaxed max-w-sm">Membangun generasi yang cinta ilmu dan Al-Qur'an melalui program kelas, buku, dan kegiatan dakwah.</p>
                </div>
                <div class="lg:col-span-3">
                    <h4 class="text-sm font-bold text-white mb-4">Kontak</h4>
                    <ul class="space-y-2.5 text-xs">
                        <li class="flex items-center gap-2.5">
                            <i class="fa-solid fa-envelope text-emerald-300 text-xs shrink-0"></i>
                            <a href="mailto:info@iqrasociety.id" class="hover:text-white transition">info@iqrasociety.id</a>
                        </li>
                        <li class="flex items-center gap-2.5">
                            <i class="fa-solid fa-phone text-emerald-300 text-xs shrink-0"></i>
                            <a href="tel:+6281234567890" class="hover:text-white transition">+62 812-3456-7890</a>
                        </li>
                    </ul>
                </div>
                <div class="lg:col-span-3">
                    <h4 class="text-sm font-bold text-white mb-4">Butuh Bantuan?</h4>
                    <a href="https://wa.me/6281234567800" target="_blank" class="inline-flex items-center gap-2 bg-emerald-700 hover:bg-emerald-600 text-white text-xs font-bold px-4 py-3 rounded-xl transition shadow-sm">
                        <i class="fa-brands fa-whatsapp text-sm"></i>
                        <span>Chat Bantuan (WhatsApp)</span>
                    </a>
                </div>
            </div>
            <div class="pt-6 mt-6 border-t border-emerald-900 flex flex-col sm:flex-row items-center justify-between text-xs text-emerald-200/60 gap-3 text-center sm:text-left">
                <p>&copy; <?php echo date('Y'); ?> IQRA' Society. All Rights Reserved.</p>
            </div>
        </div>
    </footer>

<script>
function donasiHandler() {
    return {
        loading: false,
        amount: 50000,
        donorName: <?= json_encode($fullname) ?>,
        donorEmail: <?= json_encode($email) ?>,
        presetNominals: [25000, 50000, 100000, 250000, 500000, 1000000],

        async processDonation() {
            if (this.amount < 10000) {
                alert('Nominal donasi minimal Rp 10.000');
                return;
            }
            if (!this.donorName.trim() || !this.donorEmail.trim()) {
                alert('Nama dan email wajib diisi.');
                return;
            }
            this.loading = true;
            try {
                const response = await fetch('../process_donasi.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        amount: this.amount,
                        name: this.donorName,
                        email: this.donorEmail
                    })
                });
                const result = await response.json();
                if (result.success && result.snap_token) {
                    window.snap.pay(result.snap_token, {
                        onSuccess: function() { alert('Terima kasih! Donasi Anda berhasil diterima.'); location.reload(); },
                        onPending: function() { alert('Menunggu pembayaran Anda.'); location.reload(); },
                        onError: function() { alert('Pembayaran gagal atau dibatalkan.'); },
                        onClose: function() { console.log('Donatur menutup popup tanpa menyelesaikan pembayaran'); }
                    });
                } else {
                    alert(result.message || 'Gagal memproses transaksi donasi.');
                }
            } catch (err) {
                alert('Terjadi kesalahan jaringan.');
            } finally {
                this.loading = false;
            }
        }
    }
}
</script>

</body>
</html>
