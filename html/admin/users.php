<?php
require_once __DIR__ . '/../includes/auth_middleware.php';

check_access(['super_administrator', 'super_admin']);

$current_user_id = $_SESSION['user_id'];
$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Token keamanan CSRF tidak valid.";
    } else {
        $target_user_id = (int) ($_POST['user_id'] ?? 0);
        $new_role       = sanitize($_POST['role'] ?? '');

        $allowed_roles = ['super_administrator', 'administrator', 'pengajar', 'pembelajar', 'donatur'];

        if ($target_user_id === $current_user_id) {
            $error = "Anda tidak dapat mengubah role akun Anda sendiri demi alasan keamanan.";
        } elseif (!in_array($new_role, $allowed_roles, true)) {
            $error = "Pilihan role tidak valid.";
        } else {
            $u = $conn->prepare("SELECT username, fullname FROM users WHERE id = ?");
            $u->bind_param("i", $target_user_id); $u->execute();
            $ur = $u->get_result()->fetch_assoc(); $u->close();
            log_activity('UPDATE', 'user', "Mengubah role '" . ($ur['username'] ?? $target_user_id) . "' menjadi '$new_role'", $target_user_id);
            $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->bind_param("si", $new_role, $target_user_id);
            
            if ($stmt->execute()) {
                $success = "Role pengguna berhasil diperbarui!";
            } else {
                $error = "Gagal memperbarui role pengguna.";
            }
            $stmt->close();
        }
    }
}

$users_result = $conn->query("SELECT id, fullname, email, no_hp, role, created_at FROM users ORDER BY id DESC");

$pending_count_query = $conn->query("SELECT COUNT(id) FROM section_kelas WHERE status = 'pending'");
$pending_notifications = $pending_count_query ? (int) $pending_count_query->fetch_row()[0] : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengguna - Iqra' Society</title>
    <link rel="icon" type="image/x-icon" href="../asset/img/favicon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../asset/img/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Zilla+Slab:wght@600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<body class="bg-gray-50 font-sans antialiased" x-data="{ sidebarOpen: false }">
</head>
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
	    <header class="sticky top-0 z-30 bg-white/95 backdrop-blur-md border-b border-gray-100 px-4 md:px-6 py-4 flex items-center justify-between shadow-sm">                <div class="flex items-center gap-3">
                    <button @click="sidebarOpen = true" class="md:hidden text-gray-600 hover:text-gray-900 p-2 rounded-lg hover:bg-gray-100">
                        <i class="fa-solid fa-bars text-lg"></i>
                    </button>
                    <h1 class="text-base md:text-lg font-extrabold text-gray-800">Manajemen User</h1>
                </div>

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

<main class="p-4 md:p-6 space-y-4 md:space-y-6">

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

    <?php 
    // Mengubah hasil query ke array agar bisa di-loop 2x (Mobile & Desktop)
    $users_data = [];
    if ($users_result) {
        while ($r = $users_result->fetch_assoc()) {
            $users_data[] = $r;
        }
    }
    ?>

    <!-- ================= 1. TAMPILAN MOBILE (CARD VIEW) ================= -->
    <div class="space-y-3 block md:hidden">
        <?php foreach ($users_data as $row): ?>
            <div class="bg-white rounded-2xl border border-gray-100 p-4 shadow-sm space-y-3">
                
                <!-- Info User & Role Badge -->
                <div class="flex items-start justify-between gap-2 border-b border-gray-50 pb-2.5">
                    <div>
                        <div class="font-bold text-gray-900 text-xs"><?= htmlspecialchars($row['fullname']) ?></div>
                        <div class="text-[10px] text-gray-400">ID: #<?= $row['id'] ?></div>
                    </div>
                    <span class="inline-block px-2 py-0.5 rounded-md text-[9px] font-bold uppercase shrink-0
                        <?= $row['role'] === 'super_administrator' ? 'bg-purple-100 text-purple-800' : 
                           ($row['role'] === 'administrator' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-700') ?>">
                        <?= htmlspecialchars($row['role']) ?>
                    </span>
                </div>

                <!-- Detail Kontak -->
                <div class="grid grid-cols-1 gap-1 text-[11px] text-gray-600">
                    <div class="flex items-center gap-2">
                        <i class="fa-regular fa-envelope text-gray-400 w-3"></i>
                        <span class="truncate"><?= htmlspecialchars($row['email']) ?></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-phone text-gray-400 w-3"></i>
                        <span><?= htmlspecialchars($row['no_hp']) ?></span>
                    </div>
                </div>

                <!-- Form Ubah Role -->
                <div class="pt-1">
                    <?php if ($row['id'] !== $current_user_id): ?>
                        <form action="" method="POST" class="flex items-center gap-2">
                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                            <input type="hidden" name="user_id" value="<?= $row['id'] ?>">
                            
                            <select name="role" class="w-full text-xs p-2.5 rounded-xl border border-gray-200 focus:outline-none focus:ring-1 focus:ring-emerald-600 bg-gray-50">
                                <option value="pembelajar" <?= $row['role'] === 'pembelajar' ? 'selected' : '' ?>>Pembelajar</option>
                                <option value="pengajar" <?= $row['role'] === 'pengajar' ? 'selected' : '' ?>>Pengajar</option>
                                <option value="donatur" <?= $row['role'] === 'donatur' ? 'selected' : '' ?>>Donatur</option>
                                <option value="administrator" <?= $row['role'] === 'administrator' ? 'selected' : '' ?>>Administrator</option>
                                <option value="super_administrator" <?= $row['role'] === 'super_administrator' ? 'selected' : '' ?>>Super Administrator</option>
                            </select>
                            
                            <button type="submit" class="bg-emerald-800 hover:bg-emerald-900 text-white font-bold px-3 py-2.5 rounded-xl text-xs transition shrink-0"><i class="fa-solid fa-floppy-disk"></i> Simpan
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="text-[10px] text-gray-400 italic bg-gray-50 p-2 rounded-xl text-center">
                            <i class="fa-solid fa-user-check mr-1"></i> Akun Anda Saat Ini
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        <?php endforeach; ?>
    </div>


    <!-- ================= 2. TAMPILAN DESKTOP & TABLET (TABLE VIEW) ================= -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 hidden md:block">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-gray-600">
                <thead class="bg-gray-50 text-gray-700 uppercase font-bold">
                    <tr>
                        <th class="p-3">Pengguna</th>
                        <th class="p-3">Kontak</th>
                        <th class="p-3">Role Saat Ini</th>
                        <th class="p-3">Ubah Role</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($users_data as $row): ?>
                    <tr>
                        <td class="p-3">
                            <div class="font-bold text-gray-900"><?= htmlspecialchars($row['fullname']) ?></div>
                            <div class="text-[10px] text-gray-400">ID: #<?= $row['id'] ?></div>
                        </td>
                        <td class="p-3">
                            <div><?= htmlspecialchars($row['email']) ?></div>
                            <div class="text-[10px] text-gray-400"><?= htmlspecialchars($row['no_hp']) ?></div>
                        </td>
                        <td class="p-3">
                            <span class="inline-block px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase 
                                <?= $row['role'] === 'super_administrator' ? 'bg-purple-100 text-purple-800' : 
                                   ($row['role'] === 'administrator' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-700') ?>">
                                <?= htmlspecialchars($row['role']) ?>
                            </span>
                        </td>
                        <td class="p-3">
                            <?php if ($row['id'] !== $current_user_id): ?>
                                <form action="" method="POST" class="flex items-center gap-2">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <input type="hidden" name="user_id" value="<?= $row['id'] ?>">
                                    
                                    <select name="role" class="text-xs p-2 rounded-xl border border-gray-200 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                                        <option value="pembelajar" <?= $row['role'] === 'pembelajar' ? 'selected' : '' ?>>Pembelajar</option>
                                        <option value="pengajar" <?= $row['role'] === 'pengajar' ? 'selected' : '' ?>>Pengajar</option>
                                        <option value="donatur" <?= $row['role'] === 'donatur' ? 'selected' : '' ?>>Donatur</option>
                                        <option value="administrator" <?= $row['role'] === 'administrator' ? 'selected' : '' ?>>Administrator</option>
                                        <option value="super_administrator" <?= $row['role'] === 'super_administrator' ? 'selected' : '' ?>>Super Administrator</option>
                                    </select>
                                    
                                    <button type="submit" class="bg-emerald-800 hover:bg-emerald-900 text-white font-bold px-3 py-2 rounded-xl transition flex items-center gap-1">
                                        <i class="fa-solid fa-floppy-disk"></i> Simpan
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-[10px] text-gray-400 italic">Akun Anda</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>
        </div>
    </div>

</body>
</html>