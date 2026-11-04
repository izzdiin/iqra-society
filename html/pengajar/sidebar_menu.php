<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav class="space-y-1 text-xs font-semibold">
    <a href="index.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition <?= ($current_page == 'index.php') ? 'bg-emerald-800 text-white' : 'text-emerald-200 hover:bg-emerald-900' ?>">
        <i class="fa-solid fa-chart-column"></i> Dashboard
    </a>
    <a href="create_activity.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition <?= ($current_page == 'create_activity.php') ? 'bg-emerald-800 text-white' : 'text-emerald-200 hover:bg-emerald-900' ?>">
        <i class="fa-solid fa-calendar-days"></i> Aktifitas & Kegiatan
    </a>
    <a href="create_class.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition <?= ($current_page == 'create_class.php') ? 'bg-emerald-800 text-white' : 'text-emerald-200 hover:bg-emerald-900' ?>">
        <i class="fa-solid fa-book-quran"></i> Kelas & Kajian
    </a>
    <a href="create_post.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition <?= ($current_page == 'create_post.php') ? 'bg-emerald-800 text-white' : 'text-emerald-200 hover:bg-emerald-900' ?>">
        <i class="fa-solid fa-newspaper"></i> Materi & Artikel
    </a>
    <a href="create_book.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition <?= ($current_page == 'create_book.php') ? 'bg-emerald-800 text-white' : 'text-emerald-200 hover:bg-emerald-900' ?>">
        <i class="fa-solid fa-book"></i> Buku & EBook
    </a>
    <a href="analisa.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition <?= ($current_page == 'analisa.php') ? 'bg-emerald-800 text-white' : 'text-emerald-200 hover:bg-emerald-900' ?>">
        <i class="fa-solid fa-chart-line"></i> Analisa Tren
    </a>
    <a href="discussions.php" class="flex items-center justify-between px-4 py-3 rounded-xl transition <?= ($current_page == 'discussions.php') ? 'bg-emerald-800 text-white' : 'text-emerald-200 hover:bg-emerald-900' ?>">
        <div class="flex items-center gap-3">
            <i class="fa-solid fa-comments"></i> Diskusi & Tanya Jawab
        </div>
        <?php if (($active_discussions ?? 0) > 0): ?>
            <span class="<?= ($current_page == 'discussions.php') ? 'bg-amber-400 text-emerald-950' : 'bg-amber-500 text-white' ?> text-[10px] px-2 py-0.5 rounded-full font-bold"><?= $active_discussions ?></span>
        <?php endif; ?>
    </a>
    <a href="keuangan.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition <?= ($current_page == 'keuangan.php') ? 'bg-emerald-800 text-white' : 'text-emerald-200 hover:bg-emerald-900' ?>">
        <i class="fa-solid fa-wallet"></i> Keuangan
    </a>
    <a href="profile.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition <?= ($current_page == 'profile.php') ? 'bg-emerald-800 text-white' : 'text-emerald-200 hover:bg-emerald-900' ?>">
        <i class="fa-solid fa-user-gear"></i> Profil
    </a>
    <a href="setting.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition <?= ($current_page == 'setting.php') ? 'bg-emerald-800 text-white' : 'text-emerald-200 hover:bg-emerald-900' ?>">
        <i class="fa-solid fa-gears"></i> Pengaturan
    </a>
</nav>