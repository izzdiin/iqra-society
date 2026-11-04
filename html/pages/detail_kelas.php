<?php
require_once __DIR__ . '/../includes/auth_middleware.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_logged_in = isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;
$username     = $_SESSION['fullname'] ?? $_SESSION['username'] ?? 'User';
$user_email   = $_SESSION['email'] ?? '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function render_formatted_list($text, $type = 'bullet') {
    if (empty(trim($text))) {
        return '';
    }

    $lines = array_filter(explode("\n", str_replace("\r", "", $text)), 'trim');

    if (empty($lines)) {
        return '';
    }

    $output = '';

    if ($type === 'numbered') {
        $output .= '<ol class="space-y-2.5 list-none pl-0">';
        foreach ($lines as $idx => $line) {
            $clean_line = preg_replace('/^[\d\.\-\*\•\>\s]+/', '', trim($line));
            $output .= '<li class="flex items-start gap-3 p-4 bg-emerald-50/50 rounded-xl border border-emerald-100 hover:bg-emerald-50 transition text-sm">';
            $output .= '<span class="w-7 h-7 rounded-lg bg-emerald-600 text-white font-bold text-[13px] flex items-center justify-center shrink-0 mt-0.5 shadow-sm">' . ($idx + 1) . '</span>';
            $output .= '<span class="font-medium text-gray-800 leading-relaxed flex-1 pt-0.5">' . htmlspecialchars($clean_line) . '</span>';
            $output .= '</li>';
        }
        $output .= '</ol>';
    } else {
        $output .= '<ul class="space-y-3 list-none pl-0">';
        foreach ($lines as $line) {
            $clean_line = preg_replace('/^[\d\.\-\*\•\>\s]+/', '', trim($line));
            $output .= '<li class="flex items-start gap-3 text-sm text-gray-700 leading-relaxed bg-gray-50/50 p-3 rounded-xl border border-gray-100">';
            $output .= '<i class="fa-solid fa-circle-check text-emerald-600 text-base mt-0.5 shrink-0"></i>';
            $output .= '<span class="flex-1">' . htmlspecialchars($clean_line) . '</span>';
            $output .= '</li>';
        }
        $output .= '</ul>';
    }

    return $output;
}

function get_youtube_embed_url($url) {
    if (empty($url)) return false;
    
    if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i', $url, $matches)) {
        return 'https://www.youtube.com/embed/' . $matches[1];
    }
    
    return false;
}

$kelas = null;

if (isset($_GET['slug']) && !empty($_GET['slug'])) {
    $slug = trim($_GET['slug']);
    $stmt = $conn->prepare("SELECT k.*, u.fullname AS instructor_name, u.pendidikan, u.spesialisasi, u.aboutme, u.avatar AS instructor_avatar 
                            FROM section_kelas k 
                            LEFT JOIN users u ON k.instructor_id = u.id 
                            WHERE k.slug = ? AND k.status = 'approved' 
                            LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $slug);
        $stmt->execute();
        $kelas = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
} elseif (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT k.*, u.fullname AS instructor_name, u.pendidikan, u.spesialisasi, u.aboutme, u.avatar AS instructor_avatar 
                            FROM section_kelas k 
                            LEFT JOIN users u ON k.instructor_id = u.id 
                            WHERE k.id = ? AND k.status = 'approved' 
                            LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $kelas = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

if (!$kelas) {
    header("HTTP/1.0 404 Not Found");
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Kelas Tidak Ditemukan - IQRA' Society</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
    <?php
$__ck = '';
$__q = $conn->query("SELECT key_value FROM gateway_settings WHERE key_name = 'midtrans_client_key' LIMIT 1");
if ($__q && $__q->num_rows > 0) { $__ck = $__q->fetch_assoc()['key_value']; }
$__snap = (strpos($__ck, 'SB-Mid-client') === 0) ? 'https://app.sandbox.midtrans.com/snap/snap.js' : 'https://app.midtrans.com/snap/snap.js';
?>
<script type="text/javascript" src="<?= $__snap ?>" data-client-key="<?= htmlspecialchars($__ck) ?>"></script>
</head>
    <body class="bg-gray-50 flex items-center justify-center min-h-screen p-4 text-gray-800">
        <div class="max-w-md w-full bg-white rounded-3xl p-8 text-center shadow-xl border border-gray-100">
            <div class="w-20 h-20 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-6 text-3xl">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <h1 class="text-xl font-bold text-gray-900 mb-2">Kelas Tidak Ditemukan</h1>
            <p class="text-sm text-gray-500 mb-8 leading-relaxed">Kelas yang Anda cari mungkin belum disetujui, sudah ditutup, atau tautan tidak valid.</p>
            <a href="index.php" class="inline-flex items-center justify-center px-6 py-3 bg-emerald-700 hover:bg-emerald-800 text-white rounded-xl text-sm font-bold transition-all shadow-md">
                <i class="fa-solid fa-house mr-2"></i> Kembali ke Beranda
            </a>
        </div>
    
</body>
    </html>
    <?php
    exit;
}

// ===== Hak akses kelas untuk user yang login =====
require_once __DIR__ . '/../includes/fulfillment_helper.php';
$current_user_id = ($is_logged_in && !empty($_SESSION['user_id'])) ? (int)$_SESSION['user_id'] : 0;
$sudah_terdaftar = $current_user_id ? iqra_kelas_enrolled($conn, $current_user_id, (int)$kelas['id']) : false;
$adalah_pengajar  = $current_user_id && (int)$kelas['instructor_id'] === $current_user_id;
$punya_akses = $adalah_pengajar || $sudah_terdaftar
    || ($current_user_id && (float)$kelas['price'] > 0 && iqra_user_paid_item($conn, $current_user_id, 'kelas', (int)$kelas['id']));

// Sesi/materi kelas — dirender di panel akses sesuai hak akses user
$sessions_kelas = [];
$stmt_sesi = $conn->prepare("SELECT * FROM kelas_sessions WHERE kelas_id = ? ORDER BY urutan ASC, id ASC");
if ($stmt_sesi) {
    $kid = (int)$kelas['id'];
    $stmt_sesi->bind_param("i", $kid);
    $stmt_sesi->execute();
    $sessions_kelas = $stmt_sesi->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_sesi->close();
}
$sesi_gratis = array_values(array_filter($sessions_kelas, function ($s) { return (int)$s['is_free'] === 1; }));

$rekomendasi_kelas = [];
$current_id = (int)$kelas['id'];
$stmt_rek = $conn->prepare("SELECT k.*, u.fullname AS instructor_name, u.avatar AS instructor_avatar 
                            FROM section_kelas k 
                            LEFT JOIN users u ON k.instructor_id = u.id 
                            WHERE k.status = 'approved' AND k.id != ? 
                            ORDER BY k.id DESC LIMIT 3");
if ($stmt_rek) {
    $stmt_rek->bind_param("i", $current_id);
    $stmt_rek->execute();
    $res_rek = $stmt_rek->get_result();
    while ($row = $res_rek->fetch_assoc()) {
        $rekomendasi_kelas[] = $row;
    }
    $stmt_rek->close();
}

$post_title = htmlspecialchars($kelas['title'] ?? 'Kelas Online');
$plain_text = strip_tags($kelas['content'] ?? '');
$post_description = htmlspecialchars(mb_substr(trim(preg_replace('/\s+/', ' ', $plain_text)), 0, 150)) . '...';

if (empty($post_description) || $post_description === '...') {
    $post_description = 'Ikuti kelas ' . $post_title . ' bersama IQRA Society.';
}

$post_keywords = "Kelas Online, " . $post_title . ", IQRA Society, Edukasi Islam";

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
</head>
<body class="bg-gray-50 text-gray-800 antialiased w-full flex flex-col min-h-screen pb-24 lg:pb-0">

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
                <a href="/kelas" class="nav-link relative inline-block text-sm font-bold text-emerald-700 transition py-1 active">Kelas Online</a>
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
            <a href="/buku" class="mobile-link block px-4 py-2.5 rounded-xl font-bold text-gray-700 hover:bg-emerald-50 transition">Buku</a>
            <a href="/kelas" class="mobile-link block px-4 py-2.5 rounded-xl font-bold text-emerald-700 bg-emerald-50 transition">Kelas Online</a>
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

<!-- ================= START MAIN CONTENT ================= -->
<!-- Banner Status Pendaftaran -->
    <?php if (!empty($_GET['status'])): ?>
    <div class="max-w-6xl mx-auto w-full px-4 md:px-6 pt-6">
        <?php if ($_GET['status'] === 'terdaftar'): ?>
        <div class="flex items-start gap-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl p-4 text-sm">
            <i class="fa-solid fa-circle-check mt-0.5"></i>
            <p><strong>Pendaftaran berhasil!</strong> Anda sudah terdaftar di kelas ini. Selamat belajar!</p>
        </div>
        <?php elseif ($_GET['status'] === 'sudah-terdaftar'): ?>
        <div class="flex items-start gap-3 bg-sky-50 border border-sky-200 text-sky-800 rounded-xl p-4 text-sm">
            <i class="fa-solid fa-circle-info mt-0.5"></i>
            <p>Anda sudah terdaftar di kelas ini sebelumnya.</p>
        </div>
        <?php elseif ($_GET['status'] === 'bayar'): ?>
        <div class="flex items-start gap-3 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl p-4 text-sm">
            <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
            <p>Kelas ini berbayar. Silakan selesaikan pembayaran melalui tombol <strong>Bayar</strong> untuk mendapatkan akses kelas.</p>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

<main class="flex-grow max-w-6xl mx-auto w-full py-8 md:py-12 px-4 md:px-6 flex flex-col lg:flex-row gap-8">
    
    <!-- LEFT SIDE / MAIN CONTENT BAR -->
    <div class="w-full lg:w-2/3 space-y-8">
        
        <!-- Breadcrumb & Judul -->
        <div class="space-y-4">
            <nav class="flex text-xs text-emerald-600 font-semibold mb-2">
                <a href="/" class="hover:text-emerald-800">Beranda</a>
                <span class="mx-2 text-gray-300">/</span>
                <a href="/kelas" class="hover:text-emerald-800">Kelas</a>
                <span class="mx-2 text-gray-300">/</span>
                <span class="text-gray-500 truncate max-w-[200px] inline-block"><?= htmlspecialchars($kelas['title']) ?></span>
            </nav>

            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 uppercase tracking-widest shadow-sm">
                <i class="fa-solid fa-graduation-cap"></i> Program Kelas
            </span>
            
            <h1 class="text-3xl md:text-4xl font-extrabold font-zilla text-gray-900 leading-tight">
                <?= htmlspecialchars($kelas['title']) ?>
            </h1>
        </div>

        <!-- Thumbnail / Video Preview -->
        <?php if (!empty($kelas['preview_video']) || !empty($kelas['preview_image'])): ?>
            <div class="rounded-3xl overflow-hidden border border-gray-200 shadow-md bg-black w-full relative aspect-video">
                <?php if (!empty($kelas['preview_video'])): ?>
                    <?php $yt_embed = get_youtube_embed_url($kelas['preview_video']); ?>
                    <?php if ($yt_embed): ?>
                        <iframe src="<?= htmlspecialchars($yt_embed) ?>" class="w-full h-full border-0 absolute inset-0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    <?php else: ?>
                        <video controls class="w-full h-full absolute inset-0 bg-black">
                            <source src="<?= htmlspecialchars($kelas['preview_video']) ?>">
                            Browser Anda tidak mendukung pemutaran video.
                        </video>
                    <?php endif; ?>
                <?php else: ?>
                    <img src="<?= htmlspecialchars($kelas['preview_image']) ?>" alt="Pratinjau Materi" class="w-full h-full object-cover absolute inset-0">
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Ringkasan Mobile (Hidden di LG, Pindah ke Sidebar) -->
        <div class="lg:hidden grid grid-cols-2 gap-3 bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
            <div class="bg-gray-50 p-3 rounded-xl border border-gray-100 flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-calendar-days"></i>
                </div>
                <div>
                    <p class="text-[10px] text-gray-400 font-bold uppercase">Pertemuan</p>
                    <p class="text-sm font-bold text-gray-800"><?= (int)$kelas['session_count'] ?> Sesi</p>
                </div>
            </div>
            <div class="bg-emerald-50 p-3 rounded-xl border border-emerald-100 flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-emerald-600 text-white flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-tag"></i>
                </div>
                <div>
                    <p class="text-[10px] text-emerald-800 font-bold uppercase">Biaya</p>
                    <p class="text-sm font-black text-emerald-900">
                        <?= $kelas['price'] == 0 ? 'GRATIS' : 'Rp ' . number_format($kelas['price'], 0, ',', '.') ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Tab Navigasi Konten -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden" x-data="{ tab: 'deskripsi' }">
            <div class="flex border-b border-gray-100 overflow-x-auto no-scrollbar">
                <button @click="tab = 'deskripsi'" :class="{'border-emerald-600 text-emerald-800': tab === 'deskripsi', 'border-transparent text-gray-500 hover:text-gray-700': tab !== 'deskripsi'}" class="px-6 py-4 text-sm font-bold border-b-2 whitespace-nowrap transition">
                    <i class="fa-solid fa-book-open mr-2"></i> Deskripsi Kelas
                </button>
                <button @click="tab = 'silabus'" :class="{'border-emerald-600 text-emerald-800': tab === 'silabus', 'border-transparent text-gray-500 hover:text-gray-700': tab !== 'silabus'}" class="px-6 py-4 text-sm font-bold border-b-2 whitespace-nowrap transition">
                    <i class="fa-solid fa-list-ol mr-2"></i> Materi Silabus
                </button>
            </div>

            <!-- Tab Deskripsi -->
            <div x-show="tab === 'deskripsi'" x-transition class="p-6 md:p-8">
                <!-- Teks Preview (Benefit/Tujuan) jika ada -->
                <?php if (!empty($kelas['preview_content'])): ?>
                    <div class="mb-8 p-6 bg-emerald-50/50 rounded-2xl border border-emerald-100">
                        <h3 class="text-sm font-bold text-emerald-900 mb-4 flex items-center gap-2">
                            <i class="fa-solid fa-bullseye text-emerald-600"></i> Tujuan & Manfaat Kelas
                        </h3>
                        <?= render_formatted_list($kelas['preview_content'], 'bullet') ?>
                    </div>
                <?php endif; ?>

                <!-- Konten Utama (Dari TinyMCE/Quill) -->
                <article class="prose prose-emerald max-w-none text-gray-700 leading-relaxed ql-editor">
                    <?php if (!empty($kelas['content'])): ?>
                        <?= $kelas['content'] ?>
                    <?php else: ?>
                        <p class="text-gray-500 italic">Deskripsi kelas belum diunggah.</p>
                    <?php endif; ?>
                </article>
            </div>

            <!-- Tab Silabus -->
            <div x-show="tab === 'silabus'" x-cloak x-transition class="p-6 md:p-8">
                <?php if (!empty($kelas['syllabus_breakdown'])): ?>
                    <h3 class="text-sm font-bold text-gray-900 mb-6 flex items-center gap-2">
                        <i class="fa-solid fa-list-ol text-emerald-600"></i> Rincian Pertemuan
                    </h3>
                    <?= render_formatted_list($kelas['syllabus_breakdown'], 'numbered') ?>
                <?php else: ?>
                    <div class="text-center py-10 bg-gray-50 rounded-xl border border-gray-100">
                        <i class="fa-regular fa-clipboard text-3xl text-gray-300 mb-3 block"></i>
                        <p class="text-sm text-gray-500">Rincian pertemuan silabus belum disusun oleh pengajar.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Panel Akses Kelas -->
        <?php if ($punya_akses || !empty($sesi_gratis)): ?>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 md:p-8" id="akses-kelas">
            <h3 class="text-sm font-bold text-gray-900 mb-6 flex items-center gap-2">
                <i class="fa-solid <?= $punya_akses ? 'fa-lock-open' : 'fa-eye' ?> text-emerald-600"></i> <?= $punya_akses ? 'Akses Kelas Anda' : 'Pratinjau Materi' ?>
            </h3>
            <?php if ($punya_akses && !empty($kelas['link_url']) && in_array($kelas['delivery_mode'], ['live', 'hybrid'], true)): ?>
            <a href="<?= htmlspecialchars($kelas['link_url']) ?>" target="_blank" rel="noopener" class="flex items-center justify-center gap-2 bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-sm py-3.5 px-4 rounded-xl transition shadow-md mb-6">
                <i class="fa-solid fa-video"></i> Gabung Kelas Live
            </a>
            <?php endif; ?>
            <?php $tampil_sesi = $punya_akses ? $sessions_kelas : $sesi_gratis; ?>
            <?php if (!empty($tampil_sesi)): ?>
            <div class="space-y-4">
                <?php foreach ($tampil_sesi as $s): ?>
                <div class="p-4 bg-gray-50/60 rounded-xl border border-gray-100">
                    <div class="flex items-center gap-2 mb-1.5">
                        <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full <?= $s['tipe'] === 'meeting' ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700' ?>"><?= $s['tipe'] === 'meeting' ? 'Meeting' : 'Video' ?></span>
                        <?php if ((int)$s['is_free'] === 1): ?><span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">Pratinjau</span><?php endif; ?>
                        <?php if (!empty($s['durasi_menit'])): ?><span class="text-[11px] text-gray-400"><?= (int)$s['durasi_menit'] ?> menit</span><?php endif; ?>
                        <?php if ($s['tipe'] === 'meeting' && !empty($s['jadwal_mulai'])): ?><span class="text-[11px] text-gray-400"><?= htmlspecialchars(date('d M Y H:i', strtotime($s['jadwal_mulai']))) ?> WIB</span><?php endif; ?>
                    </div>
                    <p class="font-bold text-sm text-gray-900"><?= htmlspecialchars($s['judul']) ?></p>
                    <?php if (!empty($s['deskripsi'])): ?><p class="text-xs text-gray-500 mt-1 leading-relaxed"><?= nl2br(htmlspecialchars($s['deskripsi'])) ?></p><?php endif; ?>
                    <?php if ($s['tipe'] === 'video'): ?>
                        <?php $vurl = trim($s['video_url'] ?? ''); $yt = $vurl ? get_youtube_embed_url($vurl) : false; ?>
                        <?php if ($yt): ?>
                        <div class="aspect-video mt-3 rounded-xl overflow-hidden bg-black"><iframe src="<?= htmlspecialchars($yt) ?>" class="w-full h-full border-0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>
                        <?php elseif ($vurl): ?>
                        <a href="<?= htmlspecialchars($vurl) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-2 mt-3 text-sm font-bold text-emerald-700 hover:text-emerald-900"><i class="fa-solid fa-circle-play"></i> Tonton Video</a>
                        <?php elseif (!empty($s['video_file'])): ?>
                        <video controls class="w-full mt-3 rounded-xl bg-black"><source src="/uploads/kelas/<?= htmlspecialchars($s['video_file']) ?>"></video>
                        <?php endif; ?>
                    <?php elseif ($punya_akses && !empty($s['meeting_url'])): ?>
                        <a href="<?= htmlspecialchars($s['meeting_url']) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-2 mt-3 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-4 py-2.5 rounded-lg transition"><i class="fa-solid fa-video"></i> Gabung Meeting<?= !empty($s['meeting_platform']) ? ' (' . htmlspecialchars(ucfirst($s['meeting_platform'])) . ')' : '' ?></a>
                        <?php if (!empty($s['meeting_id']) || !empty($s['meeting_pass'])): ?>
                        <p class="text-xs text-gray-500 mt-2"><?php if (!empty($s['meeting_id'])): ?>Meeting ID: <strong><?= htmlspecialchars($s['meeting_id']) ?></strong><?php endif; ?><?php if (!empty($s['meeting_pass'])): ?> &bull; Passcode: <strong><?= htmlspecialchars($s['meeting_pass']) ?></strong><?php endif; ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php elseif ($punya_akses): ?>
            <p class="text-sm text-gray-500 leading-relaxed">Akses Anda sudah aktif. Materi kelas belum ditambahkan oleh pengajar &mdash; sesi akan tampil di sini begitu tersedia.</p>
            <?php endif; ?>
            <?php if (!$punya_akses): ?>
            <p class="text-xs text-gray-400 mt-5">Ini pratinjau gratis. Daftar atau selesaikan pembayaran untuk membuka semua materi kelas.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>

    <!-- RIGHT SIDE / SIDEBAR WIDGET -->
    <div class="w-full lg:w-1/3 space-y-6">
        
        <!-- Sticky Wrapper untuk Sidebar -->
        <div class="sticky top-28 space-y-6">
            
            <!-- Widget Pembelian Kelas (Desktop) -->
            <div class="hidden lg:block bg-white rounded-3xl p-6 border border-gray-100 shadow-xl shadow-gray-100/50">
                <div class="text-center mb-6">
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Biaya Pendaftaran</p>
                    <p class="text-3xl font-black text-emerald-900 font-zilla">
                        <?= $kelas['price'] == 0 ? '<span class="text-emerald-600">GRATIS</span>' : 'Rp ' . number_format($kelas['price'], 0, ',', '.') ?>
                    </p>
                </div>

                <div class="space-y-4 mb-6">
                    <div class="flex items-center gap-3 text-sm text-gray-600">
                        <i class="fa-solid fa-calendar-days w-5 text-center text-blue-500"></i>
                        <span>Total <strong><?= (int)$kelas['session_count'] ?></strong> Sesi Pembelajaran</span>
                    </div>
                    <div class="flex items-center gap-3 text-sm text-gray-600">
                        <i class="fa-solid fa-video w-5 text-center text-red-500"></i>
                        <span>Akses Zoom/Meet Eksternal</span>
                    </div>
                    <div class="flex items-center gap-3 text-sm text-gray-600">
                        <i class="fa-solid fa-certificate w-5 text-center text-amber-500"></i>
                        <span>E-Sertifikat Kelulusan</span>
                    </div>
                </div>

                <?php if ($punya_akses): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl p-4 text-sm text-center">
                    <i class="fa-solid fa-circle-check text-emerald-600 text-lg"></i>
                    <p class="font-bold mt-1"><?= $adalah_pengajar ? 'Anda pengajar kelas ini' : 'Anda sudah memiliki akses kelas ini' ?></p>
                    <a href="#akses-kelas" class="inline-flex items-center gap-2 mt-3 bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs px-4 py-2.5 rounded-lg transition"><i class="fa-solid fa-play"></i> Lihat Materi Kelas</a>
                </div>
                <?php else: ?>
                <?php if ($kelas['price'] > 0): ?>
                    <?php if ($is_logged_in): ?>
                    <button onclick="beliItem('kelas', <?= (int)$kelas['id'] ?>)" class="flex w-full items-center justify-center gap-2 bg-amber-500 hover:bg-amber-600 text-white font-bold text-sm py-4 rounded-xl transition shadow-md hover:shadow-lg mb-3">
                        <i class="fa-solid fa-credit-card"></i> Bayar Rp <?= number_format($kelas['price'], 0, ',', '.') ?>
                    </button>
                    <?php else: ?>
                    <a href="/login.php" class="flex w-full items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-sm py-4 rounded-xl transition mb-3">
                        <i class="fa-solid fa-right-to-bracket"></i> Login untuk Membayar
                    </a>
                    <?php endif; ?>
                <?php endif; ?>
                <form action="daftar_kelas.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="kelas_id" value="<?= (int)$kelas['id'] ?>">
                    <button type="submit" class="flex w-full items-center justify-center gap-2 bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-sm py-4 rounded-xl transition shadow-md hover:shadow-lg">
                        Daftar Kelas Sekarang <i class="fa-solid fa-arrow-right ml-1"></i>
                    </button>
                </form>
                <?php endif; ?>
            </div>

            <!-- Widget Profil Pengajar -->
            <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm text-center">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4 text-left">Tentang Pengajar</p>
                
                <div class="relative w-24 h-24 mx-auto mb-4">
                    <?php if (!empty($kelas['instructor_avatar'])): ?>
                        <img src="../uploads/avatars/<?= htmlspecialchars($kelas['instructor_avatar']) ?>" alt="<?= htmlspecialchars($kelas['instructor_name']) ?>" class="w-full h-full rounded-full object-cover border-4 border-emerald-50 shadow-md">
                    <?php else: ?>
                        <div class="w-full h-full rounded-full bg-emerald-100 text-emerald-800 flex items-center justify-center font-black text-3xl border-4 border-emerald-50 shadow-md">
                            <?= strtoupper(substr($kelas['instructor_name'] ?? 'P', 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <div class="absolute bottom-0 right-0 bg-blue-500 w-6 h-6 rounded-full flex items-center justify-center text-white text-[10px] border-2 border-white shadow-sm" title="Verified Instructor">
                        <i class="fa-solid fa-check"></i>
                    </div>
                </div>

                <h4 class="text-base font-bold text-gray-900 font-zilla"><?= htmlspecialchars($kelas['instructor_name'] ?? 'Pengajar Umum') ?></h4>
                
                <?php if (!empty($kelas['spesialisasi']) || !empty($kelas['pendidikan'])): ?>
                    <div class="flex flex-col items-center gap-1 mt-2 mb-3">
                        <?php if (!empty($kelas['spesialisasi'])): ?>
                            <span class="text-xs font-semibold text-emerald-700 bg-emerald-50 px-3 py-1 rounded-full"><?= htmlspecialchars($kelas['spesialisasi']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($kelas['pendidikan'])): ?>
                            <span class="text-xs font-semibold text-blue-700"><?= htmlspecialchars($kelas['pendidikan']) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <p class="text-xs text-gray-500 leading-relaxed pt-2 border-t border-gray-100">
                    <?= !empty($kelas['aboutme']) ? nl2br(htmlspecialchars($kelas['aboutme'])) : 'Pengajar profesional dan tersertifikasi yang aktif membimbing proses pembelajaran di IQRA Society.' ?>
                </p>
            </div>
            
        </div>
    </div>
</main>
<!-- ================= END MAIN CONTENT ================= -->

<!-- ================= REKOMENDASI SECTION ================= -->
<?php if (!empty($rekomendasi_kelas)): ?>
<section class="max-w-6xl mx-auto w-full px-4 md:px-6 mb-16">
    <div class="border-t border-gray-200 pt-12">
        <h2 class="text-2xl font-bold font-zilla text-gray-900 mb-8 flex items-center gap-3">
            <i class="fa-solid fa-layer-group text-emerald-600"></i> Rekomendasi Kelas Lainnya
        </h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($rekomendasi_kelas as $item): ?>
                <?php $url_link = !empty($item['slug']) ? "" . urlencode($item['slug']) : "" . (int)$item['id']; ?>
                
                <a href="<?= $url_link ?>" class="bg-white rounded-3xl overflow-hidden border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition duration-300 flex flex-col group">
                    <div class="relative h-48 bg-gray-200 overflow-hidden">
                        <?php if(!empty($item['preview_image'])): ?>
                            <img src="<?= htmlspecialchars($item['preview_image']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center bg-emerald-900 text-white/20">
                                <i class="fa-solid fa-book-open-reader text-6xl"></i>
                            </div>
                        <?php endif; ?>
                        <div class="absolute top-3 left-3 bg-white/95 backdrop-blur-sm px-3 py-1.5 rounded-xl shadow-sm text-xs font-bold text-emerald-800 flex items-center gap-1.5">
                            <i class="fa-solid fa-tag"></i> <?= $item['price'] == 0 ? 'GRATIS' : 'Rp ' . number_format($item['price'], 0, ',', '.') ?>
                        </div>
                    </div>
                    
                    <div class="p-6 flex-1 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 mb-3">
                                <i class="fa-solid fa-calendar-days text-blue-500"></i> <?= (int)$item['session_count'] ?> Pertemuan
                            </div>
                            <h3 class="text-lg font-bold font-zilla text-gray-900 group-hover:text-emerald-700 transition line-clamp-2 leading-snug mb-4">
                                <?= htmlspecialchars($item['title']) ?>
                            </h3>
                        </div>
                        
                        <div class="flex items-center gap-3 pt-4 border-t border-gray-100 mt-auto">
                            <?php if (!empty($item['instructor_avatar'])): ?>
                                <img src="../uploads/avatars/<?= htmlspecialchars($item['instructor_avatar']) ?>" alt="Tutor" class="w-8 h-8 rounded-full object-cover">
                            <?php else: ?>
                                <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-xs font-bold">
                                    <?= strtoupper(substr($item['instructor_name'] ?? 'T', 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <span class="text-xs font-medium text-gray-600 truncate"><?= htmlspecialchars($item['instructor_name'] ?? 'Tutor IQRA') ?></span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ================= MOBILE STICKY BOTTOM ACTION BAR ================= -->
<div class="lg:hidden fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-md border-t border-gray-200 p-4 z-50 shadow-[0_-4px_10px_rgba(0,0,0,0.05)]">
    <div class="flex items-center justify-between gap-4 max-w-4xl mx-auto">
        <div class="hidden sm:block">
            <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wide">Biaya Pendaftaran</p>
            <p class="text-base font-black text-emerald-900 font-zilla">
                <?= $kelas['price'] == 0 ? 'GRATIS' : 'Rp ' . number_format($kelas['price'], 0, ',', '.') ?>
            </p>
        </div>

        <?php if ($punya_akses): ?>
            <a href="#akses-kelas" class="flex-1 w-full inline-flex items-center justify-center gap-2 bg-emerald-700 active:bg-emerald-800 text-white font-bold text-sm py-3.5 px-4 rounded-xl shadow-md transition">
                <i class="fa-solid fa-play"></i> Lihat Materi Kelas
            </a>
        <?php elseif ($kelas['price'] > 0): ?>
            <?php if ($is_logged_in): ?>
            <button onclick="beliItem('kelas', <?= (int)$kelas['id'] ?>)" class="flex-1 w-full inline-flex items-center justify-center gap-2 bg-amber-500 active:bg-amber-600 text-white font-bold text-sm py-3.5 px-4 rounded-xl shadow-md transition">
                <i class="fa-solid fa-credit-card"></i> Bayar Rp <?= number_format($kelas['price'], 0, ',', '.') ?>
            </button>
            <?php else: ?>
            <a href="/login.php" class="flex-1 w-full inline-flex items-center justify-center gap-2 bg-gray-100 active:bg-gray-200 text-gray-700 font-bold text-sm py-3.5 px-4 rounded-xl transition">
                <i class="fa-solid fa-right-to-bracket"></i> Login untuk Membayar
            </a>
            <?php endif; ?>
        <?php else: ?>
        <form action="daftar_kelas.php" method="POST" class="flex-1 w-full">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="kelas_id" value="<?= (int)$kelas['id'] ?>">
            <button type="submit" class="w-full inline-flex items-center justify-center gap-2 bg-emerald-700 active:bg-emerald-800 text-white font-bold text-sm py-3.5 px-4 rounded-xl shadow-md transition">
                Daftar Kelas Sekarang <i class="fa-solid fa-arrow-right-to-bracket"></i>
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- ================= START FOOTER ================= -->
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