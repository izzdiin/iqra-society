<?php
require_once __DIR__ . '/../includes/auth_middleware.php';

check_access(['super_administrator', 'administrator', 'pengajar']);

// Menggunakan user_id dari session
$current_user_id = (int)$_SESSION['user_id'];
$is_admin = in_array($_SESSION['role'] ?? '', ['super_administrator', 'administrator'], true);
$owner_and = $is_admin ? '' : ' AND author_id = ' . (int)$current_user_id;
$success = '';
$error   = '';

// --- HANDLE DELETE POST ---
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    $stmt_del = $conn->prepare("DELETE FROM posts WHERE id = ?" . $owner_and);
    $stmt_del->bind_param("i", $delete_id); 
    
    if ($stmt_del->execute()) {
        $success = "Konten berhasil dihapus.";
    } else {
        $error = "Gagal menghapus konten: " . $conn->error;
    }
    $stmt_del->close();
}

// --- HANDLE TOGGLE STATUS (DRAFT/PUBLISHED) ---
if (isset($_GET['toggle_status'])) {
    $toggle_id = (int)$_GET['toggle_status'];
    
    $stmt_cek = $conn->prepare("SELECT status FROM posts WHERE id = ?" . $owner_and);
    $stmt_cek->bind_param("i", $toggle_id);
    $stmt_cek->execute();
    $res = $stmt_cek->get_result();
    
    if ($row = $res->fetch_assoc()) {
        $new_status = ($row['status'] === 'published') ? 'draft' : 'published';
        $stmt_upd = $conn->prepare("UPDATE posts SET status = ? WHERE id = ?");
        $stmt_upd->bind_param("si", $new_status, $toggle_id);
        
        if ($stmt_upd->execute()) {
            $success = "Status berhasil diubah menjadi " . ucfirst($new_status) . ".";
        } else {
            $error = "Gagal mengubah status: " . $conn->error;
        }
        $stmt_upd->close();
    }
    $stmt_cek->close();
}

// --- HANDLE SUBMIT ARTICLE (INSERT / UPDATE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_article'])) {
    $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
    $title   = trim($_POST['title'] ?? '');
    $slug    = trim($_POST['slug'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if (empty($slug) && !empty($title)) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
    } else {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $slug), '-'));
    }

    if (empty($title) || empty($content)) {
        $error = "Judul dan Isi Konten tidak boleh kosong!";
    } else {
        if ($post_id > 0) {
            $stmt_check = $conn->prepare("SELECT id FROM posts WHERE slug = ? AND id != ?");
            $stmt_check->bind_param("si", $slug, $post_id);
        } else {
            $stmt_check = $conn->prepare("SELECT id FROM posts WHERE slug = ?");
            $stmt_check->bind_param("s", $slug);
        }
        
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            $slug .= '-' . time();
        }
        $stmt_check->close();

        if ($post_id > 0) {
            $stmt = $conn->prepare("UPDATE posts SET title = ?, slug = ?, content = ? WHERE id = ?" . $owner_and);
            $stmt->bind_param("sssi", $title, $slug, $content, $post_id);
            if ($stmt->execute()) {
                $success = "Konten berhasil diperbarui! URL: <strong>" . htmlspecialchars($slug) . "</strong>";
            } else {
                $error = "Gagal memperbarui data: " . $conn->error;
            }
            $stmt->close();
        } else {
            $stmt = $conn->prepare("INSERT INTO posts (title, slug, content, author_id, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("sssi", $title, $slug, $content, $current_user_id); 
            if ($stmt->execute()) {
                $success = "Konten berhasil disimpan! URL: <strong>" . htmlspecialchars($slug) . "</strong>";
            } else {
                $error = "Gagal menyimpan data: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// --- PERSIAPAN VARIABEL UNTUK FORM EDIT ---
$edit_id = 0;
$edit_title = '';
$edit_slug = '';
$edit_content = '';

if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $stmt_edit = $conn->prepare("SELECT title, slug, content FROM posts WHERE id = ?" . $owner_and);
    $stmt_edit->bind_param("i", $edit_id);
    $stmt_edit->execute();
    $res_edit = $stmt_edit->get_result();
    
    if ($row_edit = $res_edit->fetch_assoc()) {
        $edit_title   = $row_edit['title'];
        $edit_slug    = $row_edit['slug'];
        $edit_content = $row_edit['content'];
    } else {
        $error = "Data konten tidak ditemukan atau Anda tidak memiliki akses.";
        $edit_id = 0; 
    }
    $stmt_edit->close();
}

$pending_count_query = $conn->query("SELECT COUNT(id) FROM section_kelas WHERE status = 'pending'");
$pending_notifications = $pending_count_query ? (int) $pending_count_query->fetch_row()[0] : 0;

// --- AMBIL DATA POST UNTUK TABEL ---
if ($is_admin) {
    $posts_data = $conn->query("SELECT id, title, slug, status, created_at FROM posts ORDER BY created_at DESC");
} else {
    $stmt_posts = $conn->prepare("SELECT id, title, slug, status, created_at FROM posts WHERE author_id = ? ORDER BY created_at DESC");
    $stmt_posts->bind_param("i", $current_user_id);
    $stmt_posts->execute();
    $posts_data = $stmt_posts->get_result();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Konten - Iqra' Society</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Zilla+Slab:wght@600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Quill.js (Rich Text Editor) CSS & JS -->
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
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

            <nav class="space-y-1 text-xs font-semibold">
                <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 text-emerald-200 hover:bg-emerald-900 rounded-xl transition">
                    <i class="fa-solid fa-chart-line"></i> Dashboard
                </a>
                <a href="manage_landing.php" class="flex items-center gap-3 px-4 py-3 text-emerald-200 hover:bg-emerald-900 rounded-xl transition">
                    <i class="fa-solid fa-layer-group"></i> Landing Page
                </a>
                <a href="manage_class.php" class="flex items-center justify-between px-4 py-3 text-emerald-200 hover:bg-emerald-900 rounded-xl transition">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-graduation-cap"></i> Manajemen Kelas
                    </div>
                    <?php if ($pending_notifications > 0): ?>
                        <span class="bg-red-500 text-white text-[10px] px-2 py-0.5 rounded-full font-bold"><?= $pending_notifications ?></span>
                    <?php endif; ?>
                </a>

                <?php if (in_array($_SESSION['role'], ['super_administrator', 'super_admin'], true)): ?>
                    <a href="users.php" class="flex items-center gap-3 px-4 py-3 text-emerald-200 hover:bg-emerald-900 rounded-xl transition">
                        <i class="fa-solid fa-users-gear"></i> Manajemen User
                    </a>
                    <a href="settings_gateway.php" class="flex items-center gap-3 px-4 py-3 text-emerald-200 hover:bg-emerald-900 rounded-xl transition">
                        <i class="fa-solid fa-credit-card"></i> Payment Gateway
                    </a>
                <?php endif; ?>
                <a href="post.php" class="flex items-center gap-3 px-4 py-3 bg-emerald-800 text-white rounded-xl">
                    <i class="fa-solid fa-pen-nib"></i> Manajemen Konten
                </a>
                <a href="profile.php" class="flex items-center gap-3 px-4 py-3 text-emerald-200 hover:bg-emerald-900 rounded-xl transition">
                    <i class="fa-solid fa-user-gear"></i> Profil
                </a>
            </nav>
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

                <nav class="space-y-1 text-xs font-semibold">
                    <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 text-emerald-200 hover:bg-emerald-900 rounded-xl transition">
                        <i class="fa-solid fa-chart-line"></i> Dashboard
                    </a>
                    <a href="manage_landing.php" class="flex items-center gap-3 px-4 py-3 text-emerald-200 hover:bg-emerald-900 rounded-xl transition">
                        <i class="fa-solid fa-layer-group"></i> Landing Page
                    </a>
                    <a href="manage_class.php" class="flex items-center justify-between px-4 py-3 text-emerald-200 hover:bg-emerald-900 rounded-xl transition">
                        <div class="flex items-center gap-3">
                            <i class="fa-solid fa-graduation-cap"></i> Manajemen Kelas
                        </div>
                        <?php if ($pending_notifications > 0): ?>
                            <span class="bg-red-500 text-white text-[10px] px-2 py-0.5 rounded-full font-bold"><?= $pending_notifications ?></span>
                        <?php endif; ?>
                    </a>
                    <?php if (in_array($_SESSION['role'], ['super_administrator', 'super_admin'], true)): ?>
                        <a href="users.php" class="flex items-center gap-3 px-4 py-3 text-emerald-200 hover:bg-emerald-900 rounded-xl transition">
                            <i class="fa-solid fa-users-gear"></i> Manajemen User
                        </a>
                        <a href="settings_gateway.php" class="flex items-center gap-3 px-4 py-3 text-emerald-200 hover:bg-emerald-900 rounded-xl transition">
                            <i class="fa-solid fa-credit-card"></i> Payment Gateway
                        </a>
                    <?php endif; ?>
                    <a href="post.php" class="flex items-center gap-3 px-4 py-3 bg-emerald-800 text-white rounded-xl">
                        <i class="fa-solid fa-pen-nib"></i> Manajemen Konten
                    </a>
                    <a href="profile.php" class="flex items-center gap-3 px-4 py-3 text-emerald-200 hover:bg-emerald-900 rounded-xl transition">
                        <i class="fa-solid fa-user-gear"></i> Profil
                    </a>
                </nav>
            </div>

            <div class="p-6 border-t border-emerald-900">
                <a href="../logout.php" class="flex items-center gap-2 text-xs font-bold text-red-400 hover:text-red-300">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Navbar Header -->
            <header class="sticky top-0 z-30 bg-white/95 backdrop-blur-md border-b border-gray-100 px-4 md:px-6 py-4 flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-3">
                    <button @click="sidebarOpen = true" class="md:hidden text-gray-600 hover:text-gray-900 p-2 rounded-lg hover:bg-gray-100">
                        <i class="fa-solid fa-bars text-lg"></i>
                    </button>
                    <h1 class="text-base md:text-lg font-extrabold text-gray-800">Manajemen Konten</h1>
                </div>

                <div class="flex items-center gap-2 md:gap-3">
                    <div class="relative" x-data="{ linkDropdown: false }">
                        <button @click="linkDropdown = !linkDropdown" class="relative p-2.5 rounded-xl text-gray-500 hover:text-gray-800 hover:bg-gray-100 transition focus:outline-none" title="Tautan Web">
                            <i class="fa-solid fa-link text-lg"></i>
                        </button>
                        <div x-show="linkDropdown" @click.outside="linkDropdown = false" style="display: none;" class="absolute right-0 mt-2 w-52 bg-white rounded-2xl shadow-xl border border-gray-100 py-2 z-50">
                            <div class="px-4 py-1.5 border-b border-gray-100">
                                <span class="text-xs font-bold text-gray-800">Akses Cepat Web</span>
                            </div>
                            <div class="py-1">
                                <a href="../index.php" target="_blank" class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-gray-700 hover:bg-emerald-50 hover:text-emerald-600 transition">
                                    <i class="fa-solid fa-globe w-4 text-center text-gray-400"></i>
                                    Web Iqra
                                </a>
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
                        <div x-show="notifDropdown" @click.outside="notifDropdown = false" style="display: none;" class="absolute right-0 mt-2 w-72 bg-white rounded-2xl shadow-xl border border-gray-100 py-3 z-50">
                            <div class="px-4 pb-2 border-b border-gray-100 flex items-center justify-between">
                                <span class="text-xs font-bold text-gray-800">Notifikasi</span>
                                <?php if ($pending_notifications > 0): ?>
                                    <span class="bg-red-100 text-red-700 text-[10px] font-bold px-2 py-0.5 rounded-full"><?= $pending_notifications ?> Baru</span>
                                <?php endif; ?>
                            </div>
                            <div class="divide-y divide-gray-50 max-h-60 overflow-y-auto">
                                <?php if ($pending_notifications > 0): ?>
                                    <a href="approve_class.php" class="flex items-start gap-3 p-3 hover:bg-emerald-50/50 transition">
                                        <div class="w-8 h-8 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center shrink-0 mt-0.5 text-xs">
                                            <i class="fa-solid fa-graduation-cap"></i>
                                        </div>
                                        <div>
                                            <p class="text-xs font-bold text-gray-800">Moderasi Kelas Baru</p>
                                            <p class="text-[10px] text-gray-500 mt-0.5">Ada <strong><?= $pending_notifications ?></strong> pengajuan kelas baru menunggu persetujuan Anda.</p>
                                        </div>
                                    </a>
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

            <!-- MAIN CONTENT -->
            <main class="p-4 md:p-6 space-y-4 md:space-y-6" x-data="{ showListModal: false }">

                <!-- Alert Error -->
                <?php if ($error): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 p-3 md:p-4 rounded-xl text-xs flex items-center gap-2">
                        <i class="fa-solid fa-circle-exclamation text-base shrink-0"></i> 
                        <span><?= $error ?></span>
                    </div>
                <?php endif; ?>

                <!-- Alert Sukses -->
                <?php if ($success): ?>
                    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-3 md:p-4 rounded-xl text-xs flex items-center gap-2">
                        <i class="fa-solid fa-circle-check text-base shrink-0"></i> 
                        <span><?= $success ?></span>
                    </div>
                <?php endif; ?>

                <!-- CARD FORM EDIT / INPUT KONTEN -->
                <div id="form-section" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 md:p-6 scroll-mt-24 relative z-0">
                    <div class="flex flex-wrap items-center justify-between pb-4 mb-4 border-b border-gray-100 gap-3">
                        <h2 class="text-sm md:text-base font-extrabold text-gray-800 flex items-center gap-2">
                            <i class="fa-solid <?= $edit_id > 0 ? 'fa-pen' : 'fa-pen-to-square' ?> text-emerald-600"></i>
                            <?= $edit_id > 0 ? 'Edit Konten' : 'Tambah Konten Baru' ?>
                        </h2>
                        
                        <div class="flex items-center gap-2">
                            <!-- TOMBOL TRIGGER POPUP MODAL -->
                            <button type="button" @click="showListModal = true" class="text-xs font-bold px-4 py-2 bg-emerald-100 text-emerald-800 hover:bg-emerald-200 rounded-lg transition flex items-center gap-2">
                                <i class="fa-solid fa-list-ul"></i> List Postingan/Artikel
                            </button>

                            <?php if ($edit_id > 0): ?>
                                <a href="post.php" class="text-xs font-bold text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100 px-4 py-2 rounded-lg transition flex items-center gap-1.5">
                                    <i class="fa-solid fa-xmark"></i> Batal
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <form action="post.php" method="POST" id="articleForm" class="space-y-4">
                        <input type="hidden" name="post_id" value="<?= $edit_id ?>">
                        
                        <div>
                            <label for="title" class="block text-xs font-bold text-gray-700 mb-1">Judul Postingan/Artikel <span class="text-red-500">*</span></label>
                            <input type="text" id="title" name="title" value="<?= htmlspecialchars($edit_title) ?>" required placeholder="Masukkan judul artikel..." class="w-full px-3.5 py-2.5 text-xs bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
                        </div>

                        <div>
                            <label for="slug" class="block text-xs font-bold text-gray-700 mb-1">URL Postingan/Artikel</label>
                            <div class="flex items-center rounded-xl bg-gray-50 border border-gray-200 focus-within:ring-2 focus-within:ring-emerald-500/20 focus-within:border-emerald-500 transition overflow-hidden">
                                <span class="px-3 text-xs text-gray-400 border-r border-gray-200 font-mono bg-gray-100/70 py-2.5">
                                    https://iqrasociety.id/post/
                                </span>
                                <input type="text" id="slug" name="slug" value="<?= htmlspecialchars($edit_slug) ?>" placeholder="url-otomatis" class="w-full px-3 py-2.5 text-xs bg-transparent focus:outline-none font-mono text-emerald-700 font-semibold">
                            </div>
                            <span class="text-[10px] text-gray-400 mt-1 block">Otomatis dibuat dari judul di atas, tapi juga dapat diubah secara manual.</span>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Isi Konten <span class="text-red-500">*</span></label>
                            <div id="editor" class="bg-white rounded-b-xl min-h-[220px] text-sm">
                                <?= $edit_content ?>
                            </div>
                            <input type="hidden" name="content" id="content">
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                            <?php if ($edit_id == 0): ?>
                            <button type="reset" class="px-4 py-2.5 rounded-xl text-xs font-bold text-gray-500 hover:bg-gray-100 transition">
                                Reset
                            </button>
                            <?php endif; ?>
                            
                            <!-- Tombol Preview Baru -->
                            <button type="button" id="btnPreview" class="px-4 py-2.5 bg-blue-500 hover:bg-blue-600 text-white rounded-xl text-xs font-bold transition flex items-center gap-2 shadow-sm">
                                <i class="fa-solid fa-eye"></i> Preview
                            </button>

                            <button type="submit" name="submit_article" class="px-5 py-2.5 <?= $edit_id > 0 ? 'bg-amber-500 hover:bg-amber-600' : 'bg-emerald-600 hover:bg-emerald-700' ?> text-white rounded-xl text-xs font-bold transition flex items-center gap-2 shadow-sm">
                                <i class="fa-solid <?= $edit_id > 0 ? 'fa-floppy-disk' : 'fa-paper-plane' ?>"></i>
                                <?= $edit_id > 0 ? 'Perbarui Konten' : 'Simpan Konten' ?>
                            </button>
                        </div>
                    </form>
                </div>


                <!-- ================= POP UP MODAL DAFTAR POSTS ================= -->
                <div x-show="showListModal" style="display: none;" 
                     class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6 bg-gray-900/60 backdrop-blur-sm"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0">
                    
                    <div @click.outside="showListModal = false" 
                         class="bg-white rounded-2xl w-full max-w-5xl max-h-full flex flex-col shadow-2xl relative"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">
                        
                        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50 rounded-t-2xl shrink-0">
                            <h2 class="text-base font-extrabold text-gray-800 flex items-center gap-2">
                                <i class="fa-solid fa-list text-emerald-600"></i>
                                Daftar Konten Anda
                            </h2>
                            <button @click="showListModal = false" class="text-gray-400 hover:text-red-500 bg-white hover:bg-red-50 w-8 h-8 rounded-lg flex items-center justify-center transition border border-gray-200">
                                <i class="fa-solid fa-xmark text-lg"></i>
                            </button>
                        </div>

                        <div class="p-4 overflow-y-auto w-full min-h-[300px]">
                            <table class="w-full text-left border-collapse overflow-visible">
                                <thead>
                                    <tr class="bg-gray-100 text-gray-500 text-[10px] uppercase tracking-wider">
                                        <th class="px-4 py-3 font-bold rounded-tl-xl">Judul & Slug</th>
                                        <th class="px-4 py-3 font-bold hidden sm:table-cell">Tanggal</th>
                                        <th class="px-4 py-3 font-bold text-center">Status</th>
                                        <th class="px-4 py-3 font-bold text-center rounded-tr-xl">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-xs">
                                    <?php if ($posts_data && $posts_data->num_rows > 0): ?>
                                        <?php while ($row = $posts_data->fetch_assoc()): ?>
                                            <tr class="hover:bg-gray-50/50 transition">
                                                <td class="px-4 py-3 pr-2">
                                                    <p class="font-bold text-gray-800 line-clamp-1"><?= htmlspecialchars($row['title']) ?></p>
                                                    <p class="text-[10px] text-gray-400 font-mono mt-0.5 truncate max-w-[150px] sm:max-w-xs">/post/<?= htmlspecialchars($row['slug']) ?></p>
                                                </td>
                                                <td class="px-4 py-3 text-gray-600 hidden sm:table-cell whitespace-nowrap">
                                                    <?= date('d M Y, H:i', strtotime($row['created_at'])) ?>
                                                </td>
                                                <td class="px-4 py-3 text-center">
                                                    <?php if (($row['status'] ?? 'draft') === 'published'): ?>
                                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 whitespace-nowrap">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Published
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-gray-100 text-gray-600 whitespace-nowrap">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> Draft
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                
                                                <td class="px-4 py-3 text-center relative w-16">
                                                    <div class="inline-block relative" x-data="{ actionOpen: false }">
                                                        <button @click="actionOpen = !actionOpen" 
                                                                class="p-2 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition" 
                                                                title="Menu Aksi">
                                                            <i class="fa-solid fa-gear text-sm"></i>
                                                        </button>
                                                        
                                                        <div x-show="actionOpen" 
                                                             @click.outside="actionOpen = false" 
                                                             style="display: none;" 
                                                             class="absolute right-8 top-0 mt-1 w-44 bg-white border border-gray-100 rounded-xl shadow-[0_10px_20px_-5px_rgba(0,0,0,0.15)] py-1.5 z-20 text-left"
                                                             x-transition:enter="transition ease-out duration-100"
                                                             x-transition:enter-start="opacity-0 scale-95"
                                                             x-transition:enter-end="opacity-100 scale-100"
                                                             x-transition:leave="transition ease-in duration-75"
                                                             x-transition:leave-start="opacity-100 scale-100"
                                                             x-transition:leave-end="opacity-0 scale-95">
                                                            
                                                            <a href="?edit_id=<?= $row['id'] ?>#form-section" 
                                                               @click="showListModal = false" 
                                                               class="block px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition flex items-center gap-2">
                                                                <i class="fa-solid fa-pen w-3 text-center"></i> Edit Konten
                                                            </a>
                                                            
                                                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_administrator'): ?>
                                                                <a href="?toggle_status=<?= $row['id'] ?>" 
                                                                   class="block px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-amber-50 hover:text-amber-600 transition flex items-center gap-2">
                                                                    <i class="fa-solid fa-rotate w-3 text-center"></i> Ubah Status
                                                                </a>
                                                            <?php endif; ?>
                                                            
                                                            <div class="h-px bg-gray-100 my-1"></div>
                                                            
                                                            <a href="?delete=<?= $row['id'] ?>" 
                                                               onclick="return confirm('Apakah Anda yakin ingin menghapus konten ini? Tindakan ini tidak dapat dibatalkan.');" 
                                                               class="block px-4 py-2 text-xs font-semibold text-red-600 hover:bg-red-50 transition flex items-center gap-2">
                                                                <i class="fa-solid fa-trash w-3 text-center"></i> Hapus
                                                            </a>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                        <tr><td colspan="4" class="p-6"></td></tr>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="px-4 py-12 text-center text-gray-400">
                                                <i class="fa-regular fa-folder-open text-4xl mb-3 block text-gray-300"></i>
                                                Belum ada konten yang Anda tulis.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            
            const quill = new Quill('#editor', {
                theme: 'snow',
                placeholder: 'Tuliskan isi konten di sini...',
                modules: {
                    toolbar: [
                        [{ 'header': [1, 2, 3, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ 'color': [] }, { 'background': [] }],
                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                        [{ 'align': [] }],
                        ['link', 'blockquote', 'code-block', 'image'],
                        ['clean']
                    ]
                }
            });

            function createSlug(text) {
                return text
                    .toString()
                    .toLowerCase()
                    .trim()
                    .replace(/\s+/g, '-')           
                    .replace(/[^\w\-]+/g, '')       
                    .replace(/\-\-+/g, '-')         
                    .replace(/^-+/, '')             
                    .replace(/-+$/, '');            
            }

            const titleInput = document.getElementById('title');
            const slugInput  = document.getElementById('slug');
            
            let isCustomSlug = <?= $edit_id > 0 ? 'true' : 'false' ?>; 

            titleInput.addEventListener('keyup', function () {
                if (!isCustomSlug) {
                    slugInput.value = createSlug(this.value);
                }
            });

            slugInput.addEventListener('input', function () {
                isCustomSlug = true;
                this.value = createSlug(this.value);
            });

            slugInput.addEventListener('blur', function () {
                if (this.value.trim() === '') {
                    isCustomSlug = false;
                    this.value = createSlug(titleInput.value);
                }
            });

            const form = document.getElementById('articleForm');
            
            // Tangkap submit normal form
            form.addEventListener('submit', function (e) {
                const contentInput = document.getElementById('content');
                const htmlContent = quill.root.innerHTML;
                
                if (quill.getText().trim().length === 0) {
                    contentInput.value = '';
                } else {
                    contentInput.value = htmlContent;
                }
            });

            // Script Khusus untuk Tombol Preview
            const btnPreview = document.getElementById('btnPreview');
            if (btnPreview) {
                btnPreview.addEventListener('click', function () {
                    const contentInput = document.getElementById('content');
                    contentInput.value = quill.root.innerHTML;

                    const originalAction = form.action;
                    const originalTarget = form.target;

                    const previewFlag = document.createElement('input');
                    previewFlag.type = 'hidden';
                    previewFlag.name = 'is_preview';
                    previewFlag.value = '1';
                    previewFlag.id = 'temp_preview_flag';
                    form.appendChild(previewFlag);

                    // Arahkan form ke view_post.php (sesuaikan path relatifnya jika view_post di root)
                    form.action = '../view_post.php'; 
                    form.target = '_blank';
                    
                    form.submit();

                    // Kembalikan form ke fungsi semula 
                    form.action = originalAction;
                    form.target = originalTarget;
                    form.removeChild(previewFlag);
                });
            }

        });
    </script>
</body>
</html>