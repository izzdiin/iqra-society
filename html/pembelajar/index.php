<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
check_access(['super_administrator', 'administrator', 'pembelajar']);

$user_id  = (int)($_SESSION['user_id'] ?? 0);
$fullname = $_SESSION['fullname'] ?? '';
$email    = $_SESSION['email'] ?? '';
$avatar   = $_SESSION['avatar'] ?? '';

// Nomor HP user (tidak disimpan di sesi, ambil dari DB)
$no_hp = '';
$stmt = $conn->prepare("SELECT no_hp FROM users WHERE id = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) { $no_hp = $row['no_hp'] ?? ''; }
    $stmt->close();
}

// Path avatar (mengikuti pola includes/header.php)
$avatar_url = '';
if (!empty($avatar) && file_exists(__DIR__ . '/../uploads/avatars/' . $avatar)) {
    $avatar_url = '../uploads/avatars/' . $avatar;
}

/* ============================================================
   DATA DINAMIS DARI DATABASE
   ============================================================ */

// 1. Kelas (hanya yang disetujui)
$kelas_list = [];
$stmt = $conn->prepare(
    "SELECT k.id, k.title, k.slug, k.session_count, k.price, k.preview_image,
            u.fullname AS instructor_name
     FROM section_kelas k
     LEFT JOIN users u ON k.instructor_id = u.id
     WHERE k.status = 'approved'
     ORDER BY k.id DESC"
);
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $kelas_list[] = $row; }
    $stmt->close();
}

// 2. Buku digital
$buku_list = [];
$stmt = $conn->prepare(
    "SELECT id, title, cover, price, link_description
     FROM section_buku
     WHERE status = 'approved'
     ORDER BY id DESC"
);
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $cover_file = trim($row['cover'] ?? '');
        if ($cover_file !== '') {
            $row['cover_url'] = (strpos($cover_file, 'http') === 0 || strpos($cover_file, '/') === 0)
                ? $cover_file
                : '/uploads/buku/' . $cover_file;
        } else {
            $row['cover_url'] = '';
        }
        $buku_list[] = $row;
    }
    $stmt->close();
}

// 3. Kegiatan mendatang
$kegiatan_list = [];
$stmt = $conn->prepare(
    "SELECT id, title, event_date, event_time, execution_type, recurring_day, location, category, image
     FROM section_kegiatan
     WHERE status = 'approved' AND (event_date >= CURDATE() OR execution_type = 'berulang')
     ORDER BY (execution_type = 'berulang'), event_date ASC, id DESC"
);
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $img = trim($row['image'] ?? '');
        $row['thumbnail_url'] = $img !== '' ? '/uploads/kegiatan/' . $img : '';
        // Field turunan untuk render Alpine.js (hindari parsing tanggal di JS)
        $row['is_rutin'] = ($row['execution_type'] ?? '') === 'berulang';
        if (!empty($row['event_date'])) {
            $hb = hari_bln($row['event_date']);
            $row['day'] = $hb['d'];
            $row['mon'] = $hb['m'];
            $row['date_label'] = tgl_id($row['event_date']);
        } else {
            $row['day'] = '';
            $row['mon'] = '';
            $row['date_label'] = '';
        }
        $row['time_label'] = !empty($row['event_time']) ? substr($row['event_time'], 0, 5) . ' WIB' : '';
        $kegiatan_list[] = $row;
    }
    $stmt->close();
}

// 4. Pengumuman / artikel terbaru
$posts_list = [];
$stmt = $conn->prepare(
    "SELECT id, title, slug, created_at
     FROM posts
     WHERE status = 'published'
     ORDER BY id DESC LIMIT 5"
);
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $posts_list[] = $row; }
    $stmt->close();
}

// Statistik banner
$stats = [
    'kelas'    => count($kelas_list),
    'buku'     => count($buku_list),
    'kegiatan' => count($kegiatan_list),
    'artikel'  => count($posts_list),
];

// JSON untuk Alpine.js (filter, pencarian, pagination di sisi klien)
$json_flags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
$kelas_json    = json_encode($kelas_list, $json_flags);
$buku_json     = json_encode($buku_list, $json_flags);
$kegiatan_json = json_encode($kegiatan_list, $json_flags);

/* ---------- helper ---------- */
function rupiah($n) {
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}
function tgl_id($dt, $with_year = true) {
    if (!$dt) return '-';
    $bulan = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',
              7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];
    $t = strtotime($dt);
    $d = date('j', $t) . ' ' . $bulan[(int)date('n', $t)];
    return $with_year ? $d . ' ' . date('Y', $t) : $d;
}
function hari_bln($dt) {
    $bulan = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',
              7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];
    $t = strtotime($dt);
    return ['d' => date('j', $t), 'm' => $bulan[(int)date('n', $t)]];
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$site_url = rtrim($protocol . $host, '/');
$current_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$current_url = $site_url . ($current_path === '' ? '/' : $current_path);
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Member Pembelajar - IQRA' Society</title>
    <meta name="robots" content="noindex, nofollow">

    <!-- Tailwind CSS & FontAwesome -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Zilla+Slab:wght@600;700&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            DEFAULT: '#065f46',
                            dark: '#044e39',
                            light: '#047857',
                            soft: '#ecfdf5',
                            accent: '#10b981'
                        }
                    },
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; color: #1f2937; }
        .scrollbar-none::-webkit-scrollbar { display: none; }
        .scrollbar-none { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="antialiased flex flex-col min-h-screen" x-data="{ activeTab: 'dashboard', userMenuOpen: false }">

    <!-- HEADER / NAVBAR MOBILE-FIRST -->
    <header class="bg-white/90 backdrop-blur-md sticky top-0 z-50 border-b border-gray-100 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 sm:h-20">
                <!-- Logo -->
                <a href="../index.php" class="flex items-center gap-2.5" aria-label="Beranda Iqra Society">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-xl shadow-sm">
                        <img src="../asset/img/logo_kitab.png" alt="Logo" width="40" height="40">
                    </div>
                    <div class="flex flex-col font-['Zilla_Slab'] leading-tight">
                        <span class="text-lg font-bold text-emerald-900 tracking-wide uppercase leading-none">IQRA'</span>
                        <span class="text-base font-semibold text-lime-500 tracking-normal leading-none mt-0.5">Society</span>
                    </div>
                </a>

                <!-- User Info & Dropdown -->
                <div class="flex items-center gap-3">
                    <div class="hidden sm:flex flex-col text-right">
                        <span class="text-xs font-bold text-gray-800"><?= htmlspecialchars($fullname) ?></span>
                        <span class="text-[10px] text-emerald-700 font-medium capitalize">Pembelajar</span>
                    </div>

                    <div class="relative">
                        <button @click="userMenuOpen = !userMenuOpen" class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold text-sm border-2 border-emerald-500 focus:outline-none overflow-hidden">
                            <?php if ($avatar_url): ?>
                                <img src="<?= htmlspecialchars($avatar_url) ?>" alt="Avatar" class="w-full h-full object-cover">
                            <?php else: ?>
                                <i class="fa-solid fa-user"></i>
                            <?php endif; ?>
                        </button>

                        <!-- Dropdown Menu -->
                        <div x-show="userMenuOpen" @click.away="userMenuOpen = false" x-cloak
                             class="absolute right-0 mt-2 w-48 bg-white rounded-2xl shadow-xl border border-gray-100 py-2 z-50 text-xs font-medium">
                            <div class="px-4 py-2 border-b border-gray-100">
                                <p class="font-bold text-gray-800"><?= htmlspecialchars($fullname) ?></p>
                                <p class="text-[10px] text-gray-500 truncate"><?= htmlspecialchars($email) ?></p>
                            </div>
                            <a href="#" @click.prevent="activeTab = 'profil'; userMenuOpen = false" class="block px-4 py-2 text-gray-700 hover:bg-emerald-50 hover:text-emerald-800">Edit Profil</a>
                            <div class="border-t border-gray-100 my-1"></div>
                            <a href="../logout.php" class="block px-4 py-2 text-red-600 hover:bg-red-50 font-bold">Keluar</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- MAIN DASHBOARD CONTENT -->
    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

        <!-- WELCOME BANNER (Mobile-First Card) -->
        <div class="bg-gradient-to-r from-emerald-900 to-emerald-700 rounded-3xl p-6 text-white shadow-lg relative overflow-hidden">
            <div class="absolute right-0 bottom-0 opacity-10 pointer-events-none translate-x-6 translate-y-6">
                <i class="fa-solid fa-graduation-cap text-[180px]"></i>
            </div>
            <div class="relative z-10">
                <span class="bg-emerald-800/80 text-emerald-200 text-[10px] sm:text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider">Panel Member</span>
                <h1 class="text-xl sm:text-3xl font-extrabold mt-2">Ahlan wa Sahlan, <?= htmlspecialchars($fullname) ?>!</h1>
                <p class="text-xs sm:text-sm text-emerald-100/90 mt-1 max-w-xl">
                    Semoga hari-hari Anda dipenuhi berkah dan kemudahan dalam menuntut ilmu bersama Iqra' Society.
                </p>

                <!-- Quick Stats Grid inside Banner (DINAMIS) -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-6 pt-6 border-t border-emerald-800/60">
                    <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-3 border border-white/10">
                        <span class="block text-2xl font-black text-white"><?= $stats['kelas'] ?></span>
                        <span class="text-[11px] text-emerald-200 font-medium">Kelas Tersedia</span>
                    </div>
                    <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-3 border border-white/10">
                        <span class="block text-2xl font-black text-white"><?= $stats['buku'] ?></span>
                        <span class="text-[11px] text-emerald-200 font-medium">Koleksi Buku</span>
                    </div>
                    <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-3 border border-white/10">
                        <span class="block text-2xl font-black text-white"><?= $stats['kegiatan'] ?></span>
                        <span class="text-[11px] text-emerald-200 font-medium">Kegiatan Mendatang</span>
                    </div>
                    <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-3 border border-white/10">
                        <span class="block text-2xl font-black text-white"><?= $stats['artikel'] ?></span>
                        <span class="text-[11px] text-emerald-200 font-medium">Artikel</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- NAVIGATION TABS (Mobile Scrollable) -->
        <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none">
            <button @click="activeTab = 'dashboard'" :class="activeTab === 'dashboard' ? 'bg-emerald-800 text-white shadow-md' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'" class="px-4 py-2.5 rounded-2xl text-xs font-bold transition shrink-0">
                <i class="fa-solid fa-house mr-1.5"></i> Beranda Panel
            </button>
            <button @click="activeTab = 'kelas'" :class="activeTab === 'kelas' ? 'bg-emerald-800 text-white shadow-md' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'" class="px-4 py-2.5 rounded-2xl text-xs font-bold transition shrink-0">
                <i class="fa-solid fa-graduation-cap mr-1.5"></i> Katalog Kelas
            </button>
            <button @click="activeTab = 'buku'" :class="activeTab === 'buku' ? 'bg-emerald-800 text-white shadow-md' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'" class="px-4 py-2.5 rounded-2xl text-xs font-bold transition shrink-0">
                <i class="fa-solid fa-book mr-1.5"></i> Koleksi Buku
            </button>
            <button @click="activeTab = 'kegiatan'" :class="activeTab === 'kegiatan' ? 'bg-emerald-800 text-white shadow-md' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'" class="px-4 py-2.5 rounded-2xl text-xs font-bold transition shrink-0">
                <i class="fa-solid fa-calendar-days mr-1.5"></i> Jadwal Kegiatan
            </button>
            <button @click="activeTab = 'profil'" :class="activeTab === 'profil' ? 'bg-emerald-800 text-white shadow-md' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'" class="px-4 py-2.5 rounded-2xl text-xs font-bold transition shrink-0">
                <i class="fa-solid fa-user-gear mr-1.5"></i> Profil & Akun
            </button>
        </div>

        <!-- TAB CONTENT 1: DASHBOARD UTAMA -->
        <div x-show="activeTab === 'dashboard'" class="space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Kolom Kiri & Tengah -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white rounded-3xl p-5 sm:p-6 border border-gray-100 shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-sm font-extrabold text-gray-900 uppercase tracking-wider">Kelas Terbaru</h2>
                            <button @click="activeTab = 'kelas'" class="text-xs font-bold text-emerald-700 hover:underline">Semua Kelas</button>
                        </div>

                        <div class="space-y-4">
                            <?php if (empty($kelas_list)): ?>
                                <p class="text-xs text-gray-500 text-center py-6">Belum ada kelas tersedia saat ini.</p>
                            <?php else: ?>
                                <?php foreach (array_slice($kelas_list, 0, 3) as $k): ?>
                                <div class="bg-gray-50 border border-gray-200/80 rounded-2xl p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                                    <div class="flex items-start gap-3.5">
                                        <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center text-lg shrink-0">
                                            <i class="fa-solid fa-book-open-reader"></i>
                                        </div>
                                        <div>
                                            <?php if (!empty($k['session_count'])): ?>
                                            <span class="text-[10px] font-bold bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-md"><?= (int)$k['session_count'] ?> Pertemuan</span>
                                            <?php endif; ?>
                                            <h3 class="font-bold text-gray-900 text-sm mt-1"><?= htmlspecialchars($k['title']) ?></h3>
                                            <p class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($k['instructor_name'] ?? 'Pengajar') ?><?= !empty($k['price']) ? ' • ' . rupiah($k['price']) : '' ?></p>
                                        </div>
                                    </div>
                                    <a href="../kelas/<?= (int)$k['id'] ?>" class="w-full sm:w-auto bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-4 py-2.5 rounded-xl text-center transition shadow-sm">
                                        Lihat Kelas
                                    </a>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Buku Terbaru -->
                    <div class="bg-white rounded-3xl p-5 sm:p-6 border border-gray-100 shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-sm font-extrabold text-gray-900 uppercase tracking-wider">Buku Digital Terbaru</h2>
                            <button @click="activeTab = 'buku'" class="text-xs font-bold text-emerald-700 hover:underline">Lihat Koleksi</button>
                        </div>
                        <?php if (empty($buku_list)): ?>
                            <p class="text-xs text-gray-500 text-center py-6">Belum ada buku tersedia saat ini.</p>
                        <?php else: ?>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                            <?php foreach (array_slice($buku_list, 0, 3) as $b): ?>
                            <div class="border border-gray-100 bg-gray-50 rounded-2xl p-3 flex flex-col justify-between">
                                <?php if ($b['cover_url']): ?>
                                    <img src="<?= htmlspecialchars($b['cover_url']) ?>" alt="<?= htmlspecialchars($b['title']) ?>" class="h-36 w-full rounded-xl object-cover mb-2" loading="lazy">
                                <?php else: ?>
                                    <div class="h-36 rounded-xl bg-emerald-50 mb-2 flex items-center justify-center text-emerald-700 font-bold text-xs shadow-inner">
                                        <i class="fa-solid fa-book text-2xl"></i>
                                    </div>
                                <?php endif; ?>
                                <h4 class="text-xs font-bold text-gray-900 truncate"><?= htmlspecialchars($b['title']) ?></h4>
                                <?php if (!empty($b['price'])): ?>
                                <p class="text-[10px] text-emerald-700 font-bold mt-0.5"><?= rupiah($b['price']) ?></p>
                                <?php endif; ?>
                                <a href="../buku" class="mt-2 text-center bg-white hover:bg-emerald-50 text-emerald-800 border border-emerald-700/30 text-xs font-bold py-1.5 rounded-xl transition">Lihat Detail</a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Kolom Kanan: Pengumuman & Agenda -->
                <div class="space-y-6">
                    <div class="bg-emerald-900 text-white rounded-3xl p-5 sm:p-6 shadow-sm relative overflow-hidden">
                        <h2 class="text-sm font-extrabold text-emerald-300 uppercase tracking-wider mb-3">Artikel</h2>
                        <?php if (empty($posts_list)): ?>
                            <p class="text-xs text-emerald-100/70">Belum ada artikel.</p>
                        <?php else: ?>
                        <div class="space-y-3 text-xs text-emerald-100/90">
                            <?php foreach (array_slice($posts_list, 0, 3) as $i => $p): ?>
                            <div class="<?= $i < 2 ? 'pb-3 border-b border-emerald-800' : '' ?>">
                                <span class="text-[10px] text-emerald-300 font-semibold"><?= tgl_id($p['created_at']) ?></span>
                                <a href="../post/<?= htmlspecialchars($p['slug']) ?>" class="block font-bold text-white mt-0.5 hover:text-emerald-200 transition"><?= htmlspecialchars($p['title']) ?></a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="bg-white rounded-3xl p-5 sm:p-6 border border-gray-100 shadow-sm">
                        <h2 class="text-sm font-extrabold text-gray-900 uppercase tracking-wider mb-4">Agenda Terdekat</h2>
                        <?php if (empty($kegiatan_list)): ?>
                            <p class="text-xs text-gray-500">Belum ada agenda mendatang.</p>
                        <?php else: ?>
                        <div class="space-y-4 text-xs">
                            <?php foreach (array_slice($kegiatan_list, 0, 2) as $g): ?>
                            <div class="flex items-start gap-3">
                                <?php if (($g['execution_type'] ?? '') === 'berulang'): ?>
                                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-800 flex items-center justify-center shrink-0" title="Kegiatan rutin">
                                    <i class="fa-solid fa-repeat"></i>
                                </div>
                                <?php else: $hb = hari_bln($g['event_date']); ?>
                                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-800 flex flex-col items-center justify-center shrink-0 font-bold">
                                    <span class="text-[10px] uppercase"><?= $hb['m'] ?></span>
                                    <span class="text-sm leading-none"><?= $hb['d'] ?></span>
                                </div>
                                <?php endif; ?>
                                <div>
                                    <h4 class="font-bold text-gray-900"><?= htmlspecialchars($g['title']) ?></h4>
                                    <p class="text-gray-500 mt-0.5">
                                        <?php if (($g['execution_type'] ?? '') === 'berulang'): ?>
                                        <span class="text-[10px] font-bold bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-md mr-1">Rutin<?= !empty($g['recurring_day']) ? ' • Setiap ' . htmlspecialchars($g['recurring_day']) : '' ?></span><br>
                                        <?php endif; ?>
                                        <i class="fa-solid fa-clock mr-1 text-[10px]"></i> <?= $g['event_time'] ? substr($g['event_time'], 0, 5) . ' WIB' : '-' ?> &bull; <?= htmlspecialchars($g['location'] ?: 'Online') ?>
                                    </p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>

        <!-- TAB CONTENT 2: KATALOG KELAS -->
        <div x-show="activeTab === 'kelas'" x-cloak class="space-y-6">
            <div class="bg-white rounded-3xl p-5 sm:p-6 border border-gray-100 shadow-sm" x-data="kelasApp()">
                <h2 class="text-lg font-extrabold text-gray-900 mb-2">Katalog Kelas</h2>
                <p class="text-xs text-gray-500 mb-4">Jelajahi semua kelas yang tersedia dan mulai belajar.</p>

                <!-- Pencarian & Filter -->
                <div class="flex flex-col sm:flex-row gap-3 mb-6">
                    <div class="relative flex-1">
                        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                        <input x-model="search" @input="page = 1" type="text" placeholder="Cari kelas atau pengajar..."
                            class="w-full pl-10 pr-3 py-2.5 rounded-xl border border-gray-200 text-xs font-medium focus:outline-none focus:border-emerald-600">
                    </div>
                    <select x-model="priceFilter" @change="page = 1"
                        class="px-3 py-2.5 rounded-xl border border-gray-200 text-xs font-bold text-gray-700 bg-white focus:outline-none focus:border-emerald-600">
                        <option value="all">Semua Harga</option>
                        <option value="free">Gratis</option>
                        <option value="paid">Berbayar</option>
                    </select>
                </div>

                <template x-if="paged.length === 0">
                    <p class="text-xs text-gray-500 text-center py-8"
                       x-text="items.length === 0 ? 'Belum ada kelas tersedia saat ini.' : 'Tidak ada kelas yang cocok dengan pencarian / filter.'"></p>
                </template>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <template x-for="k in paged" :key="k.id">
                        <div class="border border-gray-200 rounded-2xl p-4 flex flex-col justify-between bg-gray-50">
                            <div>
                                <span class="text-[10px] font-bold bg-emerald-100 text-emerald-800 px-2.5 py-1 rounded-md">Tersedia</span>
                                <h3 class="font-bold text-gray-900 text-sm mt-3" x-text="k.title"></h3>
                                <p class="text-xs text-gray-500 mt-1">Pengajar: <span x-text="k.instructor_name || '-'"></span></p>
                                <template x-if="k.session_count">
                                    <p class="text-xs text-gray-500"><span x-text="k.session_count"></span> pertemuan</p>
                                </template>
                            </div>
                            <div class="mt-4 pt-3 border-t border-gray-200/60 flex items-center justify-between">
                                <span class="text-xs font-bold text-emerald-700" x-text="parseFloat(k.price) > 0 ? rupiahID(k.price) : 'Gratis'"></span>
                                <a :href="'../kelas/' + k.id" class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-3.5 py-2 rounded-xl transition">Buka Kelas</a>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Pagination -->
                <template x-if="totalPages > 1">
                    <div class="flex items-center justify-center gap-1.5 mt-6">
                        <button @click="page = Math.max(1, page - 1)" :disabled="page === 1"
                            class="w-9 h-9 rounded-xl border border-gray-200 text-xs font-bold text-gray-600 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition">
                            <i class="fa-solid fa-chevron-left"></i>
                        </button>
                        <template x-for="p in pageNumbers" :key="p">
                            <button @click="page = p" x-text="p"
                                :class="page === p ? 'bg-emerald-700 text-white border-emerald-700' : 'border-gray-200 text-gray-600 hover:bg-gray-100'"
                                class="w-9 h-9 rounded-xl border text-xs font-bold transition"></button>
                        </template>
                        <button @click="page = Math.min(totalPages, page + 1)" :disabled="page === totalPages"
                            class="w-9 h-9 rounded-xl border border-gray-200 text-xs font-bold text-gray-600 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition">
                            <i class="fa-solid fa-chevron-right"></i>
                        </button>
                    </div>
                </template>
                <p class="text-[11px] text-gray-400 text-center mt-3"
                   x-text="filtered.length > 0 ? 'Menampilkan ' + paged.length + ' dari ' + filtered.length + ' kelas' : ''"></p>
            </div>
        </div>

        <!-- TAB CONTENT 3: KOLEKSI BUKU -->
        <div x-show="activeTab === 'buku'" x-cloak class="space-y-6">
            <div class="bg-white rounded-3xl p-5 sm:p-6 border border-gray-100 shadow-sm" x-data="bukuApp()">
                <h2 class="text-lg font-extrabold text-gray-900 mb-2">Perpustakaan Digital</h2>
                <p class="text-xs text-gray-500 mb-4">Unduh atau baca langsung buku-buku terbitan dan rekomendasi dari Iqra' Society.</p>

                <!-- Pencarian & Filter -->
                <div class="flex flex-col sm:flex-row gap-3 mb-6">
                    <div class="relative flex-1">
                        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                        <input x-model="search" @input="page = 1" type="text" placeholder="Cari judul buku..."
                            class="w-full pl-10 pr-3 py-2.5 rounded-xl border border-gray-200 text-xs font-medium focus:outline-none focus:border-emerald-600">
                    </div>
                    <select x-model="priceFilter" @change="page = 1"
                        class="px-3 py-2.5 rounded-xl border border-gray-200 text-xs font-bold text-gray-700 bg-white focus:outline-none focus:border-emerald-600">
                        <option value="all">Semua Harga</option>
                        <option value="free">Gratis</option>
                        <option value="paid">Berbayar</option>
                    </select>
                </div>

                <template x-if="paged.length === 0">
                    <p class="text-xs text-gray-500 text-center py-8"
                       x-text="items.length === 0 ? 'Belum ada buku tersedia saat ini.' : 'Tidak ada buku yang cocok dengan pencarian / filter.'"></p>
                </template>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                    <template x-for="b in paged" :key="b.id">
                        <div class="border border-gray-200 rounded-2xl p-3 flex flex-col justify-between bg-gray-50">
                            <div>
                                <template x-if="b.cover_url">
                                    <img :src="b.cover_url" :alt="b.title" class="h-40 w-full rounded-xl object-cover mb-3" loading="lazy">
                                </template>
                                <template x-if="!b.cover_url">
                                    <div class="h-40 rounded-xl bg-emerald-100 mb-3 flex items-center justify-center text-emerald-800 font-bold text-xs"><i class="fa-solid fa-book text-3xl"></i></div>
                                </template>
                                <h4 class="text-xs font-bold text-gray-900" x-text="b.title"></h4>
                                <template x-if="parseFloat(b.price) > 0">
                                    <p class="text-[10px] text-emerald-700 font-bold mt-0.5" x-text="rupiahID(b.price)"></p>
                                </template>
                            </div>
                            <a href="../buku" class="mt-4 text-center bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold py-2 rounded-xl transition">Lihat Detail</a>
                        </div>
                    </template>
                </div>

                <!-- Pagination -->
                <template x-if="totalPages > 1">
                    <div class="flex items-center justify-center gap-1.5 mt-6">
                        <button @click="page = Math.max(1, page - 1)" :disabled="page === 1"
                            class="w-9 h-9 rounded-xl border border-gray-200 text-xs font-bold text-gray-600 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition">
                            <i class="fa-solid fa-chevron-left"></i>
                        </button>
                        <template x-for="p in pageNumbers" :key="p">
                            <button @click="page = p" x-text="p"
                                :class="page === p ? 'bg-emerald-700 text-white border-emerald-700' : 'border-gray-200 text-gray-600 hover:bg-gray-100'"
                                class="w-9 h-9 rounded-xl border text-xs font-bold transition"></button>
                        </template>
                        <button @click="page = Math.min(totalPages, page + 1)" :disabled="page === totalPages"
                            class="w-9 h-9 rounded-xl border border-gray-200 text-xs font-bold text-gray-600 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition">
                            <i class="fa-solid fa-chevron-right"></i>
                        </button>
                    </div>
                </template>
                <p class="text-[11px] text-gray-400 text-center mt-3"
                   x-text="filtered.length > 0 ? 'Menampilkan ' + paged.length + ' dari ' + filtered.length + ' buku' : ''"></p>
            </div>
        </div>

        <!-- TAB CONTENT 4: JADWAL KEGIATAN -->
        <div x-show="activeTab === 'kegiatan'" x-cloak class="space-y-6">
            <div class="bg-white rounded-3xl p-5 sm:p-6 border border-gray-100 shadow-sm" x-data="kegiatanApp()">
                <h2 class="text-lg font-extrabold text-gray-900 mb-2">Jadwal Agenda & Kegiatan Komunitas</h2>
                <p class="text-xs text-gray-500 mb-4">Daftar kegiatan mendatang yang bisa Anda ikuti secara online maupun offline.</p>

                <!-- Pencarian & Filter -->
                <div class="flex flex-col sm:flex-row gap-3 mb-6">
                    <div class="relative flex-1">
                        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                        <input x-model="search" @input="page = 1" type="text" placeholder="Cari kegiatan atau lokasi..."
                            class="w-full pl-10 pr-3 py-2.5 rounded-xl border border-gray-200 text-xs font-medium focus:outline-none focus:border-emerald-600">
                    </div>
                    <select x-model="catFilter" @change="page = 1"
                        class="px-3 py-2.5 rounded-xl border border-gray-200 text-xs font-bold text-gray-700 bg-white focus:outline-none focus:border-emerald-600">
                        <option value="all">Semua Kategori</option>
                        <template x-for="c in categories" :key="c">
                            <option :value="c" x-text="c"></option>
                        </template>
                    </select>
                    <select x-model="typeFilter" @change="page = 1"
                        class="px-3 py-2.5 rounded-xl border border-gray-200 text-xs font-bold text-gray-700 bg-white focus:outline-none focus:border-emerald-600">
                        <option value="all">Semua Jenis</option>
                        <option value="rutin">Rutin</option>
                        <option value="sekali">Sekali Jalan</option>
                    </select>
                </div>

                <template x-if="paged.length === 0">
                    <p class="text-xs text-gray-500 text-center py-8"
                       x-text="items.length === 0 ? 'Belum ada kegiatan mendatang.' : 'Tidak ada kegiatan yang cocok dengan pencarian / filter.'"></p>
                </template>

                <div class="space-y-3">
                    <template x-for="g in paged" :key="g.id">
                        <div class="p-4 rounded-2xl border border-gray-200 bg-gray-50 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                            <div class="flex items-center gap-4">
                                <template x-if="g.is_rutin">
                                    <div class="w-12 h-12 rounded-xl bg-emerald-800 text-white flex items-center justify-center shrink-0" title="Kegiatan rutin">
                                        <i class="fa-solid fa-repeat"></i>
                                    </div>
                                </template>
                                <template x-if="!g.is_rutin">
                                    <div class="w-12 h-12 rounded-xl bg-emerald-800 text-white flex flex-col items-center justify-center font-bold shrink-0">
                                        <span class="text-[10px] uppercase" x-text="g.mon"></span>
                                        <span class="text-base leading-none" x-text="g.day"></span>
                                    </div>
                                </template>
                                <div>
                                    <template x-if="g.is_rutin">
                                        <span class="text-[10px] font-bold bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-md"
                                              x-text="'Rutin' + (g.recurring_day ? ' • Setiap ' + g.recurring_day : '')"></span>
                                    </template>
                                    <template x-if="g.category">
                                        <span class="text-[10px] font-bold bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-md" x-text="g.category"></span>
                                    </template>
                                    <h3 class="font-bold text-gray-900 text-sm mt-1" x-text="g.title"></h3>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        <i class="fa-solid fa-location-dot mr-1"></i>
                                        <span x-text="g.location || 'Online'"></span> &bull;
                                        <span x-text="g.is_rutin ? (g.recurring_day ? 'Setiap ' + g.recurring_day : 'Jadwal rutin') : g.date_label"></span><span x-text="g.time_label ? ' • ' + g.time_label : ''"></span>
                                    </p>
                                </div>
                            </div>
                            <a href="../kegiatan" class="w-full sm:w-auto bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-4 py-2.5 rounded-xl text-center transition">Lihat Acara</a>
                        </div>
                    </template>
                </div>

                <!-- Pagination -->
                <template x-if="totalPages > 1">
                    <div class="flex items-center justify-center gap-1.5 mt-6">
                        <button @click="page = Math.max(1, page - 1)" :disabled="page === 1"
                            class="w-9 h-9 rounded-xl border border-gray-200 text-xs font-bold text-gray-600 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition">
                            <i class="fa-solid fa-chevron-left"></i>
                        </button>
                        <template x-for="p in pageNumbers" :key="p">
                            <button @click="page = p" x-text="p"
                                :class="page === p ? 'bg-emerald-700 text-white border-emerald-700' : 'border-gray-200 text-gray-600 hover:bg-gray-100'"
                                class="w-9 h-9 rounded-xl border text-xs font-bold transition"></button>
                        </template>
                        <button @click="page = Math.min(totalPages, page + 1)" :disabled="page === totalPages"
                            class="w-9 h-9 rounded-xl border border-gray-200 text-xs font-bold text-gray-600 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition">
                            <i class="fa-solid fa-chevron-right"></i>
                        </button>
                    </div>
                </template>
                <p class="text-[11px] text-gray-400 text-center mt-3"
                   x-text="filtered.length > 0 ? 'Menampilkan ' + paged.length + ' dari ' + filtered.length + ' kegiatan' : ''"></p>
            </div>
        </div>

        <!-- TAB CONTENT 5: PROFIL & PENGATURAN -->
        <div x-show="activeTab === 'profil'" x-cloak class="space-y-6">
            <div class="bg-white rounded-3xl p-5 sm:p-6 border border-gray-100 shadow-sm max-w-2xl mx-auto">
                <h2 class="text-lg font-extrabold text-gray-900 mb-2">Pengaturan Profil Member</h2>
                <p class="text-xs text-gray-500 mb-6">Perbarui informasi data diri dan kata sandi akun Anda.</p>

                <form onsubmit="event.preventDefault(); alert('Perubahan profil berhasil disimpan!');" class="space-y-4 text-xs">
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Nama Lengkap</label>
                        <input type="text" value="<?= htmlspecialchars($fullname) ?>" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-emerald-600 font-medium">
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Email</label>
                        <input type="email" value="<?= htmlspecialchars($email) ?>" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none" disabled>
                        <span class="text-[10px] text-gray-400 mt-1 block">Email tidak dapat diubah secara mandiri. Hubungi admin jika diperlukan.</span>
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Nomor WhatsApp</label>
                        <input type="text" value="<?= htmlspecialchars($no_hp) ?>" placeholder="+62 812-xxxx-xxxx" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-emerald-600 font-medium">
                    </div>
                    <div class="pt-2 border-t border-gray-100">
                        <label class="block font-bold text-gray-700 mb-1">Kata Sandi Baru (Opsional)</label>
                        <input type="password" placeholder="Kosongkan jika tidak ingin mengubah sandi" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-emerald-600 font-medium">
                    </div>
                    <button type="submit" class="w-full bg-emerald-700 hover:bg-emerald-800 text-white font-bold py-3 rounded-xl transition shadow-md">
                        Simpan Perubahan
                    </button>
                </form>
            </div>
        </div>

    </main>

    <!-- Data untuk Alpine.js (di-parse via JSON.parse agar aman) -->
    <script type="application/json" id="data-kelas"><?= $kelas_json ?></script>
    <script type="application/json" id="data-buku"><?= $buku_json ?></script>
    <script type="application/json" id="data-kegiatan"><?= $kegiatan_json ?></script>

    <!-- Alpine.js: pencarian, filter & pagination (sisi klien) -->
    <script>
        function rupiahID(n) {
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(parseFloat(n) || 0);
        }
        function kelasApp() {
            return {
                items: JSON.parse(document.getElementById('data-kelas').textContent || '[]'),
                search: '',
                priceFilter: 'all',
                perPage: 6,
                page: 1,
                get filtered() {
                    let r = this.items;
                    if (this.search) {
                        const q = this.search.toLowerCase();
                        r = r.filter(i => ((i.title || '') + ' ' + (i.instructor_name || '')).toLowerCase().includes(q));
                    }
                    if (this.priceFilter === 'free') r = r.filter(i => !(parseFloat(i.price) > 0));
                    if (this.priceFilter === 'paid') r = r.filter(i => parseFloat(i.price) > 0);
                    return r;
                },
                get totalPages() { return Math.max(1, Math.ceil(this.filtered.length / this.perPage)); },
                get paged() {
                    if (this.page > this.totalPages) this.page = this.totalPages;
                    return this.filtered.slice((this.page - 1) * this.perPage, this.page * this.perPage);
                },
                get pageNumbers() {
                    const total = this.totalPages, cur = this.page, delta = 2, pages = [];
                    for (let i = Math.max(1, cur - delta); i <= Math.min(total, cur + delta); i++) pages.push(i);
                    return pages;
                }
            };
        }
        function bukuApp() {
            return {
                items: JSON.parse(document.getElementById('data-buku').textContent || '[]'),
                search: '',
                priceFilter: 'all',
                perPage: 8,
                page: 1,
                get filtered() {
                    let r = this.items;
                    if (this.search) {
                        const q = this.search.toLowerCase();
                        r = r.filter(i => (i.title || '').toLowerCase().includes(q));
                    }
                    if (this.priceFilter === 'free') r = r.filter(i => !(parseFloat(i.price) > 0));
                    if (this.priceFilter === 'paid') r = r.filter(i => parseFloat(i.price) > 0);
                    return r;
                },
                get totalPages() { return Math.max(1, Math.ceil(this.filtered.length / this.perPage)); },
                get paged() {
                    if (this.page > this.totalPages) this.page = this.totalPages;
                    return this.filtered.slice((this.page - 1) * this.perPage, this.page * this.perPage);
                },
                get pageNumbers() {
                    const total = this.totalPages, cur = this.page, delta = 2, pages = [];
                    for (let i = Math.max(1, cur - delta); i <= Math.min(total, cur + delta); i++) pages.push(i);
                    return pages;
                }
            };
        }
        function kegiatanApp() {
            return {
                items: JSON.parse(document.getElementById('data-kegiatan').textContent || '[]'),
                search: '',
                catFilter: 'all',
                typeFilter: 'all',
                perPage: 5,
                page: 1,
                get categories() {
                    return [...new Set(this.items.map(i => i.category).filter(Boolean))].sort();
                },
                get filtered() {
                    let r = this.items;
                    if (this.search) {
                        const q = this.search.toLowerCase();
                        r = r.filter(i => ((i.title || '') + ' ' + (i.location || '')).toLowerCase().includes(q));
                    }
                    if (this.catFilter !== 'all') r = r.filter(i => i.category === this.catFilter);
                    if (this.typeFilter === 'rutin') r = r.filter(i => i.is_rutin);
                    if (this.typeFilter === 'sekali') r = r.filter(i => !i.is_rutin);
                    return r;
                },
                get totalPages() { return Math.max(1, Math.ceil(this.filtered.length / this.perPage)); },
                get paged() {
                    if (this.page > this.totalPages) this.page = this.totalPages;
                    return this.filtered.slice((this.page - 1) * this.perPage, this.page * this.perPage);
                },
                get pageNumbers() {
                    const total = this.totalPages, cur = this.page, delta = 2, pages = [];
                    for (let i = Math.max(1, cur - delta); i <= Math.min(total, cur + delta); i++) pages.push(i);
                    return pages;
                }
            };
        }
    </script>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>
