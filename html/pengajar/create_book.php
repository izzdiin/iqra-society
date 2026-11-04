<?php
require_once __DIR__ . '/../includes/auth_middleware.php';

check_access(['super_administrator', 'administrator', 'pengajar']);

$instructor_id = $_SESSION['user_id'] ?? 0;
$is_admin = in_array($_SESSION['role'] ?? '', ['super_administrator', 'administrator'], true);
$error = '';
$success = '';

if (!function_exists('sanitize')) {
    function sanitize($data) {
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}
function generate_slug($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return empty($text) ? 'n-a' : $text;
}
function ensure_unique_slug_buku($conn, $slug, $exclude_id = 0) {
    $base = $slug; $i = 2;
    while (true) {
        if ($exclude_id > 0) { $s = $conn->prepare("SELECT id FROM section_buku WHERE slug = ? AND id != ? LIMIT 1"); $s->bind_param("si", $slug, $exclude_id); }
        else { $s = $conn->prepare("SELECT id FROM section_buku WHERE slug = ? LIMIT 1"); $s->bind_param("s", $slug); }
        $s->execute(); $ex = $s->get_result()->num_rows > 0; $s->close();
        if (!$ex) return $slug;
        $slug = $base . '-' . $i; $i++;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target = $_POST['target'] ?? '';
    $action = $_POST['action'] ?? '';

    if ($target === 'buku') {
        $title            = sanitize($_POST['title'] ?? '');
        $price            = (float) ($_POST['price'] ?? 0);
        $link_description = sanitize($_POST['link_description'] ?? '');
        $id               = (int) ($_POST['id'] ?? 0);

        $is_super_admin   = isset($_SESSION['role']) && $_SESSION['role'] === 'super_administrator';
        if ($is_super_admin && isset($_POST['status'])) {
            $status = sanitize($_POST['status']);
            if (!in_array($status, ['pending', 'approved'], true)) {
                $status = 'pending';
            }
        } else {
            $status = 'pending';
            if ($action === 'update') {
                $id_check = (int) ($_POST['id'] ?? 0);
                $st_chk = $conn->prepare("SELECT status FROM section_buku WHERE id = ?" . ($is_admin ? "" : " AND user_id = " . (int)$instructor_id));
                if ($st_chk) {
                    $st_chk->bind_param("i", $id_check);
                    $st_chk->execute();
                    $res_chk = $st_chk->get_result()->fetch_assoc();
                    $status = $res_chk['status'] ?? 'pending';
                    $st_chk->close();
                }
            }
        }

        if ($action === 'delete') {
            $st = $conn->prepare("SELECT cover FROM section_buku WHERE id = ?" . ($is_admin ? "" : " AND user_id = " . (int)$instructor_id));
            if ($st) {
                $st->bind_param("i", $id);
                $st->execute();
                $old = $st->get_result()->fetch_assoc();
                $st->close();

                if (!empty($old['cover']) && file_exists(__DIR__ . '/../uploads/buku/' . $old['cover'])) {
                    unlink(__DIR__ . '/../uploads/buku/' . $old['cover']);
                }
            }

            $stmt = $conn->prepare("DELETE FROM section_buku WHERE id = ?" . ($is_admin ? "" : " AND user_id = " . (int)$instructor_id));
            if ($stmt) {
                $stmt->bind_param("i", $id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $success = "Buku berhasil dihapus!";
                } else {
                    $error = "Buku tidak ditemukan atau bukan milik Anda.";
                }
                $stmt->close();
            }
        } else {
            if (empty($title)) {
                $error = "Judul buku wajib diisi.";
            } else {
                $cover_filename = $_POST['old_cover'] ?? '';
                if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
                    $file_tmp  = $_FILES['cover']['tmp_name'];
                    $finfo     = new finfo(FILEINFO_MIME_TYPE);
                    $mime_type = $finfo->file($file_tmp);
                    $allowed   = ['image/jpeg', 'image/png', 'image/webp'];

                    if (in_array($mime_type, $allowed, true)) {
                        $ext = match($mime_type) { 'image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp' };
                        $dir = __DIR__ . '/../uploads/buku/';
                        if (!is_dir($dir)) mkdir($dir, 0755, true);

                        $new_name = 'book_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                        if (move_uploaded_file($file_tmp, $dir . $new_name)) {
                            if (!empty($cover_filename) && file_exists($dir . $cover_filename)) {
                                unlink($dir . $cover_filename);
                            }
                            $cover_filename = $new_name;
                        }
                    }
                }

                if ($action === 'create') {
                    if (empty($cover_filename)) {
                        $error = "Sampul buku wajib diunggah.";
                    } else {
                        $slug = ensure_unique_slug_buku($conn, generate_slug($title));
                        $stmt = $conn->prepare("INSERT INTO section_buku (user_id, title, slug, cover, price, link_description, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        if ($stmt) {
                            $stmt->bind_param("isssdss", $instructor_id, $title, $slug, $cover_filename, $price, $link_description, $status);
                            $success = "Buku baru berhasil ditambahkan sebagai PENDING!";
                        }
                    }
                } elseif ($action === 'update') {
                    $stmt = $conn->prepare("UPDATE section_buku SET title = ?, cover = ?, price = ?, link_description = ?, status = ? WHERE id = ?" . ($is_admin ? "" : " AND user_id = " . (int)$instructor_id));
                    if ($stmt) {
                        $stmt->bind_param("ssdssi", $title, $cover_filename, $price, $link_description, $status, $id);
                    }
                }

                if (isset($stmt) && empty($error)) {
                    if (!$stmt->execute()) {
                        $error = "Gagal menyimpan data buku: " . $stmt->error;
                        $success = '';
                    } elseif ($action === 'update' && $stmt->affected_rows === 0) {
                        $error = "Buku tidak ditemukan atau bukan milik Anda.";
                        $success = '';
                    }
                    $stmt->close();
                }
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

$buku_list = [];
if ($is_admin) {
    $res_buku = $conn->query("SELECT id, title, cover, price, link_description, status FROM section_buku ORDER BY id DESC");
} else {
    $st_buku = $conn->prepare("SELECT id, title, cover, price, link_description, status FROM section_buku WHERE user_id = ? ORDER BY id DESC");
    $st_buku->bind_param("i", $instructor_id);
    $st_buku->execute();
    $res_buku = $st_buku->get_result();
}
if ($res_buku) {
    while ($row = $res_buku->fetch_assoc()) {
        $buku_list[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Buku - Iqra' Society</title>
    <link rel="icon" type="image/x-icon" href="../asset/img/favicon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../asset/img/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Zilla+Slab:wght@600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>

<body class="bg-gray-50 font-sans antialiased" 
      x-data="bukuApp(<?= htmlspecialchars(json_encode($buku_list), ENT_QUOTES, 'UTF-8') ?>)">

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
                    <h1 class="text-base md:text-lg font-extrabold text-gray-800">Kelola Buku</h1>
                </div>
                <div class="flex items-center gap-2">
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
                <?php if (!empty($error)): ?>
                    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-xs font-semibold flex items-center gap-2">
                        <i class="fa-solid fa-circle-exclamation text-base"></i> 
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs font-semibold flex items-center gap-2">
                        <i class="fa-solid fa-circle-check text-base"></i> 
                        <span><?= htmlspecialchars($success) ?></span>
                    </div>
                <?php endif; ?>

                <!-- Bagian Header Tabel & Tombol Tambah -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                        <div>
                            <h2 class="text-sm font-bold text-gray-900">Daftar Buku & Literatur</h2>
                            <p class="text-[11px] text-gray-500">Tambah, ubah, atau hapus data katalog buku Anda</p>
                        </div>
                        <button type="button" @click="openCreateModal()" class="bg-emerald-800 hover:bg-emerald-900 text-white text-xs font-bold px-4 py-2.5 rounded-xl transition flex items-center justify-center gap-2 shadow-sm">
                            <i class="fa-solid fa-plus"></i> Tambah Buku
                        </button>
                    </div>

                    <template x-if="allBooks.length === 0">
                        <div class="text-center py-12 text-gray-400 text-xs">
                            <i class="fa-solid fa-book text-3xl mb-2 text-gray-300 block"></i>
                            Belum ada data buku yang ditambahkan.
                        </div>
                    </template>

                    <template x-if="allBooks.length > 0">
                        <div>
                            <!-- ================= MOBILE CARD VIEW (< 768px) ================= -->
                            <div class="grid grid-cols-1 gap-4 md:hidden">
                                <template x-for="row in paginatedBooks" :key="row.id">
                                    <div class="bg-white rounded-3xl border border-gray-100 p-5 shadow-sm space-y-4">
                                        
                                        <!-- Header Card: Cover, Judul, Info, Badge Status -->
                                        <div class="flex items-start gap-4">
                                            <!-- Sampul Buku / Gambar -->
                                            <template x-if="row.cover">
                                                <img :src="'../uploads/buku/' + row.cover" alt="Cover" class="w-20 h-24 rounded-2xl object-cover border border-gray-100 shrink-0 shadow-sm">
                                            </template>
                                            <template x-if="!row.cover">
                                                <div class="w-20 h-24 rounded-2xl bg-gray-100 text-gray-400 flex items-center justify-center text-xs font-semibold shrink-0 border border-gray-100">
                                                    No Cover
                                                </div>
                                            </template>
                            
                                            <!-- Detail Judul & Metadata -->
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-start justify-between gap-2">
                                                    <h3 class="text-base font-bold text-gray-900 leading-snug truncate" x-text="row.title"></h3>
                                                    <span class="px-3 py-1 rounded-full text-[10px] font-bold tracking-wide border shrink-0"
                                                          :class="getStatusBadge(row.status)"
                                                          x-text="row.status.toUpperCase()">
                                                    </span>
                                                </div>
                            
                                                <!-- Metadata Tambahan (Harga / Status Gratis) -->
                                                <div class="mt-2 space-y-1.5 text-xs text-gray-500">
                                                    <div class="flex items-center gap-2">
                                                        <i class="fa-solid fa-tag text-emerald-700 w-3.5 text-center"></i>
                                                        <span class="font-bold text-gray-800">
                                                            <template x-if="row.price > 0">
                                                                <span x-text="formatRupiah(row.price)"></span>
                                                            </template>
                                                            <template x-if="!row.price || row.price <= 0">
                                                                <span class="text-emerald-700 font-semibold">Gratis</span>
                                                            </template>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                            
                                        <!-- Box Sinopsis / Deskripsi (Kotak Abu-abu) -->
                                        <div class="bg-gray-50/80 rounded-2xl p-3.5 border border-gray-100 text-xs text-gray-500 leading-relaxed break-words">
                                            <span class="font-bold text-gray-800">Sinopsis:</span> 
                                            <span x-text="row.link_description || 'Belum ada ringkasan atau sinopsis deskripsi untuk buku ini.'"></span>
                                        </div>
                            
                                        <!-- Tombol Aksi (Edit & Hapus) -->
                                        <div class="flex items-center gap-3 pt-1">
                                            <!-- Tombol Edit -->
                                            <button type="button" 
                                                    @click="openEditModal(row)" 
                                                    class="flex-1 bg-amber-50/80 hover:bg-amber-100 text-amber-800 py-3 rounded-2xl transition text-xs font-bold flex items-center justify-center gap-2 border border-amber-100/50">
                                                <i class="fa-solid fa-pen-to-square text-amber-600"></i> Edit
                                            </button>
                            
                                            <!-- Tombol Hapus -->
                                            <form action="" method="POST" class="flex-1" onsubmit="return confirm('Apakah Anda yakin ingin menghapus buku ini?');">
                                                <input type="hidden" name="target" value="buku">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" :value="row.id">
                                                <button type="submit" 
                                                        class="w-full bg-red-50/80 hover:bg-red-100 text-red-600 py-3 rounded-2xl transition text-xs font-bold flex items-center justify-center gap-2 border border-red-100/50">
                                                    <i class="fa-solid fa-trash text-red-500"></i> Hapus
                                                </button>
                                            </form>
                                        </div>
                            
                                    </div>
                                </template>
                            </div>

                            <!-- 2. DESKTOP TABLE VIEW (>= 768px) -->
                            <div class="hidden md:block overflow-x-auto rounded-xl border border-gray-100">
                                <table class="w-full text-left text-xs text-gray-600">
                                    <thead class="bg-gray-50 text-gray-700 uppercase font-bold">
                                        <tr>
                                            <th class="p-3 rounded-tl-xl">Sampul</th>
                                            <th class="p-3">Judul Buku</th>
                                            <th class="p-3">Harga</th>
                                            <th class="p-3">Sinopsis</th>
                                            <th class="p-3">Status</th>
                                            <th class="p-3 text-right rounded-tr-xl">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <template x-for="row in paginatedBooks" :key="row.id">
                                            <tr class="hover:bg-gray-50/50 transition">
                                                <td class="p-3">
                                                    <template x-if="row.cover">
                                                        <img :src="'../uploads/buku/' + row.cover" alt="Cover" class="w-10 h-12 rounded-lg object-cover border border-gray-200">
                                                    </template>
                                                    <template x-if="!row.cover">
                                                        <div class="w-10 h-12 rounded-lg bg-gray-100 text-gray-400 flex items-center justify-center text-[9px] font-medium">No Cover</div>
                                                    </template>
                                                </td>
                                                <td class="p-3 font-bold text-gray-800" x-text="row.title"></td>
                                                <td class="p-3 font-bold text-gray-900 whitespace-nowrap">
                                                    <template x-if="row.price > 0">
                                                        <span x-text="formatRupiah(row.price)"></span>
                                                    </template>
                                                    <template x-if="!row.price || row.price <= 0">
                                                        <span class="text-emerald-600">Gratis</span>
                                                    </template>
                                                </td>
                                                <!-- Kolom Sinopsis: dibatasi lebarnya (max-w-xs) & dibikin wrap ke bawah (whitespace-normal break-words) -->
                                                <td class="p-3 text-gray-500 max-w-xs whitespace-normal break-words leading-relaxed">
                                                    <span x-text="row.link_description || '-'"></span>
                                                </td>
                                                <td class="p-3 whitespace-nowrap">
                                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold border"
                                                          :class="getStatusBadge(row.status)"
                                                          x-text="row.status.toUpperCase()">
                                                    </span>
                                                </td>
                                                <td class="p-3 text-right whitespace-nowrap space-x-2">
                                                    <!-- Tombol Edit -->
                                                    <button type="button" @click="openEditModal(row)" class="bg-amber-50 hover:bg-amber-100 text-amber-700 p-2 rounded-lg transition" title="Edit">
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </button>
                                                    
                                                    <!-- Tombol Hapus -->
                                                    <form action="" method="POST" class="inline-block" onsubmit="return confirm('Apakah Anda yakin ingin menghapus buku ini?');">
                                                        <input type="hidden" name="target" value="buku">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" :value="row.id">
                                                        <button type="submit" class="bg-red-50 hover:bg-red-100 text-red-600 p-2 rounded-lg transition" title="Hapus">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                            <!-- ALPINE.JS PAGINATION CONTROLS -->
                            <div class="mt-5 pt-4 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <p class="text-[10px] sm:text-xs text-gray-500 text-center sm:text-left">
                                    Menampilkan <span class="font-bold text-gray-700" x-text="startItem"></span>–<span class="font-bold text-gray-700" x-text="endItem"></span> dari <span class="font-bold text-gray-700" x-text="allBooks.length"></span> buku
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

    <!-- MODAL FORM TAMBAH / EDIT BUKU -->
    <div x-show="modalOpen" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div x-show="modalOpen" x-transition @click="modalOpen = false" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="modalOpen" x-transition class="relative w-full max-w-lg bg-white rounded-2xl shadow-xl overflow-hidden z-10 border border-gray-100 my-8">
                <div class="p-6 text-white flex items-center justify-between" :class="editMode ? 'bg-amber-900' : 'bg-emerald-950'">
                    <div>
                        <h3 class="text-sm font-bold" x-text="editMode ? 'Edit Data Buku' : 'Tambah Buku Baru'"></h3>
                        <p class="text-[11px] opacity-80 mt-0.5">Lengkapi data informasi buku dan unggah sampul</p>
                    </div>
                    <button type="button" @click="modalOpen = false" class="text-white/80 hover:text-white transition"><i class="fa-solid fa-xmark text-lg"></i></button>
                </div>

                <form action="" method="POST" enctype="multipart/form-data" class="p-6 space-y-4 text-xs font-semibold max-h-[80vh] overflow-y-auto">
                    <input type="hidden" name="target" value="buku">
                    <input type="hidden" name="action" :value="editMode ? 'update' : 'create'">
                    <input type="hidden" name="id" x-model="currentId">
                    <input type="hidden" name="old_cover" x-model="currentCover">

                    <div>
                        <label class="block text-gray-700 mb-1">Judul Buku <span class="text-red-500">*</span></label>
                        <input type="text" name="title" x-model="currentTitle" required placeholder="Masukkan judul buku..." class="w-full px-3.5 py-2.5 text-xs bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-600/20 focus:border-emerald-600 transition">
                    </div>

                    <div>
                        <label class="block text-gray-700 mb-1">Harga (Rp)</label>
                        <input type="number" step="0.01" name="price" x-model="currentPrice" placeholder="0 untuk gratis" required class="w-full px-3.5 py-2.5 text-xs bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-600/20 focus:border-emerald-600 transition">
                    </div>

                    <div>
                        <label class="block text-gray-700 mb-1">Sinopsis / Deskripsi</label>
                        <input type="text" name="link_description" x-model="currentLinkDesc" placeholder="Gambaran isi atau inti materi buku secara singkat" class="w-full px-3.5 py-2.5 text-xs bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-600/20 focus:border-emerald-600 transition">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-center">
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_administrator'): ?>
                            <div>
                                <label class="block text-gray-700 mb-1">Status Publikasi</label>
                                <select name="status" x-model="currentStatus" class="w-full px-3.5 py-2.5 text-xs bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-600/20 focus:border-emerald-600 transition">
                                    <option value="pending">Pending</option>
                                    <option value="approved">Approved</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-1">Unggah Sampul (JPG, PNG, WebP)</label>
                                <input type="file" name="cover" accept="image/jpeg,image/png,image/webp" class="w-full text-xs text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-[10px] file:font-bold file:bg-emerald-50 file:text-emerald-700">
                            </div>
                        <?php else: ?>
                            <div class="sm:col-span-2">
                                <label class="block text-gray-700 mb-1">Unggah Sampul Buku (JPG, PNG, WebP)</label>
                                <input type="file" name="cover" accept="image/jpeg,image/png,image/webp" class="w-full text-xs text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-[10px] file:font-bold file:bg-emerald-50 file:text-emerald-700">
                                <p class="text-[10px] text-gray-400 mt-1 font-normal">Kosongkan jika tidak ingin mengubah sampul saat edit.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-gray-100">
                        <button type="button" @click="modalOpen = false" class="px-4 py-2.5 rounded-xl text-xs font-bold text-gray-600 hover:bg-gray-100 transition">Batal</button>
                        <button type="submit" class="px-5 py-2.5 rounded-xl text-xs font-bold text-white shadow-sm transition" :class="editMode ? 'bg-amber-600 hover:bg-amber-700' : 'bg-emerald-800 hover:bg-emerald-900'">
                            <i class="fa-solid fa-floppy-disk mr-1"></i> Simpan Data
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<script>
function bukuApp(initialData) {
    return {
        sidebarOpen: false,
        modalOpen: false,
        editMode: false,
        currentId: '',
        currentTitle: '',
        currentPrice: '0',
        currentLinkDesc: '',
        currentStatus: 'pending',
        currentCover: '',

        // Data & Pagination
        allBooks: initialData || [],
        currentPage: 1,
        perPage: 10,

        get totalPages() {
            return Math.max(1, Math.ceil(this.allBooks.length / this.perPage));
        },

        get paginatedBooks() {
            const start = (this.currentPage - 1) * this.perPage;
            return this.allBooks.slice(start, start + this.perPage);
        },

        get startItem() {
            if (this.allBooks.length === 0) return 0;
            return (this.currentPage - 1) * this.perPage + 1;
        },

        get endItem() {
            return Math.min(this.currentPage * this.perPage, this.allBooks.length);
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

        openCreateModal() {
            this.editMode = false;
            this.currentId = '';
            this.currentTitle = '';
            this.currentPrice = '0';
            this.currentLinkDesc = '';
            this.currentStatus = 'pending';
            this.currentCover = '';
            this.modalOpen = true;
        },

        openEditModal(row) {
            this.editMode = true;
            this.currentId = row.id;
            this.currentTitle = row.title || '';
            this.currentPrice = row.price || '0';
            this.currentLinkDesc = row.link_description || '';
            this.currentStatus = row.status || 'pending';
            this.currentCover = row.cover || '';
            this.modalOpen = true;
        },

        getStatusBadge(status) {
            return status === 'approved' 
                ? 'bg-emerald-100 text-emerald-800 border-emerald-200' 
                : 'bg-amber-100 text-amber-800 border-amber-200';
        },

        formatRupiah(number) {
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(number);
        }
    };
}
</script>

</body>
</html>