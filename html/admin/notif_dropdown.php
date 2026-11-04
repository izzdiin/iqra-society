<?php
// Include bersama: variabel + dropdown notifikasi pending (Kelas, Kegiatan, Buku, Posting)
// Dipakai di semua halaman admin. Membutuhkan $conn dari config.php.
// Set tiap variabel yg belum ada (halaman berbeda set variabel berbeda)
if (!isset($pending_classes))  $pending_classes  = (int)($conn->query("SELECT COUNT(id) FROM section_kelas WHERE status = 'pending'")->fetch_row()[0] ?? 0);
if (!isset($pending_activity)) $pending_activity = (int)($conn->query("SELECT COUNT(id) FROM section_kegiatan WHERE status = 'pending'")->fetch_row()[0] ?? 0);
if (!isset($pending_book))     $pending_book     = (int)($conn->query("SELECT COUNT(id) FROM section_buku WHERE status = 'pending'")->fetch_row()[0] ?? 0);
if (!isset($pending_post))     $pending_post     = (int)($conn->query("SELECT COUNT(id) FROM posts WHERE status = 'pending'")->fetch_row()[0] ?? 0);
if (!isset($pending_withdraw)) $pending_withdraw = (int)($conn->query("SELECT COUNT(id) FROM withdrawals WHERE status = 'requested'")->fetch_row()[0] ?? 0);
$pending_notifications = $pending_classes + $pending_activity + $pending_book + $pending_post + $pending_withdraw;
?>
        <!-- NOTIFIKASI LONCENG -->
        <div class="relative" x-data="{ notifDropdown: false }">
            <button @click="notifDropdown = !notifDropdown" class="relative p-2.5 rounded-xl text-gray-500 hover:text-gray-800 hover:bg-gray-100 transition focus:outline-none" title="Notifikasi">
                <i class="fa-regular fa-bell text-lg"></i>
                <?php if ($pending_notifications > 0): ?>
                    <span class="absolute top-2 right-2 w-2.5 h-2.5 bg-red-500 rounded-full ring-2 ring-white animate-pulse"></span>
                <?php endif; ?>
            </button>
            <div x-show="notifDropdown"
                 @click.outside="notifDropdown = false"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="absolute right-0 mt-2 w-72 bg-white rounded-2xl shadow-xl border border-gray-100 py-3 z-50"
                 style="display: none;">
                <div class="px-4 pb-2 border-b border-gray-100 flex items-center justify-between">
                    <span class="text-xs font-bold text-gray-800">Notifikasi</span>
                    <?php if ($pending_notifications > 0): ?>
                        <span class="bg-red-100 text-red-700 text-[10px] font-bold px-2 py-0.5 rounded-full"><?= $pending_notifications ?> Baru</span>
                    <?php endif; ?>
                </div>
                <div class="divide-y divide-gray-50 max-h-80 overflow-y-auto">
                    <?php if ($pending_notifications > 0): ?>
                        <?php if ($pending_classes > 0): ?>
                        <a href="approve_class.php" class="flex items-start gap-3 p-3 hover:bg-emerald-50/50 transition">
                            <div class="w-8 h-8 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center shrink-0 mt-0.5 text-xs"><i class="fa-solid fa-graduation-cap"></i></div>
                            <div><p class="text-xs font-bold text-gray-800">Moderasi Kelas Baru</p><p class="text-[10px] text-gray-500 mt-0.5">Ada <strong><?= $pending_classes ?></strong> pengajuan kelas baru menunggu persetujuan Anda.</p></div>
                        </a>
                        <?php endif; ?>
                        <?php if ($pending_activity > 0): ?>
                        <a href="manage_kegiatan.php" class="flex items-start gap-3 p-3 hover:bg-emerald-50/50 transition">
                            <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center shrink-0 mt-0.5 text-xs"><i class="fa-solid fa-calendar-days"></i></div>
                            <div><p class="text-xs font-bold text-gray-800">Moderasi Kegiatan Baru</p><p class="text-[10px] text-gray-500 mt-0.5">Ada <strong><?= $pending_activity ?></strong> kegiatan baru menunggu persetujuan.</p></div>
                        </a>
                        <?php endif; ?>
                        <?php if ($pending_book > 0): ?>
                        <a href="manage_buku.php" class="flex items-start gap-3 p-3 hover:bg-emerald-50/50 transition">
                            <div class="w-8 h-8 rounded-full bg-purple-100 text-purple-700 flex items-center justify-center shrink-0 mt-0.5 text-xs"><i class="fa-solid fa-book"></i></div>
                            <div><p class="text-xs font-bold text-gray-800">Moderasi Buku Baru</p><p class="text-[10px] text-gray-500 mt-0.5">Ada <strong><?= $pending_book ?></strong> buku baru menunggu persetujuan.</p></div>
                        </a>
                        <?php endif; ?>
                        <?php if ($pending_withdraw > 0): ?>
                        <a href="bagi_hasil.php" class="flex items-start gap-3 p-3 hover:bg-emerald-50/50 transition">
                            <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0 mt-0.5 text-xs"><i class="fa-solid fa-money-bill-transfer"></i></div>
                            <div><p class="text-xs font-bold text-gray-800">Penarikan Bagi Hasil</p><p class="text-[10px] text-gray-500 mt-0.5">Ada <strong><?= $pending_withdraw ?></strong> pengajuan penarikan menunggu persetujuan.</p></div>
                        </a>
                        <?php endif; ?>
                        <?php if ($pending_post > 0): ?>
                        <a href="manage_post.php" class="flex items-start gap-3 p-3 hover:bg-emerald-50/50 transition">
                            <div class="w-8 h-8 rounded-full bg-pink-100 text-pink-700 flex items-center justify-center shrink-0 mt-0.5 text-xs"><i class="fa-solid fa-newspaper"></i></div>
                            <div><p class="text-xs font-bold text-gray-800">Moderasi Postingan</p><p class="text-[10px] text-gray-500 mt-0.5">Ada <strong><?= $pending_post ?></strong> postingan baru menunggu persetujuan.</p></div>
                        </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="p-4 text-center text-xs text-gray-400">
                            <i class="fa-regular fa-circle-check text-emerald-500 text-base mb-1 block"></i>
                            Tidak ada notifikasi baru.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
