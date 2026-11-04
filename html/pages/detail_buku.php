<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$is_logged_in = isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;
$username = $_SESSION['fullname'] ?? $_SESSION['username'] ?? 'User';

$buku = null;
if (!empty($_GET['slug'])) {
    $slug = trim($_GET['slug']);
    $stmt = $conn->prepare("SELECT * FROM section_buku WHERE slug = ? AND status = 'approved' LIMIT 1");
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $buku = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$buku) { header("HTTP/1.0 404 Not Found"); die("<h1 style='text-align:center;padding:50px;font-family:sans-serif;'>404 - Buku tidak ditemukan</h1>"); }
$cover_url = !empty($buku['cover']) ? ((strpos($buku['cover'], 'http') === 0) ? $buku['cover'] : '/uploads/buku/' . $buku['cover']) : '';

// ===== Kepemilikan & tautan baca buku =====
// link_description berisi tautan baca (URL) untuk sebagian buku; tautan itu
// hanya boleh tampil ke pembeli, tidak sebagai teks deskripsi publik.
require_once __DIR__ . '/../includes/fulfillment_helper.php';
$buku_link = trim($buku['link_description'] ?? '');
$buku_url = filter_var($buku_link, FILTER_VALIDATE_URL) ? $buku_link : '';
$sudah_beli = false;
if ($is_logged_in && !empty($_SESSION['user_id'])) {
    $sudah_beli = ((float)$buku['price'] <= 0) || iqra_user_paid_item($conn, (int)$_SESSION['user_id'], 'buku', (int)$buku['id']);
}
$rekomendasi = [];
$rs = $conn->prepare("SELECT id, title, slug, cover, price FROM section_buku WHERE status='approved' AND id != ? ORDER BY id DESC LIMIT 3");
$rs->bind_param("i", $buku['id']); $rs->execute(); $rekomendasi = $rs->get_result()->fetch_all(MYSQLI_ASSOC); $rs->close();
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Primary Meta Tags -->
    <title><?= htmlspecialchars($buku["title"] ?? "Detail Buku") ?> - IQRA Society</title>
    <meta name="title" content="<?= $post_title ?> - IQRA Society">
    <meta name="description" content="<?= $post_description ?>">
    <meta name="keywords" content="<?= $post_keywords ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= htmlspecialchars($current_url) ?>">

    <!-- Open Graph / Facebook / WhatsApp -->
    <meta property="og:type" content="article">
    <meta property="og:url" content="<?= htmlspecialchars($current_url) ?>">
    <meta property="og:title" content="<?= $post_title ?> - IQRA Society">
    <meta property="og:description" content="<?= $post_description ?>">
    <meta property="og:image" content="<?= !empty($kelas['preview_image']) ? htmlspecialchars($kelas['preview_image']) : $protocol . $_SERVER['HTTP_HOST'] . '/asset/img/og-image.jpg' ?>">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?= htmlspecialchars($current_url) ?>">
    <meta property="twitter:title" content="<?= $post_title ?> - IQRA Society">
    <meta property="twitter:description" content="<?= $post_description ?>">
    <meta property="twitter:image" content="<?= !empty($kelas['preview_image']) ? htmlspecialchars($kelas['preview_image']) : $protocol . $_SERVER['HTTP_HOST'] . '/asset/img/og-image.jpg' ?>">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/asset/img/favicon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/asset/img/favicon.png">

    <!-- CSS & Fonts -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.tailwindcss.com?plugins=typography"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Zilla+Slab:wght@600;700&display=swap" rel="stylesheet">

    <!-- Schema.org JSON-LD -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Course",
      "name": "<?= $post_title ?>",
      "description": "<?= $post_description ?>",
      "provider": {
        "@type": "Organization",
        "name": "IQRA Society",
        "url": "<?= $protocol . $_SERVER['HTTP_HOST'] ?>"
      }
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
                        zilla: ['Zilla Slab', 'serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f9fafb; color: #1f2937; overflow-x: hidden; }
        .site-header { position: sticky; top: 0; z-index: 1000; width: 100%; }
        
        /* Modifikasi Layout TinyMCE/HTML konten (opsional) */
        .ql-editor { font-size: 1rem; line-height: 1.75; }
        .ql-editor h2, .ql-editor h3 { font-family: 'Zilla Slab', serif; color: #111827; margin-top: 1.5em; margin-bottom: 0.75em; font-weight: 700; }
        .ql-editor p { margin-bottom: 1.25em; }
    </style>
<?php
$__ck = '';
$__q = $conn->query("SELECT key_value FROM gateway_settings WHERE key_name = 'midtrans_client_key' LIMIT 1");
if ($__q && $__q->num_rows > 0) { $__ck = $__q->fetch_assoc()['key_value']; }
$__snap = (strpos($__ck, 'SB-Mid-client') === 0) ? 'https://app.sandbox.midtrans.com/snap/snap.js' : 'https://app.midtrans.com/snap/snap.js';
?>
<script type="text/javascript" src="<?= $__snap ?>" data-client-key="<?= htmlspecialchars($__ck) ?>"></script>
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
                <a href="/buku" class="nav-link relative inline-block text-sm font-bold text-emerald-700 transition py-1 active">Buku</a>
                <a href="/kelas" class="nav-link relative inline-block text-sm font-bold text-gray-700 hover:text-emerald-700 transition py-1">Kelas Online</a>
                <a href="/#donasi" class="nav-link relative inline-block text-sm font-bold text-gray-700 hover:text-emerald-700 transition py-1">Donasi</a>
            </nav>

            <div class="hidden lg:flex items-center gap-3">
                <?php if ($is_logged_in): ?>
                    <div class="flex items-center gap-2 bg-emerald-50 border border-emerald-200 text-emerald-900 font-bold text-xs px-4 py-2.5 rounded-xl">
                        <i class="fa-solid fa-user-circle text-base text-emerald-700" aria-hidden="true"></i>
                        <span>Halo, <?php echo htmlspecialchars($username); ?></span>
                    </div>
                    <a href="logout.php" class="bg-red-600 hover:bg-red-700 text-white font-bold text-xs px-4 py-3 rounded-xl shadow-sm transition flex items-center gap-1.5" title="Logout">
                        <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                    </a>
                <?php else: ?>
                    <a href="login.php" class="border border-emerald-800 text-emerald-800 hover:bg-emerald-50 font-bold text-xs px-4 py-2.5 rounded-xl transition">
                        Masuk
                    </a>
                    <a href="register.php" class="bg-emerald-800 hover:bg-emerald-900 text-white font-bold text-xs px-5 py-2.5 rounded-xl shadow-sm transition">
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
            <a href="/tentang-kami" class="mobile-link block px-4 py-2.5 rounded-xl font-bold text-gray-700 hover:bg-emerald-50 transition">Tentang</a>
            <a href="/#program" class="mobile-link block px-4 py-2.5 rounded-xl font-bold text-gray-700 hover:bg-emerald-50 transition">Program</a>
            <a href="/kegiatan" class="mobile-link block px-4 py-2.5 rounded-xl font-bold text-gray-700 hover:bg-emerald-50 transition">Kegiatan</a>
            <a href="/buku" class="mobile-link block px-4 py-2.5 rounded-xl font-bold text-emerald-700 bg-emerald-50 transition">Buku</a>
            <a href="/kelas" class="mobile-link block px-4 py-2.5 rounded-xl font-bold text-gray-700 hover:bg-emerald-50 transition">Kelas Online</a>
            <a href="/#donasi" class="mobile-link block px-4 py-2.5 rounded-xl font-bold text-gray-700 hover:bg-emerald-50 transition">Donasi</a>
            <div class="pt-3 border-t border-gray-100 space-y-2">
                <?php if ($is_logged_in): ?>
                    <div class="flex items-center gap-2 px-4 py-2.5 text-emerald-900 font-bold text-xs bg-emerald-50 rounded-xl">
                        <i class="fa-solid fa-user-circle text-base text-emerald-700" aria-hidden="true"></i>
                        <span>Halo, <?php echo htmlspecialchars($username); ?></span>
                    </div>
                    <a href="logout.php" class="mobile-link w-full bg-red-600 text-white font-bold text-xs px-5 py-3 rounded-xl flex items-center justify-center gap-2">
                        <i class="fa-solid fa-right-from-bracket"></i> Logout
                    </a>
                <?php else: ?>
                    <div class="grid grid-cols-2 gap-2">
                        <a href="login.php" class="mobile-link text-center border border-emerald-800 text-emerald-800 font-bold text-xs px-4 py-3 rounded-xl">Masuk</a>
                        <a href="register.php" class="mobile-link text-center bg-emerald-800 text-white font-bold text-xs px-4 py-3 rounded-xl">Daftar</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>
</header>
<!-- ================= END HEADER ================= -->

<main class="flex-grow max-w-6xl mx-auto w-full py-8 md:py-12 px-4 md:px-6 flex flex-col lg:flex-row gap-8">
    <div class="w-full lg:w-2/3 space-y-8">
        <div class="space-y-4">
            <nav class="flex text-xs text-emerald-600 font-semibold mb-2">
                <a href="/" class="hover:text-emerald-800">Beranda</a><span class="mx-2 text-gray-300">/</span>
                <a href="/buku" class="hover:text-emerald-800">Buku</a><span class="mx-2 text-gray-300">/</span>
                <span class="text-gray-500 truncate max-w-[200px] inline-block"><?= htmlspecialchars($buku['title']) ?></span>
            </nav>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 uppercase tracking-widest shadow-sm">
                <i class="fa-solid fa-book"></i> Katalog Buku
            </span>
            <h1 class="text-3xl md:text-4xl font-extrabold font-zilla text-gray-900 leading-tight"><?= htmlspecialchars($buku['title']) ?></h1>
        </div>
        <?php if ($cover_url): ?>
        <div class="rounded-3xl overflow-hidden border border-gray-200 shadow-md bg-gray-50 w-full flex items-center justify-center p-8">
            <img src="<?= htmlspecialchars($cover_url) ?>" alt="Cover" class="max-h-96 object-contain drop-shadow-lg">
        </div>
        <?php endif; ?>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 md:p-8">
            <h3 class="text-sm font-bold text-gray-900 mb-4 flex items-center gap-2"><i class="fa-solid fa-align-left text-emerald-600"></i> Deskripsi Buku</h3>
            <article class="prose prose-emerald max-w-none text-gray-700 leading-relaxed">
                <?php if ($buku_url): ?>
                <p class="text-gray-500 italic">Buku ini dibaca secara online. Tautan baca tersedia untuk pembeli melalui tombol <strong>Baca Buku</strong> setelah pembelian.</p>
                <?php elseif (!empty($buku['link_description'])): ?>
                <?= nl2br(htmlspecialchars($buku['link_description'])) ?>
                <?php else: ?>
                <p class="text-gray-500 italic">Deskripsi belum tersedia.</p>
                <?php endif; ?>
            </article>
        </div>
    </div>
    <div class="w-full lg:w-1/3 space-y-6"><div class="sticky top-28 space-y-6">
        <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-xl shadow-gray-100/50">
            <div class="text-center mb-6">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Harga Buku</p>
                <p class="text-3xl font-black text-emerald-900 font-zilla"><?= $buku['price'] == 0 ? '<span class="text-emerald-600">GRATIS</span>' : 'Rp ' . number_format($buku['price'], 0, ',', '.') ?></p>
            </div>
            <?php if ($sudah_beli): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl p-4 text-sm text-center">
                <i class="fa-solid fa-circle-check text-emerald-600 text-lg"></i>
                <p class="font-bold mt-1">Anda sudah memiliki buku ini</p>
            </div>
            <?php if ($buku_url): ?>
            <a href="<?= htmlspecialchars($buku_url) ?>" target="_blank" rel="noopener" class="w-full flex items-center justify-center gap-2 bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-sm py-4 rounded-xl transition shadow-md hover:shadow-lg mt-3">
                <i class="fa-solid fa-book-open"></i> Baca Buku
            </a>
            <?php else: ?>
            <p class="text-xs text-gray-500 text-center mt-3 leading-relaxed">Tautan baca buku ini belum disediakan oleh penerbit. Silakan hubungi admin agar akses Anda disiapkan.</p>
            <?php endif; ?>
            <?php elseif ($buku['price'] > 0): ?>
            <?php if ($is_logged_in): ?>
            <button onclick="beliItem('buku', <?= (int)$buku['id'] ?>)" class="w-full flex items-center justify-center gap-2 bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-sm py-4 rounded-xl transition shadow-md hover:shadow-lg">
                <i class="fa-solid fa-cart-shopping"></i> Beli Sekarang
            </button>
            <?php else: ?>
            <a href="/login.php" class="w-full flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-sm py-4 rounded-xl transition">
                <i class="fa-solid fa-right-to-bracket"></i> Login untuk Membeli
            </a>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div></div>
</main>
<?php if (!empty($rekomendasi)): ?>
<section class="max-w-6xl mx-auto w-full px-4 md:px-6 mb-16"><div class="border-t border-gray-200 pt-12">
<h2 class="text-2xl font-bold font-zilla text-gray-900 mb-8 flex items-center gap-3"><i class="fa-solid fa-layer-group text-emerald-600"></i> Rekomendasi Buku Lainnya</h2>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
<?php foreach ($rekomendasi as $item): ?>
<a href="/buku/<?= urlencode($item['slug']) ?>" class="bg-white rounded-3xl overflow-hidden border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition duration-300 flex flex-col group">
<div class="relative h-48 bg-gray-100 overflow-hidden flex items-center justify-center p-4">
<?php if(!empty($item['cover'])): ?><img src="/uploads/buku/<?= htmlspecialchars($item['cover']) ?>" class="h-full object-contain group-hover:scale-105 transition duration-500"><?php else: ?><i class="fa-solid fa-book text-5xl text-gray-300"></i><?php endif; ?>
<div class="absolute top-3 left-3 bg-white/95 px-3 py-1.5 rounded-xl shadow-sm text-xs font-bold text-emerald-800"><i class="fa-solid fa-tag"></i> <?= $item['price'] == 0 ? 'GRATIS' : 'Rp ' . number_format($item['price'], 0, ',', '.') ?></div>
</div><div class="p-6"><h3 class="text-lg font-bold font-zilla text-gray-900 group-hover:text-emerald-700 transition line-clamp-2"><?= htmlspecialchars($item['title']) ?></h3></div>
</a>
<?php endforeach; ?>
</div></div></section>
<?php endif; ?>
<footer id="kontak" class="bg-[#053c2e] text-white pt-12 pb-6 border-t border-emerald-900/50 relative overflow-hidden">
    <div class="absolute right-0 bottom-0 opacity-10 pointer-events-none translate-x-10 translate-y-10">
        <i class="fa-solid fa-mosque text-[280px]"></i>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 pb-16 lg:pb-0"> <!-- Padding bottom extra di mobile karena ada sticky action bar -->
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
                    <a href="/#beranda" class="hover:text-emerald-300 transition">Beranda</a>
                    <a href="/#kelas" class="hover:text-emerald-300 transition">Kelas Online</a>
                    <a href="/#tentang" class="hover:text-emerald-300 transition">Tentang</a>
                    <a href="/#donasi" class="hover:text-emerald-300 transition">Donasi</a>
                    <a href="/#program" class="hover:text-emerald-300 transition">Program</a>
                    <a href="/#pengurus" class="hover:text-emerald-300 transition">Pengurus</a>
                    <a href="/#kegiatan" class="hover:text-emerald-300 transition">Kegiatan</a>
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
</script>

<script>
async function beliItem(type, id) {
    if (!confirm('Lanjutkan ke pembayaran?')) return;
    try {
        const res = await fetch('/payment/checkout.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({item_type: type, item_id: id})
        });
        const data = await res.json();
        if (data.success) {
            if (data.gateway === 'midtrans' && data.snap_token) {
                window.snap.pay(data.snap_token, {
                    onSuccess: () => { alert('Pembayaran berhasil!'); location.reload(); },
                    onPending: () => { alert('Menunggu pembayaran.'); },
                    onError: () => { alert('Pembayaran gagal.'); },
                    onClose: () => {}
                });
            } else if (data.gateway === 'tripay' && data.bayar_url) {
                window.location.href = data.bayar_url;
            }
        } else {
            alert(data.message || 'Gagal memproses pembayaran.');
        }
    } catch(e) { alert('Terjadi kesalahan jaringan.'); }
}
</script>
</body>
</html>