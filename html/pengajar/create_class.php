<?php
require_once __DIR__ . '/../includes/auth_middleware.php';

check_access(['super_administrator', 'administrator', 'pengajar']);

function generate_slug($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return empty($text) ? 'n-a' : $text;
}

function ensure_unique_slug($conn, $slug, $exclude_id = 0) {
    $base = $slug;
    $i = 2;
    while (true) {
        if ($exclude_id > 0) {
            $stmt = $conn->prepare("SELECT id FROM section_kelas WHERE slug = ? AND id != ? LIMIT 1");
            $stmt->bind_param("si", $slug, $exclude_id);
        } else {
            $stmt = $conn->prepare("SELECT id FROM section_kelas WHERE slug = ? LIMIT 1");
            $stmt->bind_param("s", $slug);
        }
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if (!$exists) return $slug;
        $slug = $base . '-' . $i;
        $i++;
    }
}

$instructor_id = $_SESSION['user_id'] ?? 0;
$success       = '';
$error         = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "Sesi keamanan tidak valid. Silakan muat ulang halaman.";
    } else {
        $action = $_POST['action'];

        if ($action === 'create_class') {
            $title              = trim($_POST['title'] ?? '');
            $content            = trim($_POST['content'] ?? '');
            $syllabus_breakdown = trim($_POST['syllabus_breakdown'] ?? '');
            $preview_content    = trim($_POST['preview_content'] ?? '');
            $preview_image      = trim($_POST['preview_image'] ?? '');
            $preview_video      = trim($_POST['preview_video'] ?? '');
            $slug_input         = trim($_POST['slug'] ?? '');
            $session_count      = filter_var($_POST['session_count'] ?? 1, FILTER_VALIDATE_INT);
            $price              = filter_var($_POST['price'] ?? 0, FILTER_VALIDATE_FLOAT);

            $slug = !empty($slug_input) ? generate_slug($slug_input) : generate_slug($title);
            $slug = ensure_unique_slug($conn, $slug);

            if (empty($title)) {
                $error = "Judul kelas/kajian wajib diisi.";
            } elseif ($session_count < 1) {
                $error = "Jumlah sesi minimal adalah 1.";
            } elseif ($price < 0) {
                $error = "Harga tidak boleh negatif.";
            } else {
                $stmt = $conn->prepare("INSERT INTO section_kelas 
                    (title, slug, content, syllabus_breakdown, preview_content, preview_image, preview_video, instructor_id, session_count, price, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                if ($stmt) {
                    // String types: sssssss (7 string), iid (2 integer, 1 double)
                    $stmt->bind_param("sssssssiid", $title, $slug, $content, $syllabus_breakdown, $preview_content, $preview_image, $preview_video, $instructor_id, $session_count, $price);
                    if ($stmt->execute()) {
                        $success = "Kelas/Kajian berhasil diajukan! Menunggu persetujuan (approval) Administrator.";
                    } else {
                        $error = "Gagal menyimpan data: " . $stmt->error;
                    }
                    $stmt->close();
                }
            }
        }

        elseif ($action === 'edit_class') {
            $class_id           = filter_var($_POST['class_id'] ?? 0, FILTER_VALIDATE_INT);
            $title              = trim($_POST['title'] ?? '');
            $content            = trim($_POST['content'] ?? '');
            $syllabus_breakdown = trim($_POST['syllabus_breakdown'] ?? '');
            $preview_content    = trim($_POST['preview_content'] ?? '');
            $preview_image      = trim($_POST['preview_image'] ?? '');
            $preview_video      = trim($_POST['preview_video'] ?? '');
            $slug_input         = trim($_POST['slug'] ?? '');
            $session_count      = filter_var($_POST['session_count'] ?? 1, FILTER_VALIDATE_INT);
            $price              = filter_var($_POST['price'] ?? 0, FILTER_VALIDATE_FLOAT);

            $slug = !empty($slug_input) ? generate_slug($slug_input) : generate_slug($title);
            $slug = ensure_unique_slug($conn, $slug, $class_id);

            if ($class_id <= 0 || empty($title) || $session_count < 1 || $price < 0) {
                $error = "Data perbaikan kelas tidak valid.";
            } else {
                $stmt = $conn->prepare("UPDATE section_kelas SET 
                    title = ?, slug = ?, content = ?, syllabus_breakdown = ?, preview_content = ?, preview_image = ?, preview_video = ?, session_count = ?, price = ? 
                    WHERE id = ? AND (instructor_id = ? OR ? IN ('super_administrator', 'administrator'))");
                if ($stmt) {
                    $user_role = $_SESSION['role'] ?? '';
                    // String types: sssssss (7 string), idiis (1 int, 1 double, 2 int, 1 string)
                    $stmt->bind_param("sssssssidiis", $title, $slug, $content, $syllabus_breakdown, $preview_content, $preview_image, $preview_video, $session_count, $price, $class_id, $instructor_id, $user_role);
                    if ($stmt->execute()) {
                        $success = "Data kelas berhasil diperbarui.";
                    } else {
                        $error = "Gagal memperbarui data: " . $stmt->error;
                    }
                    $stmt->close();
                }
            }
        }

        elseif ($action === 'toggle_status') {
            $class_id   = filter_var($_POST['class_id'] ?? 0, FILTER_VALIDATE_INT);
            $new_status = $_POST['target_status'] ?? '';

            if ($class_id > 0 && in_array($new_status, ['closed', 'pending'], true)) {
                $stmt = $conn->prepare("UPDATE section_kelas SET status = ? WHERE id = ? AND (instructor_id = ? OR ? IN ('super_administrator', 'administrator'))");
                if ($stmt) {
                    $user_role = $_SESSION['role'] ?? '';
                    $stmt->bind_param("siis", $new_status, $class_id, $instructor_id, $user_role);
                    if ($stmt->execute()) {
                        if ($new_status === 'closed') {
                            $success = "Kelas/Kajian telah ditutup (Closed).";
                        } else {
                            $success = "Kelas/Kajian diajukan kembali (Pending) dan menunggu approval Administrator.";
                        }
                    } else {
                        $error = "Gagal mengubah status kelas.";
                    }
                    $stmt->close();
                }
            } else {
                $error = "Permintaan perubahan status tidak sah.";
            }
        }
    }
}

$active_discussions = 0;
if ($conn->query("SHOW TABLES LIKE 'discussions'")->num_rows > 0) {
    $stmt_disc = $conn->prepare("SELECT COUNT(*) AS total FROM discussions WHERE instructor_id = ? AND status = 'pending'");
    if ($stmt_disc) {
        $stmt_disc->bind_param("i", $instructor_id);
        $stmt_disc->execute();
        $active_discussions = $stmt_disc->get_result()->fetch_assoc()['total'] ?? 0;
        $stmt_disc->close();
    }
}

$classes = [];
if (in_array($_SESSION['role'] ?? '', ['super_administrator', 'administrator'], true)) {
    $res = $conn->query("SELECT k.*, u.fullname FROM section_kelas k JOIN users u ON k.instructor_id = u.id ORDER BY k.created_at DESC");
} else {
    $stmt = $conn->prepare("SELECT k.*, u.fullname FROM section_kelas k JOIN users u ON k.instructor_id = u.id WHERE k.instructor_id = ? ORDER BY k.created_at DESC");
    $stmt->bind_param("i", $instructor_id);
    $stmt->execute();
    $res = $stmt->get_result();
}

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $classes[] = $row;
    }
}

$csrf_token = function_exists('generate_csrf_token') ? generate_csrf_token() : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kelas & Kajian - Iqra' Society</title>
    <link rel="icon" type="image/x-icon" href="../asset/img/favicon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../asset/img/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Zilla+Slab:wght@600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>

<body class="bg-gray-50 font-sans antialiased" 
      x-data="kelasApp(<?= htmlspecialchars(json_encode($classes), ENT_QUOTES, 'UTF-8') ?>)">

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

            <!-- MEMANGGIL MENU MOBILE -->
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

                <!-- MEMANGGIL MENU DESKTOP -->
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
                    <h1 class="text-base md:text-lg font-extrabold text-gray-800">Kelola Kelas & Kajian</h1>
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

                <!-- Alert Notifikasi -->
                <?php if (!empty($_GET['error']) || !empty($error)): ?>
                    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-xs font-semibold flex items-center gap-2">
                        <i class="fa-solid fa-circle-exclamation text-base"></i> 
                        <span><?= htmlspecialchars($_GET['error'] ?? $error) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($_GET['success']) || !empty($success)): ?>
                    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs font-semibold flex items-center gap-2">
                        <i class="fa-solid fa-circle-check text-base"></i> 
                        <span><?= htmlspecialchars($_GET['success'] ?? $success) ?></span>
                    </div>
                <?php endif; ?>

                <!-- Bagian Header Tabel & Tombol Tambah -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                        <div>
                            <h2 class="text-sm font-bold text-gray-900">Daftar Kelas & Kajian Saya</h2>
                            <p class="text-[11px] text-gray-500">Kelola informasi program belajar, silabus, preview, dan penutupan kelas</p>
                        </div>
                        <button type="button" @click="modalCreate = true" class="bg-emerald-800 hover:bg-emerald-900 text-white text-xs font-bold px-4 py-2.5 rounded-xl transition flex items-center justify-center gap-2 shadow-sm">
                            <i class="fa-solid fa-plus"></i> Buat Kelas Baru
                        </button>
                    </div>

                    <template x-if="allClasses.length === 0">
                        <div class="text-center py-12 text-gray-400 text-xs">
                            <i class="fa-solid fa-book-open text-3xl mb-2 text-gray-300 block"></i>
                            Belum ada kelas atau kajian yang dibuat.
                        </div>
                    </template>

                    <template x-if="allClasses.length > 0">
                        <div>
                            <!-- DESKTOP TABLE -->
                            <div class="hidden md:block overflow-x-auto rounded-xl border border-gray-100">
                                <table class="w-full text-left text-xs text-gray-600">
                                    <thead class="bg-gray-50 text-gray-700 uppercase font-bold">
                                        <tr>
                                            <th class="p-3 rounded-tl-xl">Judul Kelas / Kajian</th>
                                            <th class="p-3">Sesi</th>
                                            <th class="p-3">Peserta</th>
                                            <th class="p-3">Biaya / Infaq</th>
                                            <th class="p-3">Status</th>
                                            <th class="p-3 text-right rounded-tr-xl">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <template x-for="row in paginatedClasses" :key="row.id">
                                            <tr class="hover:bg-gray-50/50 transition">
                                                <td class="p-3 min-w-[220px]">
                                                    <div class="font-bold text-gray-800" x-text="row.title"></div>
                                                    <template x-if="row.slug">
                                                        <div class="text-[10px] text-gray-400 font-normal truncate max-w-xs mt-0.5" x-text="'/' + row.slug"></div>
                                                    </template>
                                                </td>
                                                <td class="p-3 font-semibold text-gray-700 whitespace-nowrap" x-text="row.session_count + ' Sesi'"></td>
                                                <td class="p-3 font-medium text-gray-700 whitespace-nowrap">
                                                    <i class="fa-solid fa-users text-gray-400 mr-1"></i> <span x-text="row.student_count || 0"></span>
                                                </td>
                                                <td class="p-3 font-bold text-gray-900 whitespace-nowrap">
                                                    <template x-if="row.price > 0">
                                                        <span x-text="formatRupiah(row.price)"></span>
                                                    </template>
                                                    <template x-if="!row.price || row.price <= 0">
                                                        <span class="text-emerald-700">Gratis</span>
                                                    </template>
                                                </td>
                                                <td class="p-3 whitespace-nowrap">
                                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold border"
                                                          :class="getStatusBadge(row.status)"
                                                          x-text="row.status.toUpperCase()">
                                                    </span>
                                                </td>
                                                <td class="p-3 text-right whitespace-nowrap">
                                                    <div class="inline-flex items-center gap-1">
                                                        <button type="button" @click="editData = Object.assign({}, row); modalEdit = true" class="bg-amber-50 hover:bg-amber-100 text-amber-700 p-2 rounded-lg transition" title="Edit Kelas">
                                                            <i class="fa-solid fa-pen-to-square"></i>
                                                        </button>

                                                        <template x-if="row.status === 'closed'">
                                                            <form action="create_class.php" method="POST" class="inline-block">
                                                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                                <input type="hidden" name="action" value="toggle_status">
                                                                <input type="hidden" name="class_id" :value="row.id">
                                                                <input type="hidden" name="target_status" value="pending">
                                                                <button type="submit" class="bg-emerald-50 hover:bg-emerald-100 text-emerald-700 p-2 rounded-lg transition" title="Aktifkan Kembali">
                                                                    <i class="fa-solid fa-rotate-left"></i>
                                                                </button>
                                                            </form>
                                                        </template>
                                                        <template x-if="row.status !== 'closed'">
                                                            <button type="button" 
                                                                    @click="selectedClassId = row.id; targetStatus = 'closed'; modalCloseConfirm = true" 
                                                                    class="bg-red-50 hover:bg-red-100 text-red-600 p-2 rounded-lg transition" title="Tutup Kelas">
                                                                <i class="fa-solid fa-lock"></i>
                                                            </button>
                                                        </template>
                                                    </div>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                            <!-- MOBILE CARDS -->
                            <div class="md:hidden space-y-3">
                                <template x-for="row in paginatedClasses" :key="row.id">
                                    <article class="bg-white border border-gray-100 rounded-2xl p-3 shadow-sm">
                                        <div class="flex items-start justify-between gap-2">
                                            <h3 class="font-bold text-sm text-gray-800 leading-snug break-words" x-text="row.title"></h3>
                                            <span class="shrink-0 px-2 py-1 rounded-full text-[9px] font-bold border"
                                                  :class="getStatusBadge(row.status)"
                                                  x-text="row.status.toUpperCase()">
                                            </span>
                                        </div>

                                        <div class="mt-2 space-y-1 text-[11px] text-gray-500">
                                            <div class="flex items-center gap-2">
                                                <i class="fa-solid fa-layer-group text-emerald-600 w-3.5"></i>
                                                <span x-text="row.session_count + ' Sesi'"></span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <i class="fa-solid fa-users text-emerald-600 w-3.5"></i>
                                                <span x-text="(row.student_count || 0) + ' Peserta'"></span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <i class="fa-solid fa-tag text-emerald-600 w-3.5"></i>
                                                <span class="font-bold text-gray-800">
                                                    <template x-if="row.price > 0">
                                                        <span x-text="formatRupiah(row.price)"></span>
                                                    </template>
                                                    <template x-if="!row.price || row.price <= 0">
                                                        <span class="text-emerald-700">Gratis</span>
                                                    </template>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="mt-3 pt-3 border-t border-gray-100 flex gap-2">
                                            <button type="button" @click="editData = Object.assign({}, row); modalEdit = true" class="flex-1 bg-amber-50 hover:bg-amber-100 text-amber-700 py-2.5 rounded-xl transition text-xs font-bold flex items-center justify-center gap-2">
                                                <i class="fa-solid fa-pen-to-square"></i> Edit
                                            </button>

                                            <template x-if="row.status === 'closed'">
                                                <form action="create_class.php" method="POST" class="flex-1">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="class_id" :value="row.id">
                                                    <input type="hidden" name="target_status" value="pending">
                                                    <button type="submit" class="w-full bg-emerald-50 hover:bg-emerald-100 text-emerald-700 py-2.5 rounded-xl transition text-xs font-bold flex items-center justify-center gap-2">
                                                        <i class="fa-solid fa-rotate-left"></i> Aktifkan
                                                    </button>
                                                </form>
                                            </template>
                                            <template x-if="row.status !== 'closed'">
                                                <button type="button" 
                                                        @click="selectedClassId = row.id; targetStatus = 'closed'; modalCloseConfirm = true" 
                                                        class="flex-1 bg-red-50 hover:bg-red-100 text-red-600 py-2.5 rounded-xl transition text-xs font-bold flex items-center justify-center gap-2">
                                                    <i class="fa-solid fa-lock"></i> Tutup
                                                </button>
                                            </template>
                                        </div>
                                    </article>
                                </template>
                            </div>

                            <!-- ALPINE.JS PAGINATION CONTROLS -->
                            <div class="mt-5 pt-4 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <p class="text-[10px] sm:text-xs text-gray-500 text-center sm:text-left">
                                    Menampilkan <span class="font-bold text-gray-700" x-text="startItem"></span>–<span class="font-bold text-gray-700" x-text="endItem"></span> dari <span class="font-bold text-gray-700" x-text="allClasses.length"></span> kelas
                                </p>

                                <template x-if="totalPages > 1">
                                    <nav class="flex items-center justify-center gap-1" aria-label="Pagination">
                                        <!-- Prev Button -->
                                        <button type="button" 
                                                @click="prevPage()" 
                                                :disabled="currentPage === 1"
                                                :class="currentPage === 1 ? 'border-gray-100 bg-gray-50 text-gray-300 cursor-not-allowed' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'"
                                                class="w-9 h-9 flex items-center justify-center rounded-lg border text-xs font-bold transition" 
                                                title="Sebelumnya">
                                            <i class="fa-solid fa-chevron-left text-[10px]"></i>
                                        </button>

                                        <!-- First Page -->
                                        <template x-if="startPage > 1">
                                            <button type="button" @click="goToPage(1)" class="hidden sm:flex w-9 h-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 text-xs font-bold">1</button>
                                        </template>
                                        <template x-if="startPage > 2">
                                            <span class="hidden sm:flex w-6 justify-center text-gray-400 text-xs">…</span>
                                        </template>

                                        <!-- Visible Pages -->
                                        <template x-for="p in visiblePages" :key="p">
                                            <button type="button" 
                                                    @click="goToPage(p)" 
                                                    :class="p === currentPage ? 'bg-emerald-800 border-emerald-800 text-white' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'"
                                                    class="w-9 h-9 flex items-center justify-center rounded-lg border text-xs font-bold transition" 
                                                    x-text="p">
                                            </button>
                                        </template>

                                        <!-- Last Page -->
                                        <template x-if="endPage < totalPages - 1">
                                            <span class="hidden sm:flex w-6 justify-center text-gray-400 text-xs">…</span>
                                        </template>
                                        <template x-if="endPage < totalPages">
                                            <button type="button" @click="goToPage(totalPages)" class="hidden sm:flex w-9 h-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 text-xs font-bold" x-text="totalPages"></button>
                                        </template>

                                        <!-- Next Button -->
                                        <button type="button" 
                                                @click="nextPage()" 
                                                :disabled="currentPage === totalPages"
                                                :class="currentPage === totalPages ? 'border-gray-100 bg-gray-50 text-gray-300 cursor-not-allowed' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'"
                                                class="w-9 h-9 flex items-center justify-center rounded-lg border text-xs font-bold transition" 
                                                title="Berikutnya">
                                            <i class="fa-solid fa-chevron-right text-[10px]"></i>
                                        </button>
                                    </nav>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

            </main>
        </div>
    </div>

    <!-- MODAL KONFIRMASI TUTUP KELAS -->
    <div x-show="modalCloseConfirm" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div x-show="modalCloseConfirm" x-transition @click="modalCloseConfirm = false" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="modalCloseConfirm" x-transition class="relative w-full max-w-md bg-white rounded-2xl shadow-xl overflow-hidden z-10 border border-gray-100 p-6 text-center">
                <div class="w-12 h-12 rounded-full bg-red-100 text-red-600 flex items-center justify-center mx-auto mb-4 text-xl">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <h3 class="text-sm font-bold text-gray-900 mb-2">Konfirmasi Penutupan Kelas</h3>
                <p class="text-xs text-gray-600 leading-relaxed mb-6">
                    Apakah kamu yakin ingin menutup kelas/kajian ini? Mengaktifkan kelas kembali membutuhkan approval dari Administrator.
                </p>
                <form action="create_class.php" method="POST" class="flex items-center justify-center gap-3">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="class_id" :value="selectedClassId">
                    <input type="hidden" name="target_status" :value="targetStatus">

                    <button type="button" @click="modalCloseConfirm = false" class="w-1/2 py-2.5 rounded-xl text-xs font-bold text-gray-600 bg-gray-100 hover:bg-gray-200 transition">
                        Batal
                    </button>
                    <button type="submit" class="w-1/2 py-2.5 rounded-xl text-xs font-bold bg-red-600 hover:bg-red-700 text-white shadow-sm transition">
                        Ya, Tutup Kelas
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL FORM BUAT KELAS BARU -->
    <div x-show="modalCreate" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div x-show="modalCreate" x-transition @click="modalCreate = false" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="modalCreate" x-transition class="relative w-full max-w-2xl bg-white rounded-2xl shadow-xl overflow-hidden z-10 border border-gray-100 my-8">
                <div class="bg-emerald-950 p-6 text-white flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold">Buat Kelas / Kajian Baru</h3>
                        <p class="text-[11px] text-emerald-200/80 mt-0.5">Isi detail program kelas, rincian silabus, dan opsi preview</p>
                    </div>
                    <button @click="modalCreate = false" class="text-emerald-300 hover:text-white transition"><i class="fa-solid fa-xmark text-lg"></i></button>
                </div>
                <form action="create_class.php" method="POST" class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
                    <input type="hidden" name="action" value="create_class">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Judul Kelas / Kajian <span class="text-red-500">*</span></label>
                        <input type="text" name="title" required placeholder="Contoh: Fiqih Muamalah & Akad Modern" class="w-full px-3.5 py-2.5 text-xs bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-600/20 focus:border-emerald-600 transition">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Slug URL (Opsional)</label>
                        <input type="text" name="slug" placeholder="Otomatis jika dikosongkan" class="w-full px-3.5 py-2.5 text-xs bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-600/20 focus:border-emerald-600 transition">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Deskripsi / Konten Utama Kelas</label>
                        <textarea name="content" rows="3" placeholder="Penjelasan ringkas mengenai kelas..." class="w-full px-3.5 py-2.5 text-xs bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-600/20 focus:border-emerald-600 transition"></textarea>
                    </div>

                    <!-- Syllabus Breakdown -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">
                            <i class="fa-solid fa-list-ol text-emerald-600 mr-1"></i> Syllabus Breakdown (Rincian Pertemuan)
                        </label>
                        <p class="text-[10px] text-gray-400 mb-1">Gunakan enter per baris untuk memisahkan bab/pertemuan.</p>
                        <textarea name="syllabus_breakdown" rows="4" placeholder="Pertemuan 1: Pengantar Fiqih Muamalah&#10;Pertemuan 2: Syarat & Rukun Jual Beli&#10;Pertemuan 3: Akad Digital & E-Commerce" class="w-full px-3.5 py-2.5 text-xs bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-600/20 focus:border-emerald-600 transition"></textarea>
                    </div>

                    <!-- Pratinjau Materi (Free Preview) -->
                    <div class="p-4 bg-emerald-50/50 rounded-2xl border border-emerald-100/80 space-y-3">
                        <div>
                            <label class="block text-xs font-bold text-emerald-950 mb-1">
                                <i class="fa-solid fa-eye text-emerald-600 mr-1"></i> Pratinjau Materi (Free Preview)
                            </label>
                            <p class="text-[10px] text-emerald-800/70 mb-2">Materi gratis/cuplikan publik. Bisa berisi kombinasi Teks, Gambar, dan Video.</p>
                            <textarea name="preview_content" rows="3" placeholder="Ringkasan atau poin gratis yang bisa ditinjau..." class="w-full px-3.5 py-2.5 text-xs bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-600/20 focus:border-emerald-600 transition"></textarea>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-700 mb-1">URL Gambar Pratinjau (Opsional)</label>
                                <input type="text" name="preview_image" placeholder="https://.../gambar.jpg atau path file" class="w-full px-3 py-2 text-xs bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-600/20 focus:border-emerald-600 transition">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-700 mb-1">URL Video Preview (YouTube / MP4)</label>
                                <input type="text" name="preview_video" placeholder="https://www.youtube.com/watch?v=..." class="w-full px-3 py-2 text-xs bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-600/20 focus:border-emerald-600 transition">
                            </div>
                        </div>
                    </div>



                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Jumlah Sesi <span class="text-red-500">*</span></label>
                            <input type="number" name="session_count" min="1" value="1" required class="w-full px-3.5 py-2.5 text-xs bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-600/20 focus:border-emerald-600 transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Harga / Infaq (Rp)</label>
                            <input type="number" name="price" min="0" step="1000" value="0" class="w-full px-3.5 py-2.5 text-xs bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-600/20 focus:border-emerald-600 transition">
                        </div>
                    </div>

                    <div class="pt-4 border-t border-gray-100 flex items-center justify-end gap-2">
                        <button type="button" @click="modalCreate = false" class="px-4 py-2.5 rounded-xl text-xs font-bold text-gray-600 hover:bg-gray-100 transition">Batal</button>
                        <button type="submit" class="px-5 py-2.5 rounded-xl text-xs font-bold bg-emerald-800 hover:bg-emerald-900 text-white shadow-sm transition">Ajukan Kelas</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL FORM EDIT KELAS -->
    <div x-show="modalEdit" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div x-show="modalEdit" x-transition @click="modalEdit = false" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="modalEdit" x-transition class="relative w-full max-w-2xl bg-white rounded-2xl shadow-xl overflow-hidden z-10 border border-gray-100 my-8">
                <div class="bg-amber-900 p-6 text-white flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold">Edit Kelas / Kajian</h3>
                        <p class="text-[11px] text-amber-200/80 mt-0.5">Ubah rincian informasi materi, silabus, dan CTA kelas</p>
                    </div>
                    <button @click="modalEdit = false" class="text-amber-200 hover:text-white transition"><i class="fa-solid fa-xmark text-lg"></i></button>
                </div>
                <form action="create_class.php" method="POST" class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
                    <input type="hidden" name="action" value="edit_class">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="class_id" :value="editData.id">

                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Judul Kelas / Kajian <span class="text-red-500">*</span></label>
                        <input type="text" name="title" :value="editData.title" required class="w-full px-3.5 py-2.5 text-xs bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-600/20 focus:border-amber-600 transition">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Slug URL</label>
                            <input type="text" name="slug" :value="editData.slug" placeholder="Otomatis terisi jika dikosongkan" class="w-full px-3.5 py-2.5 text-xs bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-600/20 focus:border-amber-600 transition">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Deskripsi / Konten Utama Kelas</label>
                        <textarea name="content" rows="3" x-text="editData.content" class="w-full px-3.5 py-2.5 text-xs bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-600/20 focus:border-amber-600 transition"></textarea>
                    </div>

                    <!-- Syllabus Breakdown -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">
                            <i class="fa-solid fa-list-ol text-amber-600 mr-1"></i> Syllabus Breakdown (Rincian Pertemuan)
                        </label>
                        <textarea name="syllabus_breakdown" rows="4" x-text="editData.syllabus_breakdown" placeholder="Rincian bab atau materi tiap pertemuan..." class="w-full px-3.5 py-2.5 text-xs bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-600/20 focus:border-amber-600 transition"></textarea>
                    </div>

                    <!-- Pratinjau Materi (Free Preview) -->
                    <div class="p-4 bg-amber-50/50 rounded-2xl border border-amber-100/80 space-y-3">
                        <div>
                            <label class="block text-xs font-bold text-amber-950 mb-1">
                                <i class="fa-solid fa-eye text-amber-600 mr-1"></i> Pratinjau Materi (Free Preview)
                            </label>
                            <textarea name="preview_content" rows="3" x-text="editData.preview_content" placeholder="Materi publik / cuplikan bebas..." class="w-full px-3.5 py-2.5 text-xs bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-600/20 focus:border-amber-600 transition"></textarea>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-700 mb-1">URL Gambar Pratinjau (Opsional)</label>
                                <input type="text" name="preview_image" :value="editData.preview_image" placeholder="https://.../gambar.jpg" class="w-full px-3 py-2 text-xs bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-600/20 focus:border-amber-600 transition">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-700 mb-1">URL Video Preview (YouTube / MP4)</label>
                                <input type="text" name="preview_video" :value="editData.preview_video" placeholder="https://www.youtube.com/watch?v=..." class="w-full px-3 py-2 text-xs bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-600/20 focus:border-amber-600 transition">
                            </div>
                        </div>
                    </div>

                    <!-- Tombol Aksi (CTA) Kustom -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-gray-100">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Jumlah Sesi <span class="text-red-500">*</span></label>
                            <input type="number" name="session_count" min="1" :value="editData.session_count" required class="w-full px-3.5 py-2.5 text-xs bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-600/20 focus:border-amber-600 transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Harga / Infaq (Rp)</label>
                            <input type="number" name="price" min="0" step="1000" :value="editData.price" class="w-full px-3.5 py-2.5 text-xs bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-600/20 focus:border-amber-600 transition">
                        </div>
                    </div>

                    <div class="pt-4 border-t border-gray-100 flex items-center justify-end gap-2">
                        <button type="button" @click="modalEdit = false" class="px-4 py-2.5 rounded-xl text-xs font-bold text-gray-600 hover:bg-gray-100 transition">Batal</button>
                        <button type="submit" class="px-5 py-2.5 rounded-xl text-xs font-bold bg-amber-600 hover:bg-amber-700 text-white shadow-sm transition"><i class="fa-solid fa-floppy-disk"></i> Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<script>
function kelasApp(initialData) {
    return {
        sidebarOpen: false,
        modalCreate: false,
        modalEdit: false,
        modalCloseConfirm: false,
        editData: {},
        selectedClassId: null,
        targetStatus: '',

        // Data & Pagination
        allClasses: initialData || [],
        currentPage: 1,
        perPage: 10,

        get totalPages() {
            return Math.max(1, Math.ceil(this.allClasses.length / this.perPage));
        },

        get paginatedClasses() {
            const start = (this.currentPage - 1) * this.perPage;
            return this.allClasses.slice(start, start + this.perPage);
        },

        get startItem() {
            if (this.allClasses.length === 0) return 0;
            return (this.currentPage - 1) * this.perPage + 1;
        },

        get endItem() {
            return Math.min(this.currentPage * this.perPage, this.allClasses.length);
        },

        get startPage() {
            return Math.max(1, this.currentPage - 2);
        },

        get endPage() {
            return Math.min(this.totalPages, this.currentPage + 2);
        },

        get visiblePages() {
            let pages = [];
            for (let p = this.startPage; p <= this.endPage; p++) {
                pages.push(p);
            }
            return pages;
        },

        goToPage(page) {
            if (page >= 1 && page <= this.totalPages) {
                this.currentPage = page;
            }
        },

        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
            }
        },

        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
            }
        },

        getStatusBadge(status) {
            const map = {
                'pending': 'bg-amber-100 text-amber-800 border-amber-200',
                'approved': 'bg-emerald-100 text-emerald-800 border-emerald-200',
                'rejected': 'bg-red-100 text-red-800 border-red-200',
                'closed': 'bg-gray-100 text-gray-700 border-gray-200'
            };
            return map[status] || 'bg-gray-100 text-gray-800 border-gray-200';
        },

        formatRupiah(number) {
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(number);
        }
    };
}
</script>

</body>
</html>