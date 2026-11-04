<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
check_access(['super_administrator', 'administrator']);

$user_id = $_SESSION['user_id'];
$success = '';
$error   = '';

$stmt = $conn->prepare("SELECT fullname, email, no_hp, avatar, password, pendidikan, spesialisasi, aboutme FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Token keamanan CSRF tidak valid.";
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_profile') {
            $fullname    = sanitize($_POST['fullname'] ?? '');
            $email       = sanitize($_POST['email'] ?? '');
            $no_hp       = sanitize($_POST['no_hp'] ?? '');
            $pendidikan  = sanitize($_POST['pendidikan'] ?? '');
            $spesialisasi = sanitize($_POST['spesialisasi'] ?? '');
            $aboutme     = sanitize($_POST['aboutme'] ?? '');
            $avatar_filename = $user['avatar'];

            if (empty($fullname) || empty($email) || empty($no_hp)) {
                $error = "Nama lengkap, Email, dan Nomor HP wajib diisi.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Format email tidak valid.";
            } else {
                $no_hp = preg_replace('/[^0-9]/', '', $no_hp);
                if (str_starts_with($no_hp, '0')) {
                    $no_hp = '62' . substr($no_hp, 1);
                }

                $check_stmt = $conn->prepare("SELECT id, email, no_hp FROM users WHERE (email = ? OR no_hp = ?) AND id != ? LIMIT 1");
                $check_stmt->bind_param("ssi", $email, $no_hp, $user_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();

                if ($duplicate_user = $check_result->fetch_assoc()) {
                    if ($duplicate_user['email'] === $email) {
                        $error = "Email <strong>" . htmlspecialchars($email) . "</strong> sudah digunakan oleh pengguna lain.";
                    } else {
                        $error = "Nomor HP <strong>" . htmlspecialchars($no_hp) . "</strong> sudah digunakan oleh pengguna lain.";
                    }
                }
                $check_stmt->close();

                if (empty($error) && isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE && $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
                    $uplErr = array(
                        UPLOAD_ERR_INI_SIZE => 'Ukuran foto melebihi batas server (' . ini_get('upload_max_filesize') . '). Perkecil dulu fotonya.',
                        UPLOAD_ERR_FORM_SIZE => 'Ukuran foto melebihi batas form. Perkecil dulu fotonya.',
                        UPLOAD_ERR_PARTIAL => 'Upload terputus, coba lagi.',
                        UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara server bermasalah, hubungi admin.',
                        UPLOAD_ERR_CANT_WRITE => 'Gagal menyimpan file, hubungi admin.',
                        UPLOAD_ERR_EXTENSION => 'Upload diblokir ekstensi PHP.',
                    );
                    $error = isset($uplErr[$_FILES['avatar']['error']]) ? $uplErr[$_FILES['avatar']['error']] : 'Upload gagal (kode ' . $_FILES['avatar']['error'] . ').';
                }
                if (empty($error) && isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                    $file_tmp  = $_FILES['avatar']['tmp_name'];
                    $file_size = $_FILES['avatar']['size'];
                    
                    $finfo     = new finfo(FILEINFO_MIME_TYPE);
                    $mime_type = $finfo->file($file_tmp);
                    $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];

                    if (!in_array($mime_type, $allowed_mimes, true)) {
                        $error = "Format foto tidak valid. Gunakan ekstensi JPG, PNG, atau WEBP.";
                    } elseif ($file_size > 5 * 1024 * 1024) { // Max 5MB
                        $error = "Ukuran foto maksimal adalah 5MB.";
                    } else {
                        $ext = match($mime_type) {
                            'image/jpeg' => 'jpg',
                            'image/png'  => 'png',
                            'image/webp' => 'webp',
                        };

                        $upload_dir = __DIR__ . '/../uploads/avatars/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }

                        $new_filename = 'avatar_' . $user_id . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                        $target_path  = $upload_dir . $new_filename;

                        if (move_uploaded_file($file_tmp, $target_path)) {
                            if (!empty($user['avatar']) && file_exists($upload_dir . $user['avatar'])) {
                                unlink($upload_dir . $user['avatar']);
                            }
                            $avatar_filename = $new_filename;
                        } else {
                            $error = "Gagal mengunggah foto profil ke server.";
                        }
                    }
                }

                if (empty($error)) {
                    $stmt_up = $conn->prepare("UPDATE users SET fullname = ?, email = ?, no_hp = ?, pendidikan = ?, spesialisasi = ?, aboutme = ?, avatar = ? WHERE id = ?");
                    $stmt_up->bind_param("sssssssi", $fullname, $email, $no_hp, $pendidikan, $spesialisasi, $aboutme, $avatar_filename, $user_id);

                    if ($stmt_up->execute()) {
                        log_activity('UPDATE', 'profil', "Memperbarui profil sendiri", $user_id);
                        $_SESSION['username'] = $fullname;
                        $_SESSION['avatar']   = $avatar_filename;
                        $success = "Profil berhasil diperbarui!";
                        
                        $user['fullname']     = $fullname;
                        $user['email']        = $email;
                        $user['no_hp']        = $no_hp;
                        $user['pendidikan']   = $pendidikan;
                        $user['spesialisasi'] = $spesialisasi;
                        $user['aboutme']      = $aboutme;
                        $user['avatar']       = $avatar_filename;
                    } else {
                        $error = "Gagal menyimpan perubahan profil ke database.";
                    }
                    $stmt_up->close();
                }
            }
        }

        elseif ($action === 'change_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password     = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error = "Semua kolom kata sandi wajib diisi.";
            } elseif (!password_verify($current_password, $user['password'])) {
                $error = "Kata sandi saat ini/lama tidak sesuai.";
            } elseif (strlen($new_password) < 8) {
                $error = "Kata sandi baru minimal harus 8 karakter.";
            } elseif ($new_password !== $confirm_password) {
                $error = "Konfirmasi kata sandi baru tidak cocok.";
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                
                $stmt_pass = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt_pass->bind_param("si", $hashed_password, $user_id);

                if ($stmt_pass->execute()) {
                    $success = "Kata sandi berhasil diubah!";
                } else {
                    $error = "Gagal memperbarui kata sandi.";
                }
                $stmt_pass->close();
            }
        }
    }
}

$pending_count_query = $conn->query("SELECT COUNT(id) FROM section_kelas WHERE status = 'pending'");
$pending_notifications = $pending_count_query ? (int) $pending_count_query->fetch_row()[0] : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Profil - Iqra' Society</title>
    <link rel="icon" type="image/x-icon" href="../asset/img/favicon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../asset/img/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Zilla+Slab:wght@600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50 font-sans antialiased" x-data="{ sidebarOpen: false, activeTab: 'profile', showPassOld: false, showPassNew: false, showConPass: false }">

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
        <h1 class="text-base md:text-lg font-extrabold text-gray-800">Profile</h1>
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

            <main class="p-6 md:p-6 w-full space-y-6">
            
                <!-- Alert Notifikasi Error (Hilang otomatis dalam 3 detik) -->
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

                <!-- Alert Notifikasi Success (Hilang otomatis dalam 3 detik) -->
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
                <div class="flex border-b border-gray-200 gap-6 text-xs font-bold">
                    <button type="button" @click="activeTab = 'profile'" :class="activeTab === 'profile' ? 'border-emerald-600 text-emerald-800 border-b-2 pb-3' : 'text-gray-400 pb-3 hover:text-gray-600 transition'">
                        <i class="fa-solid fa-user-pen mr-1"></i> Ubah Data Diri
                    </button>
                    <button type="button" @click="activeTab = 'password'" :class="activeTab === 'password' ? 'border-emerald-600 text-emerald-800 border-b-2 pb-3' : 'text-gray-400 pb-3 hover:text-gray-600 transition'">
                        <i class="fa-solid fa-lock mr-1"></i> Ubah Kata Sandi
                    </button>
                </div>
            
                <!-- TAB CONTENT 1: Form Edit Data Diri, Email, No HP, Pendidikan, Spesialisasi, About Me & Avatar -->
                <div x-show="activeTab === 'profile'" x-cloak class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                    <form action="" method="POST" enctype="multipart/form-data" class="space-y-5">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="action" value="update_profile">
            
                        <!-- Preview & Upload Avatar -->
                        <div class="flex items-center gap-5">
                            <div class="relative shrink-0">
                                <?php if (!empty($user['avatar']) && file_exists(__DIR__ . '/../uploads/avatars/' . $user['avatar'])): ?>
                                    <img src="../uploads/avatars/<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar" class="w-20 h-20 rounded-full object-cover border-2 border-emerald-600 shadow-sm">
                                <?php else: ?>
                                    <div class="w-20 h-20 rounded-full bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold text-2xl border-2 border-emerald-600 shadow-sm">
                                        <?= strtoupper(substr($user['fullname'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">Foto Profil / Avatar</label>
                                <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 transition">
                                <p class="text-[10px] text-gray-400 mt-1">Format: JPG, PNG, WEBP. Maksimal 2MB.</p>
                            </div>
                        </div>
            
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">Nama Lengkap</label>
                                <input type="text" name="fullname" value="<?= htmlspecialchars($user['fullname'] ?? '') ?>" required class="w-full text-xs px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">Nomor WhatsApp / HP</label>
                                <input type="text" name="no_hp" value="<?= htmlspecialchars($user['no_hp'] ?? '') ?>" placeholder="081234567890" required class="w-full text-xs px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                            </div>
                        </div>
            
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Alamat Email</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required class="w-full text-xs px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                            <p class="text-[10px] text-gray-400 mt-1">Email digunakan untuk login dan pemulihan password.</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">Pendidikan Terakhir</label>
                                <input type="text" name="pendidikan" value="<?= htmlspecialchars($user['pendidikan'] ?? '') ?>" placeholder="Contoh: S1 Ilmu Al-Qur'an dan Tafsir, Universitas Al-Azhar" class="w-full text-xs px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">Spesialisasi / Keahlian</label>
                                <input type="text" name="spesialisasi" value="<?= htmlspecialchars($user['spesialisasi'] ?? '') ?>" placeholder="Contoh: Tajwid, Fiqih Muamalah, Bahasa Arab" class="w-full text-xs px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                            </div>
                        </div>

                        <!-- 4. INPUT TEXTAREA UNTUK TENTANG SAYA / ABOUT ME -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Tentang Saya (Biografi Singkat)</label>
                            <textarea name="aboutme" rows="4" placeholder="Tuliskan biografi singkat, latar belakang, atau motivasi dan pengalaman Anda..." class="w-full text-xs p-4 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-600 resize-y"><?= htmlspecialchars($user['aboutme'] ?? '') ?></textarea>
                            <p class="text-[10px] text-gray-400 mt-1">Informasi ini akan ditampilkan pada profil publik pengajar.</p>
                        </div>
            
                        <button type="submit" class="bg-emerald-800 hover:bg-emerald-900 text-white font-bold text-xs px-6 py-3 rounded-xl shadow-md transition flex items-center gap-2">
                            <i class="fa-solid fa-floppy-disk"></i> Simpan
                        </button>
                    </form>
                </div>
            
                <!-- TAB CONTENT 2: Form Ubah Password -->
                <div x-show="activeTab === 'password'" x-cloak class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                    <form action="" method="POST" class="space-y-4 w-full">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="action" value="change_password">
            
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Kata Sandi Saat Ini</label>
                            <div class="relative">
                                <input :type="showPassOld ? 'text' : 'password'" name="current_password" required class="w-full text-xs px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                                <button type="button" @click="showPassOld = !showPassOld" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs">
                                    <i class="fa-solid" :class="showPassOld ? 'fa-eye-slash' : 'fa-eye'"></i>
                                </button>
                            </div>
                        </div>
            
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Kata Sandi Baru</label>
                            <div class="relative">
                                <input :type="showPassNew ? 'text' : 'password'" name="new_password" required class="w-full text-xs px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                                <button type="button" @click="showPassNew = !showPassNew" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs">
                                    <i class="fa-solid" :class="showPassNew ? 'fa-eye-slash' : 'fa-eye'"></i>
                                </button>
                            </div>
                        </div>
            
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Konfirmasi Kata Sandi Baru</label>
                            <div class="relative">
                                <input :type="showConPass ? 'text' : 'password'" 
                                       name="confirm_password" 
                                       required 
                                       class="w-full text-xs px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-600 pr-10">
                                <button type="button" 
                                        @click="showConPass = !showConPass" 
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs">
                                    <i class="fa-solid" :class="showConPass ? 'fa-eye-slash' : 'fa-eye'"></i>
                                </button>
                            </div>
                        </div>
            
                        <button type="submit" class="bg-gray-900 hover:bg-black text-white font-bold text-xs px-6 py-3 rounded-xl shadow-md transition flex items-center gap-2">
                            <i class="fa-solid fa-key"></i> Perbarui
                        </button>
                    </form>
                </div>
            
            </main>
        </div>
    </div>

</body>
</html>