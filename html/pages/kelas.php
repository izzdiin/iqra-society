<?php
require_once __DIR__ . '/../includes/auth_middleware.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_logged_in = isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;
$username     = $_SESSION['fullname'] ?? $_SESSION['username'] ?? 'User';

$kelas_list = [];
$stmt = $conn->prepare("SELECT k.*, u.fullname AS instructor_name, u.avatar AS instructor_avatar 
                        FROM section_kelas k 
                        LEFT JOIN users u ON k.instructor_id = u.id 
                        WHERE k.status = 'approved' 
                        ORDER BY k.id DESC");

if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $kelas_list[] = $row;
    }
    $stmt->close();
}

$kelas_json = json_encode($kelas_list, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

$post_title = "Katalog Kelas Online";
$post_description = "Jelajahi berbagai program kelas online dan edukasi Islam terbaik bersama IQRA Society. Belajar langsung dari pengajar berkompeten di bidangnya.";
$post_keywords = "Daftar Kelas, Kelas Online, Belajar Islam, IQRA Society, Edukasi Islam";

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$current_url = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Primary Meta Tags -->
    <title><?= $post_title ?> - IQRA Society</title>
    <meta name="title" content="<?= $post_title ?> - IQRA Society">
    <meta name="description" content="<?= $post_description ?>">
    <meta name="keywords" content="<?= $post_keywords ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= htmlspecialchars($current_url) ?>">

    <!-- Open Graph / Facebook / WhatsApp -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($current_url) ?>">
    <meta property="og:title" content="<?= $post_title ?> - IQRA Society">
    <meta property="og:description" content="<?= $post_description ?>">
    <meta property="og:image" content="<?= $protocol . $_SERVER['HTTP_HOST'] ?>/asset/img/og-image.jpg">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?= htmlspecialchars($current_url) ?>">
    <meta property="twitter:title" content="<?= $post_title ?> - IQRA Society">
    <meta property="twitter:description" content="<?= $post_description ?>">
    <meta property="twitter:image" content="<?= $protocol . $_SERVER['HTTP_HOST'] ?>/asset/img/og-image.jpg">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/asset/img/favicon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/asset/img/favicon.png">

    <!-- CSS & Fonts -->
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
                        zilla: ['Zilla Slab', 'serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f9fafb; color: #1f2937; overflow-x: hidden; }
        .site-header { position: sticky; top: 0; z-index: 1000; width: 100%; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased w-full flex flex-col min-h-screen">

<!-- ================= START HEADER ================= -->
<header class="site-header bg-white/90 backdrop-blur-md transition-all border-b border-gray-100/50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-20">
            
            <a href="/" class="flex items-center gap-3" aria-label="Beranda Iqra Society">
                <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-xl shadow-sm">
                    <img src="/asset/img/logo_kitab.png" alt="Logo Iqra Society" width="40" height="40" loading="eager">
                </div>
                <div class="flex flex-col font-zilla leading-tight">
                    <span class="text-lg font-bold text-emerald-900 tracking-wide uppercase leading-none">IQRA'</span>
                    <span class="text-base font-semibold text-lime-500 tracking-normal leading-none mt-0.5">Society</span>
                </div>
            </a>

            <nav class="hidden lg:flex items-center gap-7" id="desktop-nav" aria-label="Navigasi Utama">
                <a href="/" class="nav-link relative inline-block text-sm font-bold text-gray-700 hover:text-emerald-700 transition py-1">Beranda</a>
                <a href="/tentang-kami" class="nav-link relative inline-block text-sm font-bold text-gray-700 hover:text-emerald-700 transition py-1">Tentang</a>
                <a href="/#program" class="nav-link relative inline-block text-sm font-bold text-gray-700 hover:text-emerald-700 transition py-1">Program</a>
                <a href="/kegiatan" class="nav-link relative inline-block text-sm font-bold text-gray-700 hover:text-emerald-700 transition py-1">Kegiatan</a>
                <a href="/buku" class="nav-link relative inline-block text-sm font-bold text-gray-700 hover:text-emerald-700 transition py-1">Buku</a>
                <a href="/kelas" class="nav-link relative inline-block text-sm font-bold text-emerald-700 transition py-1">Kelas Online</a>
                <a href="/#donasi" class="nav-link relative inline-block text-sm font-bold text-gray-700 hover:text-emerald-700 transition py-1">Donasi</a>
            </nav>

            <div class="hidden lg:flex items-center gap-3">
                <?php if ($is_logged_in): ?>
                    <div class="flex items-center gap-2 bg-emerald-50 border border-emerald-200 text-emerald-900 font-bold text-xs px-4 py-2.5 rounded-xl">
                        <i class="fa-solid fa-user-circle text-base text-emerald-700" aria-hidden="true"></i>
                        <span>Halo, <?php echo htmlspecialchars($username); ?></span>
                    </div>
                    <a href="/logout.php" class="bg-red-600 hover:bg-red-700 text-white font-bold text-xs px-4 py-3 rounded-xl shadow-sm transition flex items-center gap-1.5" title="Logout">
                        <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                    </a>
                <?php else: ?>
                    <a href="/login.php" class="border border-emerald-800 text-emerald-800 hover:bg-emerald-50 font-bold text-xs px-4 py-2.5 rounded-xl transition">
                        Masuk
                    </a>
                    <a href="/register.php" class="bg-emerald-800 hover:bg-emerald-900 text-white font-bold text-xs px-5 py-2.5 rounded-xl shadow-sm transition">
                        Daftar
                    </a>
                <?php endif; ?>
            </div>

            <button id="menu-btn" class="lg:hidden text-gray-700 text-2xl p-2 focus:outline-none" aria-label="Buka Menu Navigasi" aria-expanded="false" aria-controls="mobile-menu">
                <i id="menu-icon" class="fa-solid fa-bars" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <!-- MOBILE MENU -->
    <nav id="mobile-menu" class="hidden lg:hidden bg-white border-b border-gray-100 shadow-xl transition-all duration-300 ease-in-out absolute w-full left-0">
        <div class="px-4 pt-3 pb-6 space-y-2">
            <a href="/" class="mobile-link block px-4 py-2.5 rounded-xl font-bold text-gray-700 hover:bg-emerald-50 transition">Beranda</a>
            <a href="/kelas" class="mobile-link block px-4 py-2.5 rounded-xl font-bold text-emerald-700 bg-emerald-50 transition">Kelas Online</a>
            <a href="/tentang-kami" class="mobile-link block px-4 py-2.5 rounded-xl font-bold text-gray-700 hover:bg-emerald-50 transition">Tentang</a>
            <a href="/#donasi" class="mobile-link block px-4 py-2.5 rounded-xl font-bold text-gray-700 hover:bg-emerald-50 transition">Donasi</a>
            <div class="pt-3 border-t border-gray-100 space-y-2">
                <?php if ($is_logged_in): ?>
                    <div class="flex items-center gap-2 px-4 py-2.5 text-emerald-900 font-bold text-xs bg-emerald-50 rounded-xl">
                        <i class="fa-solid fa-user-circle text-base text-emerald-700" aria-hidden="true"></i>
                        <span>Halo, <?php echo htmlspecialchars($username); ?></span>
                    </div>
                    <a href="/logout.php" class="mobile-link w-full bg-red-600 text-white font-bold text-xs px-5 py-3 rounded-xl flex items-center justify-center gap-2">
                        <i class="fa-solid fa-right-from-bracket"></i> Logout
                    </a>
                <?php else: ?>
                    <div class="grid grid-cols-2 gap-2">
                        <a href="/login.php" class="mobile-link text-center border border-emerald-800 text-emerald-800 font-bold text-xs px-4 py-3 rounded-xl">Masuk</a>
                        <a href="/register.php" class="mobile-link text-center bg-emerald-800 text-white font-bold text-xs px-4 py-3 rounded-xl">Daftar</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>
</header>
<!-- ================= END HEADER ================= -->

<!-- ================= START MAIN CONTENT (ALPINE JS) ================= -->
<main x-data="kelasPagination()" class="flex-grow max-w-7xl mx-auto w-full py-8 md:py-16 px-4 sm:px-6 lg:px-8" x-cloak>
    
    <!-- Bagian Header Halaman -->
    <div class="text-center max-w-3xl mx-auto mb-12">
        <span class="inline-flex items-center gap-1.5 px-3 py-1 mb-4 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 uppercase tracking-widest shadow-sm">
            <i class="fa-solid fa-graduation-cap"></i> Program Edukasi
        </span>
        <h1 class="text-3xl md:text-5xl font-extrabold font-zilla text-gray-900 leading-tight mb-4">
            Jelajahi Kelas Online
        </h1>
        <p class="text-sm md:text-base text-gray-500 leading-relaxed">
            Tingkatkan pemahaman literasi dan ilmu keislaman Anda melalui program kelas intensif yang dibimbing langsung oleh tenaga pengajar profesional dan berpengalaman.
        </p>
    </div>

    <!-- State Kosong jika belum ada kelas -->
    <template x-if="allKelas.length === 0">
        <div class="text-center py-20 bg-white rounded-3xl border border-gray-100 shadow-sm">
            <div class="w-24 h-24 bg-gray-50 text-gray-300 rounded-full flex items-center justify-center mx-auto mb-6 text-4xl">
                <i class="fa-solid fa-folder-open"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900 mb-2 font-zilla">Belum Ada Kelas Tersedia</h3>
            <p class="text-sm text-gray-500">Saat ini belum ada program kelas yang dibuka. Silakan kembali lagi nanti.</p>
        </div>
    </template>

    <!-- State jika kelas tersedia -->
    <template x-if="allKelas.length > 0">
        <div>
            <!-- Grid Daftar Kelas (Di-paginate oleh Alpine) -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8 min-h-[400px] content-start">
                <template x-for="item in paginatedClasses" :key="item.id">
                    <a :href="getUrl(item)" class="bg-white rounded-3xl overflow-hidden border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-1.5 transition-all duration-300 flex flex-col group relative">
                        <!-- Thumbnail Area -->
                        <div class="relative h-52 bg-gray-200 overflow-hidden">
                            <template x-if="item.preview_image">
                                <img :src="item.preview_image" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                            </template>
                            <template x-if="!item.preview_image">
                                <div class="w-full h-full flex items-center justify-center bg-emerald-900 text-white/20">
                                    <i class="fa-solid fa-book-open-reader text-6xl"></i>
                                </div>
                            </template>
                            
                            <!-- Badge Harga -->
                            <div class="absolute top-4 left-4 bg-white/95 backdrop-blur-sm px-3 py-1.5 rounded-xl shadow-sm text-xs font-bold text-emerald-800 flex items-center gap-1.5">
                                <i class="fa-solid fa-tag"></i> <span x-text="formatRupiah(item.price)"></span>
                            </div>
                        </div>
                        
                        <!-- Detail Area -->
                        <div class="p-6 flex-1 flex flex-col justify-between relative">
                            <div>
                                <div class="flex items-center gap-2 text-[11px] font-semibold text-gray-500 mb-3">
                                    <span class="flex items-center gap-1.5 bg-blue-50 text-blue-600 px-2.5 py-1 rounded-md">
                                        <i class="fa-solid fa-calendar-days"></i> <span x-text="item.session_count + ' Sesi'"></span>
                                    </span>
                                </div>
                                <h3 class="text-xl font-bold font-zilla text-gray-900 group-hover:text-emerald-700 transition line-clamp-2 leading-snug mb-4" x-text="item.title"></h3>
                            </div>
                            
                            <!-- Tutor & Action Area -->
                            <div class="flex items-center justify-between pt-5 border-t border-gray-100 mt-auto">
                                <div class="flex items-center gap-3">
                                    <template x-if="item.instructor_avatar">
                                        <img :src="'/uploads/avatars/' + item.instructor_avatar" alt="Tutor" class="w-9 h-9 rounded-full object-cover border border-emerald-100">
                                    </template>
                                    <template x-if="!item.instructor_avatar">
                                        <div class="w-9 h-9 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-xs font-bold" x-text="getInitial(item.instructor_name)"></div>
                                    </template>
                                    <div class="flex flex-col">
                                        <span class="text-[9px] text-gray-400 font-bold uppercase tracking-wider">Pengajar</span>
                                        <span class="text-xs font-bold text-gray-700 truncate max-w-[100px] sm:max-w-[130px]" x-text="item.instructor_name || 'Tutor IQRA'"></span>
                                    </div>
                                </div>

                                <div class="w-9 h-9 rounded-xl bg-gray-50 group-hover:bg-emerald-600 text-gray-400 group-hover:text-white flex items-center justify-center transition-colors">
                                    <i class="fa-solid fa-arrow-right text-sm"></i>
                                </div>
                            </div>
                        </div>
                    </a>
                </template>
            </div>

            <!-- PAGINATION CONTROLS -->
            <template x-if="totalPages > 1">
                <div class="flex justify-center items-center gap-2 mt-12 pt-6 border-t border-gray-100">
                    <!-- Tombol Prev -->
                    <button @click="prevPage()" 
                            :disabled="currentPage === 1" 
                            class="w-10 h-10 rounded-xl flex items-center justify-center border border-gray-200 text-gray-500 hover:bg-emerald-50 hover:text-emerald-700 hover:border-emerald-200 disabled:opacity-40 disabled:cursor-not-allowed transition">
                        <i class="fa-solid fa-chevron-left text-sm"></i>
                    </button>
                    
                    <!-- Angka Halaman -->
                    <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar px-2 max-w-[200px] sm:max-w-none">
                        <template x-for="page in totalPages" :key="page">
                            <button @click="goToPage(page)" 
                                    :class="{'bg-emerald-600 text-white shadow-md border-emerald-600': currentPage === page, 'bg-white text-gray-600 border-gray-200 hover:bg-emerald-50 hover:text-emerald-700': currentPage !== page}" 
                                    class="w-10 h-10 shrink-0 rounded-xl flex items-center justify-center border font-bold text-sm transition"
                                    x-text="page">
                            </button>
                        </template>
                    </div>
                    
                    <!-- Tombol Next -->
                    <button @click="nextPage()" 
                            :disabled="currentPage === totalPages" 
                            class="w-10 h-10 rounded-xl flex items-center justify-center border border-gray-200 text-gray-500 hover:bg-emerald-50 hover:text-emerald-700 hover:border-emerald-200 disabled:opacity-40 disabled:cursor-not-allowed transition">
                        <i class="fa-solid fa-chevron-right text-sm"></i>
                    </button>
                </div>
            </template>
        </div>
    </template>

</main>
<!-- ================= END MAIN CONTENT ================= -->

<!-- ================= START FOOTER ================= -->
<footer id="kontak" class="bg-[#053c2e] text-white pt-12 pb-6 border-t border-emerald-900/50 relative overflow-hidden mt-auto">
    <div class="absolute right-0 bottom-0 opacity-10 pointer-events-none translate-x-10 translate-y-10">
        <i class="fa-solid fa-mosque text-[280px]"></i>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10"> 
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-8 pb-10 border-b border-emerald-800/60">
            
            <div class="lg:col-span-4 pr-0 lg:pr-4">
                <a href="/" class="flex items-center gap-3 mb-4">
                    <div class="w-9 h-9 rounded-full bg-white border border-white/20 flex items-center justify-center text-white font-bold text-lg">
                        <img src="/asset/img/logo_kitab.png">
                    </div>
                    <div class="flex flex-col font-zilla leading-tight">
                        <span class="text-xl font-bold text-white tracking-wide uppercase leading-none">IQRA'</span>
                        <span class="text-base font-semibold text-lime-500 tracking-normal leading-none mt-0.5">Society</span>
                    </div>
                </a>
                <p class="text-xs text-emerald-100/80 leading-relaxed max-w-sm mb-6">
                    Komunitas pendidikan Islam yang berfokus pada pembelajaran, diskusi, literasi, dan pengembangan masyarakat melalui ilmu yang bermanfaat.
                </p>
                <div class="flex items-center gap-2.5">
                    <a href="#" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 border border-white/15 flex items-center justify-center text-white text-xs transition">
                        <i class="fa-brands fa-youtube"></i>
                    </a>
                    <a href="#" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 border border-white/15 flex items-center justify-center text-white text-xs transition">
                        <i class="fa-brands fa-instagram"></i>
                    </a>
                    <a href="#" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 border border-white/15 flex items-center justify-center text-white text-xs transition">
                        <i class="fa-brands fa-tiktok"></i>
                    </a>
                    <a href="#" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 border border-white/15 flex items-center justify-center text-white text-xs transition">
                        <i class="fa-brands fa-facebook-f"></i>
                    </a>
                </div>
            </div>

            <div class="lg:col-span-3">
                <h4 class="text-sm font-bold text-white mb-4">Navigasi</h4>
                <div class="grid grid-cols-2 gap-x-2 gap-y-2.5 text-xs text-emerald-100/80 font-medium">
                    <a href="/" class="hover:text-emerald-300 transition">Beranda</a>
                    <a href="/kelas" class="hover:text-emerald-300 transition">Kelas Online</a>
                    <a href="/tentang-kami" class="hover:text-emerald-300 transition">Tentang</a>
                    <a href="/#donasi" class="hover:text-emerald-300 transition">Donasi</a>
                    <a href="/#program" class="hover:text-emerald-300 transition">Program</a>
                    <a href="/#pengurus" class="hover:text-emerald-300 transition">Pengurus</a>
                    <a href="/kegiatan" class="hover:text-emerald-300 transition">Kegiatan</a>
                    <a href="#kontak" class="hover:text-emerald-300 transition">Kontak</a>
                </div>
            </div>

            <div class="lg:col-span-2">
                <h4 class="text-sm font-bold text-white mb-4">Kontak Kami</h4>
                <ul class="space-y-3 text-xs text-emerald-100/80">
                    <li class="flex items-start gap-2.5">
                        <i class="fa-solid fa-location-dot text-emerald-300 mt-0.5 text-xs shrink-0"></i>
                        <span>Jakarta, Indonesia</span>
                    </li>
                    <li class="flex items-center gap-2.5">
                        <i class="fa-solid fa-envelope text-emerald-300 text-xs shrink-0"></i>
                        <a href="mailto:info@iqrasociety.id" class="hover:text-white transition">info@iqrasociety.id</a>
                    </li>
                    <li class="flex items-center gap-2.5">
                        <i class="fa-solid fa-phone text-emerald-300 text-xs shrink-0"></i>
                        <a href="tel:+6281234567890" class="hover:text-white transition">+62 812-3456-7890</a>
                    </li>
                </ul>
            </div>

            <div class="lg:col-span-3">
                <h4 class="text-sm font-bold text-white mb-4">Newsletter</h4>
                <p class="text-xs text-emerald-100/80 mb-4 leading-relaxed">
                    Dapatkan update kegiatan dan konten terbaru dari kami.
                </p>
                <form onsubmit="event.preventDefault();" class="relative">
                    <input type="email" placeholder="Masukkan email Anda" class="w-full bg-white text-gray-900 text-xs placeholder-gray-400 pl-4 pr-11 py-3 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-400 font-medium shadow-sm">
                    <button type="submit" class="absolute right-1.5 top-1/2 -translate-y-1/2 w-8 h-8 bg-transparent hover:bg-emerald-50 rounded-lg text-emerald-800 flex items-center justify-center transition">
                        <i class="fa-solid fa-paper-plane text-emerald-700 text-xs"></i>
                    </button>
                </form>
            </div>

        </div>

        <div class="pt-6 flex flex-col sm:flex-row items-center justify-between text-xs text-emerald-200/60 gap-3 text-center sm:text-left">
            <p>&copy; <?php echo date('Y'); ?> IQRA' Society. All Rights Reserved.</p>
            <div class="flex items-center gap-4">
                <a href="#" class="hover:text-white transition">Kebijakan Privasi</a>
                <span>|</span>
                <a href="#" class="hover:text-white transition">Syarat & Ketentuan</a>
            </div>
        </div>
    </div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const menuBtn = document.getElementById('menu-btn');
    const mobileMenu = document.getElementById('mobile-menu');
    const menuIcon = document.getElementById('menu-icon');
    
    if (menuBtn && mobileMenu && menuIcon) {
        menuBtn.addEventListener('click', function () {
            const isOpen = !mobileMenu.classList.contains('hidden');
            mobileMenu.classList.toggle('hidden', isOpen);
            menuIcon.classList.toggle('fa-bars', isOpen);
            menuIcon.classList.toggle('fa-xmark', !isOpen);
        });
    }
});

function kelasPagination() {
    return {
        allKelas: <?= $kelas_json ?>,
        currentPage: 1,
        itemsPerPage: 9, // Tampilkan 9 item per halaman

        get totalPages() {
            return Math.ceil(this.allKelas.length / this.itemsPerPage);
        },
        
        get paginatedClasses() {
            const start = (this.currentPage - 1) * this.itemsPerPage;
            const end = start + this.itemsPerPage;
            return this.allKelas.slice(start, end);
        },

        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        },

        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        },

        goToPage(page) {
            this.currentPage = page;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },

        formatRupiah(price) {
            if (price == 0 || price == null) return 'GRATIS';
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(price);
        },

        getInitial(name) {
            if (!name) return 'T';
            return name.charAt(0).toUpperCase();
        },

        getUrl(item) {
            if (item.slug && item.slug.trim() !== '') {
                return '/kelas/' + encodeURIComponent(item.slug);
            }
            return '/kelas/' + item.id;
        }
    }
}
</script>
</body>
</html>