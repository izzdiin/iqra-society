<?php
$page_title       = "IQRA Society - Membangun Generasi Cinta Ilmu dan Al-Qur'an";
$page_description = "IQRA Society adalah wadah edukasi Islam, pembelajaran Al-Qur'an, kelas online keislaman, serta literasi buku-buku Islam bermutu untuk mencetak generasi Qur'ani.";
$page_keywords    = "IQRA Society, Belajar Al Quran, Kelas Islam Online, Buku Islam, Komunitas Muslim, Literasi Islam";

require_once __DIR__ . '/includes/header.php';
?>

<!-- 1. HERO SECTION WITH STATS OVERLAP -->
<section id="beranda" class="relative bg-white pt-6 pb-12 overflow-hidden">
    <div class="absolute top-0 right-0 bottom-16 sm:bottom-20 w-full lg:w-3/5 z-0 pointer-events-none">
        <img src="asset/img/liko.jpg?auto=format&fit=crop&w=1400&q=80" alt="Komunitas Iqra Society" width="1400" height="700" loading="eager" decoding="async" class="w-full h-full object-cover object-center">
        <div class="absolute inset-0 bg-gradient-to-r from-white via-white/50 to-transparent hidden lg:block"></div>
        <div class="absolute top-0 inset-x-0 h-24 bg-gradient-to-b from-white to-transparent"></div>
        <div class="absolute bottom-0 inset-x-0 h-32 bg-gradient-to-t from-white via-white/60 to-transparent"></div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="grid grid-cols-1 lg:grid-cols-12 items-center gap-8 min-h-[440px]">
            <div class="lg:col-span-7 pt-28 sm:pt-36 lg:pt-8" data-aos="fade-right">
                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-gray-900 leading-[1.15] tracking-tight">
                    Membangun Generasi yang Cinta Ilmu dan <span class="text-emerald-700">Al-Qur'an</span>
                </h1>
                
                <!-- Menggunakan tag <p> dengan teks yang sudah dipangkas rapi -->
                <p class="mt-5 text-sm sm:text-base text-gray-600 max-w-xl leading-relaxed">
                    <?= htmlspecialchars($about_content); ?>
                </p>
                
                <div class="mt-8 flex flex-wrap items-center gap-3 sm:gap-4">
                    <a href="/register" target="_blank" class="min-h-11 bg-emerald-800 hover:bg-emerald-900 focus-visible:ring-2 focus-visible:ring-emerald-300 focus-visible:ring-offset-2 text-white font-bold text-xs sm:text-sm px-5 sm:px-6 py-3.5 rounded-xl shadow-md transition flex items-center gap-2.5">
                        <i class="fa-solid fa-user-plus text-xs"></i>
                        <span>Gabung Komunitas</span>
                    </a>
                    <a href="#donasi" class="min-h-11 bg-white hover:bg-emerald-50 focus-visible:ring-2 focus-visible:ring-emerald-300 focus-visible:ring-offset-2 text-emerald-800 font-bold text-xs sm:text-sm px-5 sm:px-6 py-3.5 rounded-xl border border-emerald-800/40 shadow-sm transition flex items-center gap-2.5">
                        <span>Donasi Sekarang</span>
                        <i class="fa-regular fa-heart text-xs"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- STATS STRIP -->
        <div class="mt-12 relative z-20" data-aos="fade-up">
            <div class="bg-white rounded-2xl sm:rounded-3xl shadow-xl border border-gray-100 p-6 sm:p-8 grid grid-cols-2 lg:grid-cols-4 gap-6 lg:gap-8 divide-y sm:divide-y-0 sm:divide-x divide-gray-100">
                <div class="flex items-center gap-4 pt-4 sm:pt-0 sm:pl-4 first:pl-0 first:pt-0">
                    <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center text-2xl shrink-0">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div>
                        <h3 class="text-xl sm:text-2xl font-extrabold text-gray-900 leading-none">450+</h3>
                        <p class="text-xs text-gray-500 font-medium mt-1">Anggota Komunitas</p>
                    </div>
                </div>

                <div class="flex items-center gap-4 pt-4 sm:pt-0 sm:pl-6">
                    <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center text-2xl shrink-0">
                        <i class="fa-solid fa-book-open"></i>
                    </div>
                    <div>
                        <h3 class="text-xl sm:text-2xl font-extrabold text-gray-900 leading-none">35+</h3>
                        <p class="text-xs text-gray-500 font-medium mt-1">Buku Diterbitkan</p>
                    </div>
                </div>

                <div class="flex items-center gap-4 pt-4 sm:pt-0 sm:pl-6">
                    <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center text-2xl shrink-0">
                        <i class="fa-regular fa-calendar-check"></i>
                    </div>
                    <div>
                        <h3 class="text-xl sm:text-2xl font-extrabold text-gray-900 leading-none">120+</h3>
                        <p class="text-xs text-gray-500 font-medium mt-1">Kegiatan Terlaksana</p>
                    </div>
                </div>

                <div class="flex items-center gap-4 pt-4 sm:pt-0 sm:pl-6">
                    <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center text-2xl shrink-0">
                        <i class="fa-solid fa-graduation-cap"></i>
                    </div>
                    <div>
                        <h3 class="text-xl sm:text-2xl font-extrabold text-gray-900 leading-none">18+</h3>
                        <p class="text-xs text-gray-500 font-medium mt-1">Kelas Online</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 3. SECTION PROGRAM KAMI -->
<section id="program" class="pt-4 pb-4 bg-gray-50 overflow-hidden">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <span class="text-xs font-extrabold text-primary tracking-widest uppercase" data-aos="fade-up">PROGRAM KAMI</span>
        <h2 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-gray-900 mt-2 mb-8 leading-tight" data-aos="fade-up" data-aos-delay="100">
            Wadah untuk Belajar, Berbagi,<br class="hidden sm:inline"> dan Berdampak untuk Umat
        </h2>

        <div class="swiper programSwiper !overflow-visible pb-6 lg:!pb-0" data-aos="fade-up" data-aos-delay="200">
            <!-- Diubah: Menggunakan flex & justify-center untuk desktop agar item otomatis di tengah -->
            <div class="swiper-wrapper lg:!transform-none lg:!grid lg:grid-cols-3 xl:grid-cols-6 lg:gap-4">
                <?php if ($query_program && $query_program->num_rows > 0): ?>
                    <?php while ($row = $query_program->fetch_assoc()): ?>
                        <!-- Diubah: Lebar card disesuaikan secara dinamis di layar besar -->
                        <div class="swiper-slide h-auto lg:!w-auto lg:!mr-0">
                            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition text-center h-full flex flex-col items-center justify-start">
                                <div class="w-14 h-14 rounded-xl bg-emerald-50 text-primary flex items-center justify-center text-2xl mb-4">
                                    <i class="fa-solid <?= get_program_icon($row['title']) ?>"></i>
                                </div>
                                <h3 class="text-sm font-bold text-primary mb-2"><?= htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                                <p class="text-xs text-gray-500 leading-relaxed"><?= htmlspecialchars($row['description'], ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="w-full py-8 text-center text-gray-400 text-xs">
                        Belum ada data program yang aktif.
                    </div>
                <?php endif; ?>
            </div>
            <div class="swiper-pagination program-pagination lg:!hidden"></div>
        </div>
    </div>
</section>

<!-- 4. BANNER QUOTE -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-2 pb-4" data-aos="fade-up">
    <div class="bg-[#053c2e] rounded-3xl overflow-hidden shadow-xl grid grid-cols-1 md:grid-cols-12 items-center min-h-[200px]">
        <div class="md:col-span-4 lg:col-span-4 h-48 md:h-full relative overflow-hidden">
            <img src="https://images.unsplash.com/photo-1609599006353-e629aaabfeae?auto=format&fit=crop&w=800&q=80" alt="Al-Qur'an dan Lentera" width="800" height="500" loading="lazy" decoding="async" class="w-full h-full object-cover object-center">
            <div class="absolute inset-0 bg-gradient-to-r from-transparent via-[#053c2e]/40 to-[#053c2e] hidden md:block"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-[#053c2e] via-transparent to-transparent md:hidden"></div>
        </div>

        <div class="md:col-span-8 lg:col-span-8 p-6 sm:p-8 md:p-10 text-white relative flex flex-col justify-between h-full">
            <div class="text-emerald-400 font-serif text-5xl leading-none mb-1 select-none opacity-80">&ldquo;</div>
            <div class="pl-2 sm:pl-4">
                <p class="text-lg sm:text-xl md:text-2xl font-serif tracking-wide leading-relaxed text-gray-100">
                    <?= htmlspecialchars($quote_text, ENT_QUOTES, 'UTF-8') ?>
                </p>
                <p class="text-emerald-300 text-xs sm:text-sm font-sans mt-3 font-medium">
                    &mdash; <?= htmlspecialchars($quote_source, ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>
            <div class="text-emerald-400 font-serif text-5xl leading-none text-right select-none opacity-80 mt-1">&rdquo;</div>
        </div>
    </div>
</section>

<!-- 5. SECTION KEGIATAN TERBARU -->
<section id="kegiatan" class="pt-4 pb-4 bg-white overflow-hidden">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between mb-5 gap-4" data-aos="fade-up">
            <div>
                <span class="text-xs font-extrabold text-primary tracking-widest uppercase bg-emerald-100/70 px-3 py-1 rounded-full">KEGIATAN TERBARU</span>
                <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mt-2">Aktivitas dan Kegiatan Kami</h2>
            </div>
            <a href="kegiatan" class="text-primary hover:text-primary-dark font-bold text-sm flex items-center gap-2">
                <span>Lihat Semua Kegiatan</span>
                <i class="fa-solid fa-arrow-right text-xs"></i>
            </a>
        </div>

        <div class="swiper kegiatanSwiper overflow-hidden pb-8 lg:!pb-0" data-aos="fade-up" data-aos-delay="100">
            <div class="swiper-wrapper lg:!transform-none lg:!grid lg:grid-cols-3 xl:grid-cols-5 lg:gap-6">
                
                <?php if ($result_kegiatan && mysqli_num_rows($result_kegiatan) > 0): ?>
                    <?php while ($kegiatan = mysqli_fetch_assoc($result_kegiatan)): ?>
                        <?php 
                            $timestamp  = strtotime($kegiatan['event_date'] ?? $kegiatan['created_at']);
                            $day        = date('d', $timestamp);
                            $month_names = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                            $month      = $month_names[(int)date('n', $timestamp) - 1];

                            $image_path = 'uploads/kegiatan/' . $kegiatan['image'];
                            if (!empty($kegiatan['image']) && file_exists($image_path)) {
                                $src_image = $image_path;
                            } else {
                                $src_image = 'https://images.unsplash.com/photo-1542810634-71277d95dcbb?auto=format&fit=crop&w=600&q=80';
                            }

                            $loc = strtolower($kegiatan['location'] ?? '');
                            $icon_class = 'fa-solid fa-location-dot text-primary';
                            if (str_contains($loc, 'zoom') || str_contains($loc, 'online')) {
                                $icon_class = 'fa-solid fa-video text-primary';
                            } elseif (str_contains($loc, 'youtube') || str_contains($loc, 'live')) {
                                $icon_class = 'fa-brands fa-youtube text-red-500';
                            }
                        ?>
                        <a href="/kegiatan/<?= htmlspecialchars($kegiatan['slug'] ?? $kegiatan['id']) ?>" class="swiper-slide h-auto block">
                            <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm hover:shadow-md transition flex flex-col h-full">
                                <div class="relative h-44 overflow-hidden shrink-0">
                                    <img src="<?= htmlspecialchars($src_image, ENT_QUOTES, 'UTF-8') ?>" 
                                         alt="<?= htmlspecialchars($kegiatan['title'], ENT_QUOTES, 'UTF-8') ?>" width="600" height="400" loading="lazy" decoding="async" class="w-full h-full object-cover">
                                    
                                    <!-- Badge Tanggal -->
                                    <div class="absolute top-3 right-3 bg-white/95 backdrop-blur px-2.5 py-1 rounded-lg text-center shadow-md">
                                        <span class="block text-xs font-extrabold text-gray-900"><?= $day ?></span>
                                        <span class="block text-xs font-bold text-primary uppercase"><?= $month ?></span>
                                    </div>
                                </div>
                                <div class="p-4 flex-1 flex flex-col justify-between">
                                    <div>
                                        <h3 class="font-bold text-gray-900 text-sm mb-2 line-clamp-1" title="<?= htmlspecialchars($kegiatan['title'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($kegiatan['title'], ENT_QUOTES, 'UTF-8') ?>
                                        </h3>
                                        <p class="text-xs text-gray-500 flex items-center gap-1.5 mb-3">
                                            <i class="<?= $icon_class ?> text-xs"></i>
                                            <span class="truncate"><?= htmlspecialchars($kegiatan['location'] ?? 'Online', ENT_QUOTES, 'UTF-8') ?></span>
                                        </p>
                                    </div>
                                    <div>
                                        <span class="inline-block bg-emerald-50 text-primary text-xs font-bold px-2.5 py-1 rounded-md">
                                            <?= htmlspecialchars($kegiatan['category'] ?? 'Umum', ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-span-full py-8 text-center">
                        <p class="text-xs text-gray-400 italic">Belum ada kegiatan terbaru yang tersedia.</p>
                    </div>
                <?php endif; ?>

            </div>
            <div class="swiper-pagination kegiatan-pagination lg:!hidden"></div>
        </div>
    </div>
</section>

<section id="buku" class="pt-4 pb-3 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8 items-stretch">

            <div class="bg-white rounded-3xl p-5 sm:p-6 lg:p-7 shadow-sm border border-gray-100 relative flex flex-col min-w-0">

                <!-- Header -->
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2 mb-5 sm:mb-6">

                    <div class="min-w-0">
                        <span class="text-xs font-extrabold text-primary tracking-wider uppercase">
                            ILMU UNTUK KEBAIKAN
                        </span>

                        <h3 class="text-xl font-extrabold text-gray-900 mt-1 leading-tight">
                            Ilmu yang Ditulis dan Diajarkan
                        </h3>
                    </div>

                    <a
                        href="buku"
                        class="text-xs font-bold text-primary hover:underline inline-flex items-center gap-1 shrink-0 self-start sm:self-auto mt-1"
                        aria-label="Lihat semua buku"
                    >
                        <span>Lihat Semua Buku</span>
                        <i
                            class="fa-solid fa-arrow-right text-xs"
                            aria-hidden="true"
                        ></i>
                    </a>

                </div>

                <div class="relative min-w-0">

                    <div class="swiper bukuSwiper !px-1">
                        <div class="swiper-wrapper">

                            <?php if ($result_buku && mysqli_num_rows($result_buku) > 0): ?>

                                <?php while ($buku = mysqli_fetch_assoc($result_buku)): ?>

                                    <?php

                                    $bukuTitle = htmlspecialchars(
                                        trim((string)($buku['title'] ?? 'Buku')),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );

                                    $coverRaw = basename(
                                        trim((string)($buku['cover'] ?? ''))
                                    );

                                    $allowedCoverExtensions = [
                                        'jpg',
                                        'jpeg',
                                        'png',
                                        'webp'
                                    ];

                                    $coverExtension = strtolower(
                                        pathinfo($coverRaw, PATHINFO_EXTENSION)
                                    );

                                    $bukuCover = '';

                                    if (
                                        $coverRaw !== '' &&
                                        in_array(
                                            $coverExtension,
                                            $allowedCoverExtensions,
                                            true
                                        )
                                    ) {
                                        $bukuCover = htmlspecialchars(
                                            $coverRaw,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                    }

                                    $bukuPrice = (float)($buku['price'] ?? 0);
                                    ?>

                                    <a href="/buku/<?= htmlspecialchars($buku['slug'] ?? $buku['id']) ?>" class="swiper-slide min-w-0 block">

                                        <article class="flex flex-col h-full min-w-0">

                                            <!-- Cover -->
                                            <div
                                                class="rounded-2xl h-48 sm:h-52 shadow-md relative overflow-hidden border border-gray-100 bg-gray-100"
                                            >

                                                <?php if ($bukuCover !== ''): ?>

                                                    <img
                                                        src="uploads/buku/<?= $bukuCover ?>"
                                                        alt="Cover <?= $bukuTitle ?>"
                                                        width="400"
                                                        height="520"
                                                        loading="lazy"
                                                        decoding="async"
                                                        class="w-full h-full object-contain"
                                                    >

                                                <?php else: ?>

                                                    <div
                                                        class="w-full h-full flex items-center justify-center text-gray-400 text-xs text-center px-2"
                                                        aria-label="Cover buku tidak tersedia"
                                                    >
                                                        Cover tidak tersedia
                                                    </div>

                                                <?php endif; ?>

                                            </div>

                                            <div class="mt-3 min-w-0">

                                                <h5
                                                    class="text-xs font-bold text-gray-900 leading-tight truncate"
                                                    title="<?= $bukuTitle ?>"
                                                >
                                                    <?= $bukuTitle ?>
                                                </h5>



                                            </div>

                                        </article>

                                    </a>

                                <?php endwhile; ?>

                            <?php else: ?>

                                <div class="swiper-slide">
                                    <div class="py-8 text-center">
                                        <p class="text-xs text-gray-400 italic">
                                            Belum ada data buku.
                                        </p>
                                    </div>
                                </div>

                            <?php endif; ?>

                        </div>
                    </div>

                    <button
                        type="button"
                        class="buku-prev-btn absolute left-0 sm:-left-2 top-1/2 -translate-y-1/2 z-20 w-9 h-9 rounded-full bg-white shadow-lg border border-gray-100 flex items-center justify-center text-gray-600 hover:text-primary transition-all duration-200 disabled:opacity-30 disabled:cursor-not-allowed"
                        aria-label="Buku sebelumnya"
                    >
                        <i
                            class="fa-solid fa-chevron-left text-xs"
                            aria-hidden="true"
                        ></i>
                    </button>

                    <button
                        type="button"
                        class="buku-next-btn absolute right-0 sm:-right-2 top-1/2 -translate-y-1/2 z-20 w-9 h-9 rounded-full bg-white shadow-lg border border-gray-100 flex items-center justify-center text-gray-600 hover:text-primary transition-all duration-200 disabled:opacity-30 disabled:cursor-not-allowed"
                        aria-label="Buku berikutnya"
                    >
                        <i
                            class="fa-solid fa-chevron-right text-xs"
                            aria-hidden="true"
                        ></i>
                    </button>

                </div>

            </div>

            <div
                id="kelas"
                class="bg-white rounded-3xl p-5 sm:p-6 lg:p-7 shadow-sm border border-gray-100 relative flex flex-col min-w-0"
            >

                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2 mb-5 sm:mb-6">

                    <div class="min-w-0">

                        <span class="text-xs font-extrabold text-primary tracking-wider uppercase">
                            BELAJAR BERSAMA
                        </span>

                        <h3 class="text-xl font-extrabold text-gray-900 mt-1 leading-tight">
                            Kelas Online Terpopuler
                        </h3>

                    </div>

                    <a
                        href="kelas"
                        class="text-xs font-bold text-primary hover:underline inline-flex items-center gap-1 shrink-0 self-start sm:self-auto mt-1"
                        aria-label="Lihat semua kelas"
                    >
                        <span>Lihat Semua Kelas</span>
                        <i
                            class="fa-solid fa-arrow-right text-xs"
                            aria-hidden="true"
                        ></i>
                    </a>

                </div>

                <div class="relative min-w-0">

                    <div class="swiper kelasSwiper !px-1">
                        <div class="swiper-wrapper">

                            <?php if ($result_kelas && mysqli_num_rows($result_kelas) > 0): ?>

                                <?php while ($kelas = mysqli_fetch_assoc($result_kelas)): ?>

                                    <?php

                                    $kelasTitle = htmlspecialchars(
                                        trim((string)($kelas['title'] ?? 'Kelas Online')),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );

                                    $instructorName = htmlspecialchars(
                                        trim((string)($kelas['instructor_name'] ?? 'Pengajar')),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );

                                    $price = (float)($kelas['price'] ?? 0);
                                    $studentCount = (int)($kelas['student_count'] ?? 0);
                                    $sessionCount = (int)($kelas['session_count'] ?? 0);

                                    $targetLink = '#';

                                    {
                                        $slug = trim(
                                            (string)($kelas['slug'] ?? '')
                                        );

                                        $id = trim(
                                            (string)($kelas['id'] ?? '')
                                        );

                                        if ($slug !== '') {

                                            $targetLink =
                                                'kelas/' .
                                                rawurlencode($slug);

                                        } elseif ($id !== '') {

                                            $targetLink =
                                                'kelas/' .
                                                rawurlencode($id);

                                        } else {

                                            $targetLink = '#';

                                        }
                                    }

                                    $targetLinkHtml = htmlspecialchars(
                                        $targetLink,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>
                                    <div class="swiper-slide min-w-0">
                                        <article
                                            class="bg-white rounded-2xl p-3 border border-gray-100 shadow-sm flex flex-col justify-between min-h-[270px] h-full text-center min-w-0"
                                        >
                                            <!-- Konten -->
                                            <div class="min-w-0">
                                                <!-- Icon -->
                                                <div
                                                    class="w-9 h-9 rounded-full bg-emerald-50 text-primary flex items-center justify-center text-sm mx-auto mb-2"
                                                >
                                                    <i
                                                        class="fa-solid fa-graduation-cap"
                                                        aria-hidden="true"
                                                    ></i>
                                                </div>
                                                <!-- Judul -->
                                                <h4
                                                    class="font-extrabold text-gray-900 text-xs mb-0.5 leading-tight line-clamp-2 break-words"
                                                    title="<?= $kelasTitle ?>"
                                                >
                                                    <?= $kelasTitle ?>
                                                </h4>
                                                <!-- Pengajar -->
                                                <p
                                                    class="text-xs text-gray-400 mb-2 truncate"
                                                    title="<?= $instructorName ?>"
                                                >
                                                    <?= $instructorName ?>
                                                </p>
                                                <!-- Statistik -->
                                                <div class="space-y-1 text-xs text-gray-500 font-medium mb-2">
                                                    <!-- Pertemuan -->
                                                    <p class="flex items-center justify-center gap-1 min-w-0">
                                                        <i
                                                            class="fa-solid fa-clock text-[9px] shrink-0"
                                                            aria-hidden="true"
                                                        ></i>
                                                        <?php if ($sessionCount > 0): ?>
                                                            <span>
                                                                <?= number_format($sessionCount, 0, ',', '.') ?>
                                                                Pertemuan
                                                            </span>
                                                        <?php else: ?>
                                                            <span>
                                                                Jadwal menyusul
                                                            </span>
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <!-- Footer Card -->
                                            <div class="min-w-0">
                                                <!-- Tombol -->
                                                <?php if ($targetLink !== '#'): ?>
                                                    <a
                                                        href="<?= $targetLinkHtml ?>"

                                                        class="block w-full bg-primary hover:bg-primary-dark text-white text-xs font-bold py-2 px-2 rounded-xl transition-all duration-200 shadow-sm leading-tight break-words"
                                                        aria-label="Lihat kelas <?= $kelasTitle ?>"
                                                    >
                                                        Lihat Kelas
                                                    </a>
                                                <?php else: ?>
                                                    <span
                                                        class="block w-full bg-gray-200 text-gray-400 text-xs font-bold py-2 px-2 rounded-xl leading-tight cursor-not-allowed"
                                                        aria-disabled="true"
                                                    >
                                                        Belum tersedia
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </article>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="swiper-slide">
                                    <div class="py-8 text-center">
                                        <p class="text-xs text-gray-400 italic">
                                            Belum ada kelas yang tersedia.
                                        </p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- Navigation Kelas -->
                    <button
                        type="button"
                        class="kelas-prev-btn absolute left-0 sm:-left-2 top-1/2 -translate-y-1/2 z-20 w-9 h-9 rounded-full bg-white shadow-lg border border-gray-100 flex items-center justify-center text-gray-600 hover:text-primary transition-all duration-200 disabled:opacity-30 disabled:cursor-not-allowed"
                        aria-label="Kelas sebelumnya"
                    >
                        <i
                            class="fa-solid fa-chevron-left text-xs"
                            aria-hidden="true"
                        ></i>
                    </button>
                    <button
                        type="button"
                        class="kelas-next-btn absolute right-0 sm:-right-2 top-1/2 -translate-y-1/2 z-20 w-9 h-9 rounded-full bg-white shadow-lg border border-gray-100 flex items-center justify-center text-gray-600 hover:text-primary transition-all duration-200 disabled:opacity-30 disabled:cursor-not-allowed"
                        aria-label="Kelas berikutnya"
                    >
                        <i
                            class="fa-solid fa-chevron-right text-xs"
                            aria-hidden="true"
                        ></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>

<div x-data="donasiHandler()" x-cloak>

    <!-- 8. BANNER DONASI -->
    <section id="donasi" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4 pb-12" data-aos="zoom-in">
        <div class="bg-stone-100 rounded-3xl overflow-hidden border border-stone-200 grid grid-cols-1 md:grid-cols-12 items-center">
            <div class="md:col-span-5 h-64 md:h-full relative min-h-[260px]">
                <img src="https://images.unsplash.com/photo-1542601906990-b4d3fb778b09?auto=format&fit=crop&w=800&q=80" alt="Dukungan Dakwah" width="800" height="600" loading="lazy" decoding="async" class="w-full h-full object-cover">
            </div>
            <div class="md:col-span-7 p-8 sm:p-12 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                <div>
                    <span class="text-xs font-extrabold text-emerald-800 tracking-widest uppercase bg-emerald-100 px-2.5 py-0.5 rounded-full">DONASI & DUKUNGAN</span>
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mt-2">Mari Jadi Bagian dari Dakwah Ilmu</h2>
                    <p class="text-sm text-gray-600 mt-2 max-w-md">Dukungan Anda akan membantu kami menghadirkan lebih banyak kegiatan, kelas, dan konten bermanfaat untuk umat.</p>
                </div>
                <div class="shrink-0 flex flex-col items-start md:items-end gap-2">
                    <button @click="openModal = true" type="button" class="min-h-11 bg-emerald-700 hover:bg-emerald-800 focus-visible:ring-2 focus-visible:ring-emerald-300 focus-visible:ring-offset-2 text-white font-bold text-sm px-6 py-3 rounded-xl shadow-md transition flex items-center gap-2">
                        <i class="fa-solid fa-heart text-xs text-red-300"></i>
                        <span>Donasi Sekarang</span>
                    </button>
                    <span class="text-xs text-gray-500 font-medium italic">Setiap kebaikan, adalah investasi akhirat.</span>
                </div>
            </div>
        </div>
    </section>

    <!-- MODAL POPUP DONASI (style="display:none" dihapus, dikontrol penuh x-show) -->
    <div x-show="openModal" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm overflow-y-auto">
        
        <div @click.away="openModal = false" class="bg-white rounded-3xl max-w-md w-full p-6 shadow-xl border border-gray-100 space-y-5 relative">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-emerald-50 text-emerald-700 flex items-center justify-center text-xs">
                        <i class="fa-solid fa-heart"></i>
                    </div>
                    <h3 class="text-sm font-bold text-gray-900">Formulir Donasi Dakwah</h3>
                </div>
                <button @click="openModal = false" type="button" class="text-gray-400 hover:text-gray-600 transition text-sm">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Modal Body / Form -->
            <form @submit.prevent="processDonation()" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

                <!-- Pilihan Nominal Cepat -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1.5">Pilih Nominal Donasi</label>
                    <div class="grid grid-cols-3 gap-2">
                        <template x-for="nominal in presetNominals" :key="nominal">
                            <button type="button" 
                                    @click="amount = nominal" 
                                    :class="amount == nominal ? 'bg-emerald-50 border-emerald-600 text-emerald-800 font-bold' : 'bg-gray-50 border-gray-200 text-gray-600 font-medium'"
                                    class="py-2 px-2 text-xs border rounded-xl transition text-center hover:border-emerald-400">
                                <span x-text="'Rp ' + Number(nominal).toLocaleString('id-ID')"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Input Nominal Custom -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Nominal Lain (Rp)</label>
                    <input type="number" x-model="amount" min="10000" placeholder="Minimal Rp 10.000" required
                           class="w-full text-xs px-3 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-emerald-600 font-semibold">
                </div>

                <!-- Input Nama Donatur -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-xs font-bold text-gray-700">Nama Lengkap</label>
                        <?php if ($is_logged_in): ?>
                            <span class="text-[10px] bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded-md font-semibold">
                                <i class="fa-solid fa-circle-check text-[9px]"></i> Terisi Otomatis
                            </span>
                        <?php endif; ?>
                    </div>
                    <input type="text" x-model="donorName" placeholder="Hamba Allah / Nama Anda" required
                           class="w-full text-xs px-3 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-emerald-600 <?= $is_logged_in ? 'bg-gray-50' : '' ?>">
                </div>

                <!-- Input Email -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-xs font-bold text-gray-700">Email</label>
                        <?php if ($is_logged_in): ?>
                            <span class="text-[10px] bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded-md font-semibold">
                                <i class="fa-solid fa-circle-check text-[9px]"></i> Terisi Otomatis
                            </span>
                        <?php endif; ?>
                    </div>
                    <input type="email" x-model="donorEmail" placeholder="email@contoh.com" required
                           class="w-full text-xs px-3 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-emerald-600 <?= $is_logged_in ? 'bg-gray-50' : '' ?>">
                </div>

                <!-- Submit Button -->
                <button type="submit" :disabled="loading" 
                        class="w-full bg-emerald-700 hover:bg-emerald-800 active:bg-emerald-900 disabled:opacity-50 text-white font-bold text-xs py-3 rounded-xl transition shadow-md flex items-center justify-center gap-2">
                    <template x-if="!loading">
                        <span><i class="fa-solid fa-shield-halved mr-1"></i> Lanjut ke Pembayaran</span>
                    </template>
                    <template x-if="loading">
                        <span><i class="fa-solid fa-spinner fa-spin mr-1"></i> Memproses...</span>
                    </template>
                </button>
            </form>
        </div>
    </div>

</div>

<!-- COMPONENT ALPINE.JS -->
<script>
function donasiHandler() {
    return {
        openModal: false,
        loading: false,
        amount: 50000,
        // Gunakan json_encode agar aman dari syntax error string JavaScript
        donorName: <?= json_encode($_SESSION['fullname'] ?? $_SESSION['username'] ?? '') ?>,
        donorEmail: <?= json_encode($_SESSION['email'] ?? '') ?>,
        presetNominals: [25000, 50000, 100000, 250000, 500000, 1000000],

        async processDonation() {
            if (this.amount < 10000) {
                alert('Nominal donasi minimal Rp 10.000');
                return;
            }

            this.loading = true;

            try {
                const response = await fetch('process_donasi.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        amount: this.amount,
                        name: this.donorName,
                        email: this.donorEmail
                    })
                });

                const result = await response.json();

                if (result.success && result.gateway === 'tripay' && result.bayar_url) {
                    // Tripay: redirect ke halaman bayar IQRA
                    window.location.href = result.bayar_url;
                } else if (result.success && result.snap_token) {
                    this.openModal = false;
                    
                    window.snap.pay(result.snap_token, {
                        onSuccess: function(result) { alert('Terima kasih! Donasi Anda berhasil diterima.'); location.reload(); },
                        onPending: function(result) { alert('Menunggu pembayaran Anda.'); location.reload(); },
                        onError: function(result) { alert('Pembayaran gagal atau dibatalkan.'); },
                        onClose: function() { console.log('Customer menutup popup tanpa menyelesaikan pembayaran'); }
                    });
                } else {
                    alert(result.message || 'Gagal memproses transaksi donasi.');
                }
            } catch (err) {
                alert('Terjadi kesalahan jaringan.');
            } finally {
                this.loading = false;
            }
        }
    }
}
</script>

<!-- 10. SECTION PENGURUS -->
<section id="pengurus" class="pt-4 pb-12 bg-gray-50 overflow-hidden">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center" data-aos="fade-up">
        <span class="text-xs font-extrabold text-primary tracking-widest uppercase bg-emerald-100/70 px-2.5 py-0.5 rounded-full">PENGURUS IQRA' SOCIETY</span>
        <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mt-2 mb-6">Bersinergi dalam Kebaikan</h2>

        <div class="swiper pengurusSwiper overflow-hidden pb-8 lg:!pb-0">
            <div class="swiper-wrapper lg:!transform-none lg:!grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 lg:gap-6 lg:justify-center">
                
                <?php if ($result_pengurus && mysqli_num_rows($result_pengurus) > 0): ?>
                    <?php while ($pengurus = mysqli_fetch_assoc($result_pengurus)): ?>
                        <?php 
                            // 1. Cek ketersediaan file avatar
                            $avatar_path = 'uploads/avatars/' . $pengurus['avatar']; // penyesuaian folder upload Anda
                            
                            if (!empty($pengurus['avatar']) && file_exists($avatar_path)) {
                                $src_avatar = $avatar_path;
                            } else {
                                // 2. Fallback Gambar Default dari Unsplash jika avatar kosong / file tidak ada
                                $src_avatar = "https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=300&q=80";
                            }
                        ?>
                        <div class="swiper-slide h-auto">
                            <?php $profil_url = '/' . rawurlencode($pengurus['username'] ?? ''); ?>
                            <a href="<?= htmlspecialchars($profil_url, ENT_QUOTES, 'UTF-8') ?>" class="flex flex-col items-center p-3 bg-white lg:bg-transparent rounded-2xl border lg:border-none border-gray-100 shadow-sm lg:shadow-none h-full hover:shadow-md transition cursor-pointer">
                                <img src="<?= htmlspecialchars($src_avatar, ENT_QUOTES, 'UTF-8') ?>" 
                                     alt="<?= htmlspecialchars($pengurus['fullname'], ENT_QUOTES, 'UTF-8') ?>" width="80" height="80" loading="lazy" decoding="async" class="w-20 h-20 rounded-full object-cover shadow-md mb-3 border-2 border-emerald-500 p-0.5">
                                
                                <h4 class="font-bold text-gray-900 text-xs">
                                    <?= htmlspecialchars($pengurus['fullname'], ENT_QUOTES, 'UTF-8') ?>
                                </h4>
                                
                                <p class="text-xs text-gray-500 mt-0.5">
                                    <?= htmlspecialchars(html_entity_decode($pengurus['spesialisasi'] ?? 'Pengurus', ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            </a>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-span-full py-6">
                        <p class="text-xs text-gray-400 italic">Belum ada data pengurus yang ditampilkan.</p>
                    </div>
                <?php endif; ?>

            </div>
            <div class="swiper-pagination pengurus-pagination lg:!hidden"></div>
        </div>
    </div>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';
?>