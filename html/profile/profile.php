<?php

define('IQRA_APP', true);

require_once __DIR__ . '/../includes/auth_middleware.php';

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function starts_with(string $value, string $prefix): bool
{
    return strpos($value, $prefix) === 0;
}

/** Validasi URL mini-site: hanya HTTPS/HTTP dan domain resmi platform. */
function normalize_profile_link_url(string $type, string $value): string
{
    $value = trim($value);
    if ($value === '' || strlen($value) > 2048 || preg_match('/[\x00-\x20\x7f]/', $value)) {
        return '';
    }

    if ($type === 'email') {
        if (stripos($value, 'mailto:') === 0) {
            $value = substr($value, 7);
        }
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false || strlen($value) > 254) {
            return '';
        }
        return 'mailto:' . $value;
    }

    if ($type === 'whatsapp') {
        if (preg_match('/^[+()0-9.\-]{8,24}$/', $value)) {
            $digits = preg_replace('/\D+/', '', $value);
            if (strpos($digits, '0') === 0) {
                $digits = '62' . substr($digits, 1);
            }
            return preg_match('/^[1-9][0-9]{7,14}$/', $digits) ? 'https://wa.me/' . $digits : '';
        }
    }

    if (!preg_match('#^https?://#i', $value)) {
        $value = 'https://' . ltrim($value, '/');
    }
    $parts = parse_url($value);
    if (!is_array($parts) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
        return '';
    }
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return '';
    }
    if (isset($parts['port']) && !in_array((int) $parts['port'], [80, 443], true)) {
        return '';
    }
    $host = strtolower(rtrim((string) $parts['host'], '.'));
    $allowed_hosts = [
        'whatsapp' => ['wa.me', 'api.whatsapp.com', 'chat.whatsapp.com'],
        'instagram' => ['instagram.com', 'www.instagram.com'],
        'youtube' => ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be'],
        'facebook' => ['facebook.com', 'www.facebook.com', 'm.facebook.com', 'fb.watch'],
        'telegram' => ['t.me', 'telegram.me', 'www.telegram.me'],
        'tiktok' => ['tiktok.com', 'www.tiktok.com', 'm.tiktok.com'],
        'threads' => ['threads.net', 'www.threads.net'],
    ];
    if (!isset($allowed_hosts[$type]) || !in_array($host, $allowed_hosts[$type], true)) {
        return '';
    }

    if ($type === 'whatsapp') {
        if ($host === 'wa.me') {
            $phone = trim((string) ($parts['path'] ?? ''), '/');
            if (!preg_match('/^[1-9][0-9]{7,14}$/', $phone)) return '';
            return 'https://wa.me/' . $phone;
        }
        if ($host === 'api.whatsapp.com') {
            parse_str((string) ($parts['query'] ?? ''), $query);
            $phone = preg_replace('/\D+/', '', (string) ($query['phone'] ?? ''));
            if (!preg_match('/^[1-9][0-9]{7,14}$/', $phone)) return '';
            return 'https://wa.me/' . $phone;
        }
    }

    // Selalu simpan HTTPS dan buang port default untuk mengurangi variasi URL.
    $path = (string) ($parts['path'] ?? '');
    $query = isset($parts['query']) ? '?' . $parts['query'] : '';
    $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
    return 'https://' . $host . $path . $query . $fragment;
}

/** Hanya menerima nama banner yang dibuat aplikasi; mencegah path traversal. */
function profile_banner_filename(string $storedPath): string
{
    $path = trim($storedPath);
    if (preg_match('~(?:^|/)profile_banners/(profile-[0-9]+-[a-f0-9]{20}\.(?:jpg|png|webp))$~i', $path, $m)) {
        return $m[1];
    }
    return '';
}

function profile_banner_disk_path(string $storedPath): string
{
    $filename = profile_banner_filename($storedPath);
    return $filename === '' ? '' : __DIR__ . '/../uploads/profile_banners/' . $filename;
}

$profile_username = trim(strtolower($_GET['username'] ?? ''));

if (
    $profile_username === '' ||
    !preg_match('/^[a-z0-9](?:[a-z0-9-]{1,48}[a-z0-9])?$/', $profile_username)
) {
    require_once __DIR__ . '/not_found.php';
    exit;
}

/**
 * ============================================================
 * AMBIL DATA PROFILE
 *
 * Data publik saja. Email dan no_hp tidak diperlukan untuk
 * halaman publik.
 * ============================================================
 */
$stmt = $conn->prepare("
    SELECT
        id,
        username,
        fullname,
        pendidikan,
        role,
        spesialisasi,
        aboutme,
        avatar,
        created_at
    FROM users
    WHERE username = ?
    LIMIT 1
");

if (!$stmt) {
    http_response_code(500);
    exit('Database error.');
}

$stmt->bind_param('s', $profile_username);
$stmt->execute();

$profile = $stmt->get_result()->fetch_assoc();

$stmt->close();

/**
 * ============================================================
 * USERNAME HISTORY
 *
 * Username lama akan diarahkan permanen (301) ke username aktif.
 * ============================================================
 */
if (!$profile) {
    $stmt_history = $conn->prepare("
        SELECT
            h.user_id,
            u.username AS current_username
        FROM profile_username_history AS h
        INNER JOIN users AS u
            ON u.id = h.user_id
        WHERE h.username = ?
        LIMIT 1
    ");

    if ($stmt_history) {
        $stmt_history->bind_param('s', $profile_username);
        $stmt_history->execute();

        $history = $stmt_history->get_result()->fetch_assoc();

        $stmt_history->close();

        if (
            $history &&
            !empty($history['current_username'])
        ) {
            $current_username = strtolower(trim($history['current_username']));

            header(
                'Location: /' . rawurlencode($current_username),
                true,
                301
            );

            exit;
        }
    }

    require_once __DIR__ . '/not_found.php';
    exit;
}

/**
 * ============================================================
 * AVATAR
 *
 * Avatar disimpan sebagai:
 * - URL absolut
 * - path absolut
 * - nama file di /uploads/avatars/
 * ============================================================
 */
$default_avatar = '/uploads/avatars/default-avatar.png';

$avatar_value = trim((string) ($profile['avatar'] ?? ''));

if ($avatar_value === '') {
    $avatar_url = $default_avatar;
} elseif (
    starts_with($avatar_value, '/') ||
    starts_with($avatar_value, 'http://') ||
    starts_with($avatar_value, 'https://')
) {
    $avatar_url = $avatar_value;
} else {
    $avatar_url = '/uploads/avatars/' . rawurlencode($avatar_value);
}

/**
 * ============================================================
 * OWNER CHECK
 *
 * Prioritas utama: user_id dari session.
 *
 * Fallback username dipertahankan agar kompatibel dengan sistem
 * login lama yang belum menyimpan $_SESSION['user_id'].
 *
 * Rekomendasi: setelah login berhasil, simpan:
 * $_SESSION['user_id'] = (int)$user['id'];
 * ============================================================
 */
$is_logged_in = (
    isset($_SESSION['is_logged_in']) &&
    $_SESSION['is_logged_in'] === true
);

$is_owner = false;

$session_user_id = isset($_SESSION['user_id'])
    ? (int) $_SESSION['user_id']
    : 0;

if (
    $is_logged_in &&
    $session_user_id > 0
) {
    $is_owner = ($session_user_id === (int) $profile['id']);
} else {
    $session_username = trim(
        strtolower((string) ($_SESSION['username'] ?? ''))
    );

    if (
        $is_logged_in &&
        $session_username !== '' &&
        $profile['username'] === $session_username
    ) {
        $is_owner = true;
    }
}

/**
 * ============================================================
 * MINI-SITE SETTINGS & LINKS
 * ============================================================
 */
$cover_color = '#065f46';
$cover_banner = '';
$profile_links = [];

$stmt_settings = $conn->prepare("
    SELECT cover_color, cover_banner
    FROM profile_microsite_settings
    WHERE user_id = ?
    LIMIT 1
");
if ($stmt_settings) {
    $stmt_settings->bind_param('i', $profile['id']);
    $stmt_settings->execute();
    $settings_row = $stmt_settings->get_result()->fetch_assoc();
    $stmt_settings->close();
    if ($settings_row) {
        if (preg_match('/^#[0-9a-fA-F]{6}$/', (string) $settings_row['cover_color'])) {
            $cover_color = strtolower($settings_row['cover_color']);
        }
        $cover_banner = trim((string) ($settings_row['cover_banner'] ?? ''));
        // Kompatibilitas nilai lama yang tersimpan sebagai '/../uploads/...'.
        $legacy_banner_name = profile_banner_filename($cover_banner);
        if ($legacy_banner_name !== '') $cover_banner = '/uploads/profile_banners/' . $legacy_banner_name;
        elseif ($cover_banner !== '' && strpos($cover_banner, '/uploads/profile_banners/') !== 0) $cover_banner = '';
    }
}

$stmt_links = $conn->prepare("
    SELECT id, title, url, description, icon, sort_order, is_active
    FROM profile_links
    WHERE user_id = ?
    ORDER BY sort_order ASC, id ASC
");
if ($stmt_links) {
    $stmt_links->bind_param('i', $profile['id']);
    $stmt_links->execute();
    $links_result = $stmt_links->get_result();
    while ($link_row = $links_result->fetch_assoc()) {
        $type_by_icon = [
            'fa-brands fa-whatsapp' => 'whatsapp', 'fa-brands fa-instagram' => 'instagram',
            'fa-brands fa-youtube' => 'youtube', 'fa-brands fa-facebook-f' => 'facebook',
            'fa-brands fa-telegram' => 'telegram', 'fa-brands fa-tiktok' => 'tiktok',
            'fa-brands fa-threads' => 'threads', 'fa-regular fa-envelope' => 'email'
        ];
        $link_type_from_icon = $type_by_icon[(string) ($link_row['icon'] ?? '')] ?? '';
        $safe_url = $link_type_from_icon !== '' ? normalize_profile_link_url($link_type_from_icon, (string) ($link_row['url'] ?? '')) : '';
        if ($safe_url === '') {
            // Jangan tampilkan tautan legacy yang tidak memenuhi allowlist keamanan.
            continue;
        }
        $link_row['url'] = $safe_url;
        $profile_links[] = $link_row;
    }
    $stmt_links->close();
}

/**
 * AJAX/POST manager for owner-only mini-site settings and links.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));
    $wants_json = isset($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

    $respond = static function (bool $ok, string $message, array $extra = []) use ($wants_json): void {
        if ($wants_json) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array_merge(['success' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        header('Location: ' . strtok($_SERVER['REQUEST_URI'] ?? '/', '?'));
        exit;
    };

    if (!$is_owner) {
        http_response_code(403);
        $respond(false, 'Anda tidak memiliki akses untuk mengubah profil ini.');
    }

    if (!function_exists('verify_csrf_token') || !verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(419);
        $respond(false, 'Token keamanan tidak valid. Silakan muat ulang halaman.');
    }

    $allowed_types = ['whatsapp','instagram','youtube','facebook','telegram','tiktok','threads','email'];

    $normalize_link = static function (string $type, string $value): string {
        return normalize_profile_link_url($type, $value);
    };

    if ($action === 'save_cover') {
        $color = trim((string) ($_POST['cover_color'] ?? '#065f46'));
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $respond(false, 'Warna cover tidak valid.');
        }

        $current_banner = $cover_banner;
        $new_banner = $current_banner;
        $new_file_path = '';
        $remove_requested = isset($_POST['remove_banner']) && $_POST['remove_banner'] === '1';
        if ($remove_requested) {
            $new_banner = '';
        }

        $file = $_FILES['cover_banner'] ?? null;
        $has_upload = is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($has_upload) {
            if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                $respond(false, 'Upload banner gagal. Silakan pilih file kembali.');
            }
            $size = (int) ($file['size'] ?? 0);
            if ($size < 1 || $size > 5 * 1024 * 1024) {
                $respond(false, 'Ukuran banner harus lebih dari 0 byte dan maksimal 5 MB.');
            }

            $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
            $detected_mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
            if ($finfo) finfo_close($finfo);
            $allowed_mimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $image_info = @getimagesize($file['tmp_name']);
            $image_mime = is_array($image_info) ? (string) ($image_info['mime'] ?? '') : '';
            if (!isset($allowed_mimes[$detected_mime]) || $detected_mime !== $image_mime) {
                $respond(false, 'Banner harus berupa gambar JPG, PNG, atau WebP yang valid.');
            }
            $width = (int) ($image_info[0] ?? 0);
            $height = (int) ($image_info[1] ?? 0);
            if ($width < 1 || $height < 1 || $width > 10000 || $height > 10000 || ($width * $height) > 25000000) {
                $respond(false, 'Dimensi gambar terlalu besar. Maksimal 25 megapiksel.');
            }
            // Decode bila GD tersedia, agar file yang hanya menyamar sebagai gambar ditolak.
            if (function_exists('imagecreatefromstring')) {
                $raw_image = @file_get_contents($file['tmp_name']);
                $decoded = $raw_image !== false ? @imagecreatefromstring($raw_image) : false;
                if ($decoded === false) $respond(false, 'File gambar rusak atau tidak dapat dibaca.');
                imagedestroy($decoded);
            }

            $upload_dir = __DIR__ . '/../uploads/profile_banners';
            if (!is_dir($upload_dir) && !@mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
                $respond(false, 'Folder upload banner tidak dapat dibuat.');
            }
            if (!is_writable($upload_dir)) {
                $respond(false, 'Folder upload banner tidak memiliki izin tulis.');
            }
            // Proteksi Apache: file skrip di folder gambar tidak boleh dieksekusi.
            $htaccess = $upload_dir . '/.htaccess';
            if (!is_file($htaccess)) {
                @file_put_contents($htaccess, "Options -Indexes\n<FilesMatch \"\.(php[0-9]?|phtml|phar|cgi|pl|py|sh)$\">\n  Require all denied\n</FilesMatch>\n");
            }

            try {
                $random = bin2hex(random_bytes(10));
            } catch (Throwable $ex) {
                $respond(false, 'Tidak dapat membuat nama file aman. Coba lagi.');
            }
            $filename = 'profile-' . (int) $profile['id'] . '-' . $random . '.' . $allowed_mimes[$detected_mime];
            $new_file_path = $upload_dir . '/' . $filename;
            if (!move_uploaded_file($file['tmp_name'], $new_file_path)) {
                $respond(false, 'Banner gagal disimpan di server.');
            }
            @chmod($new_file_path, 0644);
            $new_banner = '/uploads/profile_banners/' . $filename;
        }

        $stmt = $conn->prepare("
            INSERT INTO profile_microsite_settings (user_id, cover_color, cover_banner)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE cover_color = VALUES(cover_color), cover_banner = VALUES(cover_banner)
        ");
        if (!$stmt) {
            if ($new_file_path !== '' && is_file($new_file_path)) @unlink($new_file_path);
            $respond(false, 'Database error saat menyiapkan pengaturan cover.');
        }
        $user_id = (int) $profile['id'];
        $stmt->bind_param('iss', $user_id, $color, $new_banner);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            if ($new_file_path !== '' && is_file($new_file_path)) @unlink($new_file_path);
            $respond(false, 'Pengaturan cover gagal disimpan. Banner lama tetap dipertahankan.');
        }

        // Hapus file lama hanya setelah DB berhasil; hanya nama file buatan aplikasi.
        if ($current_banner !== '' && $current_banner !== $new_banner) {
            $old_file = profile_banner_disk_path($current_banner);
            if ($old_file !== '' && is_file($old_file)) @unlink($old_file);
        }
        $respond(true, 'Cover berhasil diperbarui.', ['cover_color' => strtolower($color), 'cover_banner' => $new_banner]);
    }

    if ($action === 'save_link') {
        $id = (int) ($_POST['id'] ?? 0);
        $type = trim(strtolower((string) ($_POST['link_type'] ?? '')));
        $url_input = trim((string) ($_POST['link_url'] ?? $_POST['url'] ?? ''));

        if (!in_array($type, $allowed_types, true)) {
            $respond(false, 'Jenis tautan tidak valid.');
        }

        $platform_titles = [
            'whatsapp' => 'WhatsApp',
            'instagram' => 'Instagram',
            'youtube' => 'YouTube',
            'facebook' => 'Facebook',
            'telegram' => 'Telegram',
            'tiktok' => 'TikTok',
            'threads' => 'Threads',
            'email' => 'Email',
        ];
        $title = $platform_titles[$type];

        $url = $normalize_link($type, $url_input);
        if ($url === '') {
            $respond(false, 'Alamat tautan tidak valid.');
        }

        $icon_map = [
            'whatsapp'  => 'fa-brands fa-whatsapp',
            'instagram' => 'fa-brands fa-instagram',
            'youtube'   => 'fa-brands fa-youtube',
            'facebook'  => 'fa-brands fa-facebook-f',
            'telegram'  => 'fa-brands fa-telegram',
            'tiktok'    => 'fa-brands fa-tiktok',
            'threads'   => 'fa-brands fa-threads',
            'email'     => 'fa-regular fa-envelope',
        ];
        $icon = $icon_map[$type];
        $user_id = (int) $profile['id'];

        if ($id > 0) {
            $stmt = $conn->prepare("
                UPDATE profile_links
                SET title = ?, url = ?, icon = ?
                WHERE id = ? AND user_id = ?
            ");
            if (!$stmt) {
                $respond(false, 'Database error.');
            }
            $stmt->bind_param('sssii', $title, $url, $icon, $id, $user_id);
        } else {
            $stmt_order = $conn->prepare("
                SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_order
                FROM profile_links
                WHERE user_id = ?
            ");
            if (!$stmt_order) {
                $respond(false, 'Database error.');
            }
            $stmt_order->bind_param('i', $user_id);
            $stmt_order->execute();
            $next_order = (int) ($stmt_order->get_result()->fetch_assoc()['next_order'] ?? 1);
            $stmt_order->close();

            $stmt = $conn->prepare("
                INSERT INTO profile_links
                    (user_id, title, url, description, icon, sort_order, is_active)
                VALUES (?, ?, ?, NULL, ?, ?, 1)
            ");
            if (!$stmt) {
                $respond(false, 'Database error.');
            }
            $stmt->bind_param('isssi', $user_id, $title, $url, $icon, $next_order);
        }

        $ok = $stmt->execute();
        $saved_id = $id > 0 ? $id : (int) $conn->insert_id;
        $stmt->close();

        if (!$ok) {
            $respond(false, 'Tautan gagal disimpan.');
        }

        $saved_link = null;
        $stmt_saved = $conn->prepare("
            SELECT id, title, url, description, icon, sort_order, is_active
            FROM profile_links
            WHERE id = ? AND user_id = ?
            LIMIT 1
        ");
        if ($stmt_saved) {
            $stmt_saved->bind_param('ii', $saved_id, $user_id);
            $stmt_saved->execute();
            $saved_link = $stmt_saved->get_result()->fetch_assoc();
            $stmt_saved->close();
        }

        $respond(true, 'Tautan berhasil disimpan.', ['link' => $saved_link]);
    }

    if ($action === 'toggle_link') {
        $id = (int) ($_POST['id'] ?? 0);
        $active = (int) ($_POST['is_active'] ?? 0) === 1 ? 1 : 0;
        $user_id = (int) $profile['id'];
        $stmt = $conn->prepare("UPDATE profile_links SET is_active = ? WHERE id = ? AND user_id = ?");
        if (!$stmt) $respond(false, 'Database error.');
        $stmt->bind_param('iii', $active, $id, $user_id);
        $ok = $stmt->execute();
        $stmt->close();
        $respond($ok, $ok ? 'Status tautan diperbarui.' : 'Status tautan gagal diperbarui.');
    }

    if ($action === 'delete_link') {
        $id = (int) ($_POST['id'] ?? 0);
        $user_id = (int) $profile['id'];
        $stmt = $conn->prepare("DELETE FROM profile_links WHERE id = ? AND user_id = ?");
        if (!$stmt) $respond(false, 'Database error.');
        $stmt->bind_param('ii', $id, $user_id);
        $ok = $stmt->execute();
        $stmt->close();
        $respond($ok, $ok ? 'Tautan dihapus.' : 'Tautan gagal dihapus.');
    }

    if ($action === 'reorder_links') {
        $order = $_POST['order'] ?? [];
        if (!is_array($order) || count($order) > 100) $respond(false, 'Urutan tautan tidak valid.');
        $ids = [];
        foreach ($order as $raw_id) {
            if (!is_scalar($raw_id) || !ctype_digit((string) $raw_id) || (int) $raw_id < 1) {
                $respond(false, 'ID tautan pada urutan tidak valid.');
            }
            $id = (int) $raw_id;
            if (isset($ids[$id])) $respond(false, 'Urutan mengandung ID tautan duplikat.');
            $ids[$id] = true;
        }
        $user_id = (int) $profile['id'];
        $owned_ids = [];
        $owned_stmt = $conn->prepare("SELECT id FROM profile_links WHERE user_id = ? ORDER BY sort_order ASC, id ASC");
        if (!$owned_stmt) $respond(false, 'Database error saat memeriksa tautan.');
        $owned_stmt->bind_param('i', $user_id);
        if (!$owned_stmt->execute()) { $owned_stmt->close(); $respond(false, 'Gagal memeriksa tautan.'); }
        $owned_result = $owned_stmt->get_result();
        while ($owned_row = $owned_result->fetch_assoc()) $owned_ids[] = (int) $owned_row['id'];
        $owned_stmt->close();
        $submitted_ids = array_map('intval', array_keys($ids));
        sort($owned_ids); sort($submitted_ids);
        if ($owned_ids !== $submitted_ids) $respond(false, 'Daftar tautan berubah. Muat ulang halaman sebelum mengurutkan.');

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE profile_links SET sort_order = ? WHERE id = ? AND user_id = ?");
            if (!$stmt) throw new RuntimeException('prepare failed');
            $sort = 1;
            foreach (array_keys($ids) as $link_id) {
                $stmt->bind_param('iii', $sort, $link_id, $user_id);
                if (!$stmt->execute() || $stmt->affected_rows < 0) throw new RuntimeException('update failed');
                $sort++;
            }
            $stmt->close();
            $conn->commit();
        } catch (Throwable $ex) {
            if (isset($stmt) && $stmt instanceof mysqli_stmt) $stmt->close();
            $conn->rollback();
            $respond(false, 'Urutan tautan gagal disimpan. Muat ulang halaman lalu coba lagi.');
        }
        $respond(true, 'Urutan tautan diperbarui.');
    }

    $respond(false, 'Aksi tidak dikenal.');
}

$role_labels = [
    'pembelajar' => 'Pembelajar',
    'pengajar'   => 'Pengajar',
    'pengurus'   => 'Pengurus',
    'admin'      => 'Administrator',
];

$role_key = strtolower(trim((string) ($profile['role'] ?? '')));

$role_label = $role_labels[$role_key]
    ?? ucfirst($profile['role'] ?? 'Anggota');

$display_name = trim((string) ($profile['fullname'] ?? ''));

if ($display_name === '') {
    $display_name = "Pengguna IQRA' Society";
}

$spesialisasi = trim((string) ($profile['spesialisasi'] ?? ''));
$pendidikan   = trim((string) ($profile['pendidikan'] ?? ''));
$aboutme      = trim((string) ($profile['aboutme'] ?? ''));

$title_role = $spesialisasi !== '' ? $spesialisasi : $role_label;
$page_title = $display_name . ' (' . $title_role . ') - IQRA\' Society';

if ($aboutme !== '') {
    $clean_about = trim(preg_replace('/\s+/', ' ', strip_tags($aboutme)));
    $meta_description = mb_substr($clean_about, 0, 155) . (mb_strlen($clean_about) > 155 ? '...' : '');
} else {
    $meta_description = 'Profil resmi ' . $display_name . ' di IQRA\' Society';
    if ($spesialisasi !== '') {
        $meta_description .= ' - Spesialisasi: ' . $spesialisasi;
    }
    if ($pendidikan !== '') {
        $meta_description .= ' (' . $pendidikan . ')';
    }
    $meta_description .= '. Temukan kegiatan, kelas, buku, dan artikel terbaru.';
}

$keywords_array = [
    $display_name,
    '@' . $profile['username'],
    'IQRA Society',
    $role_label,
];

if ($spesialisasi !== '') {
    $spec_list = array_map('trim', explode(',', $spesialisasi));
    $keywords_array = array_merge($keywords_array, $spec_list);
}

if ($pendidikan !== '') {
    $keywords_array[] = $pendidikan;
}

$meta_keywords = implode(', ', array_unique(array_filter($keywords_array)));

// URL dasar kanonis: utamakan konfigurasi server, bukan Host header mentah.
$configured_site_url = '';
if (defined('IQRA_SITE_URL')) {
    $configured_site_url = (string) IQRA_SITE_URL;
} elseif (defined('SITE_URL')) {
    $configured_site_url = (string) SITE_URL;
} elseif (isset($GLOBALS['site_url']) && is_string($GLOBALS['site_url'])) {
    $configured_site_url = $GLOBALS['site_url'];
} else {
    $env_site_url = getenv('IQRA_SITE_URL');
    if (is_string($env_site_url)) $configured_site_url = $env_site_url;
}

$configured_site_url = trim($configured_site_url);
if ($configured_site_url !== '') {
    $site_parts = parse_url($configured_site_url);
    if (!is_array($site_parts) || !in_array(strtolower((string) ($site_parts['scheme'] ?? '')), ['http', 'https'], true) || empty($site_parts['host']) || isset($site_parts['user']) || isset($site_parts['pass'])) {
        $configured_site_url = '';
    } else {
        $site_url = rtrim($configured_site_url, '/');
    }
}

if (!isset($site_url) || $site_url === '') {
    // Fallback kompatibilitas. Atur IQRA_SITE_URL di konfigurasi produksi
    // agar canonical tidak dipengaruhi perubahan Host header/proxy.
    $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
    if (!preg_match('/^(?=.{1,253}$)[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?(?::[0-9]{1,5})?$/i', $host) || strpos($host, '..') !== false) {
        $host = 'localhost';
    }
    $is_https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    $site_url = ($is_https ? 'https://' : 'http://') . $host;
}

$canonical_url = $site_url . '/' . rawurlencode((string) $profile['username']);
$og_url = $canonical_url;
$og_image = preg_match('~^https?://~i', $avatar_url) ? $avatar_url : $site_url . '/' . ltrim($avatar_url, '/');
$organization_logo = $site_url . '/asset/img/logo_kitab.png';
$person_schema = [
    '@context' => 'https://schema.org',
    '@type' => 'ProfilePage',
    'name' => $page_title,
    'url' => $canonical_url,
    'mainEntity' => array_filter([
        '@type' => 'Person',
        'name' => $display_name,
        'alternateName' => '@' . (string) $profile['username'],
        'url' => $canonical_url,
        'image' => $og_image,
        'description' => $meta_description,
        'jobTitle' => $spesialisasi !== '' ? $spesialisasi : null,
        'memberOf' => [
            '@type' => 'Organization',
            'name' => "IQRA' Society",
            'url' => $site_url,
            'logo' => $organization_logo,
        ],
    ], static fn($value) => $value !== null && $value !== ''),
];
$person_schema_json = json_encode($person_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

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
<?php
// Load Midtrans Snap.js
$mid_client_key = '';
$ck_q = $conn->query("SELECT key_value FROM gateway_settings WHERE key_name = 'midtrans_client_key' LIMIT 1");
if ($ck_q && $ck_q->num_rows > 0) { $mid_client_key = $ck_q->fetch_assoc()['key_value']; }
$is_prod_key = (strpos($mid_client_key, 'SB-Mid-client') !== 0);
$snap_js_url = $is_prod_key ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js';
?>
<script type="text/javascript" src="<?= $snap_js_url ?>" data-client-key="<?= htmlspecialchars($mid_client_key) ?>"></script>
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
<main id="main-content" class="flex-1 bg-slate-50 pb-10">
    <div class="mx-auto max-w-3xl overflow-hidden bg-white shadow-sm sm:my-8 sm:rounded-[2rem] sm:border sm:border-slate-100">
        <!-- Mini-site cover -->
        <section
            class="relative h-36 overflow-hidden sm:h-48" data-profile-cover
            style="background-color: <?= e($cover_color) ?>;"
        >
            <?php if ($cover_banner !== ''): ?>
                <img src="<?= e($cover_banner) ?>" alt="Banner profil <?= e($display_name) ?>" width="1200" height="420" class="absolute inset-0 h-full w-full object-cover" fetchpriority="high" decoding="async" onerror="this.style.display='none';">
                <div class="absolute inset-0 bg-gradient-to-t from-black/45 via-black/10 to-black/15" data-cover-overlay></div>
            <?php else: ?>
                <div class="absolute inset-0 opacity-30" style="background: radial-gradient(circle at 15% 20%, #fff 0 2px, transparent 3px), radial-gradient(circle at 80% 70%, #fff 0 2px, transparent 3px); background-size: 42px 42px;"></div>
                <div class="absolute -right-10 -top-16 h-56 w-56 rounded-full border-[28px] border-white/10"></div>
                <div class="absolute -bottom-24 left-1/3 h-52 w-52 rounded-full bg-white/10 blur-2xl"></div>
            <?php endif; ?>
            <?php if ($is_owner): ?>
                <button type="button" id="open-mini-settings" class="absolute right-4 top-4 inline-flex items-center gap-2 rounded-full bg-white px-4 py-2.5 text-xs font-bold shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-white/30" style="color: <?= e($cover_color) ?>;">
                    <i class="fa-solid fa-palette"></i><span>Atur tampilan</span>
                </button>
            <?php endif; ?>
        </section>

        <section class="relative px-5 pb-7 sm:px-8 sm:pb-9">
            <div class="-mt-14 flex flex-col items-center text-center sm:-mt-16">
                <div class="relative rounded-full bg-white p-1.5 shadow-lg ring-1 ring-slate-100">
                    <img src="<?= e($avatar_url) ?>" alt="Foto profil <?= e($display_name) ?>" width="120" height="120" class="h-28 w-28 rounded-full border border-slate-100 bg-slate-100 object-cover sm:h-32 sm:w-32" fetchpriority="high" decoding="async" onerror="this.onerror=null;this.src='<?= e($default_avatar) ?>';">
                    <span class="absolute bottom-1 right-1 flex h-7 w-7 items-center justify-center rounded-full border-2 border-white bg-emerald-600 text-xs text-white" title="Anggota IQRA’ Society"><i class="fa-solid fa-check"></i></span>
                </div>
                <h1 class="mt-4 text-xl font-bold uppercase tracking-[.18em] text-emerald-700"><?= e($display_name) ?></h1>
                <p class="mt-1 text-sm font-medium text-slate-500">@<?= e($profile['username']) ?></p>
                <span class="mt-3 inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3.5 py-1.5 text-xs font-bold text-emerald-800"><i class="fa-solid fa-user-tag"></i><?= e($role_label) ?></span>

                <?php if ($spesialisasi !== '' || $pendidikan !== ''): ?>
                    <div class="mt-4 flex flex-wrap justify-center gap-2">
                        <?php if ($pendidikan !== ''): ?><span class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600"><i class="fa-solid fa-graduation-cap text-emerald-700"></i><?= e($pendidikan) ?></span><?php endif; ?>
                        <?php if ($spesialisasi !== ''): ?><span class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600"><i class="fa-solid fa-star text-emerald-700"></i><?= e($spesialisasi) ?></span><?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($aboutme !== ''): ?>
                    <p class="mx-auto mt-5 max-w-xl whitespace-pre-line text-sm leading-7 text-slate-600"><?= e($aboutme) ?></p>
                <?php else: ?>
                    <p class="mx-auto mt-5 max-w-xl text-sm leading-7 text-slate-500">Temukan kegiatan, buku, kelas, dan tulisan dari <?= e($display_name) ?> di satu tempat.</p>
                <?php endif; ?>

                <div class="mt-3 grid w-full max-w-md grid-cols-2 gap-3">
                    <button type="button" id="share-profile" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-2xl bg-emerald-800 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-900 focus:outline-none focus:ring-4 focus:ring-emerald-100"><i class="fa-solid fa-share-nodes"></i>Bagikan profil</button>
                    <button type="button" id="btn-dukungan" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-2xl border border-emerald-200 bg-white px-4 py-2.5 text-sm font-bold text-emerald-800 transition hover:bg-emerald-50 focus:outline-none focus:ring-4 focus:ring-emerald-100"><i class="fa-solid fa-heart"></i>Kirim Dukungan</button>
                </div>
                <p id="share-feedback" class="mt-1 text-xs font-medium text-emerald-700 empty:hidden" aria-live="polite"></p>
            </div>
        </section>

        <?php
        $link_meta = [
            'fa-brands fa-whatsapp'   => ['type' => 'whatsapp',  'icon' => 'fa-brands fa-whatsapp',  'bg' => 'bg-emerald-50', 'text' => 'text-emerald-800'],
            'fa-brands fa-instagram'  => ['type' => 'instagram', 'icon' => 'fa-brands fa-instagram', 'bg' => 'bg-pink-50',    'text' => 'text-pink-700'],
            'fa-brands fa-youtube'    => ['type' => 'youtube',   'icon' => 'fa-brands fa-youtube',   'bg' => 'bg-red-50',     'text' => 'text-red-700'],
            'fa-brands fa-facebook-f' => ['type' => 'facebook',  'icon' => 'fa-brands fa-facebook-f','bg' => 'bg-blue-50',    'text' => 'text-blue-700'],
            'fa-brands fa-telegram'   => ['type' => 'telegram',  'icon' => 'fa-brands fa-telegram',  'bg' => 'bg-sky-50',     'text' => 'text-sky-700'],
            'fa-brands fa-tiktok'     => ['type' => 'tiktok',    'icon' => 'fa-brands fa-tiktok',    'bg' => 'bg-slate-100',  'text' => 'text-slate-900'],
            'fa-brands fa-threads'    => ['type' => 'threads',   'icon' => 'fa-brands fa-threads',   'bg' => 'bg-slate-100',  'text' => 'text-slate-900'],
            'fa-regular fa-envelope'  => ['type' => 'email',     'icon' => 'fa-regular fa-envelope', 'bg' => 'bg-amber-50',   'text' => 'text-amber-800'],
        ];
        $active_links = array_values(array_filter($profile_links, static fn($item) => (int) $item['is_active'] === 1));
        ?>
        <?php if (!empty($active_links)): ?>
            <section class="-mt-2 px-4 pb-3 sm:px-8" id="public-links-section">
                <?php if ($is_owner): ?>
                    <div class="mb-2 flex justify-end">
                        <button type="button" id="open-links-manager" class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1 text-[11px] font-extrabold text-slate-700 shadow-sm transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-800"><i class="fa-solid fa-sliders"></i> Kelola</button>
                    </div>
                <?php endif; ?>
                <div id="public-profile-links" class="flex w-full flex-wrap justify-center gap-2.5 px-1">
                    <?php foreach ($active_links as $link): ?>
                        <?php $meta = $link_meta[$link['icon']] ?? ['type' => 'custom', 'icon' => 'fa-solid fa-link', 'bg' => 'bg-slate-100', 'text' => 'text-slate-700']; ?>
                        <a href="<?= e($link['url']) ?>" <?= strpos((string) $link['url'], 'mailto:') === 0 ? '' : 'target="_blank" rel="noopener noreferrer"' ?> title="<?= e($link['title']) ?>" aria-label="<?= e($link['title']) ?>" class="group flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-100 bg-white shadow-sm transition hover:-translate-y-0.5 hover:border-slate-200 hover:shadow-md sm:h-12 sm:w-12">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl <?= e($meta['bg']) ?> <?= e($meta['text']) ?>"><i class="<?= e($meta['icon']) ?> text-lg"></i></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="px-4 py-3 sm:px-8 sm:py-4">
            <h2 id="profile-content-heading" class="sr-only">Konten dari <?= e($display_name) ?></h2>
            <nav class="grid grid-cols-4 gap-2" aria-labelledby="profile-content-heading">
                <button type="button" data-tab="kegiatan" class="tab-btn group flex min-h-[72px] flex-col items-center justify-center gap-1.5 rounded-2xl bg-emerald-800 px-2 py-2.5 text-white shadow-sm transition focus:outline-none focus:ring-4 focus:ring-emerald-100" aria-pressed="true"><i class="fa-regular fa-calendar-days text-lg"></i><span class="text-[11px] font-bold sm:text-xs">Kegiatan</span></button>
                <button type="button" data-tab="buku" class="tab-btn group flex min-h-[72px] flex-col items-center justify-center gap-1.5 rounded-2xl bg-amber-50 px-2 py-2.5 text-amber-900 transition hover:bg-amber-100 focus:outline-none focus:ring-4 focus:ring-amber-100" aria-pressed="false"><i class="fa-solid fa-book-open text-lg"></i><span class="text-[11px] font-bold sm:text-xs">Buku</span></button>
                <button type="button" data-tab="kelas" class="tab-btn group flex min-h-[72px] flex-col items-center justify-center gap-2 rounded-2xl bg-sky-50 px-2 py-2.5 text-sky-900 transition hover:bg-sky-100 focus:outline-none focus:ring-4 focus:ring-sky-100" aria-pressed="false"><i class="fa-solid fa-display text-lg"></i><span class="text-[11px] font-bold sm:text-xs">Kelas</span></button>
                <button type="button" data-tab="artikel" class="tab-btn group flex min-h-[72px] flex-col items-center justify-center gap-1.5 rounded-2xl bg-violet-50 px-2 py-2.5 text-violet-900 transition hover:bg-violet-100 focus:outline-none focus:ring-4 focus:ring-violet-100" aria-pressed="false"><i class="fa-regular fa-newspaper text-lg"></i><span class="text-[11px] font-bold sm:text-xs">Artikel</span></button>
            </nav>

            <div id="profile-tab-content" class="mt-4 min-h-[220px]" data-username="<?= e($profile['username']) ?>">
                <div id="profile-tab-loading" class="hidden rounded-2xl border border-slate-100 bg-slate-50 py-12 text-center"><i class="fa-solid fa-circle-notch fa-spin text-2xl text-emerald-700"></i><p class="mt-3 text-sm font-medium text-slate-500">Memuat konten…</p></div>
                <div id="profile-tab-error" class="hidden rounded-2xl border border-rose-100 bg-rose-50 px-4 py-10 text-center"><i class="fa-solid fa-triangle-exclamation text-xl text-rose-500"></i><p class="mt-3 text-sm font-bold text-slate-700">Konten belum dapat dimuat.</p><button type="button" id="profile-tab-retry" class="mt-3 rounded-xl px-4 py-2 text-sm font-bold text-emerald-800 hover:bg-white">Coba lagi</button></div>
                <div id="profile-tab-result"></div>
            </div>
        </section>

        <?php if ($is_owner): ?>
            <!-- OWNER INLINE CONTROLS: pengaturan dibuka sebagai responsive bottom-sheet/modal -->
            <div id="builder-toast" class="pointer-events-none fixed inset-x-4 bottom-5 z-[160] hidden sm:left-auto sm:right-5 sm:inset-x-auto sm:max-w-sm" aria-live="polite">
                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-800 shadow-2xl">
                    <span id="builder-toast-text"></span>
                </div>
            </div>

            <div id="builder-sheet" class="fixed inset-0 z-[140] hidden items-end justify-center bg-slate-950/55 p-0 backdrop-blur-[2px] sm:items-center sm:p-5" role="dialog" aria-modal="true" aria-labelledby="builder-sheet-title">
                <div id="builder-panel" class="flex max-h-[92vh] w-full max-w-2xl flex-col overflow-hidden rounded-t-[2rem] bg-white shadow-2xl sm:max-h-[86vh] sm:rounded-[2rem]">
                    <div class="flex shrink-0 items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 sm:px-6">
                        <div class="min-w-0">
                            <p class="text-[10px] font-extrabold uppercase tracking-[.18em] text-emerald-700">Profile Builder</p>
                            <h2 id="builder-sheet-title" class="mt-0.5 text-lg font-extrabold text-slate-900">Atur tampilan</h2>
                        </div>
                        <button type="button" id="close-builder-sheet" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600 transition hover:bg-slate-200" aria-label="Tutup pengaturan"><i class="fa-solid fa-xmark"></i></button>
                    </div>

                    <div class="flex shrink-0 gap-2 border-b border-slate-100 bg-slate-50/80 px-5 py-3 sm:px-6">
                        <button type="button" data-builder-view="cover" class="builder-view-btn inline-flex items-center gap-2 rounded-xl bg-emerald-800 px-3.5 py-2.5 text-xs font-extrabold text-white"><i class="fa-solid fa-palette"></i> Tampilan</button>
                        <button type="button" data-builder-view="links" class="builder-view-btn inline-flex items-center gap-2 rounded-xl bg-white px-3.5 py-2.5 text-xs font-extrabold text-slate-600 ring-1 ring-slate-200"><i class="fa-solid fa-link"></i> Tautan</button>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5 sm:px-6 sm:py-6">
                        <div id="builder-cover-view" class="builder-view space-y-5">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-emerald-700 shadow-sm"><i class="fa-solid fa-image"></i></span>
                                    <div><h3 class="text-sm font-extrabold text-slate-900">Cover mini-site</h3><p class="mt-0.5 text-xs text-slate-500">Pratinjau langsung berubah sebelum disimpan.</p></div>
                                </div>
                                <div class="mt-4 overflow-hidden rounded-2xl border border-white shadow-sm">
                                    <div id="builder-cover-preview" class="relative h-32 overflow-hidden" style="background-color: <?= e($cover_color) ?>;">
                                        <?php if ($cover_banner !== ''): ?><img id="builder-banner-preview" src="<?= e($cover_banner) ?>" alt="Pratinjau banner" class="absolute inset-0 h-full w-full object-cover"><div id="builder-banner-overlay" data-cover-overlay class="absolute inset-0 bg-gradient-to-t from-black/45 via-black/10 to-black/15"></div><?php else: ?><div id="builder-banner-preview" data-cover-placeholder class="absolute inset-0 opacity-30" style="background: radial-gradient(circle at 15% 20%, #fff 0 2px, transparent 3px), radial-gradient(circle at 80% 70%, #fff 0 2px, transparent 3px); background-size:42px 42px;"></div><?php endif; ?>
                                        <div class="absolute inset-x-0 bottom-0 p-3 text-xs font-bold text-white drop-shadow"><?= e($display_name) ?></div>
                                    </div>
                                </div>
                            </div>

                            <form id="cover-form" enctype="multipart/form-data" class="space-y-4">
                                <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
                                <input type="hidden" name="action" value="save_cover">
                                <input type="hidden" name="ajax" value="1">
                                <div>
                                    <label class="mb-2 block text-xs font-extrabold text-slate-700">Banner</label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <label class="flex min-h-20 cursor-pointer items-center justify-center gap-2 rounded-2xl border border-dashed border-slate-300 bg-white px-3 text-center transition hover:border-emerald-400 hover:bg-emerald-50/40">
                                            <input type="file" name="cover_banner" id="cover_banner" accept="image/jpeg,image/png,image/webp" class="sr-only">
                                            <i class="fa-regular fa-image text-emerald-700"></i><span class="text-xs font-bold text-slate-600">Ganti banner</span>
                                        </label>
                                        <button type="button" id="remove-banner-btn" class="flex min-h-20 items-center justify-center gap-2 rounded-2xl border border-rose-100 bg-rose-50 px-3 text-xs font-bold text-rose-700 transition hover:bg-rose-100"><i class="fa-solid fa-trash-can"></i> Hapus banner</button>
                                    </div>
                                    <p class="mt-2 text-[10px] text-slate-400">JPG, PNG, WebP · maksimal 5 MB.</p>
                                </div>
                                <div>
                                    <label class="mb-2 block text-xs font-extrabold text-slate-700">Warna cover</label>
                                    <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-3">
                                        <input type="color" name="cover_color" id="cover_color" value="<?= e($cover_color) ?>" class="h-11 w-14 cursor-pointer rounded-xl border-0 bg-transparent p-0">
                                        <div class="min-w-0 flex-1"><p class="text-xs font-bold text-slate-700">Warna latar</p><p id="cover-color-value" class="text-xs text-slate-400"><?= e(strtolower($cover_color)) ?></p></div>
                                    </div>
                                </div>
                                <input type="hidden" name="remove_banner" id="remove_banner" value="0">
                                <div class="flex gap-2 pt-1">
                                    <button type="button" id="cancel-builder" class="flex-1 rounded-xl border border-slate-200 px-4 py-3 text-xs font-extrabold text-slate-600">Batal</button>
                                    <button type="submit" class="flex-1 rounded-xl bg-emerald-800 px-4 py-3 text-xs font-extrabold text-white shadow-sm hover:bg-emerald-900"><i class="fa-solid fa-check mr-1"></i> Simpan tampilan</button>
                                </div>
                            </form>
                        </div>

                        <div id="builder-links-view" class="builder-view hidden">
                            <div class="mb-3 flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <h3 class="text-sm font-extrabold text-slate-900">Tautan mini-site</h3>
                                    <p class="mt-0.5 text-[11px] text-slate-500">Geser kartu ke kiri/kanan untuk mengurutkan.</p>
                                </div>
                                <button type="button" id="add-link" class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-xl bg-emerald-800 px-3 text-[11px] font-extrabold text-white shadow-sm hover:bg-emerald-900"><i class="fa-solid fa-plus"></i><span>Tambah</span></button>
                            </div>
                            <div id="links-manager" class="flex gap-2.5 overflow-x-auto px-0.5 pb-2 snap-x snap-mandatory scrollbar-none touch-pan-x overscroll-x-contain">
                                <?php foreach ($profile_links as $link): ?>
                                    <?php $meta = $link_meta[$link['icon']] ?? ['type' => 'custom', 'icon' => 'fa-solid fa-link', 'bg' => 'bg-slate-100', 'text' => 'text-slate-700']; ?>
                                    <div class="link-row group relative flex h-[118px] w-[92px] shrink-0 snap-start flex-col items-center rounded-2xl border border-slate-200 bg-slate-50 p-2 text-center shadow-sm <?= (int) $link['is_active'] !== 1 ? 'opacity-60' : '' ?>" draggable="false" data-id="<?= (int) $link['id'] ?>" data-active="<?= (int) $link['is_active'] ?>">
                                        <button type="button" class="drag-handle absolute left-1/2 top-1 z-10 flex h-5 w-8 -translate-x-1/2 cursor-grab touch-none items-center justify-center rounded-full text-slate-300 hover:bg-white hover:text-slate-500" title="Geser kiri/kanan" aria-label="Geser tautan"><i class="fa-solid fa-ellipsis text-[10px]"></i></button>
                                        <span class="link-icon mt-3 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl <?= e($meta['bg']) ?> <?= e($meta['text']) ?>"><i class="<?= e($meta['icon']) ?> text-lg"></i></span>
                                        <p class="mt-1.5 w-full truncate text-[11px] font-extrabold text-slate-800"><?= e($link['title']) ?></p>
                                        <div class="mt-auto flex w-full items-center justify-center gap-0.5 border-t border-slate-200/70 pt-1.5">
                                            <button type="button" class="move-link-up flex h-6 w-6 items-center justify-center rounded-lg text-slate-400 hover:bg-white" title="Geser kiri"><i class="fa-solid fa-chevron-left text-[9px]"></i></button>
                                            <button type="button" class="toggle-link flex h-6 w-6 items-center justify-center rounded-lg text-slate-500 hover:bg-white" title="Aktif/nonaktif"><i class="fa-solid <?= (int) $link['is_active'] === 1 ? 'fa-eye' : 'fa-eye-slash' ?> text-[10px]"></i></button>
                                            <button type="button" class="edit-link flex h-6 w-6 items-center justify-center rounded-lg text-slate-500 hover:bg-white" data-id="<?= (int) $link['id'] ?>" data-type="<?= e($meta['type'] ?? '') ?>" data-url="<?= e($link['url']) ?>" title="Edit"><i class="fa-solid fa-pen text-[9px]"></i></button>
                                            <button type="button" class="delete-link flex h-6 w-6 items-center justify-center rounded-lg text-rose-500 hover:bg-rose-50" title="Hapus"><i class="fa-solid fa-trash text-[9px]"></i></button>
                                            <button type="button" class="move-link-down flex h-6 w-6 items-center justify-center rounded-lg text-slate-400 hover:bg-white" title="Geser kanan"><i class="fa-solid fa-chevron-right text-[9px]"></i></button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($profile_links)): ?><div id="links-empty" class="flex h-[118px] min-w-[150px] items-center justify-center rounded-2xl border border-dashed border-slate-200 px-4 text-center text-[11px] text-slate-400">Belum ada tautan.<br>Tekan + Tambah.</div><?php endif; ?>
                            </div>
                            <p id="links-status" class="mt-1 min-h-4 text-[11px] font-bold text-emerald-700" aria-live="polite"></p>
                        </div>
                    </div>
                </div>
            </div>

            <div id="link-modal" class="fixed inset-0 z-[155] hidden items-end justify-center bg-slate-950/55 p-0 backdrop-blur-sm sm:items-center sm:p-5" role="dialog" aria-modal="true" aria-labelledby="link-modal-title">
                <div class="w-full max-w-md rounded-t-[2rem] bg-white p-5 shadow-2xl sm:rounded-[2rem] sm:p-6">
                    <div class="flex items-center justify-between gap-3"><div><p class="text-[10px] font-extrabold uppercase tracking-[.16em] text-emerald-700">Profile Builder</p><h3 id="link-modal-title" class="mt-1 text-lg font-extrabold text-slate-900">Tambah tautan</h3></div><button type="button" id="close-link-modal" class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-500"><i class="fa-solid fa-xmark"></i></button></div>
                    <form id="link-form" class="mt-5 space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>"><input type="hidden" name="action" value="save_link"><input type="hidden" name="ajax" value="1"><input type="hidden" name="id" id="link-id" value="0">
                        <div><label class="mb-1.5 block text-xs font-bold text-slate-700">Platform</label><select name="link_type" id="link-type" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-50"><option value="whatsapp">WhatsApp</option><option value="instagram">Instagram</option><option value="youtube">YouTube</option><option value="facebook">Facebook</option><option value="telegram">Telegram</option><option value="tiktok">TikTok</option><option value="threads">Threads</option><option value="email">Email</option></select><p class="mt-1 text-[10px] text-slate-400">Judul otomatis mengikuti platform.</p></div>
                        <div><label class="mb-1.5 block text-xs font-bold text-slate-700">URL / nomor / akun</label><input type="text" name="link_url" id="link-url" placeholder="https://..." required class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-50"><p id="link-help" class="mt-1 text-[10px] text-slate-400">Contoh WhatsApp: 081234567890 · Email: nama@email.com</p></div>
                        <div class="flex gap-2 pt-1"><button type="button" id="cancel-link" class="flex-1 rounded-xl border border-slate-200 px-4 py-3 text-xs font-extrabold text-slate-600">Batal</button><button type="submit" class="flex-1 rounded-xl bg-emerald-800 px-4 py-3 text-xs font-extrabold text-white">Simpan tautan</button></div>
                    </form>
                </div>
            </div>

        <?php endif; ?>
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
<script>
document.addEventListener('DOMContentLoaded', function() {
    const userBtn = document.getElementById('user-dropdown-btn');
    const userMenu = document.getElementById('user-dropdown-menu');

    if (userBtn && userMenu) {
        userBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            userMenu.classList.toggle('hidden');
            let expanded = userBtn.getAttribute('aria-expanded') === 'true' || false;
            userBtn.setAttribute('aria-expanded', !expanded);
        });

        document.addEventListener('click', function(e) {
            if (!userBtn.contains(e.target) && !userMenu.contains(e.target)) {
                userMenu.classList.add('hidden');
                userBtn.setAttribute('aria-expanded', 'false');
            }
        });
    }
});

(function () {
    const shareButton = document.getElementById('share-profile');
    const copyButton = document.getElementById('copy-profile');
    const feedback = document.getElementById('share-feedback');
    const profileUrl = window.location.href;
    function copied() { if (feedback) feedback.textContent = 'Tautan profil berhasil disalin.'; }
    if (shareButton) shareButton.addEventListener('click', async function () {
        if (navigator.share) {
            try { await navigator.share({ title: document.title, text: 'Lihat profil ini di IQRA’ Society', url: profileUrl }); }
            catch (error) { if (error && error.name !== 'AbortError' && feedback) feedback.textContent = 'Berbagi dibatalkan atau tidak tersedia.'; }
        } else {
            try { await navigator.clipboard.writeText(profileUrl); copied(); }
            catch (error) { if (feedback) feedback.textContent = 'Salin alamat dari bilah alamat browser Anda.'; }
        }
    });
    if (copyButton) copyButton.addEventListener('click', async function () {
        try { await navigator.clipboard.writeText(profileUrl); copied(); }
        catch (error) {
            const field = document.createElement('textarea'); field.value = profileUrl; field.setAttribute('readonly', ''); field.style.position = 'fixed'; field.style.opacity = '0'; document.body.appendChild(field); field.select();
            const ok = document.execCommand('copy'); field.remove();
            if (feedback) feedback.textContent = ok ? 'Tautan profil berhasil disalin.' : 'Salin alamat dari bilah alamat browser Anda.';
        }
    });
})();

(function () {
    'use strict';

    const container = document.getElementById('profile-tab-content');
    const result = document.getElementById('profile-tab-result');
    const loading = document.getElementById('profile-tab-loading');
    const error = document.getElementById('profile-tab-error');
    const retry = document.getElementById('profile-tab-retry');
    const buttons = document.querySelectorAll('[data-tab]');

    if (!container || !result || !loading || !error) {
        return;
    }

    const username = container.dataset.username;
    const cache = {};
    let activeTab = 'kegiatan';

    function setActiveButton(tabId) {
        buttons.forEach(function (button) {
            const isActive = button.dataset.tab === tabId;

            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            button.classList.toggle('bg-emerald-800', isActive);
            button.classList.toggle('text-white', isActive);
            button.classList.toggle('shadow-sm', isActive);
            if (isActive) {
                button.classList.remove('bg-amber-50','text-amber-900','bg-sky-50','text-sky-900','bg-violet-50','text-violet-900');
            } else {
                const palette = { kegiatan: ['bg-emerald-50','text-emerald-900'], buku: ['bg-amber-50','text-amber-900'], kelas: ['bg-sky-50','text-sky-900'], artikel: ['bg-violet-50','text-violet-900'] };
                button.classList.remove('bg-emerald-800','text-white','shadow-sm');
                (palette[button.dataset.tab] || []).forEach(function (name) { button.classList.add(name); });
            }
        });
    }

    function showLoading() {
        error.classList.add('hidden');
        result.classList.add('hidden');
        loading.classList.remove('hidden');
    }

    function showResult(html) {
        loading.classList.add('hidden');
        error.classList.add('hidden');
        result.innerHTML = html;
        result.classList.remove('hidden');
    }

    function showError() {
        loading.classList.add('hidden');
        result.classList.add('hidden');
        error.classList.remove('hidden');
    }

    function loadTab(tabId, forceReload) {
        activeTab = tabId;
        setActiveButton(tabId);

        if (!forceReload && cache[tabId]) {
            showResult(cache[tabId]);
            return;
        }

        showLoading();

        const url =
            'profile/profile_tabs.php?username=' +
            encodeURIComponent(username) +
            '&tab=' +
            encodeURIComponent(tabId);

        fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }

                return response.text();
            })
            .then(function (html) {
                cache[tabId] = html;
                showResult(html);
            })
            .catch(function () {
                showError();
            });
    }

    buttons.forEach(function (button) {
        button.addEventListener('click', function () {
            const tabId = button.dataset.tab;

            if (!tabId) {
                return;
            }

            loadTab(tabId, false);
        });
    });

    retry.addEventListener('click', function () {
        loadTab(activeTab, true);
    });

    loadTab('kegiatan', false);
})();
</script>

<?php if ($is_owner): ?>
<script>
(function () {
    'use strict';

    const sheet = document.getElementById('builder-sheet');
    const coverView = document.getElementById('builder-cover-view');
    const linksView = document.getElementById('builder-links-view');
    const sheetTitle = document.getElementById('builder-sheet-title');
    const coverForm = document.getElementById('cover-form');
    const coverColor = document.getElementById('cover_color');
    const colorValue = document.getElementById('cover-color-value');
    const coverPreview = document.getElementById('builder-cover-preview');
    const bannerInput = document.getElementById('cover_banner');
    const removeBanner = document.getElementById('remove_banner');
    const bannerPreview = document.getElementById('builder-banner-preview');
    const bannerOverlay = document.getElementById('builder-banner-overlay');
    const linksManager = document.getElementById('links-manager');
    const linkModal = document.getElementById('link-modal');
    const linkForm = document.getElementById('link-form');
    const linkId = document.getElementById('link-id');
    const linkType = document.getElementById('link-type');
    const linkUrl = document.getElementById('link-url');
    const linkModalTitle = document.getElementById('link-modal-title');
    const csrf = <?= json_encode(generate_csrf_token()) ?>;

    const iconMap = {
        whatsapp: 'fa-brands fa-whatsapp', instagram: 'fa-brands fa-instagram', youtube: 'fa-brands fa-youtube',
        facebook: 'fa-brands fa-facebook-f', telegram: 'fa-brands fa-telegram', tiktok: 'fa-brands fa-tiktok',
        threads: 'fa-brands fa-threads', email: 'fa-regular fa-envelope'
    };
    const styleMap = {
        whatsapp: ['bg-emerald-50','text-emerald-800'], instagram: ['bg-pink-50','text-pink-700'], youtube: ['bg-red-50','text-red-700'],
        facebook: ['bg-blue-50','text-blue-700'], telegram: ['bg-sky-50','text-sky-700'], tiktok: ['bg-slate-100','text-slate-900'],
        threads: ['bg-slate-100','text-slate-900'], email: ['bg-amber-50','text-amber-800']
    };
    const metaByIcon = <?= json_encode($link_meta, JSON_UNESCAPED_SLASHES) ?>;

    function post(data, formData) {
        if (formData) {
            formData.append('csrf_token', csrf); formData.append('ajax', '1');
            return fetch(location.href, {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:formData}).then(r=>r.json());
        }
        const params = new URLSearchParams(); Object.keys(data).forEach(k => {
            if (Array.isArray(data[k])) data[k].forEach(v => params.append(k+'[]', v)); else params.append(k, data[k]);
        }); params.append('csrf_token', csrf); params.append('ajax','1');
        return fetch(location.href,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},body:params}).then(r=>r.json());
    }

    function toast(message, bad) {
        const box=document.getElementById('builder-toast'), text=document.getElementById('builder-toast-text');
        if(!box||!text)return; text.textContent=message; text.parentElement.className='rounded-2xl border px-4 py-3 text-sm font-bold shadow-2xl '+(bad?'border-rose-200 bg-rose-50 text-rose-700':'border-emerald-200 bg-white text-slate-800');
        box.classList.remove('hidden'); clearTimeout(window.__builderToast); window.__builderToast=setTimeout(()=>box.classList.add('hidden'),2600);
    }
    function openSheet(view) {
        sheet.classList.remove('hidden'); sheet.classList.add('flex'); document.body.classList.add('overflow-hidden'); setBuilderView(view || 'cover');
    }
    function closeSheet(){sheet.classList.add('hidden');sheet.classList.remove('flex');document.body.classList.remove('overflow-hidden');}
    function setBuilderView(view){
        const isLinks=view==='links'; coverView.classList.toggle('hidden',isLinks); linksView.classList.toggle('hidden',!isLinks); sheetTitle.textContent=isLinks?'Kelola tautan':'Atur tampilan';
        document.querySelectorAll('.builder-view-btn').forEach(b=>{const on=b.dataset.builderView===view;b.classList.toggle('bg-emerald-800',on);b.classList.toggle('text-white',on);b.classList.toggle('bg-white',!on);b.classList.toggle('text-slate-600',!on);});
    }
    document.getElementById('open-mini-settings')?.addEventListener('click',()=>openSheet('cover'));
    document.getElementById('close-builder-sheet')?.addEventListener('click',closeSheet);
    document.getElementById('cancel-builder')?.addEventListener('click',closeSheet);
    sheet?.addEventListener('click',e=>{if(e.target===sheet)closeSheet();});
    document.querySelectorAll('.builder-view-btn').forEach(b=>b.addEventListener('click',()=>setBuilderView(b.dataset.builderView)));
    document.addEventListener('keydown',e=>{if(e.key==='Escape'){if(!linkModal.classList.contains('hidden'))closeLinkModal();else if(!sheet.classList.contains('hidden'))closeSheet();}});

    coverColor?.addEventListener('input',()=>{coverPreview.style.backgroundColor=coverColor.value;colorValue.textContent=coverColor.value.toLowerCase();});
    bannerInput?.addEventListener('change', function () {
        const file = this.files && this.files[0];
        if (!file) return;
        if (file.size > 5 * 1024 * 1024) {
            toast('Ukuran banner maksimal 5 MB.', true);
            this.value = '';
            return;
        }
        if (!['image/jpeg','image/png','image/webp'].includes(file.type)) {
            toast('Banner harus JPG, PNG, atau WebP.', true);
            this.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = function (event) {
            let img = document.getElementById('builder-banner-preview');
            if (!img || img.tagName !== 'IMG') {
                img = document.createElement('img');
                img.id = 'builder-banner-preview';
                img.alt = 'Pratinjau banner';
                img.className = 'absolute inset-0 h-full w-full object-cover';
                coverPreview.prepend(img);
            }
            img.src = event.target.result;
            img.className = 'absolute inset-0 h-full w-full object-cover';
            const placeholder = coverPreview.querySelector('[data-cover-placeholder]');
            if (placeholder) placeholder.remove();
            if (!document.getElementById('builder-banner-overlay')) {
                const ov = document.createElement('div');
                ov.id = 'builder-banner-overlay';
                ov.className = 'absolute inset-0 bg-gradient-to-t from-black/45 via-black/10 to-black/15';
                coverPreview.appendChild(ov);
            }
            removeBanner.value = '0';
        };
        reader.readAsDataURL(file);
    });
    document.getElementById('remove-banner-btn')?.addEventListener('click',()=>{
        removeBanner.value='1'; bannerInput.value='';
        const img=document.querySelector('#builder-cover-preview img#builder-banner-preview');
        if(img) img.remove();
        document.getElementById('builder-banner-overlay')?.remove();
        let placeholder=coverPreview.querySelector('[data-cover-placeholder]');
        if(!placeholder){
            placeholder=document.createElement('div'); placeholder.id='builder-banner-preview'; placeholder.dataset.coverPlaceholder='';
            placeholder.className='absolute inset-0 opacity-30';
            placeholder.style.cssText='background: radial-gradient(circle at 15% 20%, #fff 0 2px, transparent 3px), radial-gradient(circle at 80% 70%, #fff 0 2px, transparent 3px); background-size:42px 42px;';
            coverPreview.prepend(placeholder);
        }
        toast('Banner ditandai untuk dihapus. Simpan untuk menerapkan.');
    });
    coverForm?.addEventListener('submit', async e => {
        e.preventDefault();
        const submitButton = coverForm.querySelector('button[type="submit"]');
        if (submitButton) submitButton.disabled = true;
        try {
            const fd = new FormData(coverForm);
            const j = await post({}, fd);
            if (!j.success) throw new Error(j.message || 'Gagal menyimpan cover.');
            const color = j.cover_color || coverColor.value;
            coverColor.value = color;
            colorValue.textContent = color;
            coverPreview.style.backgroundColor = color;
            const cover = document.querySelector('[data-profile-cover]');
            if (cover) {
                cover.style.backgroundColor = color;
                let img = cover.querySelector('img');
                let overlay = cover.querySelector('[data-cover-overlay]');
                if (j.cover_banner) {
                    if (!img) {
                        img = document.createElement('img');
                        img.alt = 'Banner profil';
                        img.className = 'absolute inset-0 h-full w-full object-cover';
                        img.loading = 'eager';
                        cover.prepend(img);
                    }
                    img.src = j.cover_banner;
                    img.style.display = '';
                    if (!overlay) {
                        overlay = document.createElement('div');
                        overlay.dataset.coverOverlay = '';
                        overlay.className = 'absolute inset-0 bg-gradient-to-t from-black/45 via-black/10 to-black/15';
                        cover.appendChild(overlay);
                    }
                } else {
                    if (img) img.remove();
                    if (overlay) overlay.remove();
                }
            }
            // Samakan pratinjau dengan hasil yang sudah disimpan.
            let previewImg = coverPreview.querySelector('img#builder-banner-preview');
            let previewOverlay = document.getElementById('builder-banner-overlay');
            const placeholder = coverPreview.querySelector('[data-cover-placeholder]');
            if (j.cover_banner) {
                if (!previewImg) {
                    previewImg = document.createElement('img');
                    previewImg.id = 'builder-banner-preview';
                    previewImg.alt = 'Pratinjau banner';
                    previewImg.className = 'absolute inset-0 h-full w-full object-cover';
                    coverPreview.prepend(previewImg);
                }
                previewImg.src = j.cover_banner;
                placeholder?.remove();
                if (!previewOverlay) {
                    previewOverlay = document.createElement('div');
                    previewOverlay.id = 'builder-banner-overlay';
                    previewOverlay.className = 'absolute inset-0 bg-gradient-to-t from-black/45 via-black/10 to-black/15';
                    coverPreview.appendChild(previewOverlay);
                }
            } else {
                previewImg?.remove();
                previewOverlay?.remove();
                if (!placeholder) {
                    const decor = document.createElement('div');
                    decor.dataset.coverPlaceholder = '';
                    decor.id = 'builder-banner-preview';
                    decor.className = 'absolute inset-0 opacity-30';
                    decor.style.cssText = 'background: radial-gradient(circle at 15% 20%, #fff 0 2px, transparent 3px), radial-gradient(circle at 80% 70%, #fff 0 2px, transparent 3px); background-size:42px 42px;';
                    coverPreview.prepend(decor);
                }
            }
            removeBanner.value = '0';
            bannerInput.value = '';
            closeSheet();
            toast(j.message || 'Tampilan mini-site berhasil diperbarui.');
        } catch (err) {
            toast(err.message || 'Gagal menyimpan tampilan.', true);
        } finally {
            if (submitButton) submitButton.disabled = false;
        }
    });

    function openLinkModal(data){data=data||{};linkId.value=data.id||0;linkType.value=data.type||'whatsapp';linkUrl.value=data.url||'';linkModalTitle.textContent=data.id?'Edit tautan':'Tambah tautan';linkModal.classList.remove('hidden');linkModal.classList.add('flex');setTimeout(()=>linkUrl.focus(),80);}
    function closeLinkModal(){linkModal.classList.add('hidden');linkModal.classList.remove('flex');}
    document.getElementById('add-link')?.addEventListener('click',()=>openLinkModal());document.getElementById('close-link-modal')?.addEventListener('click',closeLinkModal);document.getElementById('cancel-link')?.addEventListener('click',closeLinkModal);linkModal?.addEventListener('click',e=>{if(e.target===linkModal)closeLinkModal();});
    document.querySelectorAll('.edit-link').forEach(b=>b.addEventListener('click',()=>openLinkModal({id:b.dataset.id,type:b.dataset.type,label:b.dataset.label,url:b.dataset.url})));

    function ensureEmpty(){const rows=linksManager.querySelectorAll('.link-row');let empty=document.getElementById('links-empty');if(!rows.length){if(!empty){empty=document.createElement('div');empty.id='links-empty';empty.className='rounded-2xl border border-dashed border-slate-200 py-10 text-center text-xs text-slate-400';empty.textContent='Belum ada tautan.';linksManager.appendChild(empty);}}else empty?.remove();}
    function publicContainer(){
        let box = document.getElementById('public-profile-links');
        if (box) return box;
        const tabsSection = document.querySelector('[data-tab="kegiatan"]')?.closest('section');
        if (!tabsSection) return null;
        const section = document.createElement('section');
        section.id = 'public-links-section';
        section.className = '-mt-2 px-4 pb-3 sm:px-8';
        section.innerHTML = '<div class="mb-2 flex justify-end"><button type="button" id="open-links-manager-dynamic" class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1 text-[11px] font-extrabold text-slate-700 shadow-sm transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-800"><i class="fa-solid fa-sliders"></i> Kelola</button></div><div id="public-profile-links" class="flex w-full flex-wrap justify-center gap-2.5 px-1"></div>';
        tabsSection.before(section);
        section.querySelector('#open-links-manager-dynamic')?.addEventListener('click', () => openSheet('links'));
        return section.querySelector('#public-profile-links');
    }
    function renderPublicLink(link) {
        const meta = metaByIcon[link.icon] || {
            type: 'custom',
            icon: 'fa-solid fa-link',
            bg: 'bg-slate-100',
            text: 'text-slate-700'
        };

        const a = document.createElement('a');
        a.href = link.url;
        a.title = link.title;
        a.setAttribute('aria-label', link.title);
        a.className =
            'public-link group flex h-12 w-12 items-center justify-center ' +
            'rounded-2xl border border-slate-100 bg-white shadow-sm transition ' +
            'hover:-translate-y-0.5 hover:border-slate-200 hover:shadow-md sm:h-14 sm:w-14';

        if (!String(link.url).startsWith('mailto:')) {
            a.target = '_blank';
            a.rel = 'noopener noreferrer';
        }

        a.dataset.id = link.id;
        a.innerHTML = `
            <span class="flex h-10 w-10 items-center justify-center rounded-xl ${meta.bg} ${meta.text}">
                <i class="${meta.icon} text-xl"></i>
            </span>
        `;

        return a;
    }
    function syncPublicLinks(){
        const rows = [...linksManager.querySelectorAll('.link-row[data-active="1"]')];
        let section = document.getElementById('public-links-section');
        let box = publicContainer();
        if (!box) return;
        section = box.closest('section') || section;
        box.replaceChildren();
        rows.forEach(row => {
            const link = {id:row.dataset.id, title:row.dataset.title, url:row.dataset.url, icon:row.dataset.icon};
            if (/^https:\/\//i.test(link.url) || /^mailto:[^\s@]+@[^\s@]+\.[^\s@]+$/i.test(link.url)) box.appendChild(renderPublicLink(link));
        });
        section?.classList.toggle('hidden', box.children.length === 0);
    }
    function addRow(link){const meta=metaByIcon[link.icon]||{type:'custom',icon:'fa-solid fa-link',bg:'bg-slate-100',text:'text-slate-700'};const row=document.createElement('div');row.className='link-row group relative flex h-[118px] w-[92px] shrink-0 snap-start flex-col items-center rounded-2xl border border-slate-200 bg-slate-50 p-2 text-center shadow-sm';row.draggable=false;row.dataset.id=link.id;row.dataset.active=link.is_active;row.dataset.title=link.title;row.dataset.url=link.url;row.dataset.icon=link.icon;row.innerHTML='<button type="button" class="drag-handle absolute left-1/2 top-1 z-10 flex h-5 w-8 -translate-x-1/2 cursor-grab touch-none items-center justify-center rounded-full text-slate-300 hover:bg-white hover:text-slate-500"><i class="fa-solid fa-ellipsis text-[10px]"></i></button><span class="link-icon mt-3 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl '+meta.bg+' '+meta.text+'"><i class="'+meta.icon+' text-lg"></i></span><p class="mt-1.5 w-full truncate text-[11px] font-extrabold text-slate-800"></p><div class="mt-auto flex w-full items-center justify-center gap-0.5 border-t border-slate-200/70 pt-1.5"><button type="button" class="move-link-up flex h-6 w-6 items-center justify-center rounded-lg text-slate-400 hover:bg-white"><i class="fa-solid fa-chevron-left text-[9px]"></i></button><button type="button" class="toggle-link flex h-6 w-6 items-center justify-center rounded-lg text-slate-500 hover:bg-white"><i class="fa-solid '+(Number(link.is_active)?'fa-eye':'fa-eye-slash')+' text-[10px]"></i></button><button type="button" class="edit-link flex h-6 w-6 items-center justify-center rounded-lg text-slate-500 hover:bg-white"><i class="fa-solid fa-pen text-[9px]"></i></button><button type="button" class="move-link-down flex h-6 w-6 items-center justify-center rounded-lg text-slate-400 hover:bg-white"><i class="fa-solid fa-chevron-right text-[9px]"></i></button><button type="button" class="delete-link flex h-6 w-6 items-center justify-center rounded-lg text-rose-500 hover:bg-rose-50"><i class="fa-solid fa-trash text-[9px]"></i></button></div>';row.querySelector('p').textContent=link.title;row.querySelector('.toggle-link').addEventListener('click',()=>toggleLink(row));row.querySelector('.edit-link').addEventListener('click',()=>openLinkModal({id:link.id,type:meta.type,url:link.url}));row.querySelector('.delete-link').addEventListener('click',()=>deleteLink(row));bindMoveButtons(row);bindDrag(row);linksManager.appendChild(row);ensureEmpty();}
    function updateRow(row,link){const meta=metaByIcon[link.icon]||{type:'custom',icon:'fa-solid fa-link',bg:'bg-slate-100',text:'text-slate-700'};row.dataset.active=link.is_active;row.dataset.title=link.title;row.dataset.url=link.url;row.dataset.icon=link.icon;row.classList.toggle('opacity-60',Number(link.is_active)!==1);const title=row.querySelector('p');if(title)title.textContent=link.title;const iconBox=row.querySelector('.link-icon');if(iconBox){iconBox.className='link-icon mt-3 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl '+meta.bg+' '+meta.text;const icon=iconBox.querySelector('i');if(icon)icon.className=meta.icon+' text-lg';}row.querySelector('.toggle-link i').className='fa-solid '+(Number(link.is_active)?'fa-eye':'fa-eye-slash')+' text-[10px]';const edit=row.querySelector('.edit-link');if(edit){edit.dataset.type=meta.type||'';edit.dataset.url=link.url;}}
    linkForm?.addEventListener('submit',e=>{e.preventDefault();const data={action:'save_link',id:linkId.value,link_type:linkType.value,link_url:linkUrl.value};post(data).then(j=>{if(!j.success)throw new Error(j.message);const link=j.link;if(!link)throw new Error('Data tautan tidak dikembalikan server.');const old=linksManager.querySelector('.link-row[data-id="'+link.id+'"]');if(old)updateRow(old,link);else addRow(link);closeLinkModal();syncPublicLinks();toast(j.message);}).catch(err=>toast(err.message||'Gagal menyimpan tautan.',true));});
    function toggleLink(row){const next=Number(row.dataset.active)===1?0:1;post({action:'toggle_link',id:row.dataset.id,is_active:next}).then(j=>{if(!j.success)throw new Error(j.message);row.dataset.active=next;row.classList.toggle('opacity-60',next!==1);row.querySelector('.toggle-link i').className='fa-solid '+(next?'fa-eye':'fa-eye-slash');syncPublicLinks();toast(j.message);}).catch(err=>toast(err.message,true));}
    function deleteLink(row){if(!confirm('Hapus tautan ini?'))return;post({action:'delete_link',id:row.dataset.id}).then(j=>{if(!j.success)throw new Error(j.message);row.remove();ensureEmpty();syncPublicLinks();toast(j.message);}).catch(err=>toast(err.message,true));}
    function moveLink(row, direction){const target=direction==='up'?row.previousElementSibling:row.nextElementSibling;if(!target||!target.classList.contains('link-row'))return;if(direction==='up')target.before(row);else target.after(row);saveOrder();syncPublicLinks();}
    function bindMoveButtons(row){row.querySelector('.move-link-up')?.addEventListener('click',()=>moveLink(row,'up'));row.querySelector('.move-link-down')?.addEventListener('click',()=>moveLink(row,'down'));}
    document.querySelectorAll('.toggle-link').forEach(b=>b.addEventListener('click',()=>toggleLink(b.closest('.link-row'))));document.querySelectorAll('.delete-link').forEach(b=>b.addEventListener('click',()=>deleteLink(b.closest('.link-row'))));document.querySelectorAll('.link-row').forEach(bindMoveButtons);
    linksManager.querySelectorAll('.link-row').forEach(row=>{row.draggable=false;row.dataset.title=row.querySelector('p')?.textContent||'';row.dataset.url=row.querySelector('.edit-link')?.dataset.url||'';row.dataset.icon=[...(row.querySelector('.link-icon i')?.classList||[])].filter(c=>c.startsWith('fa-')).join(' ');row.classList.toggle('opacity-60',row.dataset.active!=='1');bindDrag(row);});
    function bindDrag(row){
        if (row.dataset.dragBound === '1') return;
        row.dataset.dragBound = '1';
        // Pointer Events dipakai sebagai satu-satunya mekanisme drag agar mouse/touch tidak saling konflik.

        const handle=row.querySelector('.drag-handle');
        handle?.addEventListener('pointerdown', function(e){
            if (e.pointerType === 'mouse' && e.button !== 0) return;
            const startX=e.clientX;
            const startY=e.clientY;
            let dragging=false;
            const onMove=function(ev){
                const dx=ev.clientX-startX, dy=ev.clientY-startY;
                if(!dragging && Math.abs(dx)<8) return;
                dragging=true;
                row.classList.add('opacity-60','scale-95');
                const els=[...linksManager.querySelectorAll('.link-row')].filter(x=>x!==row);
                const target=els.find(el=>{const r=el.getBoundingClientRect();return ev.clientX>=r.left && ev.clientX<=r.right;});
                if(target){const r=target.getBoundingClientRect();if(ev.clientX>r.left+r.width/2)target.after(row);else target.before(row);}
                ev.preventDefault();
            };
            const onUp=function(){
                document.removeEventListener('pointermove',onMove);
                document.removeEventListener('pointerup',onUp);
                row.classList.remove('opacity-60','scale-95');
                if(dragging){ syncPublicLinks(); saveOrder(); }
            };
            document.addEventListener('pointermove',onMove,{passive:false});
            document.addEventListener('pointerup',onUp,{once:true});
        });
    }
    let orderTimer;
    function saveOrder(){
        clearTimeout(orderTimer);
        const ids=[...linksManager.querySelectorAll('.link-row')].map(r=>r.dataset.id);
        if(!ids.length)return;
        orderTimer=setTimeout(()=>{
            post({action:'reorder_links',order:ids}).then(j=>{
                if(!j.success) throw new Error(j.message||'Urutan gagal disimpan.');
                syncPublicLinks();
                toast('Urutan tautan diperbarui.');
            }).catch(err=>toast(err.message||'Urutan gagal disimpan.',true));
        },120);
    }
    document.getElementById('open-links-manager')?.addEventListener('click',()=>openSheet('links'));
    syncPublicLinks();
})();
</script>
<?php endif; ?>

<!-- MODAL DONASI / KIRIM DUKUNGAN -->
<div id="modal-dukungan" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/50 backdrop-blur-sm overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-xl border border-gray-100 space-y-5 relative">
        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-heart"></i>
                </div>
                <h3 class="text-sm font-bold text-gray-900">Kirim Dukungan</h3>
            </div>
            <button id="close-dukungan" type="button" class="text-gray-400 hover:text-gray-600 transition text-sm">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="form-dukungan" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1.5">Pilih Nominal</label>
                <div class="grid grid-cols-3 gap-2" id="nominal-dukungan">
                    <button type="button" data-nominal="10000" class="nominal-btn py-2 px-2 text-xs border rounded-xl transition text-center bg-gray-50 border-gray-200 text-gray-600 font-medium hover:border-emerald-400">Rp 10rb</button>
                    <button type="button" data-nominal="25000" class="nominal-btn py-2 px-2 text-xs border rounded-xl transition text-center bg-gray-50 border-gray-200 text-gray-600 font-medium hover:border-emerald-400">Rp 25rb</button>
                    <button type="button" data-nominal="50000" class="nominal-btn py-2 px-2 text-xs border rounded-xl transition text-center bg-gray-50 border-gray-200 text-gray-600 font-medium hover:border-emerald-400">Rp 50rb</button>
                    <button type="button" data-nominal="100000" class="nominal-btn py-2 px-2 text-xs border rounded-xl transition text-center bg-gray-50 border-gray-200 text-gray-600 font-medium hover:border-emerald-400">Rp 100rb</button>
                    <button type="button" data-nominal="250000" class="nominal-btn py-2 px-2 text-xs border rounded-xl transition text-center bg-gray-50 border-gray-200 text-gray-600 font-medium hover:border-emerald-400">Rp 250rb</button>
                    <button type="button" data-nominal="500000" class="nominal-btn py-2 px-2 text-xs border rounded-xl transition text-center bg-gray-50 border-gray-200 text-gray-600 font-medium hover:border-emerald-400">Rp 500rb</button>
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1">Nominal Lain (Rp)</label>
                <input type="number" id="amount-dukungan" min="10000" placeholder="Minimal Rp 10.000" required
                       class="w-full text-xs px-3 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-emerald-500 font-semibold">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1">Nama Lengkap</label>
                <input type="text" id="name-dukungan" placeholder="Hamba Allah / Nama Anda" required
                       value="<?= htmlspecialchars($_SESSION['fullname'] ?? '') ?>"
                       class="w-full text-xs px-3 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-emerald-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1">Email</label>
                <input type="email" id="email-dukungan" placeholder="email@contoh.com" required
                       value="<?= htmlspecialchars($_SESSION['email'] ?? '') ?>"
                       class="w-full text-xs px-3 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-emerald-500">
            </div>
            <button type="submit" id="submit-dukungan" class="w-full min-h-11 bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-sm px-6 py-3 rounded-xl shadow-md transition flex items-center justify-center gap-2">
                <i class="fa-solid fa-heart text-xs"></i>
                <span>Kirim Dukungan</span>
            </button>
        </form>
    </div>
</div>
<script>
(function(){
    const modal = document.getElementById('modal-dukungan');
    const btnOpen = document.getElementById('btn-dukungan');
    const btnClose = document.getElementById('close-dukungan');
    const form = document.getElementById('form-dukungan');
    const amountInput = document.getElementById('amount-dukungan');

    function openModal(){ modal.classList.remove('hidden'); modal.classList.add('flex'); document.body.style.overflow='hidden'; }
    function closeModal(){ modal.classList.add('hidden'); modal.classList.remove('flex'); document.body.style.overflow=''; }

    btnOpen?.addEventListener('click', openModal);
    btnClose?.addEventListener('click', closeModal);
    modal?.addEventListener('click', function(e){ if(e.target===modal) closeModal(); });

    document.querySelectorAll('#nominal-dukungan .nominal-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            amountInput.value = btn.dataset.nominal;
            document.querySelectorAll('#nominal-dukungan .nominal-btn').forEach(function(b){
                b.classList.remove('bg-amber-50','border-amber-500','text-amber-800','font-bold');
                b.classList.add('bg-gray-50','border-gray-200','text-gray-600','font-medium');
            });
            btn.classList.remove('bg-gray-50','border-gray-200','text-gray-600','font-medium');
            btn.classList.add('bg-amber-50','border-amber-500','text-amber-800','font-bold');
        });
    });

    form?.addEventListener('submit', async function(e){
        e.preventDefault();
        const amount = parseInt(amountInput.value, 10);
        const name = document.getElementById('name-dukungan').value.trim();
        const email = document.getElementById('email-dukungan').value.trim();
        if(!amount || amount < 10000){ alert('Nominal donasi minimal Rp 10.000'); return; }
        if(!name || !email){ alert('Nama dan email wajib diisi'); return; }

        const submitBtn = document.getElementById('submit-dukungan');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memproses...';

        try {
            const response = await fetch('/process_donasi.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ amount: amount, name: name, email: email })
            });
            const result = await response.json();
            if (result.success && result.gateway === 'tripay' && result.bayar_url) {
                window.location.href = result.bayar_url;
            } else if (result.success && result.snap_token) {
                closeModal();
                if (typeof window.snap !== 'undefined') {
                    window.snap.pay(result.snap_token, {
                        onSuccess: function(){ alert('Terima kasih! Dukungan Anda berhasil diterima.'); location.reload(); },
                        onPending: function(){ alert('Menunggu pembayaran Anda.'); location.reload(); },
                        onError: function(){ alert('Pembayaran gagal atau dibatalkan.'); },
                        onClose: function(){ console.log('Popup ditutup'); }
                    });
                } else {
                    alert('Snap Midtrans belum dimuat. Silakan refresh halaman.');
                }
            } else {
                alert(result.message || 'Gagal memproses transaksi.');
            }
        } catch(err) {
            alert('Terjadi kesalahan jaringan.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fa-solid fa-heart text-xs"></i><span>Kirim Dukungan</span>';
        }
    });
})();
</script>
</body>
</html>