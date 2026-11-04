<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
check_access(['super_administrator', 'administrator']);

$total_users = $conn->query("SELECT COUNT(id) FROM users")->fetch_row()[0] ?? 0;
$pending_classes = $conn->query("SELECT COUNT(id) FROM section_kelas WHERE status = 'pending'")->fetch_row()[0] ?? 0;
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
        <h1 class="text-base md:text-lg font-extrabold text-gray-800">Manajemen Kelas/Kajian</h1>
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
            <main class="p-6 space-y-6">
<?php
$res = $conn->query("SELECT k.id, k.title, k.price, k.session_count, u.fullname FROM section_kelas k JOIN users u ON k.instructor_id = u.id ORDER BY k.id DESC");

$classes_data = [];
$instructors = [];

if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $classes_data[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'fullname' => $row['fullname'],
            'session_count' => (int)($row['session_count'] ?? 0),
            'price_formatted' => 'Rp ' . number_format($row['price'], 0, ',', '.')
        ];
        
        if (!in_array($row['fullname'], $instructors)) {
            $instructors[] = $row['fullname'];
        }
    }
}
sort($instructors); // Urutkan nama pengajar A-Z
$csrf_token = generate_csrf_token();
?>

<!-- Container Utama dengan Alpine.js Data -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-6" 
     x-data="{
        items: <?= htmlspecialchars(json_encode($classes_data), ENT_QUOTES, 'UTF-8') ?>,
        searchQuery: '',
        selectedInstructor: '',
        currentPage: 1,
        pageSize: 7,

        // Computed property untuk memfilter data berdasarkan Search & Instructor
        get filteredItems() {
            return this.items.filter(item => {
                let matchSearch = item.title.toLowerCase().includes(this.searchQuery.toLowerCase());
                let matchInstructor = this.selectedInstructor === '' || item.fullname === this.selectedInstructor;
                return matchSearch && matchInstructor;
            });
        },

        get totalPages() {
            return Math.ceil(this.filteredItems.length / this.pageSize) || 1;
        },

        get paginatedItems() {
            let start = (this.currentPage - 1) * this.pageSize;
            return this.filteredItems.slice(start, start + this.pageSize);
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

    <!-- Header & Info Total -->
    <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
        <h2 class="text-sm font-bold text-gray-900">Daftar keseluruhan Kelas</h2>
        <!-- Indikator Total Data Terfilter -->
        <span class="text-xs text-gray-500 font-medium">
            Total: <strong class="text-gray-800" x-text="filteredItems.length"></strong> pengajuan
        </span>
    </div>

    <!-- FITUR SEARCH & FILTER -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
        <!-- Input Pencarian Judul -->
        <div class="relative">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
            <input type="text" 
                   x-model="searchQuery" 
                   @input="currentPage = 1" 
                   placeholder="Cari judul kelas / kajian..." 
                   class="w-full pl-9 pr-4 py-2 text-xs rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition">
        </div>

        <!-- Dropdown Filter Pengajar -->
        <div class="relative">
            <i class="fa-solid fa-user-tie absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
            <select x-model="selectedInstructor" 
                    @change="currentPage = 1" 
                    class="w-full pl-9 pr-4 py-2 text-xs rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition bg-white text-gray-700">
                <option value="">Semua Pengajar</option>
                <?php foreach ($instructors as $instructor): ?>
                    <option value="<?= $instructor ?>"><?= $instructor ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    
    <div class="w-full">
        <table class="w-full text-left text-xs text-gray-600 block md:table">
            <!-- Table Header (Hanya muncul di Desktop) -->
            <thead class="bg-gray-50 text-gray-700 uppercase font-bold hidden md:table-header-group">
                <tr>
                    <th class="p-3">Judul Kelas</th>
                    <th class="p-3">Pengajar</th>
                    <th class="p-3">Sesi</th>
                    <th class="p-3">Harga</th>
                    <th class="p-3 text-center">Aksi</th>
                </tr>
            </thead>
            
            <!-- Table Body -->
            <tbody class="divide-y-0 md:divide-y divide-gray-100 block md:table-row-group space-y-3 md:space-y-0">
                <!-- State Kosong jika tidak ada data yang cocok dengan pencarian/filter -->
                <template x-if="filteredItems.length === 0">
                    <tr class="block md:table-row">
                        <td colspan="5" class="p-6 text-center text-gray-400 italic block md:table-cell">
                            Tidak ditemukan pengajuan kelas yang sesuai.
                        </td>
                    </tr>
                </template>

                <!-- Loop Data Hasil Filter & Pagination Alpine.js -->
                <template x-for="row in paginatedItems" :key="row.id">
                    <tr class="block md:table-row bg-white md:bg-transparent rounded-xl border border-gray-100 md:border-none p-4 md:p-0 shadow-sm md:shadow-none space-y-2.5 md:space-y-0">
                        
                        <!-- Judul Kelas -->
                        <td class="flex justify-between items-center md:table-cell md:p-3 font-semibold text-gray-800 text-sm md:text-xs">
                            <span class="md:hidden text-[11px] font-bold uppercase text-gray-400 shrink-0 mr-2">Judul Kelas</span>
                            <span class="text-right md:text-left text-gray-900 font-bold md:font-semibold" x-text="row.title"></span>
                        </td>

                        <!-- Pengajar -->
                        <td class="flex justify-between items-center md:table-cell md:p-3 text-gray-600">
                            <span class="md:hidden text-[11px] font-bold uppercase text-gray-400 shrink-0 mr-2">Pengajar</span>
                            <div class="flex items-center gap-1.5 text-right md:text-left">
                                <i class="fa-solid fa-user-tie text-emerald-600 md:hidden"></i>
                                <span class="font-medium md:font-normal text-gray-700 md:text-gray-600" x-text="row.fullname"></span>
                            </div>
                        </td>

                        <!-- Session Count / Jumlah Sesi -->
                        <td class="flex justify-between items-center md:table-cell md:p-3 text-gray-600">
                            <span class="md:hidden text-[11px] font-bold uppercase text-gray-400 shrink-0 mr-2">Jumlah Sesi</span>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-gray-100 text-gray-700 font-medium text-[11px]">
                                <i class="fa-regular fa-clock text-gray-400"></i>
                                <span x-text="row.session_count + ' Pertemuan'"></span>
                            </span>
                        </td>

                        <!-- Harga -->
                        <td class="flex justify-between items-center md:table-cell md:p-3 font-bold text-emerald-700 md:text-gray-600 md:font-normal">
                            <span class="md:hidden text-[11px] font-bold uppercase text-gray-400 shrink-0 mr-2">Harga</span>
                            <span class="text-emerald-600 font-bold" x-text="row.price_formatted"></span>
                        </td>

                        <!-- Aksi Dropdown Icon Gear -->
                        <td class="flex justify-between items-center md:table-cell md:p-3 pt-2 md:pt-0 border-t border-gray-100 md:border-none text-left md:text-center">
                            <span class="md:hidden text-[11px] font-bold uppercase text-gray-400">Aksi</span>
                            
                            <!-- Alpine Dropdown Control Per Baris -->
                            <div class="relative inline-block text-left" x-data="{ actionOpen: false }">
                                <button @click="actionOpen = !actionOpen" 
                                        class="p-2 rounded-lg text-gray-500 hover:text-gray-800 hover:bg-gray-100 transition focus:outline-none">
                                    <i class="fa-solid fa-gear text-base"></i>
                                </button>

                                <!-- Dropdown Menu items -->
                                <div x-show="actionOpen" 
                                     @click.outside="actionOpen = false"
                                     x-transition:enter="transition ease-out duration-150"
                                     x-transition:enter-start="opacity-0 scale-95"
                                     x-transition:enter-end="opacity-100 scale-100"
                                     x-transition:leave="transition ease-in duration-100"
                                     x-transition:leave-start="opacity-100 scale-100"
                                     x-transition:leave-end="opacity-0 scale-95"
                                     class="absolute right-0 mt-1 w-36 bg-white rounded-xl shadow-xl border border-gray-100 py-1 z-50 text-left"
                                     style="display: none;">
                                    
                                    <!-- Form Setujui -->
                                    <form action="approve_class.php" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menyetujui kelas ini?');">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                        <input type="hidden" name="class_id" :value="row.id">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="w-full px-3 py-2 text-xs font-semibold text-emerald-600 hover:bg-emerald-50 flex items-center gap-2 transition">
                                            <i class="fa-solid fa-check w-4 text-center"></i>
                                            Setujui
                                        </button>
                                    </form>

                                    <!-- Form Tolak -->
                                    <form action="approve_class.php" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menolak kelas ini?');">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                        <input type="hidden" name="class_id" :value="row.id">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="w-full px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50 flex items-center gap-2 transition">
                                            <i class="fa-solid fa-xmark w-4 text-center"></i>
                                            Tolak
                                        </button>
                                    </form>
                                </div>
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
            sampai <span class="font-bold text-gray-800" x-text="Math.min(currentPage * pageSize, filteredItems.length)"></span> 
            dari <span class="font-bold text-gray-800" x-text="filteredItems.length"></span> data
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