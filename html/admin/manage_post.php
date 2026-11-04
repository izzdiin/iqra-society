<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
check_access(['super_administrator', 'administrator']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die("CSRF Invalid");
    $id = (int)($_POST['id'] ?? 0);
    $action = sanitize($_POST['action'] ?? '');
    $new_status = ($action === 'approve') ? 'published' : 'rejected';
    $stmt = $conn->prepare("UPDATE posts SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $id);
    $stmt->execute(); $stmt->close();
    $t = $conn->prepare("SELECT title FROM posts WHERE id = ?");
    $t->bind_param("i", $id); $t->execute();
    $r = $t->get_result()->fetch_assoc(); $t->close();
    $lbl = ($action === 'approve') ? 'Menyetujui' : 'Menolak';
    log_activity($action === 'approve' ? 'APPROVE' : 'REJECT', 'posting', "$lbl posting '" . ($r['title'] ?? ('ID '.$id)) . "'", $id);
    $back = $_POST['back'] ?? '';
    header('Location: ' . ($back === 'index' ? 'index.php' : 'manage_post.php')); exit();
}

$pending_count_query = $conn->query("SELECT COUNT(id) FROM section_kelas WHERE status = 'pending'");
$pending_notifications = $pending_count_query ? (int)$pending_count_query->fetch_row()[0] : 0;

$res = $conn->query("SELECT p.id, p.title, u.fullname as author FROM posts p LEFT JOIN users u ON p.author_id=u.id WHERE p.status='pending' ORDER BY p.id DESC");
$items_data = [];
$filter_options = []; $fr = $conn->query("SELECT DISTINCT u.fullname FROM posts p JOIN users u ON p.author_id=u.id WHERE p.status='pending'"); if ($fr) while($x=$fr->fetch_assoc()) $filter_options[] = $x['fullname'];
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $items_data[] = ['id'=>(int)$row['id'],'title'=>$row['title'],'filter_key'=>$row['author']??'','col1'=>$row['author']??'-'];
    }
}
$csrf_token = bin2hex(random_bytes(32)); $_SESSION['csrf_token'] = $csrf_token;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Posting - Iqra' Society</title>
    <link rel="icon" type="image/x-icon" href="../asset/img/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50 font-sans antialiased" x-data="{ sidebarOpen: false }">
<div x-show="sidebarOpen" x-transition @click="sidebarOpen = false" class="fixed inset-0 bg-gray-900/80 z-40 md:hidden" style="display:none"></div>
<aside x-show="sidebarOpen" x-transition class="fixed inset-y-0 left-0 w-64 bg-emerald-950 text-white z-50 flex flex-col justify-between md:hidden" style="display:none">
    <div class="p-6">
        <div class="flex items-center justify-between pb-6 mb-6 border-b border-emerald-900">
            <div class="flex items-center gap-3 overflow-hidden">
                <?php if (!empty($_SESSION['avatar']) && file_exists(__DIR__.'/../uploads/avatars/'.$_SESSION['avatar'])): ?>
                    <img src="../uploads/avatars/<?= htmlspecialchars($_SESSION['avatar']) ?>" class="w-10 h-10 rounded-full object-cover border border-emerald-600 shrink-0">
                <?php else: ?>
                    <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold text-sm shrink-0"><?= strtoupper(substr($_SESSION['username']??'U',0,1)) ?></div>
                <?php endif; ?>
                <div class="flex flex-col truncate">
                    <span class="text-xs font-bold text-white truncate"><?= htmlspecialchars($_SESSION['username']??'Pengguna') ?></span>
                    <span class="text-[10px] text-lime-400 font-semibold uppercase tracking-wider truncate"><?= htmlspecialchars($_SESSION['role']??'Guest') ?></span>
                </div>
            </div>
            <button @click="sidebarOpen = false" class="text-emerald-400 hover:text-white p-1"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>
        <?php include 'menu.php'; ?>
    </div>
</aside>
<div class="flex min-h-screen">
    <aside class="hidden md:flex w-64 bg-emerald-950 text-white flex-col justify-between shrink-0">
        <div class="p-6"><?php include 'menu.php'; ?></div>
    </aside>
    <div class="flex-1 flex flex-col min-w-0">
        <header class="sticky top-0 z-30 bg-white/95 backdrop-blur-md border-b border-gray-100 px-4 md:px-6 py-4 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-3">
                <button @click="sidebarOpen = true" class="md:hidden text-gray-600 hover:text-gray-900 p-2 rounded-lg hover:bg-gray-100"><i class="fa-solid fa-bars text-lg"></i></button>
                <h1 class="text-base md:text-lg font-extrabold text-gray-800">Kelola Posting</h1>
            </div>
            <div class="flex items-center gap-2 md:gap-3">
                <!-- DROPDOWN LINK QUICK ACCESS -->
                <div class="relative" x-data="{ linkDropdown: false }">
                    <button @click="linkDropdown = !linkDropdown" class="relative p-2.5 rounded-xl text-gray-500 hover:text-gray-800 hover:bg-gray-100 transition focus:outline-none" title="Tautan Web">
                        <i class="fa-solid fa-link text-lg"></i>
                    </button>
                    <div x-show="linkDropdown" @click.outside="linkDropdown = false"
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
                                <i class="fa-solid fa-globe w-4 text-center text-gray-400"></i> Web Iqra
                            </a>
                            <a href="../pengajar" target="_blank" class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-gray-700 hover:bg-emerald-50 hover:text-emerald-600 transition">
                                <i class="fa-solid fa-chalkboard-user w-4 text-center text-gray-400"></i> Panel Pengajar
                            </a>
                            <a href="../donatur" target="_blank" class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-gray-700 hover:bg-emerald-50 hover:text-emerald-600 transition">
                                <i class="fa-solid fa-hand-holding-heart w-4 text-center text-gray-400"></i> Panel Donatur
                            </a>
                            <a href="../pembelajar" target="_blank" class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-gray-700 hover:bg-emerald-50 hover:text-emerald-600 transition">
                                <i class="fa-solid fa-users w-4 text-center text-gray-400"></i> Panel Member
                            </a>
                        </div>
                    </div>
                </div>
                <!-- NOTIFIKASI LONCENG -->
                <?php include __DIR__ . '/notif_dropdown.php'; ?>
            </div>
        </header>
        <main class="p-4 md:p-8">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-6"
                 x-data="{
                    items: <?= htmlspecialchars(json_encode($items_data), ENT_QUOTES, 'UTF-8') ?>,
                    searchQuery: '',
                    selectedFilter: '',
                    currentPage: 1,
                    pageSize: 7,
                    get filteredItems() {
                        return this.items.filter(item => {
                            let matchSearch = item.title.toLowerCase().includes(this.searchQuery.toLowerCase());
                            let matchFilter = this.selectedFilter === '' || item.filter_key === this.selectedFilter;
                            return matchSearch && matchFilter;
                        });
                    },
                    get totalPages() { return Math.ceil(this.filteredItems.length / this.pageSize) || 1; },
                    get paginatedItems() {
                        let start = (this.currentPage - 1) * this.pageSize;
                        return this.filteredItems.slice(start, start + this.pageSize);
                    },
                    nextPage() { if (this.currentPage < this.totalPages) this.currentPage++; },
                    prevPage() { if (this.currentPage > 1) this.currentPage--; },
                    goToPage(page) { this.currentPage = page; }
                 }">
                <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                    <h2 class="text-sm font-bold text-gray-900">Daftar keseluruhan Posting</h2>
                    <span class="text-xs text-gray-500 font-medium">Total: <strong class="text-gray-800" x-text="filteredItems.length"></strong> pengajuan</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                        <input type="text" x-model="searchQuery" @input="currentPage = 1" placeholder="Cari judul posting..." class="w-full pl-9 pr-4 py-2 text-xs rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition">
                    </div>
                    <div class="relative">
                        <i class="fa-solid fa-filter absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                        <select x-model="selectedFilter" @change="currentPage = 1" class="w-full pl-9 pr-4 py-2 text-xs rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition bg-white text-gray-700">
                            <option value="">Semua Penulis</option>
                            <?php foreach ($filter_options as $opt): ?>
                                <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="w-full">
                    <table class="w-full text-left text-xs text-gray-600 block md:table">
                        <thead class="bg-gray-50 text-gray-700 uppercase font-bold hidden md:table-header-group">
                            <tr><th class="p-3">Judul Posting</th><th class="p-3">Penulis</th><th class="p-3 text-center">Aksi</th></tr>
                        </thead>
                        <tbody class="divide-y-0 md:divide-y divide-gray-100 block md:table-row-group space-y-3 md:space-y-0">
                            <template x-if="filteredItems.length === 0">
                                <tr class="block md:table-row"><td colspan="5" class="p-6 text-center text-gray-400 italic block md:table-cell">Tidak ditemukan pengajuan yang sesuai.</td></tr>
                            </template>
                            <template x-for="row in paginatedItems" :key="row.id">
                                <tr class="block md:table-row bg-white md:bg-transparent rounded-xl border border-gray-100 md:border-none p-4 md:p-0 shadow-sm md:shadow-none space-y-2.5 md:space-y-0">
                                    <td class="flex justify-between items-center md:table-cell md:p-3 font-semibold text-gray-800 text-sm md:text-xs"><span class="md:hidden text-[11px] font-bold uppercase text-gray-400 shrink-0 mr-2">Judul</span><span class="text-right md:text-left text-gray-900 font-bold md:font-semibold" x-text="row.title"></span></td>
                                    <td class="flex justify-between items-center md:table-cell md:p-3 text-gray-600"><span class="md:hidden text-[11px] font-bold uppercase text-gray-400 shrink-0 mr-2">Penulis</span><span x-text="row.col1"></span></td>
                                    <td class="md:table-cell md:p-3 md:text-center pt-3 md:pt-3 border-t border-gray-100 md:border-t-0 mt-1 md:mt-0">
                                        <span class="md:hidden text-[11px] font-bold uppercase text-gray-400 block mb-2">Aksi Cepat</span>
                                        <div class="grid grid-cols-2 gap-2 md:flex md:justify-center">
                                            <form action="manage_post.php" method="POST">
                                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                <input type="hidden" name="id" :value="row.id">
                                                <input type="hidden" name="action" value="approve">
                                                <button class="w-full md:w-auto inline-flex items-center justify-center gap-1.5 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white text-xs font-bold rounded-xl shadow-sm hover:shadow transition"><i class="fa-solid fa-check"></i>Setujui</button>
                                            </form>
                                            <form action="manage_post.php" method="POST" onsubmit="return confirm('Tolak pengajuan ini?')">
                                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                <input type="hidden" name="id" :value="row.id">
                                                <input type="hidden" name="action" value="reject">
                                                <button class="w-full md:w-auto inline-flex items-center justify-center gap-1.5 px-4 py-2.5 bg-white hover:bg-red-50 active:scale-95 text-red-600 border border-red-200 text-xs font-bold rounded-xl shadow-sm hover:shadow transition"><i class="fa-solid fa-xmark"></i>Tolak</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 pt-3 border-t border-gray-100 flex items-center justify-between flex-col sm:flex-row gap-3" x-show="totalPages > 1">
                    <div class="text-xs text-gray-500">Menampilkan <span class="font-bold text-gray-800" x-text="((currentPage - 1) * pageSize) + 1"></span> sampai <span class="font-bold text-gray-800" x-text="Math.min(currentPage * pageSize, filteredItems.length)"></span> dari <span class="font-bold text-gray-800" x-text="filteredItems.length"></span> data</div>
                    <div class="flex items-center gap-1">
                        <button @click="prevPage()" :disabled="currentPage === 1" :class="currentPage === 1 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-gray-100 text-gray-700'" class="p-2 rounded-lg text-xs font-semibold border border-gray-200 transition flex items-center gap-1"><i class="fa-solid fa-chevron-left text-[10px]"></i><span class="hidden sm:inline">Sebelumnya</span></button>
                        <template x-for="p in totalPages" :key="p"><button @click="goToPage(p)" :class="p === currentPage ? 'bg-emerald-600 text-white border-emerald-600 font-bold' : 'text-gray-600 hover:bg-gray-100 border-gray-200'" class="w-8 h-8 rounded-lg text-xs border transition flex items-center justify-center" x-text="p"></button></template>
                        <button @click="nextPage()" :disabled="currentPage === totalPages" :class="currentPage === totalPages ? 'opacity-40 cursor-not-allowed' : 'hover:bg-gray-100 text-gray-700'" class="p-2 rounded-lg text-xs font-semibold border border-gray-200 transition flex items-center gap-1"><span class="hidden sm:inline">Selanjutnya</span><i class="fa-solid fa-chevron-right text-[10px]"></i></button>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
</body>
</html>
