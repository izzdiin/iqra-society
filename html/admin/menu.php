<?php
// Pastikan semua variabel pending tersedia (menu di-include sebelum notif_dropdown)
if (!isset($pending_classes))  $pending_classes  = (int)($conn->query("SELECT COUNT(id) FROM section_kelas WHERE status = 'pending'")->fetch_row()[0] ?? 0);
if (!isset($pending_activity)) $pending_activity = (int)($conn->query("SELECT COUNT(id) FROM section_kegiatan WHERE status = 'pending'")->fetch_row()[0] ?? 0);
if (!isset($pending_book))     $pending_book     = (int)($conn->query("SELECT COUNT(id) FROM section_buku WHERE status = 'pending'")->fetch_row()[0] ?? 0);
if (!isset($pending_post))     $pending_post     = (int)($conn->query("SELECT COUNT(id) FROM posts WHERE status = 'pending'")->fetch_row()[0] ?? 0);
$pending_notifications = $pending_notifications ?? 0;
$pending_classes_badge = $pending_classes ?? $pending_notifications;
$pending_konten_total = $pending_classes + $pending_activity + $pending_book + $pending_post;
$pending_withdraw = 0;
try { $pending_withdraw = (int)($conn->query("SELECT COUNT(id) FROM withdrawals WHERE status = 'requested'")->fetch_row()[0] ?? 0); } catch (Exception $e) {}
$current_page = basename($_SERVER['PHP_SELF']);

$menus = [
    [
        'name' => 'Dashboard',
        'url' => 'index.php',
        'icon' => 'fa-solid fa-chart-line',
        'badge' => 0,
        'super_only' => false
    ],
    [
        'name' => 'Landing Page',
        'url' => 'manage_landing.php',
        'icon' => 'fa-solid fa-layer-group',
        'badge' => 0,
        'super_only' => false
    ],
    [
        'name' => 'Kelola Konten',
        'icon' => 'fa-solid fa-folder-open',
        'badge' => $pending_konten_total,
        'super_only' => false,
        'dropdown' => [
            ['name' => 'Kelas', 'url' => 'manage_class.php', 'icon' => 'fa-solid fa-graduation-cap', 'badge' => $pending_classes_badge],
            ['name' => 'Kegiatan', 'url' => 'manage_kegiatan.php', 'icon' => 'fa-solid fa-calendar-days', 'badge' => $pending_activity ?? 0],
            ['name' => 'Buku', 'url' => 'manage_buku.php', 'icon' => 'fa-solid fa-book', 'badge' => $pending_book ?? 0],
            ['name' => 'Posting', 'url' => 'manage_post.php', 'icon' => 'fa-solid fa-newspaper', 'badge' => $pending_post ?? 0],
        ]
    ],
    [
        'name' => 'Bagi Hasil',
        'url' => 'bagi_hasil.php',
        'icon' => 'fa-solid fa-hand-holding-dollar',
        'badge' => $pending_withdraw,
        'super_only' => false
    ],
    [
        'name' => 'Transaksi',
        'url' => 'transaksi.php',
        'icon' => 'fa-solid fa-receipt',
        'super_only' => false
    ],
    [
        'name' => 'Kelola User',
        'url' => 'users.php',
        'icon' => 'fa-solid fa-users-gear',
        'badge' => 0,
        'super_only' => true
    ],
    [
        'name' => 'Payment Gateway',
        'url' => 'settings_gateway.php',
        'icon' => 'fa-solid fa-credit-card',
        'badge' => 0,
        'super_only' => true
    ],
    [
        'name' => 'WhatsApp Gateway',
        'url' => 'wa_gateway.php',
        'icon' => 'fab fa-whatsapp',
        'badge' => 0,
        'super_only' => true
    ],
    [
        'name' => 'Profile',
        'url' => 'profile.php',
        'icon' => 'fa-solid fa-user-gear',
        'badge' => 0,
        'super_only' => false
    ],
    [
        'name' => 'Log Aktivitas',
        'url' => 'log.php',
        'icon' => 'fa-solid fa-clock-rotate-left',
        'badge' => 0,
        'super_only' => true
    ],
];

$user_role = $_SESSION['role'] ?? 'Guest';
$is_super = in_array($user_role, ['super_administrator', 'super_admin'], true);
?>

<nav class="space-y-1 text-xs font-semibold" x-data="{ openKonten: false }">
    <?php foreach ($menus as $menu): ?>
        <?php
            if ($menu['super_only'] && !$is_super) {
                continue;
            }
            $hasDropdown = !empty($menu['dropdown']);
            $isActive = !$hasDropdown && ($current_page === ($menu['url'] ?? ''));
            $isChildActive = false;
            if ($hasDropdown) {
                foreach ($menu['dropdown'] as $sub) { if ($current_page === $sub['url']) { $isChildActive = true; break; } }
            }
            $linkClass = ($isActive || $isChildActive)
                ? 'flex items-center justify-between px-4 py-3 bg-emerald-800 text-white rounded-xl'
                : 'flex items-center justify-between px-4 py-3 text-emerald-200 hover:bg-emerald-900 rounded-xl transition';
        ?>
        <?php if ($hasDropdown): ?>
        <div>
            <button @click="openKonten = !openKonten" class="<?= $linkClass ?> w-full text-left">
                <div class="flex items-center gap-3">
                    <i class="<?= $menu['icon'] ?>"></i>
                    <span><?= $menu['name'] ?></span>
                </div>
                <div class="flex items-center gap-2">
                    <?php if (($menu['badge'] ?? 0) > 0): ?>
                        <span class="bg-red-500 text-white text-[10px] px-2 py-0.5 rounded-full font-bold"><?= $menu['badge'] ?></span>
                    <?php endif; ?>
                    <i class="fa-solid fa-chevron-down text-[10px] transition-transform" :class="openKonten && 'rotate-180'"></i>
                </div>
            </button>
            <div x-show="openKonten || <?= $isChildActive ? 'true' : 'false' ?>" x-collapse class="ml-4 mt-1 space-y-1 border-l border-emerald-800 pl-2">
                <?php foreach ($menu['dropdown'] as $sub):
                    $subActive = ($current_page === $sub['url']);
                    $subClass = $subActive
                        ? 'flex items-center justify-between px-4 py-2.5 bg-emerald-800 text-white rounded-xl'
                        : 'flex items-center justify-between px-4 py-2.5 text-emerald-200 hover:bg-emerald-900 rounded-xl transition';
                ?>
                <a href="<?= $sub['url'] ?>" class="<?= $subClass ?>">
                    <div class="flex items-center gap-3">
                        <i class="<?= $sub['icon'] ?>"></i>
                        <span><?= $sub['name'] ?></span>
                    </div>
                    <?php if ($sub['badge'] > 0): ?>
                        <span class="bg-red-500 text-white text-[10px] px-2 py-0.5 rounded-full font-bold"><?= $sub['badge'] ?></span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <a href="<?= $menu['url'] ?>" class="<?= $linkClass ?>">
            <div class="flex items-center gap-3">
                <i class="<?= $menu['icon'] ?>"></i>
                <span><?= $menu['name'] ?></span>
            </div>

            <?php if (($menu['badge'] ?? 0) > 0): ?>
                <span class="bg-red-500 text-white text-[10px] px-2 py-0.5 rounded-full font-bold">
                    <?= $menu['badge'] ?>
                </span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>