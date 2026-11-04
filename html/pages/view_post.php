<?php
require_once __DIR__ . '/../includes/auth_middleware.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_logged_in = isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;
$username     = $_SESSION['fullname'] ?? $_SESSION['username'] ?? 'User';
$user_email   = $_SESSION['email'] ?? '';

$is_preview = isset($_POST['is_preview']) && $_POST['is_preview'] === '1';

if ($is_preview) {
    if (!$is_logged_in) {
        die("Akses ditolak. Anda tidak memiliki izin untuk melakukan preview.");
    }
    
    $post = [
        'title'      => $_POST['title'] ?? 'Tanpa Judul',
        'content'    => $_POST['content'] ?? '',
        'created_at' => date('Y-m-d H:i:s')
    ];
} else {
    $slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

    if (empty($slug)) {
        http_response_code(404);
        die("Artikel tidak ditemukan.");
    }

    $stmt = $conn->prepare("SELECT title, content, created_at FROM posts WHERE slug = ? AND status = 'published'");
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $post = $result->fetch_assoc();
    } else {
        http_response_code(404);
        die("Maaf, artikel tidak ditemukan atau belum dipublikasikan.");
    }
    $stmt->close();
}

$post_title = htmlspecialchars($post['title']);

$plain_text = strip_tags($post['content']);
$post_description = htmlspecialchars(mb_substr(trim(preg_replace('/\s+/', ' ', $plain_text)), 0, 150)) . '...';

$words = array_filter(explode(' ', strtolower(preg_replace('/[^a-zA-Z0-9\s]/', '', $post_title))));
$keywords_list = array_merge(['IQRA Society', 'Artikel Islam', 'Edukasi Islam'], $words);
$post_keywords = htmlspecialchars(implode(', ', array_unique($keywords_list)));

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$current_url = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Primary Meta Tags (Dinamis sesuai Slug) -->
    <title><?= $post_title ?> - IQRA Society</title>
    <meta name="title" content="<?= $post_title ?> - IQRA Society">
    <meta name="description" content="<?= $post_description ?>">
    <meta name="keywords" content="<?= $post_keywords ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= htmlspecialchars($current_url) ?>">

    <!-- Open Graph / Facebook / WhatsApp (Dinamis) -->
    <meta property="og:type" content="article">
    <meta property="og:url" content="<?= htmlspecialchars($current_url) ?>">
    <meta property="og:title" content="<?= $post_title ?> - IQRA Society">
    <meta property="og:description" content="<?= $post_description ?>">
    <meta property="og:image" content="<?= $protocol . $_SERVER['HTTP_HOST'] ?>/asset/img/og-image.jpg">

    <!-- Twitter (Dinamis) -->
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
    
    <!-- Quill Snow CSS (Penting agar konten dari Editor terender rapi) -->
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet" />

    <!-- Schema.org JSON-LD untuk Artikel (Dinamis) -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Article",
      "headline": "<?= $post_title ?>",
      "image": [
        "<?= $protocol . $_SERVER['HTTP_HOST'] ?>/asset/img/og-image.jpg"
       ],
      "datePublished": "<?= date('c', strtotime($post['created_at'])) ?>",
      "author": [{
          "@type": "Organization",
          "name": "IQRA Society",
          "url": "<?= $protocol . $_SERVER['HTTP_HOST'] ?>"
        }]
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
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f9fafb; color: #1f2937; overflow-x: hidden; }
        .site-header { position: sticky; top: 0; z-index: 1000; width: 100%; }
        
        .ql-editor { padding: 0 !important; font-family: inherit !important; font-size: 1rem; line-height: 1.75; }
        .ql-editor p { margin-bottom: 1.25em; }
        .ql-editor h1, .ql-editor h2, .ql-editor h3 { font-weight: 700; margin-top: 2em; margin-bottom: 1em; line-height: 1.33; color: #111827; }
        .ql-editor h2 { font-size: 1.5rem; }
        .ql-editor h3 { font-size: 1.25rem; }
        .ql-editor ul, .ql-editor ol { padding-left: 1.5em; margin-bottom: 1.25em; }
        .ql-editor li { margin-bottom: 0.5em; }
        .ql-editor a { color: #047857; text-decoration: underline; }
        .ql-editor blockquote { border-left: 4px solid #10b981; padding-left: 1em; font-style: italic; color: #4b5563; margin-bottom: 1.25em; background: #ecfdf5; padding: 1rem; border-radius: 0.5rem; }
        .ql-editor img { max-width: 100%; height: auto; border-radius: 0.5rem; margin: 1.5rem 0; }
    </style>
</head>
<body class="bg-white text-gray-800 antialiased w-full flex flex-col min-h-screen">

<!-- ================= START HEADER ================= -->
<header class="site-header bg-white/90 backdrop-blur-md transition-all border-b border-gray-100/50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-20">
            
            <a href="/" class="flex items-center gap-3" aria-label="Beranda Iqra Society">
                <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-xl shadow-sm">
                    <img src="/asset/img/logo_kitab.png" alt="Logo Iqra Society" width="40" height="40" loading="eager">
                </div>
                <div class="flex flex-col font-['Zilla_Slab'] leading-tight">
                    <span class="text-lg font-bold text-emerald-900 tracking-wide uppercase leading-none">IQRA'</span>
                    <span class="text-base font-semibold text-lime-500 tracking-normal leading-none mt-0.5">Society</span>
                </div>
            </a>

            <nav class="hidden lg:flex items-center gap-7" id="desktop-nav" aria-label="Navigasi Utama">
                <a href="/" class="nav-link relative inline-block text-sm font-bold text-gray-700 hover:text-emerald-700 transition py-1">Beranda</a>
                <a href="/#tentang" class="nav-link relative inline-block text-sm font-bold text-gray-700 hover:text-emerald-700 transition py-1">Tentang</a>
                <a href="/#program" class="nav-link relative inline-block text-sm font-bold text-gray-700 hover:text-emerald-700 transition py-1">Program</a>
                <a href="/#kegiatan" class="nav-link relative inline-block text-sm font-bold text-gray-700 hover:text-emerald-700 transition py-1">Kegiatan</a>
                <a href="/#buku" class="nav-link relative inline-block text-sm font-bold text-gray-700 hover:text-emerald-700 transition py-1">Buku</a>
                <a href="/#kelas" class="nav-link relative inline-block text-sm font-bold text-gray-700 hover:text-emerald-700 transition py-1">Kelas Online</a>
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
    <nav id="mobile-menu" class="hidden lg:hidden bg-white border-b border-gray-100 shadow-xl transition-all duration-300 ease-in-out">
        <div class="px-4 pt-3 pb-6 space-y-2">
            <a href="/" class="mobile-link block px-4 py-2.5 rounded-xl font-bold text-gray-700 hover:bg-emerald-50 transition">Beranda</a>
            <a href="/#tentang" class="mobile-link block px-4 py-2.5 rounded-xl font-bold text-gray-700 hover:bg-emerald-50 transition">Tentang</a>
            <a href="/#program" class="mobile-link block px-4 py-2.5 rounded-xl font-bold text-gray-700 hover:bg-emerald-50 transition">Program</a>
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

<main class="max-w-4xl mx-auto mt-8 p-6 bg-white rounded-2xl shadow-sm border border-gray-100 mb-12">
    <article class="max-w-3xl mx-auto px-4 sm:px-6">
        
        <!-- Artikel Header -->
        <header class="mb-10 border-b border-gray-100 pb-8 text-center">
            <!-- Breadcrumbs -->
            <nav class="flex justify-center text-xs text-emerald-600 font-semibold mb-6">
                <a href="/" class="hover:text-emerald-800">Beranda</a>
                <span class="mx-2 text-gray-300">/</span>
                <span class="text-gray-500">Artikel</span>
            </nav>

            <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900 mb-4">
                <?= $post_title ?>
            </h1>
            
            <div class="flex items-center justify-center gap-4 text-sm text-gray-500">
                <div class="flex items-center gap-1.5">
                    <i class="fa-regular fa-calendar text-emerald-600"></i>
                    <span><?= date('d F Y', strtotime($post['created_at'])) ?></span>
                </div>
            </div>
        </header>

        <!-- Artikel Konten (Quill Render) -->
        <div class="ql-editor prose max-w-none">
            <?= $post['content'] ?>
        </div>
        
        <!-- Share Post -->
        <div class="mt-12 pt-8 border-t border-gray-100 flex items-center justify-between">
            <span class="text-sm font-bold text-gray-700">Bagikan artikel ini:</span>
            <div class="flex items-center gap-3">
                <a href="https://api.whatsapp.com/send?text=<?= urlencode($post_title . ' - ' . $current_url) ?>" target="_blank" class="w-10 h-10 rounded-full bg-green-100 text-green-600 hover:bg-green-600 hover:text-white flex items-center justify-center transition">
                    <i class="fa-brands fa-whatsapp text-lg"></i>
                </a>
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($current_url) ?>" target="_blank" class="w-10 h-10 rounded-full bg-blue-100 text-blue-600 hover:bg-blue-600 hover:text-white flex items-center justify-center transition">
                    <i class="fa-brands fa-facebook-f text-lg"></i>
                </a>
                <a href="https://twitter.com/intent/tweet?text=<?= urlencode($post_title) ?>&url=<?= urlencode($current_url) ?>" target="_blank" class="w-10 h-10 rounded-full bg-sky-100 text-sky-500 hover:bg-sky-500 hover:text-white flex items-center justify-center transition">
                    <i class="fa-brands fa-twitter text-lg"></i>
                </a>
            </div>
        </div>

    </article>
</main>

<footer id="kontak" class="bg-[#053c2e] text-white pt-12 pb-6 border-t border-emerald-900/50 relative overflow-hidden">
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
                    <div class="flex flex-col font-['Zilla_Slab'] leading-tight">
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

<!-- FLOATING BACK TO TOP BUTTON -->
<button id="backToTopBtn" aria-label="Kembali ke atas" class="fixed bottom-6 right-6 z-50 w-11 h-11 rounded-full bg-emerald-800 hover:bg-emerald-900 text-white shadow-lg border border-emerald-700/30 flex items-center justify-center transition-all duration-300 opacity-0 pointer-events-none translate-y-4 hover:scale-110 active:scale-95">
    <i class="fa-solid fa-arrow-up text-sm"></i>
</button>

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

    const backToTopBtn = document.getElementById('backToTopBtn');
    if (backToTopBtn) {
        window.addEventListener('scroll', () => {
            const visible = window.scrollY > 300;
            backToTopBtn.classList.toggle('opacity-100', visible);
            backToTopBtn.classList.toggle('pointer-events-auto', visible);
            backToTopBtn.classList.toggle('translate-y-0', visible);
            backToTopBtn.classList.toggle('opacity-0', !visible);
            backToTopBtn.classList.toggle('pointer-events-none', !visible);
            backToTopBtn.classList.toggle('translate-y-4', !visible);
        });
        backToTopBtn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
    }
});
</script>
</body>
</html>