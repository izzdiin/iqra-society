<?php
require_once __DIR__ . '/auth_middleware.php';

$is_logged_in = isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;

$username      = $_SESSION['fullname'] ?? $_SESSION['username'] ?? 'User';
$user_fullname = $username;
$user_email    = $_SESSION['email'] ?? '';

$query_quote = "SELECT * FROM section_quotes WHERE is_active = 1 ORDER BY id DESC LIMIT 1";
$res_quote   = $conn->query($query_quote);

$quote_text   = "Sebaik-baik manusia adalah yang paling bermanfaat bagi manusia lainnya.";
$quote_source = "HR. Ahmad";

if ($res_quote && $res_quote->num_rows > 0) {
    $row_quote    = $res_quote->fetch_assoc();
    $quote_text   = $row_quote['quote_text'];
    $quote_source = $row_quote['source'];
}

$about_content = "IQRA' Society adalah komunitas pendidikan Islam yang berfokus pada pembelajaran, diskusi, literasi, dan pengembangan masyarakat melalui ilmu yang bermanfaat.";
$stmt_about = $conn->prepare("SELECT content FROM section_about LIMIT 1");
if ($stmt_about) {
    $stmt_about->execute();
    $res_about = $stmt_about->get_result();
    if ($row_about = $res_about->fetch_assoc()) {
        if (!empty(trim($row_about['content']))) {
            $plain_text = strip_tags($row_about['content']);
            
            $clean_text = trim(preg_replace('/\s+/', ' ', $plain_text));
            
            if (mb_strlen($clean_text) > 250) {
                $about_content = mb_substr($clean_text, 0, 250) . '...';
            } else {
                $about_content = $clean_text;
            }
        }
    }
    $stmt_about->close();
}

$query_buku = "SELECT * FROM section_buku WHERE status = 'approved' ORDER BY id DESC LIMIT 8";
$result_buku = mysqli_query($conn, $query_buku);

$query_kelas = "SELECT k.*, u.fullname AS instructor_name 
                FROM section_kelas k 
                LEFT JOIN users u ON k.instructor_id = u.id 
                WHERE k.status = 'approved' 
                ORDER BY k.id DESC LIMIT 8";
$result_kelas = mysqli_query($conn, $query_kelas);

$query_pengurus = "SELECT id, username, fullname, spesialisasi, avatar FROM users ORDER BY id ASC LIMIT 8";
$result_pengurus = mysqli_query($conn, $query_pengurus);

$query_kegiatan = "SELECT id, title, slug, event_date, location, category, image, created_at 
                   FROM section_kegiatan 
                   WHERE status = 'approved'
                   ORDER BY event_date DESC 
                   LIMIT 5";
$result_kegiatan = mysqli_query($conn, $query_kegiatan);

$query_program = $conn->query("SELECT id, title, description FROM section_program WHERE is_active = 1 ORDER BY id ASC");

function get_program_icon(string $title): string {
    $t = strtolower($title);
    return match (true) {
        str_contains($t, 'pengajian') => 'fa-book-open-reader',
        str_contains($t, 'diskusi')    => 'fa-comments',
        str_contains($t, 'komunitas') => 'fa-people-group',
        str_contains($t, 'kelas')      => 'fa-display',
        str_contains($t, 'tematik')    => 'fa-file-pen',
        str_contains($t, 'digital')    => 'fa-globe',
        default                        => 'fa-layer-group',
    };
}

$midtrans_client_key = '';
$is_production = true; // Ubah ke true jika sudah mode Production

$stmt_gt = $conn->prepare("SELECT key_name, key_value FROM gateway_settings WHERE key_name IN ('midtrans_client_key', 'midtrans_server_key')");
if ($stmt_gt) {
    $stmt_gt->execute();
    $res_gt = $stmt_gt->get_result();
    while ($row = $res_gt->fetch_assoc()) {
        if ($row['key_name'] === 'midtrans_client_key') {
            $midtrans_client_key = $row['key_value'];
        }
    }
    $stmt_gt->close();
}

$snap_url = $is_production 
    ? "https://app.midtrans.com/snap/snap.js" 
    : "https://app.sandbox.midtrans.com/snap/snap.js";

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

    <!-- Primary Meta Tags Dinamis -->
    <title><?= htmlspecialchars($page_title ?? "IQRA' Society", ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="title" content="<?php echo $page_title; ?>">
    <meta name="description" content="<?= htmlspecialchars($meta_description ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <?php if (!empty($meta_keywords)): ?>
    <meta name="keywords" content="<?= htmlspecialchars($meta_keywords, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?php echo htmlspecialchars($current_url); ?>">

    <!-- Open Graph / Facebook / WhatsApp -->
    <meta property="og:type" content="profile">
    <meta property="og:title" content="<?= htmlspecialchars($page_title ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($meta_description ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image" content="<?= htmlspecialchars($og_image ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:url" content="<?= htmlspecialchars($og_url ?? '', ENT_QUOTES, 'UTF-8') ?>">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?php echo htmlspecialchars($current_url); ?>">
    <meta name="twitter:title" content="<?= htmlspecialchars($page_title ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($meta_description ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($og_image ?? '', ENT_QUOTES, 'UTF-8') ?>">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/asset/img/favicon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/asset/img/favicon.png">

    <!-- CSS & Fonts -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Zilla+Slab:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />

    <!-- Schema.org JSON-LD -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "EducationalOrganization",
      "name": "IQRA Society",
      "url": "<?php echo htmlspecialchars($site_url); ?>",
      "logo": "<?php echo htmlspecialchars($site_url); ?>/asset/img/logo_kitab.png",
      "description": "<?php echo htmlspecialchars($page_description); ?>"
    }
    </script>

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
    <?php if (!empty($midtrans_client_key)): ?>
    <script src="<?= $snap_url ?>" data-client-key="<?= htmlspecialchars($midtrans_client_key) ?>"></script>
    <?php endif; ?>
    <style>
        [x-cloak] { 
            display: none !important; 
        }

        html,
        body {
            width: 100%;
            max-width: 100%;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f9fafb;
            color: #1f2937;
            overflow-x: clip;
        }

        .site-header {
            position: sticky;
            top: 0;
            z-index: 1000;
            width: 100%;
        }

        .swiper,
        .swiper-wrapper,
        .swiper-slide {
            min-width: 0;
            box-sizing: border-box;
        }

        .swiper {
            width: 100%;
            max-width: 100%;
        }

        .swiper-slide > * {
            min-width: 0;
            max-width: 100%;
        }

        img,
        svg,
        video,
        canvas {
            max-width: 100%;
        }
        .swiper-pagination-bullet-active {
            background-color: #065f46 !important;
            width: 24px !important;
            border-radius: 999px !important;
        }
        .nav-link.active {
            color: #047857 !important;
            font-weight: 800 !important;
        }
        .nav-link.active::after {
            content: '' !important;
            position: absolute !important;
            bottom: -6px !important;
            left: 0 !important;
            width: 100% !important;
            height: 3px !important;
            background-color: #047857 !important;
            border-radius: 9999px !important;
            display: block !important;
        }
    </style>
</head>
<body class="bg-white text-gray-800 antialiased w-full">

<?php
    $role = strtolower($_SESSION['role'] ?? '');
    $dashboardLink = '#'; // Fallback link
    
    if (in_array($role, ['super_administrator', 'administrator'])) {
        $dashboardLink = '/admin';
    } elseif ($role === 'pengajar') {
        $dashboardLink = '/pengajar';
    } elseif ($role === 'pembelajar') {
        $dashboardLink = '/pembelajar';
    } elseif ($role === 'donatur') {
        $dashboardLink = '/donatur';
    }
?>

<!-- HEADER / NAVBAR -->
<header class="site-header bg-white/90 backdrop-blur-md transition-all border-b border-gray-100/50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-20">
            
            <!-- Logo dengan Alt Text & Dimensi -->
            <a href="/" class="flex items-center gap-3" aria-label="Beranda Iqra Society">
                <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-xl shadow-sm">
                    <img src="/asset/img/logo_kitab.png" alt="Logo Iqra Society" width="40" height="40" loading="eager">
                </div>
                <div class="flex flex-col font-['Zilla_Slab'] leading-tight">
                    <span class="text-lg font-bold text-emerald-900 tracking-wide uppercase leading-none">IQRA'</span>
                    <span class="text-base font-semibold text-lime-500 tracking-normal leading-none mt-0.5">Society</span>
                </div>
            </a>

            <!-- Navigasi Menu Desktop -->
            <nav class="hidden lg:flex items-center gap-7" id="desktop-nav" aria-label="Navigasi Utama">
                <a href="/" class="nav-link relative inline-block text-sm font-bold text-gray-700 hover:text-emerald-700 transition py-1 active">Beranda</a>
                <a href="tentang-kami" class="nav-link relative inline-block text-sm font-bold text-gray-700 hover:text-emerald-700 transition py-1">Tentang</a>
                <a href="#program" class="nav-link relative inline-block text-sm font-bold text-gray-700 hover:text-emerald-700 transition py-1">Program</a>
                <a href="#kegiatan" class="nav-link relative inline-block text-sm font-bold text-gray-700 hover:text-emerald-700 transition py-1">Kegiatan</a>
                <a href="#buku" class="nav-link relative inline-block text-sm font-bold text-gray-700 hover:text-emerald-700 transition py-1">Buku</a>
                <a href="#kelas" class="nav-link relative inline-block text-sm font-bold text-gray-700 hover:text-emerald-700 transition py-1">Kelas Online</a>
                <a href="#donasi" class="nav-link relative inline-block text-sm font-bold text-gray-700 hover:text-emerald-700 transition py-1">Donasi</a>
                <a href="#pengurus" class="nav-link relative inline-block text-sm font-bold text-gray-700 hover:text-emerald-700 transition py-1">Pengurus</a>
                <a href="#kontak" class="nav-link relative inline-block text-sm font-bold text-gray-700 hover:text-emerald-700 transition py-1">Kontak</a>
            </nav>

            <div class="hidden lg:flex items-center gap-3">
                <?php if ($is_logged_in): ?>
                    <!-- TAMPILAN DESKTOP: USER SUDAH LOGIN (ALPINE.JS DROPDOWN) -->
                    <div class="relative" x-data="{ userDropdown: false }">
                        
                        <!-- Tombol Avatar -->
                        <button @click="userDropdown = !userDropdown" class="flex items-center focus:outline-none transition-transform hover:scale-105" aria-expanded="false" aria-haspopup="true">
                            <?php if (!empty($_SESSION['avatar']) && file_exists(__DIR__ . '/../uploads/avatars/' . $_SESSION['avatar'])): ?>
                                <img src="../uploads/avatars/<?= htmlspecialchars($_SESSION['avatar']) ?>" alt="Avatar" class="w-10 h-10 rounded-full object-cover border border-emerald-600 shrink-0">
                            <?php else: ?>
                                <div class="w-10 h-10 rounded-full bg-emerald-100 border border-emerald-600 flex items-center justify-center text-emerald-700 shrink-0">
                                    <i class="fa-solid fa-user"></i>
                                </div>
                            <?php endif; ?>
                        </button>

                        <!-- Isi Dropdown (Style Clean) -->
                        <div x-show="userDropdown" 
                             @click.outside="userDropdown = false"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute right-0 mt-2 w-52 bg-white rounded-2xl shadow-xl border border-gray-100 py-2 z-50"
                             style="display: none;">
                            
                            <!-- Header Info User (Sesuai Note 2 Anda, dibungkus warna background gelap agar text-white terlihat) -->
                            <div class="px-4 py-2 bg-emerald-800 mx-2 mt-1 mb-2 rounded-xl">
                                <div class="flex flex-col truncate">
                                    <span class="text-xs font-bold text-white truncate"><?= htmlspecialchars($_SESSION['username'] ?? 'Pengguna') ?></span>
                                    <span class="text-[10px] text-lime-400 font-semibold uppercase tracking-wider truncate"><?= htmlspecialchars($_SESSION['role'] ?? 'Guest') ?></span>
                                </div>
                            </div>
                            
                            <hr class="border-gray-50 mb-1">

                            <!-- Menu Links -->
                            <div class="py-1">
                                <!-- Link Panel Dinamis berdasarkan logic PHP -->
                                <a href="<?= $dashboardLink ?>" class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-gray-700 hover:bg-emerald-50 hover:text-emerald-600 transition">
                                    <i class="fa-solid fa-gauge w-4 text-center text-gray-400"></i>
                                    Dashboard Akun
                                </a>
                                
                                <a href="logout" class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-red-600 hover:bg-red-50 hover:text-red-700 transition">
                                    <i class="fa-solid fa-right-from-bracket w-4 text-center text-red-400"></i>
                                    Keluar / Logout
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- TAMPILAN DESKTOP: USER BELUM LOGIN -->
                    <a href="login" class="border border-emerald-800 text-emerald-800 hover:bg-emerald-50 font-bold text-xs px-4 py-2.5 rounded-xl transition">
                        Masuk
                    </a>
                    <a href="register" class="bg-emerald-800 hover:bg-emerald-900 text-white font-bold text-xs px-5 py-2.5 rounded-xl shadow-sm transition">
                        Daftar
                    </a>
                <?php endif; ?>
            </div>

            <!-- Mobile Menu Button -->
            <button id="menu-btn" class="lg:hidden text-gray-700 text-2xl p-2 focus:outline-none" aria-label="Buka Menu Navigasi" aria-expanded="false" aria-controls="mobile-menu">
                <i id="menu-icon" class="fa-solid fa-bars" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <!-- MOBILE NAVIGATION MENU OVERLAY -->
    <nav id="mobile-menu" class="hidden lg:hidden absolute left-0 w-full bg-white border-b border-gray-100 shadow-xl transition-all duration-300 ease-in-out max-h-[calc(100vh-80px)] overflow-y-auto z-50" aria-label="Navigasi Seluler">
        
        <!-- Jarak antar menu diperkecil dari space-y-2 menjadi space-y-1 -->
        <div class="px-4 pt-3 pb-6 space-y-1">
            <a href="/" class="mobile-link block px-4 py-2 rounded-xl font-bold text-emerald-800 bg-emerald-50">Beranda</a>
            <a href="tentang-kami" class="mobile-link block px-4 py-2 rounded-xl font-bold text-gray-700 hover:text-emerald-800 hover:bg-emerald-50/50 transition">Tentang</a>
            <a href="#program" class="mobile-link block px-4 py-2 rounded-xl font-bold text-gray-700 hover:text-emerald-800 hover:bg-emerald-50/50 transition">Program</a>
            <a href="#kegiatan" class="mobile-link block px-4 py-2 rounded-xl font-bold text-gray-700 hover:text-emerald-800 hover:bg-emerald-50/50 transition">Kegiatan</a>
            <a href="#buku" class="mobile-link block px-4 py-2 rounded-xl font-bold text-gray-700 hover:text-emerald-800 hover:bg-emerald-50/50 transition">Buku</a>
            <a href="#kelas" class="mobile-link block px-4 py-2 rounded-xl font-bold text-gray-700 hover:text-emerald-800 hover:bg-emerald-50/50 transition">Kelas Online</a>
            <a href="#donasi" class="mobile-link block px-4 py-2 rounded-xl font-bold text-gray-700 hover:text-emerald-800 hover:bg-emerald-50/50 transition">Donasi</a>
            <a href="#pengurus" class="mobile-link block px-4 py-2 rounded-xl font-bold text-gray-700 hover:text-emerald-800 hover:bg-emerald-50/50 transition">Pengurus</a>
            <a href="#kontak" class="mobile-link block px-4 py-2 rounded-xl font-bold text-gray-700 hover:text-emerald-800 hover:bg-emerald-50/50 transition">Kontak</a>
            
            <div class="pt-3 border-t border-gray-100 space-y-2 mt-2">
                <?php if ($is_logged_in): ?>
                    <!-- TAMPILAN MOBILE: USER SUDAH LOGIN -->
                    <div class="flex items-center gap-3 bg-emerald-800 px-4 py-3 rounded-xl mb-3 shrink-0">
                        <?php if (!empty($_SESSION['avatar']) && file_exists(__DIR__ . '/../uploads/avatars/' . $_SESSION['avatar'])): ?>
                            <img src="../uploads/avatars/<?= htmlspecialchars($_SESSION['avatar']) ?>" alt="Avatar" class="w-10 h-10 rounded-full object-cover border border-emerald-400 shrink-0">
                        <?php else: ?>
                            <div class="w-10 h-10 rounded-full bg-emerald-700 border border-emerald-400 flex items-center justify-center text-white shrink-0">
                                <i class="fa-solid fa-user"></i>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Nama & Role User -->
                        <div class="flex flex-col truncate">
                            <span class="text-xs font-bold text-white truncate"><?= htmlspecialchars($_SESSION['username'] ?? 'Pengguna') ?></span>
                            <span class="text-[10px] text-lime-400 font-semibold uppercase tracking-wider truncate"><?= htmlspecialchars($_SESSION['role'] ?? 'Guest') ?></span>
                        </div>
                    </div>
                    
                    <!-- PERUBAHAN: Tombol Dibuat Sejajar (Grid 2 Kolom) -->
                    <div class="grid grid-cols-2 gap-2">
                        <a href="<?= $dashboardLink ?>" class="mobile-link border border-emerald-200 bg-emerald-50 text-emerald-800 font-bold text-xs px-2 py-2.5 rounded-xl transition flex items-center justify-center gap-1.5 shrink-0">
                            <i class="fa-solid fa-gauge" aria-hidden="true"></i>
                            <span class="truncate">Dashboard</span>
                        </a>
                        
                        <a href="logout" class="mobile-link bg-red-600 hover:bg-red-700 text-white font-bold text-xs px-2 py-2.5 rounded-xl transition flex items-center justify-center gap-1.5 shrink-0">
                            <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                            <span>Logout</span>
                        </a>
                    </div>

                <?php else: ?>
                    <!-- TAMPILAN MOBILE: USER BELUM LOGIN -->
                    <div class="grid grid-cols-2 gap-2">
                        <a href="login" class="mobile-link text-center border border-emerald-800 text-emerald-800 hover:bg-emerald-50 font-bold text-xs px-4 py-2.5 rounded-xl transition">
                            Masuk
                        </a>
                        <a href="register" class="mobile-link text-center bg-emerald-800 hover:bg-emerald-900 text-white font-bold text-xs px-4 py-2.5 rounded-xl shadow-md transition">
                            Daftar
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>
</header>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const userBtn = document.getElementById('user-dropdown-btn');
    const userMenu = document.getElementById('user-dropdown-menu');

    if (userBtn && userMenu) {
        userBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            userMenu.classList.toggle('hidden');
            let expanded = userBtn.getAttribute('aria-expanded') === 'true' || false;
            userBtn.setAttribute('aria-expanded', !expanded);
        });

        document.addEventListener('click', function(e) {
            if (!userBtn.contains(e.target) && !userMenu.contains(e.target)) {
                userMenu.classList.add('hidden');
                userBtn.setAttribute('aria-expanded', 'false');
            }
        });
    }
});
</script>