<?php
/**
 * Handler pendaftaran kelas — target form "Daftar Kelas Sekarang"
 * di pages/detail_kelas.php (desktop & sticky bar mobile).
 *
 * Perilaku:
 * - Kelas gratis   : user langsung dicatat ke kelas_enrollments (status active).
 * - Kelas berbayar : TIDAK dicatat; user diarahkan kembali ke halaman kelas
 *                    untuk membayar lewat tombol Bayar (payment/checkout.php).
 * - Wajib login + CSRF. Hanya menerima POST.
 */
require_once __DIR__ . '/includes/auth_middleware.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hanya menerima POST
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: /kelas');
    exit();
}

// Wajib login
if (empty($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true || empty($_SESSION['user_id'])) {
    header('Location: /login');
    exit();
}

// CSRF
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    die('Token keamanan tidak valid. Silakan kembali ke halaman kelas dan coba lagi.');
}

$kelas_id = (int)($_POST['kelas_id'] ?? 0);
$user_id  = (int)$_SESSION['user_id'];

// Ambil kelas yang disetujui
$stmt = $conn->prepare("SELECT id, slug, price FROM section_kelas WHERE id = ? AND status = 'approved' LIMIT 1");
$stmt->bind_param('i', $kelas_id);
$stmt->execute();
$kelas = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$kelas) {
    header('Location: /kelas');
    exit();
}

$detail_url = '/kelas/' . (!empty($kelas['slug']) ? $kelas['slug'] : (int)$kelas['id']);

// Kelas berbayar: arahkan ke pembayaran (tombol Bayar di halaman kelas)
if ((float)$kelas['price'] > 0) {
    header('Location: ' . $detail_url . '?status=bayar');
    exit();
}

// Kelas gratis: periksa enrollment yang sudah ada
$stmt = $conn->prepare("SELECT id, status FROM kelas_enrollments WHERE kelas_id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param('ii', $kelas_id, $user_id);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing && $existing['status'] === 'active') {
    header('Location: ' . $detail_url . '?status=sudah-terdaftar');
    exit();
}

if ($existing) {
    // Enrollment lama non-aktif (completed/cancelled): aktifkan kembali
    $stmt = $conn->prepare("UPDATE kelas_enrollments SET status = 'active', completed_at = NULL WHERE id = ?");
    $stmt->bind_param('i', $existing['id']);
    $stmt->execute();
    $stmt->close();
} else {
    $stmt = $conn->prepare("INSERT INTO kelas_enrollments (kelas_id, user_id, status) VALUES (?, ?, 'active')");
    $stmt->bind_param('ii', $kelas_id, $user_id);
    $stmt->execute();
    $stmt->close();
}

if (function_exists('log_activity')) {
    log_activity('CREATE', 'kelas', 'Mendaftar kelas gratis #' . $kelas_id, $kelas_id);
}

header('Location: ' . $detail_url . '?status=terdaftar');
exit();
