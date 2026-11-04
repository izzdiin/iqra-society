<?php
require_once __DIR__ . '/../includes/auth_middleware.php';

check_access(['super_administrator', 'administrator']);

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Token keamanan CSRF tidak valid.";
    } else {
        $target = $_POST['target'] ?? '';
        $action = $_POST['action'] ?? '';

        if ($target === 'program') {
            $title       = sanitize($_POST['title'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $id          = (int) ($_POST['id'] ?? 0);

            if ($action === 'delete') {
                $stmt = $conn->prepare("DELETE FROM section_program WHERE id = ?");
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    $success = "Program berhasil dihapus!";
                } else {
                    $error = "Gagal menghapus data program.";
                }
                $stmt->close();
            } 
            elseif ($action === 'toggle_active') {
                $is_active = (int) ($_POST['is_active'] ?? 1);
                $stmt = $conn->prepare("UPDATE section_program SET is_active = ? WHERE id = ?");
                $stmt->bind_param("ii", $is_active, $id);
                if ($stmt->execute()) {
                    $success = "Status program berhasil diperbarui!";
                } else {
                    $error = "Gagal mengubah status program.";
                }
                $stmt->close();
            } 
            else {
                if (empty($title) || empty($description)) {
                    $error = "Judul dan Deskripsi program wajib diisi.";
                } else {
                    if ($action === 'create') {
                        $stmt = $conn->prepare("INSERT INTO section_program (title, description, is_active) VALUES (?, ?, 1)");
                        $stmt->bind_param("ss", $title, $description);
                        $success = "Program baru berhasil ditambahkan!";
                    } elseif ($action === 'update') {
                        $stmt = $conn->prepare("UPDATE section_program SET title = ?, description = ? WHERE id = ?");
                        $stmt->bind_param("ssi", $title, $description, $id);
                        $success = "Data program berhasil diperbarui!";
                    }

                    if (isset($stmt)) {
                        if (!$stmt->execute()) { 
                            $error = "Gagal menyimpan data program."; 
                            $success = ''; 
                        }
                        $stmt->close();
                    }
                }
            }
        }

        elseif ($target === 'quote') {
            $quote_text = sanitize($_POST['quote_text'] ?? '');
            $source     = sanitize($_POST['source'] ?? '');
        
            if (empty($quote_text) || empty($source)) {
                $error = "Isi Quote dan Sumber/Perawi wajib diisi.";
            } else {
                if ($action === 'create') {
                    $stmt = $conn->prepare("INSERT INTO section_quotes (quote_text, source, is_active) VALUES (?, ?, 1)");
                    $stmt->bind_param("ss", $quote_text, $source);
                    $success = "Quote baru berhasil ditambahkan!";
        
                    if (!$stmt->execute()) {
                        $error = "Gagal memproses data quote.";
                        $success = '';
                    }
                    $stmt->close();
                }
            }
        }
        
        elseif ($target === 'about') {
            $content = $_POST['content'] ?? '';
            
            $stmt = $conn->prepare("INSERT INTO section_about (id, content) VALUES (1, ?) ON DUPLICATE KEY UPDATE content = ?");
            $stmt->bind_param("ss", $content, $content);
            
            if ($stmt->execute()) {
                $success = "Profil berhasil diperbarui!";
            } else {
                $error = "Gagal memperbarui Profil";
            }
            $stmt->close();
        }
    }
}

$program  = $conn->query("SELECT * FROM section_program ORDER BY id DESC");
$quotes   = $conn->query("SELECT * FROM section_quotes ORDER BY id DESC");

$about_query = $conn->query("SELECT content FROM section_about WHERE id = 1");
$about_data = $about_query ? $about_query->fetch_assoc() : null;
$about_content = $about_data ? $about_data['content'] : '';

$pending_count_query = $conn->query("SELECT COUNT(id) FROM section_kelas WHERE status = 'pending'");
$pending_notifications = $pending_count_query ? (int) $pending_count_query->fetch_row()[0] : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Konten Landing Page - Iqra' Society</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="icon" type="image/x-icon" href="../asset/img/favicon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../asset/img/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Zilla+Slab:wght@600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
    [x-cloak] { 
        display: none !important; 
    }
</style>
</head>
<body class="bg-gray-50 font-sans antialiased" x-data="{ sidebarOpen: false, activeTab: 'quotes' }">

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
        <div class="flex-1 flex flex-col">
            <!-- Navbar Header -->
            <header class="sticky top-0 z-30 bg-white/95 backdrop-blur-md border-b border-gray-100 px-4 md:px-6 py-4 flex items-center justify-between shadow-sm">                
                <div class="flex items-center gap-3">
                    <button @click="sidebarOpen = true" class="md:hidden text-gray-600 hover:text-gray-900 p-2 rounded-lg hover:bg-gray-100">
                        <i class="fa-solid fa-bars text-lg"></i>
                    </button>
                    <h1 class="text-base md:text-lg font-extrabold text-gray-800">Landing Page</h1>
                </div>

                <div class="flex items-center gap-2 md:gap-3">

                    <!-- DROPDOWN LINK QUICK ACCESS -->
                    <div class="relative" x-data="{ linkDropdown: false }">
                        <button @click="linkDropdown = !linkDropdown" class="relative p-2.5 rounded-xl text-gray-500 hover:text-gray-800 hover:bg-gray-100 transition focus:outline-none" title="Tautan Web">
                            <i class="fa-solid fa-link text-lg"></i>
                        </button>

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

            <main class="p-6 space-y-6">
                <!-- Alert Error -->
                <?php if (!empty($_GET['error']) || !empty($error)): ?>
                    <div x-data="{ show: true }" 
                         x-show="show" 
                         x-init="setTimeout(() => show = false, 3000)" 
                         x-transition.duration.500ms
                         class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl text-xs flex items-center gap-2">
                        <i class="fa-solid fa-circle-exclamation text-base"></i> 
                        <span><?= htmlspecialchars($_GET['error'] ?? $error) ?></span>
                    </div>
                <?php endif; ?>

                <!-- Alert Success -->
                <?php if (!empty($_GET['success']) || !empty($success)): ?>
                    <div x-data="{ show: true }" 
                         x-show="show" 
                         x-init="setTimeout(() => show = false, 3000)" 
                         x-transition.duration.500ms
                         class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl text-xs flex items-center gap-2">
                        <i class="fa-solid fa-circle-check text-base"></i> 
                        <span><?= htmlspecialchars($_GET['success'] ?? $success) ?></span>
                    </div>
                <?php endif; ?>

                <!-- TAB HEADERS -->
                <div class="flex border-b border-gray-200 gap-4 text-xs font-bold">
                    <button type="button" @click="activeTab = 'quotes'" :class="activeTab === 'quotes' ? 'border-emerald-600 text-emerald-800 border-b-2 pb-3' : 'text-gray-400 pb-3 hover:text-gray-600 transition'">
                        <i class="fa-solid fa-quote-left mr-1"></i> Quotes Section
                    </button>
                    <button type="button" @click="activeTab = 'profile'" :class="activeTab === 'profile' ? 'border-emerald-600 text-emerald-800 border-b-2 pb-3' : 'text-gray-400 pb-3 hover:text-gray-600 transition'">
                        <i class="fa-solid fa-file-lines mr-1"></i> Profile
                    </button>
                    <button type="button" @click="activeTab = 'program'" :class="activeTab === 'program' ? 'border-emerald-600 text-emerald-800 border-b-2 pb-3' : 'text-gray-400 pb-3 hover:text-gray-600 transition'">
                        <i class="fa-solid fa-layer-group mr-1"></i> Program
                    </button>
                </div>

                <!-- TAB 1: SECTION QUOTES -->
                <div x-show="activeTab === 'quotes'" 
                     x-data="{ openEdit: false, editData: {}, openDelete: false, deleteData: {} }" 
                     class="space-y-6">

                    <!-- Form Tambah Quote -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-4">
                        <h2 class="text-xs font-bold text-gray-700 uppercase">Tambah Quote Baru</h2>
                        <form action="" method="POST" class="space-y-3">
                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                            <input type="hidden" name="target" value="quote">
                            <input type="hidden" name="action" value="create">

                            <div>
                                <textarea name="quote_text" placeholder="Tuliskan kata mutiara / quote..." required class="w-full text-xs p-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-600"></textarea>
                            </div>
                            <div>
                                <input type="text" name="source" placeholder="Perawi / Sumber (contoh: HR. Bukhari / Imam Syafi'i)" required class="w-full text-xs p-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                            </div>
                            <button type="submit" class="bg-emerald-800 hover:bg-emerald-900 text-white font-bold text-xs px-4 py-2.5 rounded-xl transition">
                                <i class="fa-solid fa-floppy-disk"></i> Simpan 
                            </button>
                        </form>
                    </div>

                    <!-- Daftar Quote -->
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                        <h2 class="text-xs font-bold text-gray-700 uppercase mb-4">Daftar Quote</h2>
                        <div class="space-y-3">
                            <?php while ($q = $quotes->fetch_assoc()): 
                                $isActive = isset($q['is_active']) ? (int)$q['is_active'] : 1;
                            ?>
                                <div class="p-4 border border-gray-100 rounded-xl flex items-center justify-between gap-4 hover:border-gray-200 transition">
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2">
                                            <!-- Badge Status Active / Nonaktif -->
                                            <?php if ($isActive === 1): ?>
                                                <span class="inline-flex items-center gap-1 bg-emerald-100 text-emerald-800 text-[10px] font-semibold px-2 py-0.5 rounded-full">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span> Aktif
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 bg-gray-100 text-gray-600 text-[10px] font-semibold px-2 py-0.5 rounded-full">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> Nonaktif
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-xs italic text-gray-800">"<?= htmlspecialchars($q['quote_text']) ?>"</p>
                                        <span class="text-[10px] font-bold text-emerald-700 block"> <?= htmlspecialchars($q['source']) ?></span>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="flex items-center gap-2 shrink-0">
                                        <form action="" method="POST" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                            <input type="hidden" name="target" value="quote">
                                            <input type="hidden" name="action" value="toggle_active">
                                            <input type="hidden" name="id" value="<?= $q['id'] ?>">
                                            <input type="hidden" name="is_active" value="<?= $isActive === 1 ? 0 : 1 ?>">
                                            <button type="submit" 
                                                title="<?= $isActive === 1 ? 'Nonaktifkan Quote' : 'Aktifkan Quote' ?>"
                                                class="p-2 rounded-lg text-xs font-bold transition <?= $isActive === 1 ? 'bg-amber-50 text-amber-600 hover:bg-amber-100' : 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100' ?>">
                                                <i class="fa-solid <?= $isActive === 1 ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                                            </button>
                                        </form>

                                        <button type="button" 
                                            @click="editData = <?= htmlspecialchars(json_encode($q), ENT_QUOTES, 'UTF-8') ?>; openEdit = true"
                                            class="p-2 bg-blue-50 text-blue-600 hover:bg-blue-100 rounded-lg text-xs font-bold transition" 
                                            title="Edit Quote">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>

                                        <button type="button" 
                                            @click="deleteData = <?= htmlspecialchars(json_encode($q), ENT_QUOTES, 'UTF-8') ?>; openDelete = true"
                                            class="p-2 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg text-xs font-bold transition" 
                                            title="Hapus Quote">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>

                    <!-- MODAL EDIT QUOTE -->
                    <div x-show="openEdit" 
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 overflow-y-auto"
                         style="display: none;">
                        
                        <div @click.away="openEdit = false" class="bg-white w-full max-w-lg rounded-2xl p-6 shadow-xl space-y-4 relative">
                            <div class="flex items-center justify-between border-b pb-3">
                                <h3 class="text-xs font-bold text-gray-800 uppercase flex items-center gap-2">
                                    <i class="fa-solid fa-pen-to-square text-emerald-700"></i> Edit Quote
                                </h3>
                                <button type="button" @click="openEdit = false" class="text-gray-400 hover:text-gray-600 text-base">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>

                            <form action="" method="POST" class="space-y-4">
                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                <input type="hidden" name="target" value="quote">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="id" :value="editData.id">

                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Quote / Kata Mutiara</label>
                                    <textarea name="quote_text" :value="editData.quote_text" required rows="3" class="w-full text-xs p-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-600"></textarea>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Sumber / Perawi</label>
                                    <input type="text" name="source" :value="editData.source" required class="w-full text-xs p-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                                </div>

                                <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                                    <button type="button" @click="openEdit = false" class="bg-gray-100 text-gray-700 font-bold text-xs px-4 py-2.5 rounded-xl hover:bg-gray-200 transition">
                                        Batal
                                    </button>
                                    <button type="submit" class="bg-emerald-800 text-white font-bold text-xs px-4 py-2.5 rounded-xl hover:bg-emerald-900 transition">
                                        <i class="fa-solid fa-floppy-disk"></i> Simpan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- MODAL CONFIRM DELETE QUOTE -->
                    <div x-show="openDelete" 
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 overflow-y-auto"
                         style="display: none;">
                        
                        <div @click.away="openDelete = false" class="bg-white w-full max-w-sm rounded-2xl p-6 shadow-xl space-y-4 text-center relative">
                            <div class="w-12 h-12 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto text-xl">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            </div>

                            <div>
                                <h3 class="text-sm font-bold text-gray-800">Hapus Quote Ini?</h3>
                                <p class="text-xs text-gray-500 mt-1">
                                    Apakah Anda yakin ingin menghapus quote berikut?
                                </p>
                                <blockquote class="text-xs italic text-gray-700 bg-gray-50 p-3 rounded-xl border-l-4 border-red-500 mt-2 text-left" x-text="'&quot;' + deleteData.quote_text + '&quot;'"></blockquote>
                            </div>

                            <form action="" method="POST" class="pt-2">
                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                <input type="hidden" name="target" value="quote">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" :value="deleteData.id">

                                <div class="flex items-center justify-center gap-2">
                                    <button type="button" @click="openDelete = false" class="w-1/2 bg-gray-100 text-gray-700 font-bold text-xs py-2.5 rounded-xl hover:bg-gray-200 transition">
                                        Batal
                                    </button>
                                    <button type="submit" class="w-1/2 bg-red-600 text-white font-bold text-xs py-2.5 rounded-xl hover:bg-red-700 transition">
                                        <i class="fa-solid fa-trash"></i> Ya, Hapus
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                </div>

                <!-- TAB 2: SECTION PROFILE (ABOUT US & TINYMCE) -->
                <div x-show="activeTab === 'profile'" class="space-y-6" style="display: none;">
                    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-4">

                        <form action="" method="POST" class="space-y-4">
                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                            <input type="hidden" name="target" value="about">

                            <div class="border border-gray-200 rounded-xl overflow-hidden">
                                <!-- Textarea untuk Rich Text Editor -->
                                <textarea id="about_editor" name="content"><?= htmlspecialchars($about_content) ?></textarea>
                            </div>

                            <div class="flex items-center justify-end pt-3 border-t border-gray-100">
                                <button type="submit" class="bg-emerald-800 text-white font-bold text-xs px-6 py-3 rounded-xl hover:bg-emerald-900 transition flex items-center gap-2">
                                    <i class="fa-solid fa-floppy-disk"></i> Simpan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- TAB 3: SECTION PROGRAM KAMI -->
                <div x-show="activeTab === 'program'" 
                     x-data="{ openEdit: false, editData: {}, openDelete: false, deleteData: {} }" 
                     class="space-y-6" 
                     style="display: none;">
                    
                    <!-- Form Tambah Program -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-4">
                        <h2 class="text-xs font-bold text-gray-700 uppercase">Tambah Program Baru</h2>
                        <form action="" method="POST" class="space-y-3">
                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                            <input type="hidden" name="target" value="program">
                            <input type="hidden" name="action" value="create">

                            <div>
                                <input type="text" name="title" placeholder="Judul Program (contoh: Pengajian)" required class="w-full text-xs p-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                            </div>
                            
                            <div>
                                <textarea name="description" placeholder="Deskripsi Singkat Program..." required rows="3" class="w-full text-xs p-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-600"></textarea>
                            </div>

                            <button type="submit" class="bg-emerald-800 text-white font-bold text-xs px-4 py-2.5 rounded-xl hover:bg-emerald-900 transition flex items-center gap-1.5">
                                <i class="fa-solid fa-floppy-disk"></i> Simpan
                            </button>
                        </form>
                    </div>

                    <!-- Daftar Cards Program -->
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                        <h2 class="text-xs font-bold text-gray-700 uppercase mb-4">Daftar Program Kami</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php while ($p = $program->fetch_assoc()): 
                                $isActive = isset($p['is_active']) ? (int)$p['is_active'] : 1;
                            ?>
                                <div class="border border-gray-100 rounded-xl p-4 space-y-3 relative flex flex-col justify-between hover:border-gray-200 transition bg-gray-50/50">
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between gap-2">
                                            <h3 class="text-xs font-bold text-gray-900 line-clamp-1"><?= htmlspecialchars($p['title']) ?></h3>
                                            <?php if ($isActive === 1): ?>
                                                <span class="inline-flex items-center gap-1 bg-emerald-100 text-emerald-800 text-[9px] font-semibold px-2 py-0.5 rounded-full shrink-0">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span> Aktif
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 bg-gray-100 text-gray-600 text-[9px] font-semibold px-2 py-0.5 rounded-full shrink-0">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> Nonaktif
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <p class="text-[11px] text-gray-500 leading-relaxed">
                                            <?= htmlspecialchars($p['description']) ?>
                                        </p>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="pt-3 border-t border-gray-200/60 flex items-center justify-between gap-2 mt-2">
                                        <!-- Toggle Status Active/Nonaktif -->
                                        <form action="" method="POST" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                            <input type="hidden" name="target" value="program">
                                            <input type="hidden" name="action" value="toggle_active">
                                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                            <input type="hidden" name="is_active" value="<?= $isActive === 1 ? 0 : 1 ?>">
                                            <button type="submit" 
                                                    title="<?= $isActive === 1 ? 'Nonaktifkan Program' : 'Aktifkan Program' ?>"
                                                    class="p-1.5 rounded-lg text-xs font-bold transition <?= $isActive === 1 ? 'bg-amber-50 text-amber-600 hover:bg-amber-100' : 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100' ?>">
                                                <i class="fa-solid <?= $isActive === 1 ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                                            </button>
                                        </form>

                                        <div class="flex items-center gap-2">
                                            <button type="button" 
                                                    @click="editData = <?= htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8') ?>; openEdit = true" 
                                                    class="p-1.5 bg-blue-50 text-blue-600 hover:bg-blue-100 rounded-lg text-xs font-bold transition"
                                                    title="Edit Program">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>

                                            <button type="button" 
                                                    @click="deleteData = <?= htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8') ?>; openDelete = true" 
                                                    class="p-1.5 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg text-xs font-bold transition"
                                                    title="Hapus Program">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>

                    <!-- MODAL EDIT PROGRAM -->
                    <div x-show="openEdit" 
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 overflow-y-auto"
                         style="display: none;">
                        
                        <div @click.away="openEdit = false" class="bg-white w-full max-w-lg rounded-2xl p-6 shadow-xl space-y-4 relative">
                            <div class="flex items-center justify-between border-b pb-3">
                                <h3 class="text-xs font-bold text-gray-800 uppercase flex items-center gap-2">
                                    <i class="fa-solid fa-pen-to-square text-emerald-700"></i> Edit Program
                                </h3>
                                <button type="button" @click="openEdit = false" class="text-gray-400 hover:text-gray-600 text-base">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>

                            <form action="" method="POST" class="space-y-3">
                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                <input type="hidden" name="target" value="program">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="id" :value="editData.id">

                                <div class="space-y-3">
                                    <div>
                                        <label class="block text-[10px] font-bold text-gray-500 mb-1">Judul Program</label>
                                        <input type="text" name="title" :value="editData.title" required class="w-full text-xs p-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                                    </div>

                                    <div>
                                        <label class="block text-[10px] font-bold text-gray-500 mb-1">Deskripsi Program</label>
                                        <textarea name="description" :value="editData.description" required rows="3" class="w-full text-xs p-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-600"></textarea>
                                    </div>
                                </div>

                                <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                                    <button type="button" @click="openEdit = false" class="bg-gray-100 text-gray-700 font-bold text-xs px-4 py-2.5 rounded-xl hover:bg-gray-200 transition">
                                        Batal
                                    </button>
                                    <button type="submit" class="bg-emerald-800 text-white font-bold text-xs px-4 py-2.5 rounded-xl hover:bg-emerald-900 transition flex items-center gap-1.5">
                                        <i class="fa-solid fa-floppy-disk"></i> Simpan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- MODAL CONFIRM DELETE PROGRAM -->
                    <div x-show="openDelete" 
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 overflow-y-auto"
                         style="display: none;">
                        
                        <div @click.away="openDelete = false" class="bg-white w-full max-w-sm rounded-2xl p-6 shadow-xl space-y-4 text-center relative">
                            <div class="w-12 h-12 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto text-xl">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            </div>

                            <div>
                                <h3 class="text-sm font-bold text-gray-800">Hapus Program Ini?</h3>
                                <p class="text-xs text-gray-500 mt-1">
                                    Apakah Anda yakin ingin menghapus <span class="font-bold text-gray-800" x-text="deleteData.title"></span>? Tindakan ini tidak dapat dibatalkan.
                                </p>
                            </div>

                            <form action="" method="POST" class="pt-2">
                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                <input type="hidden" name="target" value="program">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" :value="deleteData.id">

                                <div class="flex items-center justify-center gap-2">
                                    <button type="button" @click="openDelete = false" class="w-1/2 bg-gray-100 text-gray-700 font-bold text-xs py-2.5 rounded-xl hover:bg-gray-200 transition">
                                        Batal
                                    </button>
                                    <button type="submit" class="w-1/2 bg-red-600 text-white font-bold text-xs py-2.5 rounded-xl hover:bg-red-700 transition flex items-center justify-center gap-1.5">
                                        <i class="fa-solid fa-trash"></i> Ya, Hapus
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                </div>

            </main>
        </div>
    </div>
    
    <!-- Script TinyMCE (Koneksi via CDN) -->
    <script src="https://cdn.tiny.cloud/1/ajat4uurupjvntr3qt3f374kybfn2miak8znlgzpykvzlg8w/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
      tinymce.init({
        selector: '#about_editor',
        height: 450,
        menubar: false,
        plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table wordcount',
        toolbar: 'undo redo | blocks | bold italic underline textcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | table link image | removeformat | code fullscreen',
        content_style: 'body { font-family: "Zilla Slab", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; font-size: 16px; color: #374151; }',
        branding: false,
        promotion: false
      });
    </script>
</body>
</html>