<?php
require_once __DIR__ . '/../includes/auth_middleware.php';

check_access(['super_administrator', 'super_admin']);

// Filter
$f_module = sanitize($_GET['module'] ?? '');
$f_action = sanitize($_GET['action'] ?? '');
$f_search = sanitize($_GET['q'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 25;
$offset   = ($page - 1) * $per_page;

$where = [];
$params = [];
$types = '';
if ($f_module !== '') { $where[] = "module = ?"; $params[] = $f_module; $types .= 's'; }
if ($f_action !== '') { $where[] = "action = ?"; $params[] = $f_action; $types .= 's'; }
if ($f_search !== '') { $where[] = "(username LIKE ? OR fullname LIKE ? OR description LIKE ?)"; $s = "%$f_search%"; $params[] = $s; $params[] = $s; $params[] = $s; $types .= 'sss'; }
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$count_stmt = $conn->prepare("SELECT COUNT(*) FROM activity_logs $where_sql");
if ($params) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total = (int)$count_stmt->get_result()->fetch_row()[0];
$count_stmt->close();
$total_pages = max(1, (int)ceil($total / $per_page));

$stmt = $conn->prepare("SELECT * FROM activity_logs $where_sql ORDER BY id DESC LIMIT ? OFFSET ?");
$all_params = array_merge($params, [$per_page, $offset]);
$all_types = $types . 'ii';
$stmt->bind_param($all_types, ...$all_params);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$mod_stmt = $conn->query("SELECT DISTINCT module FROM activity_logs ORDER BY module");
$modules = $mod_stmt ? $mod_stmt->fetch_all(MYSQLI_ASSOC) : [];
$act_stmt = $conn->query("SELECT DISTINCT action FROM activity_logs ORDER BY action");
$actions = $act_stmt ? $act_stmt->fetch_all(MYSQLI_ASSOC) : [];

$pending_count_query = $conn->query("SELECT COUNT(id) FROM section_kelas WHERE status = 'pending'");
$pending_notifications = $pending_count_query ? (int) $pending_count_query->fetch_row()[0] : 0;
$pending_classes  = (int)($conn->query("SELECT COUNT(id) FROM section_kelas WHERE status = 'pending'")->fetch_row()[0] ?? 0);
$pending_activity = (int)($conn->query("SELECT COUNT(id) FROM section_kegiatan WHERE status = 'pending'")->fetch_row()[0] ?? 0);
$pending_book     = (int)($conn->query("SELECT COUNT(id) FROM section_buku WHERE status = 'pending'")->fetch_row()[0] ?? 0);
$pending_post     = (int)($conn->query("SELECT COUNT(id) FROM posts WHERE status = 'pending'")->fetch_row()[0] ?? 0);

$action_colors = [
    'CREATE'  => 'bg-green-100 text-green-700',
    'UPDATE'  => 'bg-blue-100 text-blue-700',
    'DELETE'  => 'bg-red-100 text-red-700',
    'APPROVE' => 'bg-emerald-100 text-emerald-700',
    'REJECT'  => 'bg-orange-100 text-orange-700',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Aktivitas - Iqra' Society</title>
    <link rel="icon" type="image/x-icon" href="../asset/img/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50 font-sans antialiased" x-data="{ sidebarOpen: false }">
    <div x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false" class="fixed inset-0 bg-gray-900/80 z-40 md:hidden" style="display: none;"></div>

    <aside x-show="sidebarOpen" x-transition:enter="transition ease-in-out duration-300 transform" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in-out duration-300 transform" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full" class="fixed inset-y-0 left-0 w-64 bg-emerald-950 text-white z-50 flex flex-col justify-between md:hidden" style="display: none;">
        <div class="p-6">
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

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col min-w-0">
        <header class="sticky top-0 z-30 bg-white/95 backdrop-blur-md border-b border-gray-100 px-4 md:px-6 py-4 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-3">
                <button @click="sidebarOpen = true" class="md:hidden text-gray-600 hover:text-gray-900 p-2 rounded-lg hover:bg-gray-100">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <h1 class="text-base md:text-lg font-extrabold text-gray-800">Log Aktivitas</h1>
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
                            <a href="../donatur" target="_blank" class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-gray-700 hover:bg-emerald-50 hover:text-emerald-600 transition"><i class="fa-solid fa-hand-holding-heart w-4 text-center text-gray-400"></i>Panel Donatur</a>
                            <a href="../pembelajar" target="_blank" class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-gray-700 hover:bg-emerald-50 hover:text-emerald-600 transition"><i class="fa-solid fa-users w-4 text-center text-gray-400"></i>Panel Member</a>
                        </div>
                    </div>
                </div>
                <div class="relative" x-data="{ notifDropdown: false }">
                    <button @click="notifDropdown = !notifDropdown" class="relative p-2.5 rounded-xl text-gray-500 hover:text-gray-800 hover:bg-gray-100 transition focus:outline-none" title="Notifikasi">
                        <i class="fa-regular fa-bell text-lg"></i>
                        <?php if ($pending_notifications > 0): ?>
                            <span class="absolute top-2 right-2 w-2.5 h-2.5 bg-red-500 rounded-full ring-2 ring-white animate-pulse"></span>
                        <?php endif; ?>
                    </button>
                    <div x-show="notifDropdown" @click.outside="notifDropdown = false" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute right-0 mt-2 w-72 bg-white rounded-2xl shadow-xl border border-gray-100 py-3 z-50" style="display: none;">
                        <div class="px-4 pb-2 border-b border-gray-100 flex items-center justify-between">
                            <span class="text-xs font-bold text-gray-800">Notifikasi</span>
                            <?php if ($pending_notifications > 0): ?><span class="bg-red-100 text-red-700 text-[10px] font-bold px-2 py-0.5 rounded-full"><?= $pending_notifications ?> Baru</span><?php endif; ?>
                        </div>
                        <div class="divide-y divide-gray-50 max-h-80 overflow-y-auto">
                            <?php if ($pending_notifications > 0): ?>
                                <?php if ($pending_classes > 0): ?>
                                <a href="approve_class.php" class="flex items-start gap-3 p-3 hover:bg-emerald-50/50 transition"><div class="w-8 h-8 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center shrink-0 mt-0.5 text-xs"><i class="fa-solid fa-graduation-cap"></i></div><div><p class="text-xs font-bold text-gray-800">Moderasi Kelas Baru</p><p class="text-[10px] text-gray-500 mt-0.5">Ada <strong><?= $pending_classes ?></strong> pengajuan kelas baru menunggu persetujuan Anda.</p></div></a>
                                <?php endif; ?>
                                <?php if ($pending_activity > 0): ?>
                                <a href="approve_kegiatan.php" class="flex items-start gap-3 p-3 hover:bg-emerald-50/50 transition"><div class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center shrink-0 mt-0.5 text-xs"><i class="fa-solid fa-calendar-days"></i></div><div><p class="text-xs font-bold text-gray-800">Moderasi Kegiatan Baru</p><p class="text-[10px] text-gray-500 mt-0.5">Ada <strong><?= $pending_activity ?></strong> kegiatan baru menunggu persetujuan.</p></div></a>
                                <?php endif; ?>
                                <?php if ($pending_book > 0): ?>
                                <a href="approve_buku.php" class="flex items-start gap-3 p-3 hover:bg-emerald-50/50 transition"><div class="w-8 h-8 rounded-full bg-purple-100 text-purple-700 flex items-center justify-center shrink-0 mt-0.5 text-xs"><i class="fa-solid fa-book"></i></div><div><p class="text-xs font-bold text-gray-800">Moderasi Buku Baru</p><p class="text-[10px] text-gray-500 mt-0.5">Ada <strong><?= $pending_book ?></strong> buku baru menunggu persetujuan.</p></div></a>
                                <?php endif; ?>
                                <?php if ($pending_post > 0): ?>
                                <a href="approve_post.php" class="flex items-start gap-3 p-3 hover:bg-emerald-50/50 transition"><div class="w-8 h-8 rounded-full bg-pink-100 text-pink-700 flex items-center justify-center shrink-0 mt-0.5 text-xs"><i class="fa-solid fa-newspaper"></i></div><div><p class="text-xs font-bold text-gray-800">Moderasi Postingan</p><p class="text-[10px] text-gray-500 mt-0.5">Ada <strong><?= $pending_post ?></strong> postingan baru menunggu persetujuan.</p></div></a>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="p-4 text-center text-xs text-gray-400"><i class="fa-regular fa-circle-check text-emerald-500 text-base mb-1 block"></i>Tidak ada notifikasi baru.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="p-4 md:p-8">

            <form method="GET" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-4 flex flex-wrap gap-3 items-end">
                <div>
                    <label class="text-[11px] font-bold text-gray-500 uppercase">Modul</label>
                    <select name="module" class="block mt-1 text-sm border border-gray-200 rounded-xl px-3 py-2">
                        <option value="">Semua</option>
                        <?php foreach ($modules as $m): ?>
                            <option value="<?= htmlspecialchars($m['module']) ?>" <?= $f_module === $m['module'] ? 'selected' : '' ?>><?= htmlspecialchars($m['module']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-[11px] font-bold text-gray-500 uppercase">Aksi</label>
                    <select name="action" class="block mt-1 text-sm border border-gray-200 rounded-xl px-3 py-2">
                        <option value="">Semua</option>
                        <?php foreach ($actions as $a): ?>
                            <option value="<?= htmlspecialchars($a['action']) ?>" <?= $f_action === $a['action'] ? 'selected' : '' ?>><?= htmlspecialchars($a['action']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex-1 min-w-[200px]">
                    <label class="text-[11px] font-bold text-gray-500 uppercase">Cari</label>
                    <input type="text" name="q" value="<?= htmlspecialchars($f_search) ?>" placeholder="username, nama, deskripsi..." class="block mt-1 w-full text-sm border border-gray-200 rounded-xl px-3 py-2">
                </div>
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-xl"><i class="fa-solid fa-filter mr-1"></i> Filter</button>
                <a href="log.php" class="text-sm text-gray-500 hover:text-gray-700 px-2 py-2">Reset</a>
            </form>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-[11px] uppercase text-gray-500 font-bold">
                            <tr>
                                <th class="text-left px-4 py-3">Waktu</th>
                                <th class="text-left px-4 py-3">Pelaku</th>
                                <th class="text-left px-4 py-3">Aksi</th>
                                <th class="text-left px-4 py-3">Modul</th>
                                <th class="text-left px-4 py-3">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($logs)): ?>
                                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400 text-xs italic">Belum ada log aktivitas.</td></tr>
                            <?php else: ?>
                                <?php foreach ($logs as $l): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap"><?= date('d M Y H:i', strtotime($l['created_at'])) ?></td>
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-gray-900 text-xs"><?= htmlspecialchars($l['fullname']) ?></div>
                                        <div class="text-[11px] text-gray-400">@<?= htmlspecialchars($l['username']) ?> • <?= htmlspecialchars($l['role']) ?></div>
                                    </td>
                                    <td class="px-4 py-3"><span class="text-[11px] font-bold px-2 py-1 rounded-full <?= $action_colors[$l['action']] ?? 'bg-gray-100 text-gray-600' ?>"><?= htmlspecialchars($l['action']) ?></span></td>
                                    <td class="px-4 py-3 text-xs text-gray-600"><?= htmlspecialchars($l['module']) ?></td>
                                    <td class="px-4 py-3 text-xs text-gray-700"><?= htmlspecialchars($l['description']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pages > 1): ?>
                <div class="flex items-center justify-between px-4 py-3 border-t border-gray-100 text-xs text-gray-500">
                    <span>Halaman <?= $page ?> dari <?= $total_pages ?> (<?= $total ?> data)</span>
                    <div class="flex gap-2">
                        <?php if ($page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="px-3 py-1.5 bg-gray-100 rounded-lg hover:bg-gray-200 font-bold">← Prev</a>
                        <?php endif; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="px-3 py-1.5 bg-gray-100 rounded-lg hover:bg-gray-200 font-bold">Next →</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
        </div>
    </div>
</body>
</html>
