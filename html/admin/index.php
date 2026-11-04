<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
check_access(['super_administrator', 'administrator']);

$total_users = $conn->query("SELECT COUNT(id) FROM users")->fetch_row()[0] ?? 0;
$total_pengajar = $conn->query("SELECT COUNT(id) FROM users WHERE role = 'pengajar'")->fetch_row()[0] ?? 0;
$total_pembelajar = $conn->query("SELECT COUNT(id) FROM users WHERE role = 'pembelajar'")->fetch_row()[0] ?? 0;

$pending_classes  = $conn->query("SELECT COUNT(id) FROM section_kelas WHERE status = 'pending'")->fetch_row()[0] ?? 0;
$pending_activity = $conn->query("SELECT COUNT(id) FROM section_kegiatan WHERE status = 'pending'")->fetch_row()[0] ?? 0;
$pending_book     = $conn->query("SELECT COUNT(id) FROM section_buku WHERE status = 'pending'")->fetch_row()[0] ?? 0;
$pending_post     = $conn->query("SELECT COUNT(id) FROM posts WHERE status = 'pending'")->fetch_row()[0] ?? 0;

$pending_count_query = $conn->query("
    SELECT 
        (SELECT COUNT(id) FROM section_kelas WHERE status IN ('pending', 'draft')) +
        (SELECT COUNT(id) FROM posts WHERE status IN ('pending', 'draft')) +
        (SELECT COUNT(id) FROM section_buku WHERE status IN ('pending', 'draft')) +
        (SELECT COUNT(id) FROM section_kegiatan WHERE status IN ('pending', 'draft'))
");

$pending_notifications = $pending_count_query ? (int) $pending_count_query->fetch_row()[0] : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Iqra' Society</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="icon" type="image/x-icon" href="../asset/img/favicon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../asset/img/favicon.png">
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
        <h1 class="text-base md:text-lg font-extrabold text-gray-800">Panel Administrator</h1>
    </div>

    <!-- POJOK KANAN HEADER: DROPDOWN LINK & NOTIFIKASI LONCENG -->
    <div class="flex items-center gap-2 md:gap-3">

        <!-- DROPDOWN LINK QUICK ACCESS -->
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
        <div class="relative" x-data="{ notifDropdown: false }">
            <button @click="notifDropdown = !notifDropdown" class="relative p-2.5 rounded-xl text-gray-500 hover:text-gray-800 hover:bg-gray-100 transition focus:outline-none" title="Notifikasi">
                <i class="fa-regular fa-bell text-lg"></i>
                
                <!-- Red Dot Indicator -->
                <?php if ($pending_notifications > 0): ?>
                    <span class="absolute top-2 right-2 w-2.5 h-2.5 bg-red-500 rounded-full ring-2 ring-white animate-pulse"></span>
                <?php endif; ?>
            </button>

            <!-- Notification Dropdown Menu -->
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
                    <?php if ($pending_notifications > 0): ?>
                        <span class="bg-red-100 text-red-700 text-[10px] font-bold px-2 py-0.5 rounded-full"><?= $pending_notifications ?> Baru</span>
                    <?php endif; ?>
                </div>

                <div class="divide-y divide-gray-50 max-h-80 overflow-y-auto">
                    <?php if ($pending_notifications > 0): ?>
                        
                        <!-- Notifikasi Kelas -->
                        <?php if ($pending_classes > 0): ?>
                        <a href="approve_class.php" class="flex items-start gap-3 p-3 hover:bg-emerald-50/50 transition">
                            <div class="w-8 h-8 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center shrink-0 mt-0.5 text-xs">
                                <i class="fa-solid fa-graduation-cap"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-gray-800">Moderasi Kelas Baru</p>
                                <p class="text-[10px] text-gray-500 mt-0.5">Ada <strong><?= $pending_classes ?></strong> pengajuan kelas baru menunggu persetujuan Anda.</p>
                            </div>
                        </a>
                        <?php endif; ?>

                        <!-- Notifikasi Kegiatan -->
                        <?php if ($pending_activity > 0): ?>
                        <a href="approve_kegiatan.php" class="flex items-start gap-3 p-3 hover:bg-emerald-50/50 transition">
                            <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center shrink-0 mt-0.5 text-xs">
                                <i class="fa-solid fa-calendar-days"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-gray-800">Moderasi Kegiatan Baru</p>
                                <p class="text-[10px] text-gray-500 mt-0.5">Ada <strong><?= $pending_activity ?></strong> kegiatan baru menunggu persetujuan.</p>
                            </div>
                        </a>
                        <?php endif; ?>

                        <!-- Notifikasi Buku -->
                        <?php if ($pending_book > 0): ?>
                        <a href="approve_buku.php" class="flex items-start gap-3 p-3 hover:bg-emerald-50/50 transition">
                            <div class="w-8 h-8 rounded-full bg-purple-100 text-purple-700 flex items-center justify-center shrink-0 mt-0.5 text-xs">
                                <i class="fa-solid fa-book"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-gray-800">Moderasi Buku Baru</p>
                                <p class="text-[10px] text-gray-500 mt-0.5">Ada <strong><?= $pending_book ?></strong> buku baru menunggu persetujuan.</p>
                            </div>
                        </a>
                        <?php endif; ?>

                        <!-- Notifikasi Posting -->
                        <?php if ($pending_post > 0): ?>
                        <a href="approve_post.php" class="flex items-start gap-3 p-3 hover:bg-emerald-50/50 transition">
                            <div class="w-8 h-8 rounded-full bg-pink-100 text-pink-700 flex items-center justify-center shrink-0 mt-0.5 text-xs">
                                <i class="fa-solid fa-newspaper"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-gray-800">Moderasi Postingan</p>
                                <p class="text-[10px] text-gray-500 mt-0.5">Ada <strong><?= $pending_post ?></strong> postingan baru menunggu persetujuan.</p>
                            </div>
                        </a>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="p-4 text-center text-xs text-gray-400">
                            <i class="fa-regular fa-circle-check text-emerald-500 text-base mb-1 block"></i>
                            Tidak ada notifikasi baru.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</header>

            <!-- Dashboard Body -->
            <main class="p-6 space-y-6">
                <!-- ================= KARTU STATISTIK ================= -->
                <div class="grid grid-cols-2 lg:grid-cols-3 gap-3">
                    
                    <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-3">
                        <div class="w-10 h-10 bg-emerald-50 text-emerald-700 rounded-xl flex items-center justify-center text-lg shrink-0">
                            <i class="fa-solid fa-chalkboard-user"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[11px] text-gray-500 font-bold truncate">Total Pengajar</p>
                            <h3 class="text-lg font-extrabold text-gray-900"><?= $total_pengajar ?></h3>
                        </div>
                    </div>

                    <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-3">
                        <div class="w-10 h-10 bg-teal-50 text-teal-700 rounded-xl flex items-center justify-center text-lg shrink-0">
                            <i class="fa-solid fa-user-graduate"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[11px] text-gray-500 font-bold truncate">Total Pembelajar</p>
                            <h3 class="text-lg font-extrabold text-gray-900"><?= $total_pembelajar ?></h3>
                        </div>
                    </div>

                    <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-3">
                        <div class="w-10 h-10 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center text-lg shrink-0">
                            <i class="fa-solid fa-graduation-cap"></i>
                        </div>
                        <div>
                            <p class="text-[11px] text-gray-500 font-bold truncate">Pending Kelas</p>
                            <h3 class="text-lg font-extrabold text-gray-900"><?= $pending_classes ?></h3>
                        </div>
                    </div>

                    <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-3">
                        <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center text-lg shrink-0">
                            <i class="fa-solid fa-calendar-days"></i>
                        </div>
                        <div>
                            <p class="text-[11px] text-gray-500 font-bold truncate">Pending Kegiatan</p>
                            <h3 class="text-lg font-extrabold text-gray-900"><?= $pending_activity ?></h3>
                        </div>
                    </div>

                    <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-3">
                        <div class="w-10 h-10 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center text-lg shrink-0">
                            <i class="fa-solid fa-book"></i>
                        </div>
                        <div>
                            <p class="text-[11px] text-gray-500 font-bold truncate">Pending Buku</p>
                            <h3 class="text-lg font-extrabold text-gray-900"><?= $pending_book ?></h3>
                        </div>
                    </div>

                    <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-3">
                        <div class="w-10 h-10 bg-pink-50 text-pink-600 rounded-xl flex items-center justify-center text-lg shrink-0">
                            <i class="fa-solid fa-newspaper"></i>
                        </div>
                        <div>
                            <p class="text-[11px] text-gray-500 font-bold truncate">Pending Posting</p>
                            <h3 class="text-lg font-extrabold text-gray-900"><?= $pending_post ?></h3>
                        </div>
                    </div>
                </div>

<?php
// Query mengambil seluruh data yang pending (urut dari terbaru)
$res = $conn->query("SELECT k.id, k.title, k.price, k.session_count, u.fullname FROM section_kelas k JOIN users u ON k.instructor_id = u.id WHERE k.status = 'pending' ORDER BY k.id DESC");

$all_data = [];
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $all_data[] = [
            'id' => (int)$row['id'],
            'type' => 'kelas',
            'type_label' => 'Kelas',
            'title' => $row['title'],
            'subtitle' => $row['fullname'] . ' | ' . (int)($row['session_count'] ?? 0) . ' sesi | Rp ' . number_format($row['price'], 0, ',', '.'),
            'approve_url' => 'approve_class.php',
            'id_field' => 'class_id'
        ];
    }
}
// Kegiatan
$rk = $conn->query("SELECT id, title, event_date, location FROM section_kegiatan WHERE status = 'pending' ORDER BY id DESC");
if ($rk) while ($row = $rk->fetch_assoc()) {
    $all_data[] = ['id'=>(int)$row['id'],'type'=>'kegiatan','type_label'=>'Kegiatan','title'=>$row['title'],'subtitle'=>($row['event_date']??'-').' | '.($row['location']??'-'),'approve_url'=>'manage_kegiatan.php','id_field'=>'id'];
}
// Buku
$rb = $conn->query("SELECT id, title, price FROM section_buku WHERE status = 'pending' ORDER BY id DESC");
if ($rb) while ($row = $rb->fetch_assoc()) {
    $all_data[] = ['id'=>(int)$row['id'],'type'=>'buku','type_label'=>'Buku','title'=>$row['title'],'subtitle'=>'Rp '.number_format($row['price']??0,0,',','.'),'approve_url'=>'manage_buku.php','id_field'=>'id'];
}
// Posting
$rp = $conn->query("SELECT p.id, p.title, u.fullname as author FROM posts p LEFT JOIN users u ON p.author_id=u.id WHERE p.status='pending' ORDER BY p.id DESC");
if ($rp) while ($row = $rp->fetch_assoc()) {
    $all_data[] = ['id'=>(int)$row['id'],'type'=>'posting','type_label'=>'Posting','title'=>$row['title'],'subtitle'=>$row['author']??'-','approve_url'=>'manage_post.php','id_field'=>'id'];
}
// Urut terbaru (id DESC sudah per tabel, tapi gabungan perlu sort ulang by id)
usort($all_data, fn($a,$b) => $b['id'] <=> $a['id']);
$csrf_token = generate_csrf_token();
?>

<!-- Container Utama dengan Alpine.js Data -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-6" 
     x-data="{
        items: <?= htmlspecialchars(json_encode($all_data), ENT_QUOTES, 'UTF-8') ?>,
        currentPage: 1,
        pageSize: 5,
        get totalPages() {
            return Math.ceil(this.items.length / this.pageSize) || 1;
        },
        get paginatedItems() {
            let start = (this.currentPage - 1) * this.pageSize;
            return this.items.slice(start, start + this.pageSize);
        },
        nextPage() {
            if (this.currentPage < this.totalPages) this.currentPage++;
        },
        prevPage() {
            if (this.currentPage > 1) this.currentPage--;
        },
        goToPage(page) {
            this.currentPage = page;
        }
     }">

    <div class="flex items-center justify-between mb-4">
        <h2 class="text-sm font-bold text-gray-900">Pengajuan Terbaru</h2>
        <!-- Indikator Total Data -->
        <span class="text-xs text-gray-500 font-medium" x-show="items.length > 0">
            Total: <strong class="text-gray-800" x-text="items.length"></strong> pengajuan
        </span>
    </div>
    
    <div class="w-full">
        <table class="w-full text-left text-xs text-gray-600 block md:table">
            <!-- Table Header (Hanya muncul di Desktop) -->
            <thead class="bg-gray-50 text-gray-700 uppercase font-bold hidden md:table-header-group">
                <tr>
                    <th class="p-3">Tipe</th>
                    <th class="p-3">Judul</th>
                    <th class="p-3">Detail</th>
                    <th class="p-3 text-center">Aksi Cepat</th>
                </tr>
            </thead>
            
            <!-- Table Body -->
            <tbody class="divide-y-0 md:divide-y divide-gray-100 block md:table-row-group space-y-3 md:space-y-0">
                <!-- State Kosong jika tidak ada data -->
                <template x-if="items.length === 0">
                    <tr class="block md:table-row">
                        <td colspan="4" class="p-6 text-center text-gray-400 italic block md:table-cell">
                            Tidak ada pengajuan yang perlu disetujui.
                        </td>
                    </tr>
                </template>

                <!-- Loop Data Hasil Pagination Alpine.js -->
                <template x-for="row in paginatedItems" :key="row.type + '-' + row.id">
                    <tr class="block md:table-row bg-white md:bg-transparent rounded-xl border border-gray-100 md:border-none p-4 md:p-0 shadow-sm md:shadow-none space-y-2.5 md:space-y-0">
                        <td class="flex justify-between items-center md:table-cell md:p-3">
                            <span class="md:hidden text-[11px] font-bold uppercase text-gray-400 shrink-0 mr-2">Tipe</span>
                            <span class="px-2 py-1 rounded-lg text-[10px] font-bold uppercase"
                                :class="{'bg-amber-100 text-amber-700': row.type==='kelas', 'bg-blue-100 text-blue-700': row.type==='kegiatan', 'bg-purple-100 text-purple-700': row.type==='buku', 'bg-pink-100 text-pink-700': row.type==='posting'}"
                                x-text="row.type_label"></span>
                        </td>
                        <td class="flex justify-between items-center md:table-cell md:p-3 font-semibold text-gray-800 text-sm md:text-xs">
                            <span class="md:hidden text-[11px] font-bold uppercase text-gray-400 shrink-0 mr-2">Judul</span>
                            <span class="text-right md:text-left text-gray-900 font-bold md:font-semibold" x-text="row.title"></span>
                        </td>
                        <td class="flex justify-between items-center md:table-cell md:p-3 text-gray-600">
                            <span class="md:hidden text-[11px] font-bold uppercase text-gray-400 shrink-0 mr-2">Detail</span>
                            <span class="text-right md:text-left text-[11px] text-gray-500" x-text="row.subtitle"></span>
                        </td>
                        <td class="md:table-cell md:p-3 md:text-center pt-3 md:pt-3 border-t border-gray-100 md:border-t-0 mt-1 md:mt-0">
                            <span class="md:hidden text-[11px] font-bold uppercase text-gray-400 block mb-2">Aksi Cepat</span>
                            <div class="grid grid-cols-2 gap-2 md:flex md:justify-center">
                                <form :action="row.approve_url" method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                    <input type="hidden" name="back" value="index">
                                    <input type="hidden" :name="row.id_field" :value="row.id">
                                    <input type="hidden" name="action" value="approve">
                                    <button class="w-full md:w-auto inline-flex items-center justify-center gap-1.5 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white text-xs font-bold rounded-xl shadow-sm hover:shadow transition"><i class="fa-solid fa-check"></i>Setujui</button>
                                </form>
                                <form :action="row.approve_url" method="POST" onsubmit="return confirm('Tolak pengajuan ini?')">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                    <input type="hidden" name="back" value="index">
                                    <input type="hidden" :name="row.id_field" :value="row.id">
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

    <!-- Pager / Pagination Controls -->
    <div class="mt-4 pt-3 border-t border-gray-100 flex items-center justify-between flex-col sm:flex-row gap-3" x-show="totalPages > 1">
        <!-- Info Halaman -->
        <div class="text-xs text-gray-500">
            Menampilkan <span class="font-bold text-gray-800" x-text="((currentPage - 1) * pageSize) + 1"></span> 
            sampai <span class="font-bold text-gray-800" x-text="Math.min(currentPage * pageSize, items.length)"></span> 
            dari <span class="font-bold text-gray-800" x-text="items.length"></span> data
        </div>

        <!-- Tombol Pagination -->
        <div class="flex items-center gap-1">
            <button @click="prevPage()" 
                    :disabled="currentPage === 1" 
                    :class="currentPage === 1 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-gray-100 text-gray-700'"
                    class="p-2 rounded-lg text-xs font-semibold border border-gray-200 transition flex items-center gap-1">
                <i class="fa-solid fa-chevron-left text-[10px]"></i>
                <span class="hidden sm:inline">Sebelumnya</span>
            </button>

            <template x-for="p in totalPages" :key="p">
                <button @click="goToPage(p)" 
                        :class="p === currentPage ? 'bg-emerald-600 text-white border-emerald-600 font-bold' : 'text-gray-600 hover:bg-gray-100 border-gray-200'"
                        class="w-8 h-8 rounded-lg text-xs border transition flex items-center justify-center"
                        x-text="p">
                </button>
            </template>

            <button @click="nextPage()" 
                    :disabled="currentPage === totalPages" 
                    :class="currentPage === totalPages ? 'opacity-40 cursor-not-allowed' : 'hover:bg-gray-100 text-gray-700'"
                    class="p-2 rounded-lg text-xs font-semibold border border-gray-200 transition flex items-center gap-1">
                <span class="hidden sm:inline">Selanjutnya</span>
                <i class="fa-solid fa-chevron-right text-[10px]"></i>
            </button>
        </div>
    </div>
</div>
            </main>
        </div>
    </div>

</body>
</html>