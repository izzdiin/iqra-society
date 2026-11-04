<?php

require_once __DIR__ . '/../includes/auth_middleware.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Escape output HTML.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * ============================================================
 * VALIDASI REQUEST
 * ============================================================
 */
$username = trim(strtolower($_GET['username'] ?? ''));
$tab = trim(strtolower($_GET['tab'] ?? ''));

$allowed_tabs = [
    'kegiatan',
    'buku',
    'kelas',
    'artikel',
];

if (
    $username === '' ||
    !preg_match('/^[a-z0-9](?:[a-z0-9-]{1,48}[a-z0-9])?$/', $username) ||
    !in_array($tab, $allowed_tabs, true)
) {
    http_response_code(400);
    exit('Invalid request.');
}

/**
 * ============================================================
 * CARI USER
 * ============================================================
 */
$stmt = $conn->prepare("
    SELECT
        id,
        username,
        fullname,
        role
    FROM users
    WHERE username = ?
    LIMIT 1
");

if (!$stmt) {
    http_response_code(500);
    exit('Database error.');
}

$stmt->bind_param('s', $username);
$stmt->execute();

$user = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$user) {
    http_response_code(404);
    exit('Profile not found.');
}

$user_id = (int) $user['id'];

/**
 * ============================================================
 * HELPER UNTUK EMPTY STATE
 * ============================================================
 */
function render_empty_state(
    string $icon,
    string $title,
    string $description
): void {
    ?>
    <div class="text-center py-12 bg-gray-50/70 rounded-2xl border border-dashed border-gray-200">

        <div class="w-12 h-12 mx-auto bg-gray-100 rounded-2xl flex items-center justify-center">
            <i class="fa-solid <?= e($icon) ?> text-gray-400"></i>
        </div>

        <p class="mt-3 text-sm font-bold text-gray-600">
            <?= e($title) ?>
        </p>

        <p class="mt-1 text-xs text-gray-400 max-w-sm mx-auto">
            <?= e($description) ?>
        </p>

    </div>
    <?php
}

/**
 * ============================================================
 * TAB: ARTIKEL
 *
 * posts.author_id = users.id
 * Hanya artikel published yang tampil di public profile.
 * ============================================================
 */
if ($tab === 'artikel') {

    $stmt = $conn->prepare("
        SELECT
            id,
            title,
            slug,
            content,
            created_at
        FROM posts
        WHERE author_id = ?
          AND status = 'published'
        ORDER BY created_at DESC, id DESC
        LIMIT 12
    ");

    if (!$stmt) {
        http_response_code(500);
        exit('Database error.');
    }

    $stmt->bind_param('i', $user_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();

        render_empty_state(
            'fa-newspaper',
            'Belum ada artikel',
            'Belum ada artikel yang dipublikasikan oleh pengguna ini.'
        );

        exit;
    }
    ?>

    <div class="space-y-4">

        <?php while ($post = $result->fetch_assoc()): ?>

            <?php
            $excerpt = trim(
                preg_replace(
                    '/\s+/',
                    ' ',
                    strip_tags((string) $post['content'])
                )
            );

            if ($excerpt !== '') {
                $excerpt = mb_substr($excerpt, 0, 150);

                if (mb_strlen($excerpt) >= 150) {
                    $excerpt .= '...';
                }
            }

            $post_url = '/post/' . rawurlencode($post['slug']);
            ?>

            <a
                href="<?= e($post_url) ?>"
                class="block group rounded-2xl bg-gray-50 border border-gray-100 p-4 hover:border-emerald-100 hover:bg-emerald-50/40 transition"
            >

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">

                    <div class="min-w-0 flex-1">

                        <p class="text-[10px] font-bold text-emerald-600 uppercase tracking-wide mb-1">
                            Artikel
                        </p>

                        <h3 class="text-sm font-bold text-gray-800 group-hover:text-emerald-700 transition-colors">
                            <?= e($post['title']) ?>
                        </h3>

                        <?php if ($excerpt !== ''): ?>
                            <p class="mt-1 text-xs text-gray-500 line-clamp-2">
                                <?= e($excerpt) ?>
                            </p>
                        <?php endif; ?>

                    </div>

                    <span class="inline-flex items-center gap-1.5 text-[10px] font-bold text-gray-400 bg-white px-2.5 py-1 rounded-lg border border-gray-100 shrink-0">
                        <i class="fa-regular fa-calendar"></i>
                        <?= e(date('d M Y', strtotime($post['created_at']))) ?>
                    </span>

                </div>

            </a>

        <?php endwhile; ?>

    </div>

    <?php
    $stmt->close();
    exit;
}

/**
 * ============================================================
 * TAB: BUKU
 *
 * section_buku.user_id = pemilik/pengaju buku.
 * Hanya buku approved yang tampil.
 * ============================================================
 */
if ($tab === 'buku') {

    $stmt = $conn->prepare("
        SELECT
            id,
            title,
            cover,
            price,
            link_description,
            created_at
        FROM section_buku
        WHERE user_id = ?
          AND status = 'approved'
        ORDER BY created_at DESC, id DESC
        LIMIT 12
    ");

    if (!$stmt) {
        http_response_code(500);
        exit('Database error.');
    }

    $stmt->bind_param('i', $user_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();

        render_empty_state(
            'fa-book-open',
            'Belum ada buku',
            'Belum ada buku yang disetujui dan terkait dengan profil ini.'
        );

        exit;
    }
    ?>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

        <?php while ($book = $result->fetch_assoc()): ?>

            <?php
            $book_description = trim(
                preg_replace(
                    '/\s+/',
                    ' ',
                    strip_tags((string) $book['link_description'])
                )
            );

            if ($book_description !== '') {
                $book_description = mb_substr($book_description, 0, 120);

                if (mb_strlen($book_description) >= 120) {
                    $book_description .= '...';
                }
            }
            ?>

            <div class="group rounded-2xl bg-gray-50 border border-gray-100 p-4 hover:border-emerald-100 hover:bg-emerald-50/40 transition">

                <div class="flex items-start gap-3">

                    <div class="w-12 h-12 rounded-xl bg-white border border-gray-100 text-emerald-700 flex items-center justify-center shrink-0 shadow-sm group-hover:scale-105 transition-transform">
                        <i class="fa-solid fa-book-open text-lg"></i>
                    </div>

                    <div class="min-w-0 flex-1">

                        <p class="text-[10px] font-bold text-emerald-600 uppercase tracking-wide mb-1">
                            Buku
                        </p>

                        <h3 class="text-sm font-bold text-gray-800 line-clamp-2">
                            <?= e($book['title']) ?>
                        </h3>

                        <?php if ($book_description !== ''): ?>
                            <p class="mt-1 text-xs text-gray-500 line-clamp-2">
                                <?= e($book_description) ?>
                            </p>
                        <?php endif; ?>

                        <?php if ((float) $book['price'] > 0): ?>
                            <p class="mt-2 text-xs font-bold text-emerald-700">
                                Rp <?= e(number_format((float) $book['price'], 0, ',', '.')) ?>
                            </p>
                        <?php else: ?>
                            <p class="mt-2 text-xs font-bold text-emerald-700">
                                Gratis
                            </p>
                        <?php endif; ?>

                    </div>

                </div>

            </div>

        <?php endwhile; ?>

    </div>

    <?php
    $stmt->close();
    exit;
}

/**
 * ============================================================
 * TAB: KEGIATAN
 *
 * section_kegiatan.user_id = pengguna yang membuat/mengajukan
 * kegiatan. Hanya kegiatan approved yang ditampilkan.
 *
 * Catatan:
 * Schema yang diberikan belum mempunyai tabel relasi peserta,
 * sehingga tab ini tidak mengklaim "kegiatan yang diikuti".
 * ============================================================
 */
if ($tab === 'kegiatan') {

    $stmt = $conn->prepare("
        SELECT
            id,
            title,
            event_date,
            execution_type,
            recurring_day,
            event_time,
            location,
            category,
            created_at
        FROM section_kegiatan
        WHERE user_id = ?
          AND status = 'approved'
        ORDER BY
            CASE WHEN event_date IS NULL THEN 1 ELSE 0 END,
            event_date DESC,
            created_at DESC,
            id DESC
        LIMIT 12
    ");

    if (!$stmt) {
        http_response_code(500);
        exit('Database error.');
    }

    $stmt->bind_param('i', $user_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();

        render_empty_state(
            'fa-calendar-xmark',
            'Belum ada kegiatan',
            'Belum ada kegiatan yang disetujui dan terkait dengan profil ini.'
        );

        exit;
    }
    ?>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

        <?php while ($activity = $result->fetch_assoc()): ?>

            <?php
            $date_label = 'Tanggal belum ditentukan';

            if (!empty($activity['event_date'])) {
                $date_label = date(
                    'd M Y',
                    strtotime($activity['event_date'])
                );
            }

            $time_label = '';

            if (!empty($activity['event_time'])) {
                $time_label = date(
                    'H:i',
                    strtotime($activity['event_time'])
                );
            }
            ?>

            <div class="group rounded-2xl bg-gray-50 border border-gray-100 p-4 hover:border-emerald-100 hover:bg-emerald-50/40 transition">

                <div class="flex items-start gap-3">

                    <div class="w-12 h-12 rounded-xl bg-white border border-gray-100 text-emerald-700 flex items-center justify-center shrink-0 shadow-sm group-hover:scale-105 transition-transform">
                        <i class="fa-solid fa-users text-lg"></i>
                    </div>

                    <div class="min-w-0 flex-1">

                        <p class="text-[10px] font-bold text-emerald-600 uppercase tracking-wide mb-1">
                            <?= e($activity['category']) ?>
                        </p>

                        <h3 class="text-sm font-bold text-gray-800 line-clamp-2">
                            <?= e($activity['title']) ?>
                        </h3>

                        <div class="mt-2 space-y-1">

                            <p class="text-xs text-gray-500">
                                <i class="fa-regular fa-calendar mr-1 text-emerald-600"></i>
                                <?= e($date_label) ?>
                            </p>

                            <?php if ($time_label !== ''): ?>
                                <p class="text-xs text-gray-500">
                                    <i class="fa-regular fa-clock mr-1 text-emerald-600"></i>
                                    <?= e($time_label) ?>
                                </p>
                            <?php endif; ?>

                            <p class="text-xs text-gray-500 line-clamp-1">
                                <i class="fa-solid fa-location-dot mr-1 text-emerald-600"></i>
                                <?= e($activity['location']) ?>
                            </p>

                        </div>

                    </div>

                </div>

            </div>

        <?php endwhile; ?>

    </div>

    <?php
    $stmt->close();
    exit;
}

/**
 * ============================================================
 * TAB: KELAS ONLINE
 *
 * section_kelas.instructor_id = users.id.
 * Jadi yang ditampilkan di sini adalah kelas yang diajar user,
 * bukan kelas yang diikuti user.
 *
 * Schema yang diberikan belum memiliki tabel enrollment/peserta.
 * ============================================================
 */
if ($tab === 'kelas') {

    $stmt = $conn->prepare("
        SELECT
            id,
            title,
            slug,
            student_count,
            session_count,
            price,
            preview_content,
            preview_image,
            status,
            created_at
        FROM section_kelas
        WHERE instructor_id = ?
          AND status = 'approved'
        ORDER BY created_at DESC, id DESC
        LIMIT 12
    ");

    if (!$stmt) {
        http_response_code(500);
        exit('Database error.');
    }

    $stmt->bind_param('i', $user_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();

        render_empty_state(
            'fa-chalkboard-user',
            'Belum ada kelas online',
            'Belum ada kelas online yang disetujui dan diajar oleh profil ini.'
        );

        exit;
    }
    ?>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

        <?php while ($class = $result->fetch_assoc()): ?>

            <?php
            $class_description = trim(
                preg_replace(
                    '/\s+/',
                    ' ',
                    strip_tags((string) $class['preview_content'])
                )
            );

            if ($class_description === '') {
                $class_description = 'Kelas online dari pengajar IQRA\' Society.';
            }

            $class_description = mb_substr($class_description, 0, 120);

            if (mb_strlen($class_description) >= 120) {
                $class_description .= '...';
            }

            if (!empty($class['slug'])) {
                $class_url = '/kelas/' . rawurlencode($class['slug']);
            } else {
                $class_url = '/kelas/' . (int) $class['id'];
            }
            ?>

            <a
                href="<?= e($class_url) ?>"
                class="group block rounded-2xl bg-gray-50 border border-gray-100 p-4 hover:border-emerald-100 hover:bg-emerald-50/40 transition"
            >

                <div class="flex items-start gap-3">

                    <div class="w-12 h-12 rounded-xl bg-white border border-gray-100 text-emerald-700 flex items-center justify-center shrink-0 shadow-sm group-hover:scale-105 transition-transform">
                        <i class="fa-solid fa-chalkboard-user text-lg"></i>
                    </div>

                    <div class="min-w-0 flex-1">

                        <p class="text-[10px] font-bold text-emerald-600 uppercase tracking-wide mb-1">
                            Kelas Online
                        </p>

                        <h3 class="text-sm font-bold text-gray-800 group-hover:text-emerald-700 transition-colors line-clamp-2">
                            <?= e($class['title']) ?>
                        </h3>

                        <p class="mt-1 text-xs text-gray-500 line-clamp-2">
                            <?= e($class_description) ?>
                        </p>

                        <div class="flex flex-wrap items-center gap-2 mt-3">

                            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-gray-500 bg-white px-2 py-1 rounded-lg border border-gray-100">
                                <i class="fa-solid fa-users text-emerald-600"></i>
                                <?= e(number_format((int) $class['student_count'], 0, ',', '.')) ?> peserta
                            </span>

                            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-gray-500 bg-white px-2 py-1 rounded-lg border border-gray-100">
                                <i class="fa-solid fa-layer-group text-emerald-600"></i>
                                <?= e((int) $class['session_count']) ?> sesi
                            </span>

                        </div>

                    </div>

                </div>

            </a>

        <?php endwhile; ?>

    </div>

    <?php
    $stmt->close();
    exit;
}

http_response_code(400);
exit('Invalid tab.');
?>
