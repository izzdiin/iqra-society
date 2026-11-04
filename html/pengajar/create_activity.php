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
function ensure_unique_slug_kegiatan($conn, $slug, $exclude_id = 0) {
    $base = $slug; $i = 2;
    while (true) {
        if ($exclude_id > 0) { $s = $conn->prepare("SELECT id FROM section_kegiatan WHERE slug = ? AND id != ? LIMIT 1"); $s->bind_param("si", $slug, $exclude_id); }
        else { $s = $conn->prepare("SELECT id FROM section_kegiatan WHERE slug = ? LIMIT 1"); $s->bind_param("s", $slug); }
        $s->execute(); $ex = $s->get_result()->num_rows > 0; $s->close();
        if (!$ex) return $slug;
        $slug = $base . '-' . $i; $i++;
    }
}

$error = '';
$success = '';
$is_super_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'super_administrator';
$current_user_id = (int)($_SESSION['user_id'] ?? 0);
$is_admin = in_array($_SESSION['role'] ?? '', ['super_administrator', 'administrator'], true);

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

$upload_dir = __DIR__ . '/../uploads/kegiatan/';

function post_string($key, $default = '') {
    return trim((string)($_POST[$key] ?? $default));
}

function valid_image_extension($tmp) {
    if (!is_uploaded_file($tmp)) return false;

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);

    return [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ][$mime] ?? false;
}

function delete_kegiatan_image($filename, $dir) {
    $filename = basename((string)$filename);
    if ($filename !== '' && is_file($dir . $filename)) {
        @unlink($dir . $filename);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['target'] ?? '') === 'kegiatan') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if (!in_array($action, ['create', 'update', 'delete'], true)) {
        $error = 'Aksi tidak valid.';
    }

    /* DELETE */
    if (!$error && $action === 'delete') {
        if ($id <= 0) {
            $error = 'ID kegiatan tidak valid.';
        } else {
            $stmt = $conn->prepare("SELECT image FROM section_kegiatan WHERE id = ?" . ($is_admin ? "" : " AND user_id = " . $current_user_id));
            if (!$stmt) {
                $error = 'Gagal membaca data kegiatan.';
            } else {
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $old = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$old) {
                    $error = 'Data kegiatan tidak ditemukan.';
                } else {
                    $stmt = $conn->prepare("DELETE FROM section_kegiatan WHERE id = ?" . ($is_admin ? "" : " AND user_id = " . $current_user_id));
                    if (!$stmt) {
                        $error = 'Gagal memproses penghapusan.';
                    } else {
                        $stmt->bind_param("i", $id);

                        if ($stmt->execute()) {
                            delete_kegiatan_image($old['image'] ?? '', $upload_dir);
                            $success = 'Kegiatan berhasil dihapus!';
                        } else {
                            $error = 'Gagal menghapus kegiatan: ' . $stmt->error;
                        }

                        $stmt->close();
                    }
                }
            }
        }
    }

    /* CREATE / UPDATE */
    if (!$error && in_array($action, ['create', 'update'], true)) {
        $title          = post_string('title');
        $execution_type = post_string('execution_type', 'sekali');
        $category       = post_string('category');
        $location       = post_string('location');
        $rencana        = post_string('rencana_kegiatan');

        if (!in_array($execution_type, ['sekali', 'berulang'], true)) {
            $execution_type = 'sekali';
        }

        $event_date = null;
        $recurring_day = null;
        $event_time = null;

        if ($execution_type === 'sekali') {
            $event_date = post_string('event_date');
            $event_time = post_string('event_time');

            if ($event_date === '') {
                $error = 'Tanggal pelaksanaan wajib diisi.';
            } else {
                $date = DateTime::createFromFormat('Y-m-d', $event_date);
                if (!$date || $date->format('Y-m-d') !== $event_date) {
                    $error = 'Format tanggal tidak valid.';
                }
            }
        } else {
            $recurring_day = post_string('recurring_day');
            $event_time = post_string('event_time');

            $days = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];

            if (!in_array($recurring_day, $days, true)) {
                $error = 'Hari rutin tidak valid.';
            } elseif ($event_time === '') {
                $error = 'Jam kegiatan berulang wajib diisi.';
            }
        }

        if ($title === '' || $location === '' || $category === '') {
            $error = 'Judul, lokasi, dan kategori wajib diisi.';
        }

        /* Status: hanya super administrator boleh mengubah. */
        $status = 'pending';
        $old_image = '';

        if ($action === 'update') {
            if ($id <= 0) {
                $error = 'ID kegiatan tidak valid.';
            } else {
                $stmt = $conn->prepare(
                    "SELECT status, image FROM section_kegiatan WHERE id = ?" . ($is_admin ? "" : " AND user_id = " . $current_user_id)
                );

                if (!$stmt) {
                    $error = 'Gagal membaca data kegiatan.';
                } else {
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $existing = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if (!$existing) {
                        $error = 'Data kegiatan tidak ditemukan.';
                    } else {
                        $old_image = basename((string)($existing['image'] ?? ''));
                        $status = $existing['status'] ?? 'pending';
                    }
                }
            }
        }

        if ($is_super_admin) {
            $requested_status = post_string('status', 'pending');
            $status = in_array($requested_status, ['pending','approved'], true)
                ? $requested_status
                : 'pending';
        }

        /*
         * Upload baru bersifat OPSIONAL ketika edit.
         * Jika tidak ada file baru, gambar lama dipertahankan.
         */
        $image_filename = $old_image;
        $new_image = '';

        if (!$error && isset($_FILES['image']) &&
            $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {

            if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Upload gambar gagal. Kode: ' . (int)$_FILES['image']['error'];
            } else {
                $ext = valid_image_extension($_FILES['image']['tmp_name']);

                if ($ext === false) {
                    $error = 'Format gambar harus JPG, PNG, atau WebP.';
                } else {
                    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
                        $error = 'Folder upload tidak dapat dibuat.';
                    } elseif (!is_writable($upload_dir)) {
                        $error = 'Folder upload tidak dapat ditulis.';
                    } else {
                        try {
                            $new_image =
                                'event_' . date('Ymd_His') . '_' .
                                bin2hex(random_bytes(5)) . '.' . $ext;

                            if (!move_uploaded_file(
                                $_FILES['image']['tmp_name'],
                                $upload_dir . $new_image
                            )) {
                                $error = 'Gagal menyimpan gambar.';
                            } else {
                                $image_filename = $new_image;
                            }
                        } catch (Throwable $e) {
                            $error = 'Gagal membuat nama file gambar.';
                        }
                    }
                }
            }
        }

        if (!$error && $action === 'create' && $image_filename === '') {
            $error = 'Gambar kegiatan wajib diunggah.';
        }

        /* Simpan database */
        if (!$error) {
            if ($action === 'create') {
                $stmt = $conn->prepare(
                    "INSERT INTO section_kegiatan
                    (user_id, title, slug, execution_type, event_date, recurring_day, event_time,
                     location, category, image, status, rencana_kegiatan)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );

                if (!$stmt) {
                    $error = 'Gagal menyiapkan INSERT.';
                } else {
                    $slug = ensure_unique_slug_kegiatan($conn, generate_slug($title));
                    $stmt->bind_param(
                        "isssssssssss",
                        $current_user_id, $title, $slug, $execution_type, $event_date, $recurring_day,
                        $event_time, $location, $category, $image_filename,
                        $status, $rencana
                    );

                    if ($stmt->execute()) {
                        $success = 'Kegiatan baru berhasil ditambahkan!';
                    } else {
                        $error = 'Gagal menyimpan data: ' . $stmt->error;
                        if ($new_image !== '') {
                            delete_kegiatan_image($new_image, $upload_dir);
                        }
                    }

                    $stmt->close();
                }
            } else {
                $stmt = $conn->prepare(
                    "UPDATE section_kegiatan SET
                    title = ?, execution_type = ?, event_date = ?,
                    recurring_day = ?, event_time = ?, location = ?,
                    category = ?, image = ?, status = ?, rencana_kegiatan = ?
                    WHERE id = ?" . ($is_admin ? "" : " AND user_id = " . $current_user_id)
                );

                if (!$stmt) {
                    $error = 'Gagal menyiapkan UPDATE.';
                } else {
                    $stmt->bind_param(
                        "ssssssssssi",
                        $title, $execution_type, $event_date, $recurring_day,
                        $event_time, $location, $category, $image_filename,
                        $status, $rencana, $id
                    );

                    if ($stmt->execute()) {
                        /* Hapus gambar lama hanya setelah DB sukses. */
                        if ($new_image !== '' && $old_image !== '') {
                            delete_kegiatan_image($old_image, $upload_dir);
                        }

                        $success = 'Data kegiatan berhasil diperbarui!';
                    } else {
                        $error = 'Gagal memperbarui data: ' . $stmt->error;
                        if ($new_image !== '') {
                            delete_kegiatan_image($new_image, $upload_dir);
                        }
                    }

                    $stmt->close();
                }
            }
        }
    }
}

/* ================= PAGINATION ================= */
$per_page = 10;

$current_page = isset($_GET['page']) && is_numeric($_GET['page'])
    ? (int)$_GET['page']
    : 1;

if ($current_page < 1) {
    $current_page = 1;
}

$total_kegiatan = 0;

if ($is_admin) {
    $count_result = $conn->query("SELECT COUNT(*) AS total FROM section_kegiatan");
} else {
    $st_cnt = $conn->prepare("SELECT COUNT(*) AS total FROM section_kegiatan WHERE user_id = ?");
    $st_cnt->bind_param("i", $current_user_id);
    $st_cnt->execute();
    $count_result = $st_cnt->get_result();
}

if ($count_result) {
    $count_row = $count_result->fetch_assoc();
    $total_kegiatan = (int)($count_row['total'] ?? 0);
}

$total_pages = max(
    1,
    (int)ceil((int)$total_kegiatan / (int)$per_page)
);

$current_page = (int)$current_page;
$total_pages  = (int)$total_pages;

if ($current_page > $total_pages) {
    $current_page = $total_pages;
}

$offset = ((int)$current_page - 1) * (int)$per_page;

$kegiatan_list = [];

$keg_where = $is_admin ? "" : "WHERE user_id = $current_user_id ";
$stmt_keg = $conn->prepare(
    "SELECT id, title, execution_type, event_date, recurring_day,
            event_time, location, category, image, status, rencana_kegiatan
     FROM section_kegiatan
     " . $keg_where . "
     ORDER BY id DESC
     LIMIT ? OFFSET ?"
);

if ($stmt_keg) {
    $stmt_keg->bind_param("ii", $per_page, $offset);
    $stmt_keg->execute();
    $res_keg = $stmt_keg->get_result();

    while ($row = $res_keg->fetch_assoc()) {
        $kegiatan_list[] = $row;
    }

    $stmt_keg->close();
}

function page_url($page) {
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kegiatan - Iqra' Society</title>
    <link rel="icon" type="image/x-icon" href="../asset/img/favicon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../asset/img/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Zilla+Slab:wght@600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>

<body class="bg-gray-50 font-sans antialiased" x-data="kegiatanApp()">

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
                    <h1 class="text-base md:text-lg font-extrabold text-gray-800">Kelola Kegiatan</h1>
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
                <?php if (!empty($error)): ?>
                    <div class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-xs font-semibold flex items-center gap-2">
                        <i class="fa-solid fa-circle-exclamation text-base"></i> <?= $error ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs font-semibold flex items-center gap-2">
                        <i class="fa-solid fa-circle-check text-base"></i> <?= $success ?>
                    </div>
                <?php endif; ?>

                <!-- Bagian Header Tabel & Tombol Tambah -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                        <div>
                            <h2 class="text-sm font-bold text-gray-900">Daftar Kegiatan & Agenda</h2>
                            <p class="text-[11px] text-gray-500">Tambah, ubah, atau hapus data agenda kegiatan Anda</p>
                        </div>
                        <button type="button" @click="openCreate()" class="bg-emerald-800 hover:bg-emerald-900 text-white text-xs font-bold px-4 py-2.5 rounded-xl transition flex items-center justify-center gap-2 shadow-sm">
                            <i class="fa-solid fa-plus"></i> Tambah Kegiatan
                        </button>
                    </div>

                    <?php if (empty($kegiatan_list)): ?>
                        <div class="text-center py-12 text-gray-400 text-xs">
                            <i class="fa-solid fa-calendar-xmark text-3xl mb-2 text-gray-300 block"></i>
                            Belum ada data kegiatan yang ditambahkan.
                        </div>
                    <?php else: ?>
                        <!-- DESKTOP TABLE -->
                        <div class="hidden md:block overflow-x-auto rounded-xl border border-gray-100">
                            <table class="w-full text-left text-xs text-gray-600">
                                <thead class="bg-gray-50 text-gray-700 uppercase font-bold">
                                    <tr>
                                        <th class="p-3 rounded-tl-xl">Gambar</th>
                                        <th class="p-3">Judul Kegiatan</th>
                                        <th class="p-3">Waktu & Pelaksanaan</th>
                                        <th class="p-3">Kategori</th>
                                        <th class="p-3">Status</th>
                                        <th class="p-3 text-right rounded-tr-xl">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($kegiatan_list as $row): ?>
                                    <?php
                                        $statusBadge = ($row['status'] === 'approved')
                                            ? 'bg-emerald-100 text-emerald-800 border-emerald-200'
                                            : 'bg-amber-100 text-amber-800 border-amber-200';
                                    ?>
                                    <tr class="hover:bg-gray-50/50 transition">
                                        <td class="p-3">
                                            <?php if (!empty($row['image']) && file_exists(__DIR__ . '/../uploads/kegiatan/' . $row['image'])): ?>
                                                <img src="../uploads/kegiatan/<?= htmlspecialchars($row['image']) ?>" alt="Img" class="w-12 h-10 rounded-lg object-cover border border-gray-200">
                                            <?php else: ?>
                                                <div class="w-12 h-10 rounded-lg bg-gray-100 text-gray-400 flex items-center justify-center text-[10px]">No Img</div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-3 min-w-[220px]">
                                            <div class="font-bold text-gray-800"><?= htmlspecialchars($row['title']) ?></div>
                                            <?php if (!empty($row['rencana_kegiatan'])): ?>
                                                <div class="text-[10px] text-gray-400 truncate max-w-xs mt-0.5" title="<?= htmlspecialchars($row['rencana_kegiatan']) ?>">
                                                    <strong>Rencana:</strong> <?= htmlspecialchars($row['rencana_kegiatan']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-3 text-gray-600 whitespace-nowrap">
                                            <?php if ($row['execution_type'] === 'berulang'): ?>
                                                <div class="font-bold text-emerald-700"><i class="fa-solid fa-rotate mr-1"></i> Setiap <?= htmlspecialchars($row['recurring_day']) ?></div>
                                            <?php else: ?>
                                                <div class="font-bold text-gray-700"><i class="fa-regular fa-calendar mr-1 text-emerald-600"></i> <?= !empty($row['event_date']) ? date('d M Y', strtotime($row['event_date'])) : '-' ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($row['event_time'])): ?>
                                                <div class="text-[10px] text-gray-500 mt-0.5"><i class="fa-regular fa-clock mr-1"></i> Pukul <?= date('H:i', strtotime($row['event_time'])) ?> WIB</div>
                                            <?php endif; ?>
                                            <div class="text-[10px] text-gray-400 mt-0.5"><i class="fa-solid fa-location-dot mr-1"></i> <?= htmlspecialchars($row['location']) ?></div>
                                        </td>
                                        <td class="p-3 whitespace-nowrap">
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-gray-100 text-gray-700 border border-gray-200">
                                                <?= htmlspecialchars($row['category']) ?>
                                            </span>
                                        </td>
                                        <td class="p-3 whitespace-nowrap">
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold border <?= $statusBadge ?>">
                                                <?= strtoupper(htmlspecialchars($row['status'])) ?>
                                            </span>
                                        </td>
                                        <td class="p-3 text-right whitespace-nowrap">
                                            <button type="button" @click='openEdit(<?= json_encode($row, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="bg-amber-50 hover:bg-amber-100 text-amber-700 p-2 rounded-lg transition" title="Edit">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>
                                            <form action="" method="POST" class="inline-block ml-1" onsubmit="return confirm('Apakah Anda yakin ingin menghapus kegiatan ini?');">
                                                <input type="hidden" name="target" value="kegiatan">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                                <button type="submit" class="bg-red-50 hover:bg-red-100 text-red-600 p-2 rounded-lg transition" title="Hapus">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- MOBILE CARDS -->
                        <div class="md:hidden space-y-3">
                            <?php foreach ($kegiatan_list as $row): ?>
                            <?php
                                $statusBadge = ($row['status'] === 'approved')
                                    ? 'bg-emerald-100 text-emerald-800 border-emerald-200'
                                    : 'bg-amber-100 text-amber-800 border-amber-200';
                            ?>
                            <article class="bg-white border border-gray-100 rounded-2xl p-3 shadow-sm">
                                <div class="flex gap-3">
                                    <div class="shrink-0">
                                        <?php if (!empty($row['image']) && file_exists(__DIR__ . '/../uploads/kegiatan/' . $row['image'])): ?>
                                            <img src="../uploads/kegiatan/<?= htmlspecialchars($row['image']) ?>" alt="<?= htmlspecialchars($row['title']) ?>" class="w-20 h-20 rounded-xl object-cover border border-gray-200">
                                        <?php else: ?>
                                            <div class="w-20 h-20 rounded-xl bg-gray-100 text-gray-400 flex items-center justify-center text-[10px] text-center">
                                                <i class="fa-regular fa-image text-lg mb-1"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-2">
                                            <h3 class="font-bold text-sm text-gray-800 leading-snug break-words">
                                                <?= htmlspecialchars($row['title']) ?>
                                            </h3>
                                            <span class="shrink-0 px-2 py-1 rounded-full text-[9px] font-bold border <?= $statusBadge ?>">
                                                <?= strtoupper(htmlspecialchars($row['status'])) ?>
                                            </span>
                                        </div>

                                        <div class="mt-2 space-y-1 text-[11px] text-gray-500">
                                            <div class="flex items-start gap-2">
                                                <i class="fa-regular fa-calendar text-emerald-600 w-3.5 mt-0.5"></i>
                                                <span>
                                                    <?php if ($row['execution_type'] === 'berulang'): ?>
                                                        Setiap <?= htmlspecialchars($row['recurring_day']) ?>
                                                    <?php else: ?>
                                                        <?= !empty($row['event_date']) ? date('d M Y', strtotime($row['event_date'])) : '-' ?>
                                                    <?php endif; ?>
                                                    <?php if (!empty($row['event_time'])): ?>
                                                        · <?= date('H:i', strtotime($row['event_time'])) ?> WIB
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            <div class="flex items-start gap-2">
                                                <i class="fa-solid fa-location-dot text-emerald-600 w-3.5 mt-0.5"></i>
                                                <span class="break-words"><?= htmlspecialchars($row['location']) ?></span>
                                            </div>
                                            <div class="flex items-start gap-2">
                                                <i class="fa-solid fa-tag text-emerald-600 w-3.5 mt-0.5"></i>
                                                <span><?= htmlspecialchars($row['category']) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <?php if (!empty($row['rencana_kegiatan'])): ?>
                                    <div class="mt-3 px-3 py-2 rounded-xl bg-gray-50 border border-gray-100 text-[10px] text-gray-500 leading-relaxed">
                                        <span class="font-bold text-gray-600">Rencana:</span>
                                        <?= htmlspecialchars($row['rencana_kegiatan']) ?>
                                    </div>
                                <?php endif; ?>

                                <div class="mt-3 pt-3 border-t border-gray-100 flex gap-2">
                                    <button type="button" @click='openEdit(<?= json_encode($row, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="flex-1 bg-amber-50 hover:bg-amber-100 text-amber-700 py-2.5 rounded-xl transition text-xs font-bold flex items-center justify-center gap-2">
                                        <i class="fa-solid fa-pen-to-square"></i> Edit
                                    </button>
                                    <form action="" method="POST" class="flex-1" onsubmit="return confirm('Apakah Anda yakin ingin menghapus kegiatan ini?');">
                                        <input type="hidden" name="target" value="kegiatan">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="w-full bg-red-50 hover:bg-red-100 text-red-600 py-2.5 rounded-xl transition text-xs font-bold flex items-center justify-center gap-2">
                                            <i class="fa-solid fa-trash"></i> Hapus
                                        </button>
                                    </form>
                                </div>
                            </article>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($total_kegiatan > 0): ?>
                            <?php
                                $start_item = ((int)$offset) + 1;
                        
                                $end_item = min(
                                    ((int)$offset) + (int)$per_page,
                                    (int)$total_kegiatan
                                );
                        
                                $visible_pages = [];
                        
                                $start_page = max(
                                    1,
                                    ((int)$current_page) - 2
                                );
                        
                                $end_page = min(
                                    (int)$total_pages,
                                    ((int)$current_page) + 2
                                );
                        
                                for ($p = $start_page; $p <= $end_page; $p++) {
                                    $visible_pages[] = (int)$p;
                                }
                            ?>
                            <div class="mt-5 pt-4 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <p class="text-[10px] sm:text-xs text-gray-500 text-center sm:text-left">
                                    Menampilkan <span class="font-bold text-gray-700"><?= $start_item ?>–<?= $end_item ?></span> dari <span class="font-bold text-gray-700"><?= $total_kegiatan ?></span> kegiatan
                                </p>

                                <?php if ($total_pages > 1): ?>
                                    <nav class="flex items-center justify-center gap-1" aria-label="Pagination">
                                        <?php if ($current_page > 1): ?>
                                            <a href="<?= htmlspecialchars(page_url($current_page - 1)) ?>" class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-gray-50" title="Sebelumnya">
                                                <i class="fa-solid fa-chevron-left text-[10px]"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-100 bg-gray-50 text-gray-300">
                                                <i class="fa-solid fa-chevron-left text-[10px]"></i>
                                            </span>
                                        <?php endif; ?>

                                        <?php if ($start_page > 1): ?>
                                            <a href="<?= htmlspecialchars(page_url(1)) ?>" class="hidden sm:flex w-9 h-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-gray-50">1</a>
                                            <?php if ($start_page > 2): ?><span class="hidden sm:flex w-6 justify-center text-gray-400">…</span><?php endif; ?>
                                        <?php endif; ?>

                                        <?php foreach ($visible_pages as $p): ?>
                                            <a href="<?= htmlspecialchars(page_url($p)) ?>" class="w-9 h-9 flex items-center justify-center rounded-lg border text-xs font-bold <?= $p === $current_page ? 'bg-emerald-800 border-emerald-800 text-white' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50' ?>">
                                                <?= $p ?>
                                            </a>
                                        <?php endforeach; ?>

                                        <?php if ($end_page < $total_pages): ?>
                                            <?php if ($end_page < $total_pages - 1): ?><span class="hidden sm:flex w-6 justify-center text-gray-400">…</span><?php endif; ?>
                                            <a href="<?= htmlspecialchars(page_url($total_pages)) ?>" class="hidden sm:flex w-9 h-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-gray-50"><?= $total_pages ?></a>
                                        <?php endif; ?>

                                        <?php if ($current_page < $total_pages): ?>
                                            <a href="<?= htmlspecialchars(page_url($current_page + 1)) ?>" class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-gray-50" title="Berikutnya">
                                                <i class="fa-solid fa-chevron-right text-[10px]"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-100 bg-gray-50 text-gray-300">
                                                <i class="fa-solid fa-chevron-right text-[10px]"></i>
                                            </span>
                                        <?php endif; ?>
                                    </nav>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

            </main>
        </div>
    </div>

    <!-- MODAL FORM TAMBAH / EDIT KEGIATAN -->
    <div x-show="modalOpen" class="fixed inset-0 bg-gray-900/80 z-50 flex items-center justify-center p-4" style="display: none;">
        <div @click.outside="modalOpen = false" class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-gray-100 space-y-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <h3 class="text-sm font-bold text-gray-900" x-text="editMode ? 'Edit Data Kegiatan' : 'Tambah Kegiatan Baru'"></h3>
                <button @click="modalOpen = false" class="text-gray-400 hover:text-gray-600">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form action="" method="POST" enctype="multipart/form-data" class="space-y-4 text-xs font-semibold">
                <input type="hidden" name="target" value="kegiatan">
                <input type="hidden" name="action" x-bind:value="editMode ? 'update' : 'create'">
                <input type="hidden" name="id" x-model="currentId">
                

                <div>
                    <label class="block text-gray-700 mb-1">Judul Kegiatan</label>
                    <input type="text" name="title" x-model="currentTitle" required class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-600">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-gray-700 mb-1">Jenis Pelaksanaan</label>
                        <select name="execution_type" x-model="currentExecutionType" class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-600 bg-white">
                            <option value="sekali">Sekali (Tanggal Spesifik)</option>
                            <option value="berulang">Berulang (Rutinitas)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-1">Kategori</label>
                        <input type="text" name="category" x-model="currentCategory" placeholder="Misal: Kajian Akbar, Sosial" required class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-600">
                    </div>
                </div>

                <!-- Input Dinamis Berdasarkan Jenis Pelaksanaan (Sekali juga menampilkan Tanggal & Pukul/Jam secara simetris) -->
                <div>
                    <!-- Jika Sekali -->
                    <div x-show="currentExecutionType === 'sekali'" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-gray-700 mb-1">Tanggal Pelaksanaan</label>
                            <input type="date" name="event_date" x-model="currentEventDate" class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-600 bg-white">
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-1">Pukul / Jam (Opsional)</label>
                            <input type="time" name="event_time" x-model="currentEventTime" class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-600 bg-white">
                        </div>
                    </div>

                    <!-- Jika Berulang -->
                    <div x-show="currentExecutionType === 'berulang'" class="grid grid-cols-1 sm:grid-cols-2 gap-3" style="display: none;">
                        <div>
                            <label class="block text-gray-700 mb-1">Hari Rutin</label>
                            <select name="recurring_day" x-model="currentRecurringDay" class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-600 bg-white">
                                <option value="Senin">Setiap Senin</option>
                                <option value="Selasa">Setiap Selasa</option>
                                <option value="Rabu">Setiap Rabu</option>
                                <option value="Kamis">Setiap Kamis</option>
                                <option value="Jumat">Setiap Jumat</option>
                                <option value="Sabtu">Setiap Sabtu</option>
                                <option value="Minggu">Setiap Minggu</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-1">Pukul / Jam</label>
                            <input type="time" name="event_time" x-model="currentEventTime" class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-600 bg-white">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-gray-700 mb-1">Lokasi</label>
                    <input type="text" name="location" x-model="currentLocation" placeholder="Nama Tempat / Masjid / Kota" required class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-600">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-center">
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_administrator'): ?>
                        <div>
                            <label class="block text-gray-700 mb-1">Status Publikasi</label>
                            <select name="status" x-model="currentStatus" class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-600 bg-white">
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-1">Unggah Gambar/Banner Kegiatan (JPG, PNG, WebP)</label>
                            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="w-full text-xs text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded-lg file:border-0 file:text-[10px] file:font-semibold file:bg-emerald-50 file:text-emerald-700">
                            <p class="text-[10px] text-gray-400 mt-1">
                                Saat edit, kosongkan file jika ingin mempertahankan gambar lama.
                            </p>
                            <div x-show="currentImage" class="mt-2">
                                <img
                                    :src="'../uploads/kegiatan/' + currentImage"
                                    alt="Gambar saat ini"
                                    class="w-full max-h-28 object-cover rounded-lg border border-gray-200"
                                    @error="$el.style.display='none'"
                                >
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="sm:col-span-2">
                            <label class="block text-gray-700 mb-1">Unggah Gambar/Banner Kegiatan (JPG, PNG, WebP)</label>
                            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="w-full text-xs text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded-lg file:border-0 file:text-[10px] file:font-semibold file:bg-emerald-50 file:text-emerald-700">
                        </div>
                    <?php endif; ?>
                </div>

                <div>
                    <label class="block text-gray-700 mb-1">Rencana Kegiatan / Catatan Detail</label>
                    <textarea name="rencana_kegiatan" x-model="currentRencana" rows="4" class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-600 font-normal"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                    <button type="button" @click="modalOpen = false" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-800 text-white rounded-xl hover:bg-emerald-900 transition">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>


<script>
function kegiatanApp() {
    return {
        sidebarOpen: false,
        modalOpen: false,
        editMode: false,

        currentId: '',
        currentTitle: '',
        currentExecutionType: 'sekali',
        currentEventDate: '',
        currentRecurringDay: 'Sabtu',
        currentEventTime: '20:00',
        currentLocation: '',
        currentCategory: '',
        currentStatus: 'pending',
        currentRencana: 'Latar belakang:\nTujuan:\nBentuk kegiatan:',
        currentImage: '',

        openCreate() {
            this.editMode = false;
            this.currentId = '';
            this.currentTitle = '';
            this.currentExecutionType = 'sekali';
            this.currentEventDate = '';
            this.currentRecurringDay = 'Sabtu';
            this.currentEventTime = '20:00';
            this.currentLocation = '';
            this.currentCategory = '';
            this.currentStatus = 'pending';
            this.currentRencana = 'Latar belakang:\nTujuan:\nBentuk kegiatan:';
            this.currentImage = '';
            this.modalOpen = true;
        },

        openEdit(data) {
            this.editMode = true;
            this.currentId = data.id ?? '';
            this.currentTitle = data.title ?? '';
            this.currentExecutionType = data.execution_type ?? 'sekali';
            this.currentEventDate = data.event_date ?? '';
            this.currentRecurringDay = data.recurring_day ?? 'Sabtu';
            this.currentEventTime = data.event_time
                ? String(data.event_time).substring(0, 5)
                : '20:00';
            this.currentLocation = data.location ?? '';
            this.currentCategory = data.category ?? '';
            this.currentStatus = data.status ?? 'pending';
            this.currentRencana =
                data.rencana_kegiatan ??
                'Latar belakang:\nTujuan:\nBentuk kegiatan:';
            this.currentImage = data.image ?? '';
            this.modalOpen = true;
        }
    };
}
</script>

</body>
</html>