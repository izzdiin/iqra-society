<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
$is_logged_in = isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;

$username      = $_SESSION['fullname'] ?? $_SESSION['username'] ?? 'User';
$user_fullname = $username;
$user_email    = $_SESSION['email'] ?? '';

if (!defined('IQRA_APP')) {
    http_response_code(403);
    exit('Akses langsung tidak diizinkan.');
}

http_response_code(404);

$page_title = 'Profil Tidak Ditemukan';
$meta_description = 'Profil pengguna tidak ditemukan di IQRA\' Society.';

?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- SEO utama -->
    <title><?= e($page_title) ?></title>
    <meta name="description" content="<?= e($meta_description) ?>">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <meta name="theme-color" content="#065f46">
    <link rel="canonical" href="<?= e($canonical_url) ?>">

    <!-- Open Graph untuk Facebook, WhatsApp, dan platform berbagi lainnya -->
    <meta property="og:type" content="profile">
    <meta property="og:site_name" content="IQRA’ Society">
    <meta property="og:locale" content="id_ID">
    <meta property="og:title" content="<?= e($page_title) ?>">
    <meta property="og:description" content="<?= e($meta_description) ?>">
    <meta property="og:image" content="<?= e($og_image) ?>">
    <meta property="og:image:alt" content="Foto profil <?= e($display_name) ?>">
    <meta property="og:url" content="<?= e($canonical_url) ?>">
    <meta property="profile:username" content="<?= e((string) $profile['username']) ?>">

    <!-- Twitter / X -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?= e($canonical_url) ?>">
    <meta name="twitter:title" content="<?= e($page_title) ?>">
    <meta name="twitter:description" content="<?= e($meta_description) ?>">
    <meta name="twitter:image" content="<?= e($og_image) ?>">
    <meta name="twitter:image:alt" content="Foto profil <?= e($display_name) ?>">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/asset/img/favicon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/asset/img/favicon.png">

    <!-- CSS & Fonts -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Zilla+Slab:wght@600;700&display=swap" rel="stylesheet">

    <!-- Structured data: halaman profil dan individu yang ditampilkan -->
    <script type="application/ld+json"><?= $person_schema_json ?: '{}' ?></script>

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
        
        .profile-site-header {
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            background: rgba(255, 255, 255, 0.92);
            border-bottom: 1px solid #e5e7eb;
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
        
        .scrollbar-none {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        
        .scrollbar-none::-webkit-scrollbar {
            display: none;
        }
        
        /* Mobile builder */
        @media (max-width: 639px) {
            #builder-panel {
                max-height: 78vh;
                max-height: 78dvh;
            }
        
            #builder-links-view #links-manager {
                scrollbar-width: none;
                -ms-overflow-style: none;
            }
        
            #builder-links-view #links-manager::-webkit-scrollbar {
                display: none;
            }
        }        
    </style>
</head>
<body class="min-h-screen flex flex-col bg-white text-gray-800 antialiased w-full">

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
            <a href="/" class="flex items-center gap-3 shrink-0" aria-label="Beranda Iqra Society">
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
                <a href="/tentang-kami" class="nav-link relative inline-block text-sm font-bold text-gray-700 hover:text-emerald-700 transition py-1">Tentang</a>
                <a href="/#kegiatan" class="nav-link relative inline-block text-sm font-bold text-gray-700 hover:text-emerald-700 transition py-1">Kegiatan</a>
                <a href="/#buku" class="nav-link relative inline-block text-sm font-bold text-gray-700 hover:text-emerald-700 transition py-1">Buku</a>
                <a href="/#kelas" class="nav-link relative inline-block text-sm font-bold text-gray-700 hover:text-emerald-700 transition py-1">Kelas Online</a>
                <a href="/#donasi" class="nav-link relative inline-block text-sm font-bold text-gray-700 hover:text-emerald-700 transition py-1">Donasi</a>
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
                            
                            <!-- Header Info User -->
                            <div class="px-4 py-2 bg-emerald-800 mx-2 mt-1 mb-2 rounded-xl">
                                <div class="flex flex-col truncate">
                                    <span class="text-xs font-bold text-white truncate"><?= htmlspecialchars($_SESSION['username'] ?? 'Pengguna') ?></span>
                                    <span class="text-[10px] text-lime-400 font-semibold uppercase tracking-wider truncate"><?= htmlspecialchars($_SESSION['role'] ?? 'Guest') ?></span>
                                </div>
                            </div>
                            
                            <hr class="border-gray-50 mb-1">

                            <!-- Menu Links -->
                            <div class="py-1">
                                <a href="<?= $dashboardLink ?>" class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-gray-700 hover:bg-emerald-50 hover:text-emerald-600 transition">
                                    <i class="fa-solid fa-gauge w-4 text-center text-gray-400"></i>
                                    Dashboard Akun
                                </a>
                                
                                <a href="/logout" class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-red-600 hover:bg-red-50 hover:text-red-700 transition">
                                    <i class="fa-solid fa-right-from-bracket w-4 text-center text-red-400"></i>
                                    Keluar / Logout
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- TAMPILAN DESKTOP: USER BELUM LOGIN -->
                    <a href="/login" class="border border-emerald-800 text-emerald-800 hover:bg-emerald-50 font-bold text-xs px-4 py-2.5 rounded-xl transition">
                        Masuk
                    </a>
                    <a href="/register" class="bg-emerald-800 hover:bg-emerald-900 text-white font-bold text-xs px-5 py-2.5 rounded-xl shadow-sm transition">
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
        
        <div class="px-4 pt-3 pb-6 space-y-1">
            <a href="/" class="mobile-link block px-4 py-2 rounded-xl font-bold text-emerald-800 bg-emerald-50">Beranda</a>
            <a href="/tentang-kami" class="mobile-link block px-4 py-2 rounded-xl font-bold text-gray-700 hover:text-emerald-800 hover:bg-emerald-50/50 transition">Tentang</a>
            <a href="/#kegiatan" class="mobile-link block px-4 py-2 rounded-xl font-bold text-gray-700 hover:text-emerald-800 hover:bg-emerald-50/50 transition">Kegiatan</a>
            <a href="/#buku" class="mobile-link block px-4 py-2 rounded-xl font-bold text-gray-700 hover:text-emerald-800 hover:bg-emerald-50/50 transition">Buku</a>
            <a href="/#kelas" class="mobile-link block px-4 py-2 rounded-xl font-bold text-gray-700 hover:text-emerald-800 hover:bg-emerald-50/50 transition">Kelas Online</a>

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
                    
                    <div class="grid grid-cols-2 gap-2">
                        <a href="<?= $dashboardLink ?>" class="mobile-link border border-emerald-200 bg-emerald-50 text-emerald-800 font-bold text-xs px-2 py-2.5 rounded-xl transition flex items-center justify-center gap-1.5 shrink-0">
                            <i class="fa-solid fa-gauge" aria-hidden="true"></i>
                            <span class="truncate">Dashboard</span>
                        </a>
                        
                        <a href="/logout" class="mobile-link bg-red-600 hover:bg-red-700 text-white font-bold text-xs px-2 py-2.5 rounded-xl transition flex items-center justify-center gap-1.5 shrink-0">
                            <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                            <span>Logout</span>
                        </a>
                    </div>

                <?php else: ?>
                    <!-- TAMPILAN MOBILE: USER BELUM LOGIN -->
                    <div class="grid grid-cols-2 gap-2">
                        <a href="/login" class="mobile-link text-center border border-emerald-800 text-emerald-800 hover:bg-emerald-50 font-bold text-xs px-4 py-2.5 rounded-xl transition">
                            Masuk
                        </a>
                        <a href="/register" class="mobile-link text-center bg-emerald-800 hover:bg-emerald-900 text-white font-bold text-xs px-4 py-2.5 rounded-xl shadow-md transition">
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
    const menuBtn = document.getElementById('menu-btn');
    const mobileMenu = document.getElementById('mobile-menu');
    const menuIcon = document.getElementById('menu-icon');

    if (menuBtn && mobileMenu && menuIcon) {
        menuBtn.addEventListener('click', function() {
            const isHidden = mobileMenu.classList.contains('hidden');
            if (isHidden) {
                mobileMenu.classList.remove('hidden');
                menuIcon.classList.remove('fa-bars');
                menuIcon.classList.add('fa-xmark');
                menuBtn.setAttribute('aria-expanded', 'true');
            } else {
                mobileMenu.classList.add('hidden');
                menuIcon.classList.remove('fa-xmark');
                menuIcon.classList.add('fa-bars');
                menuBtn.setAttribute('aria-expanded', 'false');
            }
        });
    }
});
</script>   
<main class="min-h-[70vh] flex items-center justify-center px-4 py-16 bg-gray-50">
    <div class="w-full max-w-md text-center">

        <div class="w-20 h-20 mx-auto mb-6 rounded-3xl bg-emerald-50 flex items-center justify-center">
            <i class="fa-solid fa-user-slash text-3xl text-emerald-700"></i>
        </div>

        <h1 class="text-2xl font-extrabold text-gray-900">
            Profil Tidak Ditemukan
        </h1>

        <p class="text-sm text-gray-500 mt-3 leading-relaxed">
            <?php if (!empty($profile_username)): ?>
                Profil dengan username <strong>@<?= e($profile_username) ?></strong> tidak ditemukan atau tidak tersedia.
            <?php else: ?>
                Username yang Anda cari tidak valid atau profil tersebut tidak tersedia.
            <?php endif; ?>
        </p>

        <a
            href="/"
            class="inline-flex items-center gap-2 mt-6 bg-emerald-800 hover:bg-emerald-900 text-white font-bold text-xs px-5 py-3 rounded-xl transition shadow-sm"
        >
            <i class="fa-solid fa-house"></i>
            Kembali ke Beranda
        </a>

    </div>
</main>

<footer class="mt-2 w-full bg-[#0d4035] text-emerald-50" aria-label="Footer IQRA’ Society">
    <div class="relative h-8 overflow-hidden leading-none" aria-hidden="true">
        <svg viewBox="0 0 500 80" preserveAspectRatio="none" class="h-8 w-full text-slate-50/10">
            <path d="M0,30 C150,80 350,-20 500,40 L500,0 L0,0 Z" fill="currentColor"></path>
        </svg>
    </div>
    <div class="mx-auto max-w-7xl px-4 pb-6 pt-2 text-center sm:px-6 lg:px-8">
        <p class="mt-2 pt-4 text-[11px] text-emerald-100/70">© <?= date('Y') ?> IQRA’ Society</p>
    </div>
</footer>
</body>
</html>