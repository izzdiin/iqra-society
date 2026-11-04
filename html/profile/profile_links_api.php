<?php

require_once __DIR__ . '/../includes/auth_middleware.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function json_response(bool $success, string $message = '', array $data = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function current_user_id(mysqli $conn): int
{
    if (
        !empty($_SESSION['is_logged_in']) &&
        $_SESSION['is_logged_in'] === true &&
        !empty($_SESSION['user_id'])
    ) {
        return (int) $_SESSION['user_id'];
    }

    $username = trim(strtolower((string) ($_SESSION['username'] ?? '')));

    if ($username === '') {
        return 0;
    }

    $stmt = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('s', $username);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return 0;
    }

    $_SESSION['user_id'] = (int) $row['id'];
    return (int) $row['id'];
}

function require_owner(mysqli $conn): int
{
    $user_id = current_user_id($conn);

    if ($user_id <= 0) {
        json_response(false, 'Sesi login tidak valid. Silakan masuk kembali.', [], 401);
    }

    return $user_id;
}

function require_csrf(): void
{
    $session_token = (string) ($_SESSION['csrf_profile_links'] ?? '');
    $request_token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''));

    if (
        $session_token === '' ||
        $request_token === '' ||
        !hash_equals($session_token, $request_token)
    ) {
        json_response(false, 'Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.', [], 419);
    }
}

function validate_title(string $title): string
{
    $title = trim($title);

    if ($title === '') {
        json_response(false, 'Judul tautan wajib diisi.', [], 422);
    }

    if (mb_strlen($title) > 120) {
        json_response(false, 'Judul tautan maksimal 120 karakter.', [], 422);
    }

    return $title;
}

function validate_description(string $description): ?string
{
    $description = trim($description);

    if ($description === '') {
        return null;
    }

    if (mb_strlen($description) > 180) {
        json_response(false, 'Deskripsi maksimal 180 karakter.', [], 422);
    }

    return $description;
}

function validate_url(string $url): string
{
    $url = trim($url);

    if ($url === '' || mb_strlen($url) > 2048) {
        json_response(false, 'URL wajib diisi dan maksimal 2048 karakter.', [], 422);
    }

    $parts = parse_url($url);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));

    if (!in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)) {
        json_response(false, 'URL harus menggunakan http, https, mailto, atau tel.', [], 422);
    }

    if (in_array($scheme, ['http', 'https'], true) && empty($parts['host'])) {
        json_response(false, 'URL web harus memiliki nama domain yang valid.', [], 422);
    }

    return $url;
}

function validate_icon(string $icon): string
{
    $allowed = [
        'fa-solid fa-link',
        'fa-solid fa-globe',
        'fa-solid fa-book-open',
        'fa-solid fa-graduation-cap',
        'fa-solid fa-newspaper',
        'fa-solid fa-calendar-days',
        'fa-solid fa-cart-shopping',
        'fa-solid fa-heart',
        'fa-solid fa-envelope',
        'fa-solid fa-phone',
        'fa-solid fa-location-dot',
        'fa-brands fa-whatsapp',
        'fa-brands fa-youtube',
        'fa-brands fa-instagram',
        'fa-brands fa-facebook',
        'fa-brands fa-telegram',
        'fa-brands fa-tiktok',
        'fa-brands fa-x-twitter',
    ];

    return in_array($icon, $allowed, true) ? $icon : 'fa-solid fa-link';
}

function get_link(mysqli $conn, int $user_id, int $link_id): ?array
{
    $stmt = $conn->prepare('SELECT id, user_id, title, url, description, icon, sort_order, is_active FROM profile_links WHERE id = ? AND user_id = ? LIMIT 1');

    if (!$stmt) {
        json_response(false, 'Database error.', [], 500);
    }

    $stmt->bind_param('ii', $link_id, $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

$action = trim(strtolower((string) ($_POST['action'] ?? $_GET['action'] ?? '')));

if ($action === 'list') {
    $user_id = require_owner($conn);

    $stmt = $conn->prepare('SELECT id, title, url, description, icon, sort_order, is_active, created_at, updated_at FROM profile_links WHERE user_id = ? ORDER BY sort_order ASC, id ASC');

    if (!$stmt) {
        json_response(false, 'Database error.', [], 500);
    }

    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $links = [];
    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int) $row['id'];
        $row['sort_order'] = (int) $row['sort_order'];
        $row['is_active'] = (int) $row['is_active'];
        $links[] = $row;
    }

    $stmt->close();
    json_response(true, '', ['links' => $links]);
}

$user_id = require_owner($conn);
require_csrf();

if ($action === 'create') {
    $title = validate_title((string) ($_POST['title'] ?? ''));
    $url = validate_url((string) ($_POST['url'] ?? ''));
    $description = validate_description((string) ($_POST['description'] ?? ''));
    $icon = validate_icon((string) ($_POST['icon'] ?? 'fa-solid fa-link'));
    $is_active = !empty($_POST['is_active']) ? 1 : 0;

    $stmt_max = $conn->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 AS next_order FROM profile_links WHERE user_id = ?');
    if (!$stmt_max) {
        json_response(false, 'Database error.', [], 500);
    }
    $stmt_max->bind_param('i', $user_id);
    $stmt_max->execute();
    $next_order = (int) (($stmt_max->get_result()->fetch_assoc()['next_order'] ?? 0));
    $stmt_max->close();

    $stmt = $conn->prepare('INSERT INTO profile_links (user_id, title, url, description, icon, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)');

    if (!$stmt) {
        json_response(false, 'Database error.', [], 500);
    }

    $stmt->bind_param('issssii', $user_id, $title, $url, $description, $icon, $next_order, $is_active);

    if (!$stmt->execute()) {
        $stmt->close();
        json_response(false, 'Tautan gagal ditambahkan.', [], 500);
    }

    $link_id = (int) $stmt->insert_id;
    $stmt->close();

    json_response(true, 'Tautan berhasil ditambahkan.', ['id' => $link_id]);
}

if ($action === 'update') {
    $link_id = (int) ($_POST['id'] ?? 0);

    if ($link_id <= 0 || !get_link($conn, $user_id, $link_id)) {
        json_response(false, 'Tautan tidak ditemukan.', [], 404);
    }

    $title = validate_title((string) ($_POST['title'] ?? ''));
    $url = validate_url((string) ($_POST['url'] ?? ''));
    $description = validate_description((string) ($_POST['description'] ?? ''));
    $icon = validate_icon((string) ($_POST['icon'] ?? 'fa-solid fa-link'));
    $is_active = !empty($_POST['is_active']) ? 1 : 0;

    $stmt = $conn->prepare('UPDATE profile_links SET title = ?, url = ?, description = ?, icon = ?, is_active = ? WHERE id = ? AND user_id = ?');

    if (!$stmt) {
        json_response(false, 'Database error.', [], 500);
    }

    $stmt->bind_param('ssssiii', $title, $url, $description, $icon, $is_active, $link_id, $user_id);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        json_response(false, 'Tautan gagal diperbarui.', [], 500);
    }

    json_response(true, 'Tautan berhasil diperbarui.');
}

if ($action === 'toggle') {
    $link_id = (int) ($_POST['id'] ?? 0);
    $link = $link_id > 0 ? get_link($conn, $user_id, $link_id) : null;

    if (!$link) {
        json_response(false, 'Tautan tidak ditemukan.', [], 404);
    }

    $new_state = ((int) $link['is_active'] === 1) ? 0 : 1;

    $stmt = $conn->prepare('UPDATE profile_links SET is_active = ? WHERE id = ? AND user_id = ?');

    if (!$stmt) {
        json_response(false, 'Database error.', [], 500);
    }

    $stmt->bind_param('iii', $new_state, $link_id, $user_id);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        json_response(false, 'Status tautan gagal diubah.', [], 500);
    }

    json_response(true, $new_state === 1 ? 'Tautan diaktifkan.' : 'Tautan dinonaktifkan.', ['is_active' => $new_state]);
}

if ($action === 'delete') {
    $link_id = (int) ($_POST['id'] ?? 0);

    if ($link_id <= 0 || !get_link($conn, $user_id, $link_id)) {
        json_response(false, 'Tautan tidak ditemukan.', [], 404);
    }

    $stmt = $conn->prepare('DELETE FROM profile_links WHERE id = ? AND user_id = ?');

    if (!$stmt) {
        json_response(false, 'Database error.', [], 500);
    }

    $stmt->bind_param('ii', $link_id, $user_id);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        json_response(false, 'Tautan gagal dihapus.', [], 500);
    }

    json_response(true, 'Tautan berhasil dihapus.');
}

if ($action === 'reorder') {
    $ids_raw = $_POST['ids'] ?? [];

    if (!is_array($ids_raw) || count($ids_raw) === 0) {
        json_response(false, 'Urutan tautan tidak valid.', [], 422);
    }

    $ids = [];
    foreach ($ids_raw as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $ids[] = $id;
        }
    }

    $ids = array_values(array_unique($ids));

    if (count($ids) === 0) {
        json_response(false, 'Urutan tautan tidak valid.', [], 422);
    }

    $stmt_count = $conn->prepare('SELECT COUNT(*) AS total FROM profile_links WHERE user_id = ?');
    if (!$stmt_count) {
        json_response(false, 'Database error.', [], 500);
    }
    $stmt_count->bind_param('i', $user_id);
    $stmt_count->execute();
    $total_links = (int) (($stmt_count->get_result()->fetch_assoc()['total'] ?? 0));
    $stmt_count->close();

    if (count($ids) !== $total_links) {
        json_response(false, 'Daftar urutan tidak lengkap. Muat ulang halaman lalu coba lagi.', [], 422);
    }

    $conn->begin_transaction();

    try {
        $stmt_check = $conn->prepare('SELECT id FROM profile_links WHERE user_id = ? AND id = ? LIMIT 1');
        if (!$stmt_check) {
            throw new RuntimeException('Database error.');
        }

        $stmt_update = $conn->prepare('UPDATE profile_links SET sort_order = ? WHERE id = ? AND user_id = ?');
        if (!$stmt_update) {
            $stmt_check->close();
            throw new RuntimeException('Database error.');
        }

        foreach ($ids as $position => $id) {
            $stmt_check->bind_param('ii', $user_id, $id);
            $stmt_check->execute();
            $exists = $stmt_check->get_result()->fetch_assoc();

            if (!$exists) {
                throw new RuntimeException('Terdapat tautan yang bukan milik akun ini.');
            }

            $sort_order = $position;
            $stmt_update->bind_param('iii', $sort_order, $id, $user_id);
            $stmt_update->execute();
        }

        $stmt_check->close();
        $stmt_update->close();
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        json_response(false, $e->getMessage(), [], 422);
    }

    json_response(true, 'Urutan tautan berhasil disimpan.');
}

json_response(false, 'Aksi tidak dikenal.', [], 400);
