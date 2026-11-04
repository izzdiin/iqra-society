<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
check_access(['super_administrator', 'administrator']);

// ===== Filter =====
$f_tipe   = $_GET['tipe'] ?? '';
$f_status = $_GET['status'] ?? '';
$f_from   = $_GET['from'] ?? '';
$f_to     = $_GET['to'] ?? '';
$f_q      = trim($_GET['q'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset   = ($page - 1) * $per_page;

$valid_tipe = ['donasi', 'buku', 'kelas'];
$valid_status = ['lunas', 'belum', 'gagal'];
if (!in_array($f_tipe, $valid_tipe)) $f_tipe = '';
if (!in_array($f_status, $valid_status)) $f_status = '';

// Normalisasi status donasi
function donasi_norm($s) {
    $s = strtolower($s);
    if (in_array($s, ['settlement', 'capture'])) return 'lunas';
    if ($s === 'pending') return 'belum';
    return 'gagal';
}
function trx_norm($s) {
    $s = strtoupper($s);
    if ($s === 'PAID') return 'lunas';
    if ($s === 'UNPAID') return 'belum';
    return 'gagal';
}

// ===== Ambil data donasi =====
$rows = [];
if ($f_tipe === '' || $f_tipe === 'donasi') {
    $w = [];
    $p = [];
    $t = '';
    if ($f_from !== '') { $w[] = "created_at >= ?"; $p[] = $f_from . ' 00:00:00'; $t .= 's'; }
    if ($f_to !== '')   { $w[] = "created_at <= ?"; $p[] = $f_to . ' 23:59:59'; $t .= 's'; }
    if ($f_q !== '')    { $w[] = "(order_id LIKE ? OR donor_name LIKE ? OR donor_email LIKE ?)"; $q = "%$f_q%"; $p[] = $q; $p[] = $q; $p[] = $q; $t .= 'sss'; }
    $sql = "SELECT id, order_id AS ref, donor_name AS ket, amount, payment_type AS gateway, transaction_status AS st, created_at, user_id FROM donasi_transactions";
    if ($w) $sql .= " WHERE " . implode(' AND ', $w);
    $sql .= " ORDER BY created_at DESC";
    $st = $conn->prepare($sql);
    if ($p) $st->bind_param($t, ...$p);
    $st->execute();
    $r = $st->get_result();
    while ($row = $r->fetch_assoc()) {
        $row['tipe'] = 'donasi';
        $row['status_norm'] = donasi_norm($row['st']);
        $rows[] = $row;
    }
    $st->close();
}

// ===== Ambil data pembelian buku/kelas =====
if ($f_tipe === '' || $f_tipe === 'buku' || $f_tipe === 'kelas') {
    $w = [];
    $p = [];
    $t = '';
    if ($f_tipe === 'buku' || $f_tipe === 'kelas') { $w[] = "item_type = ?"; $p[] = $f_tipe; $t .= 's'; }
    if ($f_from !== '') { $w[] = "created_at >= ?"; $p[] = $f_from . ' 00:00:00'; $t .= 's'; }
    if ($f_to !== '')   { $w[] = "created_at <= ?"; $p[] = $f_to . ' 23:59:59'; $t .= 's'; }
    if ($f_q !== '')    { $w[] = "(merchant_ref LIKE ? OR reference_id LIKE ? OR item_name LIKE ?)"; $q = "%$f_q%"; $p[] = $q; $p[] = $q; $p[] = $q; $t .= 'sss'; }
    $sql = "SELECT id, merchant_ref AS ref, item_name AS ket, amount, payment_gateway AS gateway, payment_status AS st, created_at, user_id, item_type FROM transactions";
    if ($w) $sql .= " WHERE " . implode(' AND ', $w);
    $sql .= " ORDER BY created_at DESC";
    $st = $conn->prepare($sql);
    if ($p) $st->bind_param($t, ...$p);
    $st->execute();
    $r = $st->get_result();
    while ($row = $r->fetch_assoc()) {
        $row['tipe'] = $row['item_type'];
        $row['status_norm'] = trx_norm($row['st']);
        $rows[] = $row;
    }
    $st->close();
}

// Filter status + urut terbaru
if ($f_status !== '') {
    $rows = array_values(array_filter($rows, fn($x) => $x['status_norm'] === $f_status));
}
usort($rows, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));

$total = count($rows);
$total_pages = max(1, (int)ceil($total / $per_page));
if ($page > $total_pages) $page = $total_pages;
$rows = array_slice($rows, ($page - 1) * $per_page, $per_page);

function qstr($over = []) {
    $q = array_merge($_GET, $over);
    return '?' . http_build_query($q);
}

$badge_tipe = ['donasi' => 'bg-pink-100 text-pink-700', 'buku' => 'bg-violet-100 text-violet-700', 'kelas' => 'bg-amber-100 text-amber-700'];
$label_tipe = ['donasi' => 'Donasi', 'buku' => 'Buku', 'kelas' => 'Kelas'];
$badge_st = ['lunas' => 'bg-emerald-100 text-emerald-700', 'belum' => 'bg-amber-100 text-amber-700', 'gagal' => 'bg-red-100 text-red-700'];
$label_st = ['lunas' => 'Lunas', 'belum' => 'Belum Bayar', 'gagal' => 'Gagal'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Semua Transaksi - Admin IQRA</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
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
                <h1 class="text-base md:text-lg font-extrabold text-gray-800">Semua Transaksi</h1>
            </div>
            <div class="flex items-center gap-2 md:gap-3">
                <?php include 'notif_dropdown.php'; ?>
            </div>
        </header>

        <main class="p-4 md:p-8 space-y-5 max-w-7xl">
            <!-- Filter -->
            <form method="GET" class="bg-white rounded-2xl border border-gray-100 p-4">
                <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Tipe</label>
                        <select name="tipe" class="w-full text-xs border border-gray-200 rounded-xl px-3 py-2 focus:outline-none focus:border-emerald-500">
                            <option value="">Semua</option>
                            <option value="donasi" <?= $f_tipe === 'donasi' ? 'selected' : '' ?>>Donasi</option>
                            <option value="buku" <?= $f_tipe === 'buku' ? 'selected' : '' ?>>Buku</option>
                            <option value="kelas" <?= $f_tipe === 'kelas' ? 'selected' : '' ?>>Kelas</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Status</label>
                        <select name="status" class="w-full text-xs border border-gray-200 rounded-xl px-3 py-2 focus:outline-none focus:border-emerald-500">
                            <option value="">Semua</option>
                            <option value="lunas" <?= $f_status === 'lunas' ? 'selected' : '' ?>>Lunas</option>
                            <option value="belum" <?= $f_status === 'belum' ? 'selected' : '' ?>>Belum Bayar</option>
                            <option value="gagal" <?= $f_status === 'gagal' ? 'selected' : '' ?>>Gagal</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Dari</label>
                        <input type="date" name="from" value="<?= htmlspecialchars($f_from) ?>" class="w-full text-xs border border-gray-200 rounded-xl px-3 py-2 focus:outline-none focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Sampai</label>
                        <input type="date" name="to" value="<?= htmlspecialchars($f_to) ?>" class="w-full text-xs border border-gray-200 rounded-xl px-3 py-2 focus:outline-none focus:border-emerald-500">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">No. Transaksi / Referensi</label>
                        <div class="flex gap-2">
                            <input type="text" name="q" value="<?= htmlspecialchars($f_q) ?>" placeholder="Cari no. transaksi, nama, item..." class="flex-1 text-xs border border-gray-200 rounded-xl px-3 py-2 focus:outline-none focus:border-emerald-500">
                            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-4 rounded-xl"><i class="fa-solid fa-magnifying-glass"></i></button>
                            <?php if ($f_tipe || $f_status || $f_from || $f_to || $f_q): ?>
                            <a href="transaksi.php" class="bg-gray-100 hover:bg-gray-200 text-gray-600 text-xs font-bold px-4 rounded-xl flex items-center"><i class="fa-solid fa-rotate-left"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Tabel -->
            <div class="bg-white rounded-2xl border border-gray-100 p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-bold text-gray-800"><i class="fa-solid fa-receipt text-gray-400 mr-2"></i>Daftar Transaksi</h2>
                    <span class="text-[11px] text-gray-400"><?= number_format($total) ?> transaksi</span>
                </div>
                <div class="overflow-x-auto -mx-5 px-5 no-scrollbar">
                <table class="w-full text-xs min-w-[820px]">
                    <thead><tr class="text-left text-[10px] uppercase text-gray-400 border-b">
                        <th class="py-2 pr-3">Tanggal</th><th class="py-2 pr-3">No. Transaksi</th><th class="py-2 pr-3">Tipe</th><th class="py-2 pr-3">Keterangan</th><th class="py-2 pr-3">Nominal</th><th class="py-2 pr-3">Gateway</th><th class="py-2">Status</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50/50">
                            <td class="py-2.5 pr-3 text-gray-500 whitespace-nowrap"><?= date('d M Y H:i', strtotime($r['created_at'])) ?></td>
                            <td class="py-2.5 pr-3 font-mono text-[11px] text-gray-700"><?= htmlspecialchars($r['ref']) ?></td>
                            <td class="py-2.5 pr-3"><span class="text-[10px] font-bold px-2 py-0.5 rounded-full <?= $badge_tipe[$r['tipe']] ?? 'bg-gray-100 text-gray-600' ?>"><?= $label_tipe[$r['tipe']] ?? $r['tipe'] ?></span></td>
                            <td class="py-2.5 pr-3 text-gray-700"><?= htmlspecialchars($r['ket']) ?></td>
                            <td class="py-2.5 pr-3 font-black text-gray-800 whitespace-nowrap">Rp <?= number_format((float)$r['amount'], 0, ',', '.') ?></td>
                            <td class="py-2.5 pr-3 text-gray-500 capitalize"><?= htmlspecialchars($r['gateway'] ?: '-') ?></td>
                            <td class="py-2.5"><span class="text-[10px] font-bold px-2 py-0.5 rounded-full <?= $badge_st[$r['status_norm']] ?>"><?= $label_st[$r['status_norm']] ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$rows): ?><tr><td colspan="7" class="py-8 text-center text-gray-400">Tidak ada transaksi yang cocok dengan filter.</td></tr><?php endif; ?>
                    </tbody>
                </table>
                </div>
                <?php if ($total_pages > 1): ?>
                <div class="flex items-center justify-between mt-4 pt-3 border-t border-gray-100">
                    <span class="text-[11px] text-gray-400">Halaman <?= $page ?> dari <?= $total_pages ?></span>
                    <div class="flex gap-2">
                        <?php if ($page > 1): ?><a href="<?= qstr(['page' => $page - 1]) ?>" class="text-xs font-bold px-3 py-1.5 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50">Sebelumnya</a><?php endif; ?>
                        <?php if ($page < $total_pages): ?><a href="<?= qstr(['page' => $page + 1]) ?>" class="text-xs font-bold px-3 py-1.5 rounded-xl bg-emerald-700 text-white hover:bg-emerald-800">Berikutnya</a><?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>
</body>
</html>
