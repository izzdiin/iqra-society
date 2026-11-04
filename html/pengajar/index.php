<?php
require_once __DIR__ . '/../includes/auth_middleware.php';

check_access(['super_administrator', 'administrator', 'pengajar']);

$instructor_id = $_SESSION['user_id'] ?? 0;

$total_jamaah = 0;
$stmt_jamaah = $conn->prepare("SELECT SUM(student_count) AS total_students FROM section_kelas WHERE instructor_id = ?");
if ($stmt_jamaah) {
    $stmt_jamaah->bind_param("i", $instructor_id);
    $stmt_jamaah->execute();
    $res_jamaah = $stmt_jamaah->get_result()->fetch_assoc();
    $total_jamaah = $res_jamaah['total_students'] ?? 0;
    $stmt_jamaah->close();
}

$active_discussions = 0;
if ($conn->query("SHOW TABLES LIKE 'discussions'")->num_rows > 0) {
    $stmt_disc = $conn->prepare("SELECT COUNT(*) AS total FROM discussions WHERE instructor_id = ? AND status = 'pending'");
    if ($stmt_disc) {
        $stmt_disc->bind_param("i", $instructor_id);
        $stmt_disc->execute();
        $res_disc = $stmt_disc->get_result()->fetch_assoc();
        $active_discussions = $res_disc['total'] ?? 0;
        $stmt_disc->close();
    }
}

$total_revenue = 0;
$stmt_rev = $conn->prepare("SELECT SUM(student_count * price) AS total_income FROM section_kelas WHERE instructor_id = ? AND status = 'approved'");
if ($stmt_rev) {
    $stmt_rev->bind_param("i", $instructor_id);
    $stmt_rev->execute();
    $res_rev = $stmt_rev->get_result()->fetch_assoc();
    $total_revenue = $res_rev['total_income'] ?? 0;
    $stmt_rev->close();
}

$schedules = [];
$stmt_kelas = $conn->prepare("SELECT id, title, session_count, student_count, price, status, created_at FROM section_kelas WHERE instructor_id = ? ORDER BY created_at DESC LIMIT 5");
if ($stmt_kelas) {
    $stmt_kelas->bind_param("i", $instructor_id);
    $stmt_kelas->execute();
    $res_kelas = $stmt_kelas->get_result();
    while ($row = $res_kelas->fetch_assoc()) {
        $schedules[] = $row;
    }
    $stmt_kelas->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Pengajar - Iqra' Society</title>
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
                        <div class="w-10 h-10 rounded-full bg-emerald-800 text-lime-400 border border-emerald-600 flex items-center justify-center font-bold text-sm shrink-0">
                            <?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <div class="flex flex-col truncate">
                        <span class="text-xs font-bold text-white truncate"><?= htmlspecialchars($_SESSION['username'] ?? 'Ustadz / Pengajar') ?></span>
                        <span class="text-[10px] text-lime-400 font-semibold uppercase tracking-wider truncate"><?= htmlspecialchars($_SESSION['role'] ?? 'Pengajar') ?></span>
                    </div>
                </div>
                <button @click="sidebarOpen = false" class="text-emerald-400 hover:text-white p-1">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <!-- MEMANGGIL MENU MOBILE DARI FILE TERPISAH -->
            <?php include 'sidebar_menu.php'; ?>
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
                <!-- INFORMASI PROFIL USER -->
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
                            <?= htmlspecialchars($_SESSION['username'] ?? 'Ustadz / Pengajar') ?>
                        </span>
                        <span class="text-[10px] text-lime-400 font-semibold uppercase tracking-wider truncate">
                            <?= htmlspecialchars($_SESSION['role'] ?? 'Pengajar') ?>
                        </span>
                    </div>
                </div>

                <!-- MEMANGGIL MENU DESKTOP DARI FILE TERPISAH YANG SAMA -->
                <?php include 'sidebar_menu.php'; ?>
            </div>

            <div class="p-6 border-t border-emerald-900">
                <a href="../logout.php" class="flex items-center gap-2 text-xs font-bold text-red-400 hover:text-red-300">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col">
            <!-- Navbar Header -->
            <header class="sticky top-0 z-30 bg-white/95 backdrop-blur-md border-b border-gray-100 px-4 md:px-6 py-4 flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-3">
                    <button @click="sidebarOpen = true" class="md:hidden text-gray-600 hover:text-gray-900 p-2 rounded-lg hover:bg-gray-100">
                        <i class="fa-solid fa-bars text-lg"></i>
                    </button>
                    <h1 class="text-base md:text-lg font-extrabold text-gray-800">Panel Pengajar</h1>
                </div>
                <div class="flex items-center gap-2">
                    
                    <!-- LINK KE WEB UTAMA -->
                    <a href="../index.php" target="_blank" title="Lihat Website IQRA' Society" class="p-2.5 rounded-xl text-gray-500 hover:text-emerald-700 hover:bg-emerald-50 transition flex items-center gap-1.5 text-xs font-bold">
                        <i class="fa-solid fa-globe text-base"></i>
                        <span class="hidden sm:inline">Web IQRA'</span>
                    </a>

                    <!-- NOTIFIKASI LONCENG NAVBAR -->
                    <div class="relative" x-data="{ notifDropdown: false }">
                        <button @click="notifDropdown = !notifDropdown" class="relative p-2.5 rounded-xl text-gray-500 hover:text-gray-800 hover:bg-gray-100 transition focus:outline-none">
                            <i class="fa-regular fa-bell text-lg"></i>
                            <?php if ($active_discussions > 0): ?>
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
                                <?php if ($active_discussions > 0): ?>
                                    <span class="bg-amber-100 text-amber-800 text-[10px] font-bold px-2 py-0.5 rounded-full"><?= $active_discussions ?> Baru</span>
                                <?php endif; ?>
                            </div>

                            <div class="divide-y divide-gray-50 max-h-60 overflow-y-auto">
                                <?php if ($active_discussions > 0): ?>
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

            <!-- Dashboard Body Main Content -->
            <main class="p-6 space-y-6">
                
                <!-- CARDS METRIK REAL-TIME -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    
                    <!-- Card 1: Total Jamaah -->
                    <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4">
                        <div class="w-12 h-12 bg-emerald-50 text-emerald-700 rounded-xl flex items-center justify-center text-xl shrink-0">
                            <i class="fa-solid fa-users"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 font-bold">Total Jamaah / Member</p>
                            <h3 class="text-xl font-extrabold text-gray-900"><?= number_format($total_jamaah, 0, ',', '.') ?></h3>
                            <span class="text-[10px] text-emerald-600 font-semibold">Terdaftar di seluruh kelas Anda</span>
                        </div>
                    </div>

                    <!-- Card 2: Diskusi Berlangsung -->
                    <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4">
                        <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center text-xl shrink-0">
                            <i class="fa-solid fa-comments"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 font-bold">Diskusi / Pertanyaan</p>
                            <h3 class="text-xl font-extrabold text-gray-900"><?= number_format($active_discussions, 0, ',', '.') ?></h3>
                            <span class="text-[10px] text-amber-600 font-semibold">Menunggu Tanggapan</span>
                        </div>
                    </div>

                    <!-- Card 3: Total Pendapatan -->
                    <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4 sm:col-span-2 lg:col-span-1">
                        <div class="w-12 h-12 bg-lime-100 text-emerald-800 rounded-xl flex items-center justify-center text-xl shrink-0">
                            <i class="fa-solid fa-wallet"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 font-bold">Total Pendapatan</p>
                            <h3 class="text-xl font-extrabold text-gray-900">Rp <?= number_format($total_revenue, 0, ',', '.') ?></h3>
                            <span class="text-[10px] text-emerald-600 font-semibold">Aset akumulasi dari kelas disetujui</span>
                        </div>
                    </div>

                </div>

                <!-- INTEGRASI DAFTAR KELAS & KAJIAN -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-sm font-bold text-gray-900">Daftar Kelas & Kajian Saya</h2>
                            <p class="text-[11px] text-gray-500">Daftar kelas yang diajukan dan dikelola oleh Anda</p>
                        </div>
                        <a href="create_class.php" class="text-xs font-bold text-emerald-700 hover:text-emerald-800 transition">
                            View All <i class="fa-solid fa-arrow-right text-[10px] ml-1"></i>
                        </a>
                    </div>

                    <?php if (empty($schedules)): ?>
                        <div class="text-center py-8 text-gray-400 text-xs">
                            <i class="fa-solid fa-book-open text-2xl mb-1 text-gray-300 block"></i>
                            Belum ada data kelas atau kajian. <a href="create_class.php" class="text-emerald-600 font-bold underline">Buat Baru</a>
                        </div>
                    <?php else: ?>

                        <!-- 1. MOBILE CARD VIEW -->
                        <div class="grid grid-cols-1 gap-3 md:hidden">
                            <?php foreach ($schedules as $row): ?>
                                <?php
                                $statusBadge = [
                                    'pending'  => 'bg-amber-100 text-amber-800 border-amber-200',
                                    'approved' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                    'rejected' => 'bg-red-100 text-red-800 border-red-200',
                                    'closed'   => 'bg-gray-200 text-gray-700 border-gray-300',
                                ][$row['status']] ?? 'bg-gray-100 text-gray-800';
                                ?>
                                <div class="bg-gray-50 p-4 rounded-xl border border-gray-100 space-y-3">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border <?= $statusBadge ?>">
                                            <?= strtoupper(htmlspecialchars($row['status'])) ?>
                                        </span>
                                    </div>

                                    <h3 class="text-xs font-bold text-gray-900 leading-snug">
                                        <?= htmlspecialchars($row['title']) ?>
                                    </h3>

                                    <div class="pt-2 border-t border-gray-200/60 flex items-center justify-between gap-2">
                                        <div class="text-[11px] font-semibold text-gray-600">
                                            <i class="fa-solid fa-layer-group text-emerald-600 mr-1"></i> <?= $row['session_count'] ?> Sesi
                                        </div>
                                        <span class="text-[10px] text-gray-500 font-medium">
                                            <i class="fa-solid fa-users text-gray-400 mr-1"></i> <?= number_format($row['student_count'], 0, ',', '.') ?> Jamaah
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- 2. DESKTOP TABLE VIEW -->
                        <div class="hidden md:block overflow-x-auto">
                            <table class="w-full text-left text-xs text-gray-600">
                                <thead class="bg-gray-50 text-gray-700 uppercase font-bold">
                                    <tr>
                                        <th class="p-3 rounded-l-xl">Judul Kelas / Kajian</th>
                                        <th class="p-3">Jumlah Sesi</th>
                                        <th class="p-3">Peserta</th>
                                        <th class="p-3">Harga / Biaya</th>
                                        <th class="p-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($schedules as $row): ?>
                                    <?php
                                    $statusBadge = [
                                        'pending'  => 'bg-amber-100 text-amber-800 border-amber-200',
                                        'approved' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                        'rejected' => 'bg-red-100 text-red-800 border-red-200',
                                        'closed'   => 'bg-gray-200 text-gray-700 border-gray-300',
                                    ][$row['status']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <tr class="hover:bg-gray-50/50 transition">
                                        <td class="p-3 font-bold text-gray-800"><?= htmlspecialchars($row['title']) ?></td>
                                        <td class="p-3 font-semibold text-gray-700"><?= $row['session_count'] ?> Sesi</td>
                                        <td class="p-3 font-medium text-gray-700 whitespace-nowrap">
                                            <i class="fa-solid fa-users text-gray-400 mr-1"></i> <?= number_format($row['student_count'], 0, ',', '.') ?> Jamaah
                                        </td>
                                        <td class="p-3 font-bold text-gray-900 whitespace-nowrap">
                                            <?= $row['price'] > 0 ? 'Rp ' . number_format($row['price'], 0, ',', '.') : '<span class="text-emerald-600">Gratis</span>' ?>
                                        </td>
                                        <td class="p-3 whitespace-nowrap">
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold border <?= $statusBadge ?>">
                                                <?= strtoupper(htmlspecialchars($row['status'])) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                    <?php endif; ?>
                </div>

            </main>
        </div>
    </div>

</body>
</html>