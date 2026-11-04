<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/earnings_helper.php';
check_access(['super_administrator', 'administrator', 'pengajar']);

$teacher_id = (int)($_SESSION['user_id'] ?? 0);
$success = '';
$error = '';

earn_refresh_status($conn);
$min_withdrawal = (float)earn_setting($conn, 'min_withdrawal', '50000');
$commission_pct = (float)earn_setting($conn, 'commission_pct', '20');
$withdrawal_fee = (float)earn_setting($conn, 'withdrawal_fee', '7500');

// Notif diskusi (header standar)
$active_discussions = 0;
if ($conn->query("SHOW TABLES LIKE 'discussions'")->num_rows > 0) {
    $st_d = $conn->prepare("SELECT COUNT(*) AS total FROM discussions WHERE instructor_id = ? AND status = 'pending'");
    if ($st_d) {
        $st_d->bind_param("i", $teacher_id);
        $st_d->execute();
        $active_discussions = (int)($st_d->get_result()->fetch_assoc()['total'] ?? 0);
        $st_d->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'withdraw') {
            $amount = (float)($_POST['amount'] ?? 0);
            $bank_name = sanitize($_POST['bank_name'] ?? '');
            $account_number = preg_replace('/[^0-9]/', '', $_POST['account_number'] ?? '');
            $account_name = sanitize($_POST['account_name'] ?? '');
            $bal = earn_balance($conn, $teacher_id);
            if ($amount < $min_withdrawal) {
                $error = 'Minimal penarikan ' . earn_rupiah($min_withdrawal) . '.';
            } elseif ($amount > $bal['available']) {
                $error = 'Saldo tersedia tidak mencukupi (' . earn_rupiah($bal['available']) . ').';
            } elseif ($bank_name === '' || $account_number === '' || $account_name === '') {
                $error = 'Lengkapi data bank tujuan.';
            } else {
                $st = $conn->prepare("INSERT INTO withdrawals (teacher_id, amount, admin_fee, bank_name, account_number, account_name) VALUES (?, ?, ?, ?, ?, ?)");
                $st->bind_param("iddsss", $teacher_id, $amount, $withdrawal_fee, $bank_name, $account_number, $account_name);
                if ($st->execute()) {
                    $success = 'Pengajuan penarikan ' . earn_rupiah($amount) . ' berhasil dikirim. Menunggu persetujuan admin.';
                    if (function_exists('log_activity')) log_activity('CREATE', 'keuangan', "Ajukan penarikan Rp " . number_format($amount, 0, ',', '.'));
                } else {
                    $error = 'Gagal mengajukan penarikan.';
                }
                $st->close();
            }
        } elseif ($action === 'cancel_withdraw') {
            $wid = (int)($_POST['wid'] ?? 0);
            $st = $conn->prepare("UPDATE withdrawals SET status = 'cancelled' WHERE id = ? AND teacher_id = ? AND status = 'requested'");
            $st->bind_param("ii", $wid, $teacher_id);
            $st->execute();
            if ($st->affected_rows > 0) $success = 'Pengajuan penarikan dibatalkan.';
            else $error = 'Pengajuan tidak ditemukan atau sudah diproses.';
            $st->close();
        }
    }
}

$bal = earn_balance($conn, $teacher_id);

$st = $conn->prepare("SELECT item_name, item_type, gross, commission_pct, net, status, created_at, available_at FROM seller_earnings WHERE teacher_id = ? ORDER BY id DESC LIMIT 100");
$st->bind_param("i", $teacher_id);
$st->execute();
$sales = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

$st = $conn->prepare("SELECT * FROM withdrawals WHERE teacher_id = ? ORDER BY id DESC LIMIT 50");
$st->bind_param("i", $teacher_id);
$st->execute();
$wds = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

$st_badge = [
    'pending' => 'bg-amber-100 text-amber-700', 'available' => 'bg-emerald-100 text-emerald-700',
    'withdrawn' => 'bg-blue-100 text-blue-700', 'void' => 'bg-gray-100 text-gray-500',
];
$wd_badge = [
    'requested' => 'bg-amber-100 text-amber-700', 'approved' => 'bg-blue-100 text-blue-700',
    'paid' => 'bg-emerald-100 text-emerald-700', 'rejected' => 'bg-red-100 text-red-700',
    'cancelled' => 'bg-gray-100 text-gray-500',
];
$wd_label = ['requested' => 'Menunggu', 'approved' => 'Disetujui', 'paid' => 'Dibayar', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan'];
$tab = $_GET['tab'] ?? 'tarik';
if (!in_array($tab, ['tarik', 'riwayat', 'terjual'], true)) $tab = 'tarik';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keuangan - Panel Pengajar</title>
    <link rel="icon" type="image/x-icon" href="../asset/img/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50 font-sans antialiased" x-data="{ sidebarOpen: false, activeTab: '<?= $tab ?>' }">
    <div x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false" class="fixed inset-0 bg-gray-900/80 z-40 md:hidden" style="display: none;"></div>

    <aside x-show="sidebarOpen" x-transition:enter="transition ease-in-out duration-300 transform" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in-out duration-300 transform" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full" class="fixed inset-y-0 left-0 w-64 bg-emerald-950 text-white z-50 flex flex-col justify-between md:hidden" style="display: none;">
        <div class="p-6">
            <div class="flex items-center justify-between pb-6 mb-6 border-b border-emerald-900">
                <div class="flex items-center gap-3 overflow-hidden">
                    <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold text-sm shrink-0"><?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?></div>
                    <div class="flex flex-col truncate">
                        <span class="text-xs font-bold text-white truncate"><?= htmlspecialchars($_SESSION['fullname'] ?? $_SESSION['username'] ?? 'Pengajar') ?></span>
                        <span class="text-[10px] text-lime-400 font-semibold uppercase tracking-wider truncate">Pengajar</span>
                    </div>
                </div>
                <button @click="sidebarOpen = false" class="text-emerald-400 hover:text-white p-1"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <?php include 'sidebar_menu.php'; ?>
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
                    <div><div class="text-xs font-bold">IQRA' Society</div><div class="text-[10px] text-emerald-400">Panel Pengajar</div></div>
                </div>
                <?php include 'sidebar_menu.php'; ?>
            </div>
            <div class="p-6 border-t border-emerald-900">
                <a href="../logout.php" class="flex items-center gap-2 text-xs font-bold text-red-400 hover:text-red-300"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
        </aside>

        <div class="flex-1 min-w-0">
            <!-- Navbar Header (standar laman pengajar) -->
            <header class="sticky top-0 z-30 bg-white/95 backdrop-blur-md border-b border-gray-100 px-4 md:px-6 py-4 flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-3">
                    <button @click="sidebarOpen = true" class="md:hidden text-gray-600 hover:text-gray-900 p-2 rounded-lg hover:bg-gray-100">
                        <i class="fa-solid fa-bars text-lg"></i>
                    </button>
                    <h1 class="text-base md:text-lg font-extrabold text-gray-800">Keuangan</h1>
                </div>
                <div class="flex items-center gap-2">
                    <a href="../index.php" target="_blank" title="Lihat Website IQRA' Society" class="p-2.5 rounded-xl text-gray-500 hover:text-emerald-700 hover:bg-emerald-50 transition flex items-center gap-1.5 text-xs font-bold">
                        <i class="fa-solid fa-globe text-base"></i>
                        <span class="hidden sm:inline">Web IQRA'</span>
                    </a>

                    <div class="relative" x-data="{ notifDropdown: false }">
                        <button @click="notifDropdown = !notifDropdown" class="relative p-2.5 rounded-xl text-gray-500 hover:text-gray-800 hover:bg-gray-100 transition focus:outline-none">
                            <i class="fa-regular fa-bell text-lg"></i>
                            <?php if (($active_discussions ?? 0) > 0): ?>
                                <span class="absolute top-2 right-2 w-2.5 h-2.5 bg-red-500 rounded-full ring-2 ring-white animate-pulse"></span>
                            <?php endif; ?>
                        </button>

                        <div x-show="notifDropdown"
                             @click.outside="notifDropdown = false"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute right-0 mt-2 w-72 bg-white rounded-2xl shadow-xl border border-gray-100 py-3 z-50"
                             style="display: none;">

                            <div class="px-4 pb-2 border-b border-gray-100 flex items-center justify-between">
                                <span class="text-xs font-bold text-gray-800">Notifikasi</span>
                                <?php if (($active_discussions ?? 0) > 0): ?>
                                    <span class="bg-amber-100 text-amber-800 text-[10px] font-bold px-2 py-0.5 rounded-full"><?= $active_discussions ?> Baru</span>
                                <?php endif; ?>
                            </div>

                            <div class="divide-y divide-gray-50 max-h-60 overflow-y-auto">
                                <?php if (($active_discussions ?? 0) > 0): ?>
                                    <a href="discussions.php" class="flex items-start gap-3 p-3 hover:bg-emerald-50/50 transition">
                                        <div class="w-8 h-8 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center shrink-0 mt-0.5 text-xs">
                                            <i class="fa-solid fa-comments"></i>
                                        </div>
                                        <div>
                                            <p class="text-xs font-bold text-gray-800">Diskusi Baru</p>
                                            <p class="text-[10px] text-gray-500 mt-0.5">Ada <strong><?= $active_discussions ?></strong> pertanyaan menunggu tanggapan Anda.</p>
                                        </div>
                                    </a>
                                <?php else: ?>
                                    <div class="p-4 text-center text-xs text-gray-400">
                                        <i class="fa-regular fa-circle-check text-emerald-500 text-base mb-1 block"></i>
                                        Belum ada pertanyaan baru.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="p-6 md:p-6 w-full space-y-6">
                <?php if ($error): ?><div class="bg-red-50 border border-red-200 text-red-700 text-xs font-bold rounded-xl px-4 py-3"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <?php if ($success): ?><div class="bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-bold rounded-xl px-4 py-3"><?= htmlspecialchars($success) ?></div><?php endif; ?>

                <!-- Saldo -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-emerald-700 rounded-2xl p-4 text-white">
                        <p class="text-[10px] font-bold uppercase text-emerald-200">Saldo Tersedia</p>
                        <p class="text-2xl font-black mt-1"><?= earn_rupiah($bal['available']) ?></p>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-100 p-4">
                        <p class="text-[10px] font-bold uppercase text-gray-400">Pending (7 hari)</p>
                        <p class="text-lg font-black text-amber-600 mt-1"><?= earn_rupiah($bal['pending']) ?></p>
                    </div>
                </div>
                <?php if ($bal['reserved'] > 0): ?>
                <div class="bg-amber-50 border border-amber-200 text-amber-700 text-xs rounded-xl px-4 py-3">
                    <i class="fa-solid fa-hourglass-half mr-1"></i> <?= earn_rupiah($bal['reserved']) ?> sedang dalam pengajuan penarikan menunggu persetujuan admin.
                </div>
                <?php endif; ?>

                <!-- Tab -->
                <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
                    <div class="flex border-b border-gray-100 overflow-x-auto">
                        <button @click="activeTab = 'tarik'" :class="activeTab === 'tarik' ? 'text-emerald-700 border-emerald-600' : 'text-gray-400 border-transparent'" class="flex-1 min-w-[130px] px-4 py-3.5 text-xs font-bold border-b-2 transition whitespace-nowrap"><i class="fa-solid fa-money-bill-transfer mr-1.5"></i>Tarik Saldo</button>
                        <button @click="activeTab = 'riwayat'" :class="activeTab === 'riwayat' ? 'text-emerald-700 border-emerald-600' : 'text-gray-400 border-transparent'" class="flex-1 min-w-[130px] px-4 py-3.5 text-xs font-bold border-b-2 transition whitespace-nowrap"><i class="fa-solid fa-clock-rotate-left mr-1.5"></i>Riwayat Penarikan</button>
                        <button @click="activeTab = 'terjual'" :class="activeTab === 'terjual' ? 'text-emerald-700 border-emerald-600' : 'text-gray-400 border-transparent'" class="flex-1 min-w-[130px] px-4 py-3.5 text-xs font-bold border-b-2 transition whitespace-nowrap"><i class="fa-solid fa-receipt mr-1.5"></i>Konten Terjual</button>
                    </div>

                    <!-- Tab: Tarik Saldo -->
                    <div x-show="activeTab === 'tarik'" class="p-5" x-transition>
                        <p class="text-[11px] text-gray-400 mb-4">Minimal penarikan <?= earn_rupiah($min_withdrawal) ?>. Biaya admin <?= earn_rupiah($withdrawal_fee) ?> per penarikan (dipotong dari nominal). Contoh: tarik <?= earn_rupiah(100000) ?> &rarr; diterima <?= earn_rupiah(100000 - $withdrawal_fee) ?>. </p>
                        <form method="POST" class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-2xl">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                            <input type="hidden" name="action" value="withdraw">
                            <div><label class="block text-[11px] font-bold text-gray-500 mb-1">Nominal (Rp)</label>
                                <input type="number" name="amount" min="<?= (int)$min_withdrawal ?>" max="<?= (int)$bal['available'] ?>" step="1000" required placeholder="Contoh: 100000" class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-emerald-500"></div>
                            <div><label class="block text-[11px] font-bold text-gray-500 mb-1">Bank / E-Wallet</label>
                                <input type="text" name="bank_name" required placeholder="Contoh: BCA, Mandiri, DANA" class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-emerald-500"></div>
                            <div><label class="block text-[11px] font-bold text-gray-500 mb-1">Nomor Rekening</label>
                                <input type="text" name="account_number" required inputmode="numeric" placeholder="Nomor rekening / akun" class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-emerald-500"></div>
                            <div><label class="block text-[11px] font-bold text-gray-500 mb-1">Nama Pemilik Rekening</label>
                                <input type="text" name="account_name" required placeholder="Nama sesuai rekening" class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-emerald-500"></div>
                            <div class="sm:col-span-2">
                                <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-6 py-3 rounded-xl <?= $bal['available'] < $min_withdrawal ? 'opacity-40 cursor-not-allowed' : '' ?>" <?= $bal['available'] < $min_withdrawal ? 'disabled' : '' ?>>Ajukan Penarikan</button>
                            </div>
                        </form>
                    </div>

                    <!-- Tab: Riwayat Penarikan -->
                    <div x-show="activeTab === 'riwayat'" class="p-5" x-transition style="display: none;">
                        <div class="overflow-x-auto -mx-5 px-5">
                        <table class="w-full text-xs min-w-[560px]">
                            <thead><tr class="text-left text-[10px] uppercase text-gray-400 border-b">
                                <th class="py-2 pr-3">Tanggal</th><th class="py-2 pr-3">Nominal</th><th class="py-2 pr-3">Diterima</th><th class="py-2 pr-3">Tujuan</th><th class="py-2 pr-3">Status</th><th class="py-2">Aksi</th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($wds as $w): ?>
                                <tr class="border-b border-gray-50">
                                    <td class="py-2.5 pr-3 text-gray-500 text-[11px]"><?= htmlspecialchars(substr($w['requested_at'],0,16)) ?></td>
                                    <td class="py-2.5 pr-3 font-black"><?= earn_rupiah($w['amount']) ?></td>
                                    <td class="py-2.5 pr-3 text-emerald-700 font-bold"><?= earn_rupiah($w['amount'] - ($w['admin_fee'] ?? 0)) ?></td>
                                    <td class="py-2.5 pr-3 text-gray-500"><?= htmlspecialchars($w['bank_name']) ?> · <?= htmlspecialchars($w['account_number']) ?></td>
                                    <td class="py-2.5 pr-3"><span class="px-2 py-1 rounded-full text-[10px] font-bold <?= $wd_badge[$w['status']] ?? '' ?>"><?= $wd_label[$w['status']] ?? $w['status'] ?></span>
                                        <?php if (!empty($w['admin_note'])): ?><p class="text-[10px] text-gray-400 mt-1"><?= htmlspecialchars($w['admin_note']) ?></p><?php endif; ?></td>
                                    <td class="py-2.5">
                                        <?php if ($w['status'] === 'requested'): ?>
                                        <form method="POST" onsubmit="return confirm('Batalkan pengajuan ini?')">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                                            <input type="hidden" name="action" value="cancel_withdraw">
                                            <input type="hidden" name="wid" value="<?= (int)$w['id'] ?>">
                                            <button class="text-[11px] font-bold text-red-500 hover:text-red-700">Batalkan</button>
                                        </form>
                                        <?php else: ?><span class="text-gray-300">—</span><?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$wds): ?><tr><td colspan="6" class="py-6 text-center text-gray-400">Belum ada penarikan.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                        </div>
                    </div>

                    <!-- Tab: Konten Terjual -->
                    <div x-show="activeTab === 'terjual'" class="p-5" x-transition style="display: none;">
                        <div class="overflow-x-auto -mx-5 px-5">
                        <table class="w-full text-xs min-w-[620px]">
                            <thead><tr class="text-left text-[10px] uppercase text-gray-400 border-b">
                                <th class="py-2 pr-3">Tanggal</th><th class="py-2 pr-3">Konten</th><th class="py-2 pr-3">Harga</th><th class="py-2 pr-3">Komisi</th><th class="py-2 pr-3">Bersih</th><th class="py-2">Status</th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($sales as $s): ?>
                                <tr class="border-b border-gray-50">
                                    <td class="py-2.5 pr-3 text-gray-500 text-[11px]"><?= htmlspecialchars(substr($s['created_at'],0,16)) ?></td>
                                    <td class="py-2.5 pr-3"><span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-500 font-bold uppercase"><?= htmlspecialchars($s['item_type']) ?></span> <?= htmlspecialchars($s['item_name']) ?></td>
                                    <td class="py-2.5 pr-3"><?= earn_rupiah($s['gross']) ?></td>
                                    <td class="py-2.5 pr-3 text-gray-500"><?= rtrim(rtrim($s['commission_pct'],'0'),'.') ?>%</td>
                                    <td class="py-2.5 pr-3 font-black text-emerald-700"><?= earn_rupiah($s['net']) ?></td>
                                    <td class="py-2.5"><span class="px-2 py-1 rounded-full text-[10px] font-bold <?= $st_badge[$s['status']] ?? '' ?>"><?= ucfirst($s['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$sales): ?><tr><td colspan="6" class="py-6 text-center text-gray-400">Belum ada konten terjual.</td></tr><?php endif; ?>
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
