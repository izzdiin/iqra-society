<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/earnings_helper.php';

check_access(['super_administrator', 'administrator']);

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

earn_refresh_status($conn);
$error = '';
$success = '';

// ---------- POST handlers ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid.';
    } else {
        $act = $_POST['act'] ?? '';

        if ($act === 'save_settings') {
            $pct = max(0, min(100, (float)($_POST['commission_pct'] ?? 20)));
            $minw = max(0, (float)($_POST['min_withdrawal'] ?? 50000));
            $st = $conn->prepare("INSERT INTO gateway_settings (key_name, key_value) VALUES ('commission_pct', ?) ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");
            $s = (string)$pct; $st->bind_param("s", $s); $st->execute(); $st->close();
            $st = $conn->prepare("INSERT INTO gateway_settings (key_name, key_value) VALUES ('min_withdrawal', ?) ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");
            $s = (string)$minw; $st->bind_param("s", $s); $st->execute(); $st->close();
            $adm_earn = isset($_POST['admin_earn_enabled']) ? '1' : '0';
            $st = $conn->prepare("INSERT INTO gateway_settings (key_name, key_value) VALUES ('admin_earn_enabled', ?) ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");
            $st->bind_param("s", $adm_earn); $st->execute(); $st->close();
            $wdfee = max(0, (float)($_POST['withdrawal_fee'] ?? 7500));
            $st = $conn->prepare("INSERT INTO gateway_settings (key_name, key_value) VALUES ('withdrawal_fee', ?) ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");
            $s = (string)$wdfee; $st->bind_param("s", $s); $st->execute(); $st->close();
            if (function_exists('log_activity')) log_activity('UPDATE', 'bagi_hasil', "Ubah komisi menjadi $pct% & min penarikan Rp " . number_format($minw, 0, ',', '.'));
            $success = 'Pengaturan berhasil disimpan.';
        }

        elseif ($act === 'withdraw_decide') {
            $wid = (int)($_POST['wid'] ?? 0);
            $decision = $_POST['decision'] ?? ''; // approve | reject | paid
            $note = trim($_POST['admin_note'] ?? '');
            $st = $conn->prepare("SELECT * FROM withdrawals WHERE id = ? AND status = 'requested' LIMIT 1");
            // paid hanya dari approved
            if ($decision === 'paid') {
                $st = $conn->prepare("SELECT * FROM withdrawals WHERE id = ? AND status = 'approved' LIMIT 1");
            }
            $st->bind_param("i", $wid);
            $st->execute();
            $w = $st->get_result()->fetch_assoc();
            $st->close();
            if (!$w) {
                $error = 'Data penarikan tidak ditemukan atau status sudah berubah.';
            } elseif ($decision === 'reject') {
                $st = $conn->prepare("UPDATE withdrawals SET status = 'rejected', admin_note = ?, processed_at = NOW() WHERE id = ?");
                $st->bind_param("si", $note, $wid);
                $st->execute(); $st->close();
                if (function_exists('log_activity')) log_activity('REJECT', 'bagi_hasil', "Tolak penarikan #$wid Rp " . number_format($w['amount'], 0, ',', '.') . " ($note)");
                $success = 'Penarikan ditolak.';
            } elseif ($decision === 'approve') {
                // Konsumsi earning available tertua (FIFO) sejumlah amount (split bila perlu)
                $need = (float)$w['amount'];
                $st = $conn->prepare("SELECT * FROM seller_earnings WHERE teacher_id = ? AND status = 'available' ORDER BY available_at ASC, id ASC");
                $st->bind_param("i", $w['teacher_id']);
                $st->execute();
                $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
                $st->close();
                $avail = array_sum(array_column($rows, 'net'));
                if ($avail < $need - 0.01) {
                    $error = 'Saldo tersedia pengajar tidak mencukupi (tersedia ' . earn_rupiah($avail) . ').';
                } else {
                    $conn->begin_transaction();
                    try {
                        $remain = $need;
                        foreach ($rows as $r) {
                            if ($remain <= 0.01) break;
                            $rid = (int)$r['id'];
                            $rnet = (float)$r['net'];
                            if ($rnet <= $remain + 0.01) {
                                $conn->query("UPDATE seller_earnings SET status = 'withdrawn' WHERE id = $rid");
                                $remain -= $rnet;
                            } else {
                                // Split: sebagian withdrawn, sisa tetap available (gross proporsional)
                                $take = round($remain, 2);
                                $rest = round($rnet - $take, 2);
                                $pct = (float)$r['commission_pct'];
                                $div = $pct >= 100 ? 1 : (100 - $pct) / 100;
                                $gross_take = round($take / $div, 2);
                                $gross_rest = round($rest / $div, 2);
                                $conn->query("UPDATE seller_earnings SET net = $take, gross = $gross_take, status = 'withdrawn' WHERE id = $rid");
                                $esc_name = $conn->real_escape_string($r['item_name']);
                                $conn->query("INSERT INTO seller_earnings (teacher_id, transaction_id, item_type, item_id, item_name, gross, commission_pct, net, status, created_at, available_at) VALUES ({$r['teacher_id']}, {$r['transaction_id']}, '{$r['item_type']}', {$r['item_id']}, '$esc_name', $gross_rest, {$r['commission_pct']}, $rest, 'available', '{$r['created_at']}', '{$r['available_at']}')");
                                $remain = 0;
                            }
                        }
                        $st = $conn->prepare("UPDATE withdrawals SET status = 'approved', admin_note = ?, processed_at = NOW() WHERE id = ?");
                        $st->bind_param("si", $note, $wid);
                        $st->execute(); $st->close();
                        $conn->commit();
                        if (function_exists('log_activity')) log_activity('APPROVE', 'bagi_hasil', "Setujui penarikan #$wid Rp " . number_format($need, 0, ',', '.'));
                        $success = 'Penarikan disetujui. Tandai "Sudah Dibayar" setelah transfer.';
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error = 'Gagal memproses: ' . $e->getMessage();
                    }
                }
            } elseif ($decision === 'paid') {
                $st = $conn->prepare("UPDATE withdrawals SET status = 'paid', processed_at = NOW() WHERE id = ?");
                $st->bind_param("i", $wid);
                $st->execute(); $st->close();
                if (function_exists('log_activity')) log_activity('UPDATE', 'bagi_hasil', "Tandai lunas penarikan #$wid Rp " . number_format($w['amount'], 0, ',', '.'));
                $success = 'Penarikan ditandai sudah dibayar.';
            }
        }

        elseif ($act === 'void_earning') {
            $eid = (int)($_POST['eid'] ?? 0);
            $st = $conn->prepare("UPDATE seller_earnings SET status = 'void' WHERE id = ? AND status = 'pending'");
            $st->bind_param("i", $eid);
            $st->execute();
            if ($st->affected_rows > 0) {
                $success = 'Bagi hasil dibatalkan (void).';
                if (function_exists('log_activity')) log_activity('DELETE', 'bagi_hasil', "Void earning #$eid");
            } else {
                $error = 'Hanya earning berstatus pending yang bisa di-void.';
            }
            $st->close();
        }
    }
}

// ---------- Data ----------
$commission_pct = (float)earn_setting($conn, 'commission_pct', '20');
$min_withdrawal = (float)earn_setting($conn, 'min_withdrawal', '50000');
$admin_earn_enabled = earn_setting($conn, 'admin_earn_enabled', '0') === '1';
$withdrawal_fee = (float)earn_setting($conn, 'withdrawal_fee', '7500');

$stats = $conn->query("SELECT
    (SELECT COALESCE(SUM(amount),0) FROM transactions WHERE payment_status='PAID') AS gmv,
    (SELECT COALESCE(SUM(gross - net),0) FROM seller_earnings WHERE status != 'void') AS komisi_platform,
    (SELECT COALESCE(SUM(net),0) FROM seller_earnings WHERE status = 'withdrawn') AS dibayar,
    (SELECT COALESCE(SUM(net),0) FROM seller_earnings WHERE status = 'available') AS menunggu_tarik,
    (SELECT COUNT(*) FROM withdrawals WHERE status = 'requested') AS wd_requested
")->fetch_assoc();

$f_status = $_GET['f_status'] ?? '';
$where = $f_status !== '' ? "WHERE e.status = '" . $conn->real_escape_string($f_status) . "'" : '';
$earnings = $conn->query("SELECT e.*, u.fullname AS teacher_name FROM seller_earnings e LEFT JOIN users u ON u.id = e.teacher_id $where ORDER BY e.id DESC LIMIT 100")->fetch_all(MYSQLI_ASSOC);

$withdrawals = $conn->query("SELECT w.*, u.fullname AS teacher_name FROM withdrawals w LEFT JOIN users u ON u.id = w.teacher_id ORDER BY w.id DESC LIMIT 100")->fetch_all(MYSQLI_ASSOC);

$pending_classes  = (int)($conn->query("SELECT COUNT(id) FROM section_kelas WHERE status = 'pending'")->fetch_row()[0] ?? 0);
$pending_activity = (int)($conn->query("SELECT COUNT(id) FROM section_kegiatan WHERE status = 'pending'")->fetch_row()[0] ?? 0);
$pending_book     = (int)($conn->query("SELECT COUNT(id) FROM section_buku WHERE status = 'pending'")->fetch_row()[0] ?? 0);
$pending_post     = (int)($conn->query("SELECT COUNT(id) FROM posts WHERE status = 'pending'")->fetch_row()[0] ?? 0);

$st_badge = [
    'pending' => 'bg-amber-100 text-amber-700', 'available' => 'bg-emerald-100 text-emerald-700',
    'withdrawn' => 'bg-blue-100 text-blue-700', 'void' => 'bg-gray-100 text-gray-500',
];
$wd_badge = [
    'requested' => 'bg-amber-100 text-amber-700', 'approved' => 'bg-blue-100 text-blue-700',
    'paid' => 'bg-emerald-100 text-emerald-700', 'rejected' => 'bg-red-100 text-red-700',
    'cancelled' => 'bg-gray-100 text-gray-500',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bagi Hasil - Iqra' Society</title>
    <link rel="icon" type="image/x-icon" href="../asset/img/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>.no-scrollbar::-webkit-scrollbar{display:none}.no-scrollbar{-ms-overflow-style:none;scrollbar-width:none}</style>
</head>
<body class="bg-gray-50 font-sans antialiased" x-data="{ sidebarOpen: false }">
    <div x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false" class="fixed inset-0 bg-gray-900/80 z-40 md:hidden" style="display: none;"></div>

    <aside x-show="sidebarOpen" x-transition:enter="transition ease-in-out duration-300 transform" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in-out duration-300 transform" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full" class="fixed inset-y-0 left-0 w-64 bg-emerald-950 text-white z-50 flex flex-col justify-between md:hidden" style="display: none;">
        <div class="p-6">
            <div class="flex items-center justify-between pb-6 mb-6 border-b border-emerald-900">
                <div class="flex items-center gap-3 overflow-hidden">
                    <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold text-sm shrink-0"><?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?></div>
                    <div class="flex flex-col truncate">
                        <span class="text-xs font-bold text-white truncate"><?= htmlspecialchars($_SESSION['username'] ?? 'Pengguna') ?></span>
                        <span class="text-[10px] text-lime-400 font-semibold uppercase tracking-wider truncate"><?= htmlspecialchars($_SESSION['role'] ?? 'Guest') ?></span>
                    </div>
                </div>
                <button @click="sidebarOpen = false" class="text-emerald-400 hover:text-white p-1"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <?php include 'menu.php'; ?>
        </div>
        <div class="p-6 border-t border-emerald-900">
            <a href="../logout.php" class="flex items-center gap-2 text-xs font-bold text-red-400 hover:text-red-300"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </div>
    </aside>

    <div class="flex min-h-screen">
        <aside class="hidden md:flex w-64 bg-emerald-950 text-white flex-col justify-between shrink-0">
            <div class="p-6">
                <div class="flex items-center gap-3 pb-6 mb-6 border-b border-emerald-900">
                    <div class="w-10 h-10 rounded-xl bg-emerald-700 flex items-center justify-center text-white font-bold"><i class="fa-solid fa-book-quran"></i></div>
                    <div><div class="text-xs font-bold">IQRA' Society</div><div class="text-[10px] text-emerald-400">Admin Panel</div></div>
                </div>
                <?php include 'menu.php'; ?>
            </div>
            <div class="p-6 border-t border-emerald-900">
                <a href="../logout.php" class="flex items-center gap-2 text-xs font-bold text-red-400 hover:text-red-300"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
        </aside>

        <div class="flex-1 min-w-0">
            <header class="sticky top-0 z-30 bg-white/95 backdrop-blur-md border-b border-gray-100 px-4 md:px-6 py-4 flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-3">
                    <button @click="sidebarOpen = true" class="md:hidden text-gray-600 hover:text-gray-900 p-2 rounded-lg hover:bg-gray-100">
                        <i class="fa-solid fa-bars text-lg"></i>
                    </button>
                    <h1 class="text-base md:text-lg font-extrabold text-gray-800">Bagi Hasil</h1>
                </div>
                <div class="flex items-center gap-2 md:gap-3">
                    <div class="relative" x-data="{ linkDropdown: false }">
                        <button @click="linkDropdown = !linkDropdown" class="relative p-2.5 rounded-xl text-gray-500 hover:text-gray-800 hover:bg-gray-100 transition focus:outline-none" title="Tautan Web">
                            <i class="fa-solid fa-link text-lg"></i>
                        </button>
                        <div x-show="linkDropdown" @click.outside="linkDropdown = false" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute right-0 mt-2 w-52 bg-white rounded-2xl shadow-xl border border-gray-100 py-2 z-50" style="display: none;">
                            <div class="px-4 py-1.5 border-b border-gray-100"><span class="text-xs font-bold text-gray-800">Akses Cepat Web</span></div>
                            <div class="py-1">
                                <a href="../index.php" target="_blank" class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-gray-700 hover:bg-emerald-50 hover:text-emerald-600 transition"><i class="fa-solid fa-globe w-4 text-center text-gray-400"></i>Web Iqra</a>
                                <a href="../pengajar" target="_blank" class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-gray-700 hover:bg-emerald-50 hover:text-emerald-600 transition"><i class="fa-solid fa-chalkboard-user w-4 text-center text-gray-400"></i>Panel Pengajar</a>
                                <a href="../pembelajar" target="_blank" class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-gray-700 hover:bg-emerald-50 hover:text-emerald-600 transition"><i class="fa-solid fa-users w-4 text-center text-gray-400"></i>Panel Member</a>
                            </div>
                        </div>
                    </div>
                    <?php include 'notif_dropdown.php'; ?>
                </div>
            </header>

            <main class="p-4 md:p-8 space-y-6 max-w-6xl">
                <?php if ($error): ?><div class="bg-red-50 border border-red-200 text-red-700 text-xs font-bold rounded-xl px-4 py-3"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <?php if ($success): ?><div class="bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-bold rounded-xl px-4 py-3"><?= htmlspecialchars($success) ?></div><?php endif; ?>

                <!-- Statistik -->
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
                    <div class="bg-white rounded-2xl border border-gray-100 p-4"><p class="text-[10px] font-bold text-gray-400 uppercase">Total Penjualan</p><p class="text-base font-black text-gray-800 mt-1"><?= earn_rupiah($stats['gmv']) ?></p></div>
                    <div class="bg-white rounded-2xl border border-gray-100 p-4"><p class="text-[10px] font-bold text-gray-400 uppercase">Komisi Platform</p><p class="text-base font-black text-emerald-700 mt-1"><?= earn_rupiah($stats['komisi_platform']) ?></p></div>
                    <div class="bg-white rounded-2xl border border-gray-100 p-4"><p class="text-[10px] font-bold text-gray-400 uppercase">Sudah Dibayar</p><p class="text-base font-black text-blue-700 mt-1"><?= earn_rupiah($stats['dibayar']) ?></p></div>
                    <div class="bg-white rounded-2xl border border-gray-100 p-4"><p class="text-[10px] font-bold text-gray-400 uppercase">Menunggu Ditarik</p><p class="text-base font-black text-amber-600 mt-1"><?= earn_rupiah($stats['menunggu_tarik']) ?></p></div>
                    <div class="bg-white rounded-2xl border border-gray-100 p-4"><p class="text-[10px] font-bold text-gray-400 uppercase">Minta Penarikan</p><p class="text-base font-black text-red-600 mt-1"><?= (int)$stats['wd_requested'] ?> pengajuan</p></div>
                </div>

                <!-- Tab -->
                <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden" x-data="{ activeTab: 'penarikan' }">
                    <div class="flex border-b border-gray-100 overflow-x-auto no-scrollbar">
                        <button @click="activeTab = 'pengaturan'" :class="activeTab === 'pengaturan' ? 'text-emerald-700 border-emerald-600' : 'text-gray-400 border-transparent'" class="flex-1 min-w-[150px] px-4 py-3.5 text-xs font-bold border-b-2 transition whitespace-nowrap"><i class="fa-solid fa-gear mr-1.5"></i>Pengaturan</button>
                        <button @click="activeTab = 'penarikan'" :class="activeTab === 'penarikan' ? 'text-emerald-700 border-emerald-600' : 'text-gray-400 border-transparent'" class="flex-1 min-w-[150px] px-4 py-3.5 text-xs font-bold border-b-2 transition whitespace-nowrap"><i class="fa-solid fa-money-bill-transfer mr-1.5"></i>Pengajuan Penarikan<?php if ((int)$stats['wd_requested'] > 0): ?> <span class="ml-1 bg-red-500 text-white text-[10px] px-1.5 py-0.5 rounded-full"><?= (int)$stats['wd_requested'] ?></span><?php endif; ?></button>
                        <button @click="activeTab = 'riwayat'" :class="activeTab === 'riwayat' ? 'text-emerald-700 border-emerald-600' : 'text-gray-400 border-transparent'" class="flex-1 min-w-[150px] px-4 py-3.5 text-xs font-bold border-b-2 transition whitespace-nowrap"><i class="fa-solid fa-receipt mr-1.5"></i>Riwayat Penjualan</button>
                    </div>

                    <div x-show="activeTab === 'pengaturan'" class="p-5" x-transition style="display: none;">
                    <h2 class="text-sm font-bold text-gray-800 mb-4"><i class="fa-solid fa-gear text-gray-400 mr-2"></i>Pengaturan Bagi Hasil</h2>
                    <form method="POST" class="flex flex-wrap items-end gap-4">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="act" value="save_settings">
                        <div><label class="block text-[11px] font-bold text-gray-500 mb-1">Komisi Platform (%)</label>
                            <input type="number" name="commission_pct" min="0" max="100" step="0.5" value="<?= htmlspecialchars($commission_pct) ?>" class="w-32 px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-emerald-500"></div>
                        <div><label class="block text-[11px] font-bold text-gray-500 mb-1">Min. Penarikan (Rp)</label>
                            <input type="number" name="min_withdrawal" min="0" step="1000" value="<?= htmlspecialchars($min_withdrawal) ?>" class="w-40 px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-emerald-500"></div>
                        <div><label class="block text-[11px] font-bold text-gray-500 mb-1">Biaya Admin / Penarikan (Rp)</label>
                            <input type="number" name="withdrawal_fee" min="0" step="500" value="<?= htmlspecialchars($withdrawal_fee) ?>" class="w-40 px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-emerald-500"></div>
                        <label class="flex items-center gap-2 text-xs font-bold text-gray-600 cursor-pointer">
                            <input type="checkbox" name="admin_earn_enabled" value="1" <?= $admin_earn_enabled ? 'checked' : '' ?> class="w-4 h-4 accent-emerald-700">
                            Bagi hasil untuk konten milik admin
                        </label>
                        <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-5 py-2.5 rounded-xl">Simpan</button>
                        <p class="text-[11px] text-gray-400 w-full">Komisi berlaku untuk transaksi <em>berikutnya</em>; earning yang sudah tercatat tidak berubah. Bila toggle admin aktif, penjualan konten milik admin juga mencatat bagi hasil untuk akun admin tersebut.</p>
                    </form>
                    </div>

                    <div x-show="activeTab === 'penarikan'" class="p-5" x-transition>
                    <h2 class="text-sm font-bold text-gray-800 mb-4"><i class="fa-solid fa-money-bill-transfer text-gray-400 mr-2"></i>Pengajuan Penarikan</h2>
                    <div class="overflow-x-auto -mx-5 px-5">
                    <table class="w-full text-xs min-w-[860px]">
                        <thead><tr class="text-left text-[10px] uppercase text-gray-400 border-b">
                            <th class="py-2 pr-3">Pengajar</th><th class="py-2 pr-3">Nominal</th><th class="py-2 pr-3">Biaya Admin</th><th class="py-2 pr-3">Diterima</th><th class="py-2 pr-3">Bank</th><th class="py-2 pr-3">Status</th><th class="py-2 pr-3">Diajukan</th><th class="py-2">Aksi</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($withdrawals as $w): ?>
                            <tr class="border-b border-gray-50">
                                <td class="py-2.5 pr-3 font-bold text-gray-700"><?= htmlspecialchars($w['teacher_name'] ?? '-') ?></td>
                                <td class="py-2.5 pr-3 font-black"><?= earn_rupiah($w['amount']) ?></td>
                                <td class="py-2.5 pr-3 text-gray-500"><?= earn_rupiah($w['admin_fee'] ?? 0) ?></td>
                                <td class="py-2.5 pr-3 font-bold text-emerald-700"><?= earn_rupiah($w['amount'] - ($w['admin_fee'] ?? 0)) ?></td>
                                <td class="py-2.5 pr-3 text-gray-500"><?= htmlspecialchars($w['bank_name']) ?> · <?= htmlspecialchars($w['account_number']) ?><br><span class="text-[10px]">a.n. <?= htmlspecialchars($w['account_name']) ?></span></td>
                                <td class="py-2.5 pr-3"><span class="px-2 py-1 rounded-full text-[10px] font-bold <?= $wd_badge[$w['status']] ?? '' ?>"><?= ucfirst($w['status']) ?></span>
                                    <?php if (!empty($w['admin_note'])): ?><p class="text-[10px] text-gray-400 mt-1"><?= htmlspecialchars($w['admin_note']) ?></p><?php endif; ?></td>
                                <td class="py-2.5 pr-3 text-gray-500 text-[11px]"><?= htmlspecialchars($w['requested_at']) ?></td>
                                <td class="py-2.5">
                                    <?php if ($w['status'] === 'requested'): ?>
                                    <form method="POST" class="flex flex-wrap gap-1.5 items-center">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="act" value="withdraw_decide">
                                        <input type="hidden" name="wid" value="<?= (int)$w['id'] ?>">
                                        <input type="text" name="admin_note" placeholder="Catatan (opsional)" class="w-28 px-2 py-1.5 text-[11px] border border-gray-200 rounded-lg">
                                        <button name="decision" value="approve" class="bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-bold px-3 py-1.5 rounded-lg">Setujui</button>
                                        <button name="decision" value="reject" class="bg-white border border-red-300 text-red-600 hover:bg-red-50 text-[11px] font-bold px-3 py-1.5 rounded-lg" onclick="return confirm('Tolak penarikan ini?')">Tolak</button>
                                    </form>
                                    <?php elseif ($w['status'] === 'approved'): ?>
                                    <form method="POST" onsubmit="return confirm('Transfer sudah dilakukan?')">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="act" value="withdraw_decide">
                                        <input type="hidden" name="wid" value="<?= (int)$w['id'] ?>">
                                        <button name="decision" value="paid" class="bg-blue-600 hover:bg-blue-700 text-white text-[11px] font-bold px-3 py-1.5 rounded-lg">Sudah Dibayar</button>
                                    </form>
                                    <?php else: ?><span class="text-gray-300">—</span><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$withdrawals): ?><tr><td colspan="8" class="py-6 text-center text-gray-400">Belum ada pengajuan penarikan.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                    </div>

                    <div x-show="activeTab === 'riwayat'" class="p-5" x-transition style="display: none;">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-bold text-gray-800"><i class="fa-solid fa-receipt text-gray-400 mr-2"></i>Riwayat Penjualan &amp; Bagi Hasil</h2>
                        <form method="GET" class="flex gap-2">
                            <select name="f_status" onchange="this.form.submit()" class="text-xs border border-gray-200 rounded-xl px-3 py-2">
                                <option value="">Semua status</option>
                                <?php foreach (['pending'=>'Pending','available'=>'Tersedia','withdrawn'=>'Ditarik','void'=>'Void'] as $k=>$v): ?>
                                <option value="<?= $k ?>" <?= $f_status===$k?'selected':'' ?>><?= $v ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                    <div class="overflow-x-auto -mx-5 px-5">
                    <table class="w-full text-xs min-w-[760px]">
                        <thead><tr class="text-left text-[10px] uppercase text-gray-400 border-b">
                            <th class="py-2 pr-3">Tanggal</th><th class="py-2 pr-3">Pengajar</th><th class="py-2 pr-3">Konten</th><th class="py-2 pr-3">Harga</th><th class="py-2 pr-3">Komisi</th><th class="py-2 pr-3">Bersih</th><th class="py-2 pr-3">Status</th><th class="py-2">Aksi</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($earnings as $e): ?>
                            <tr class="border-b border-gray-50">
                                <td class="py-2.5 pr-3 text-gray-500 text-[11px]"><?= htmlspecialchars(substr($e['created_at'],0,16)) ?></td>
                                <td class="py-2.5 pr-3 font-bold text-gray-700"><?= htmlspecialchars($e['teacher_name'] ?? '-') ?></td>
                                <td class="py-2.5 pr-3"><span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-500 font-bold uppercase"><?= htmlspecialchars($e['item_type']) ?></span> <?= htmlspecialchars($e['item_name']) ?></td>
                                <td class="py-2.5 pr-3"><?= earn_rupiah($e['gross']) ?></td>
                                <td class="py-2.5 pr-3 text-gray-500"><?= rtrim(rtrim($e['commission_pct'],'0'),'.') ?>%</td>
                                <td class="py-2.5 pr-3 font-black text-emerald-700"><?= earn_rupiah($e['net']) ?></td>
                                <td class="py-2.5 pr-3"><span class="px-2 py-1 rounded-full text-[10px] font-bold <?= $st_badge[$e['status']] ?? '' ?>"><?= ucfirst($e['status']) ?></span></td>
                                <td class="py-2.5">
                                    <?php if ($e['status'] === 'pending'): ?>
                                    <form method="POST" onsubmit="return confirm('Void earning ini? (untuk refund/pembatalan)')">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="act" value="void_earning">
                                        <input type="hidden" name="eid" value="<?= (int)$e['id'] ?>">
                                        <button class="text-[11px] font-bold text-red-500 hover:text-red-700">Void</button>
                                    </form>
                                    <?php else: ?><span class="text-gray-300">—</span><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$earnings): ?><tr><td colspan="8" class="py-6 text-center text-gray-400">Belum ada penjualan tercatat.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
