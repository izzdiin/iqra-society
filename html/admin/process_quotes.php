<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
check_access(['super_administrator', 'administrator']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi Token CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        header("Location: manage_landing.php?error=" . urlencode("Token keamanan CSRF tidak valid."));
        exit;
    }

    $target = $_POST['target'] ?? '';
    $action = $_POST['action'] ?? '';

    // Prosedur Penanganan Aksi untuk Target 'quote'
    if ($target === 'quote') {
        if ($action === 'edit') {
            $id         = (int)($_POST['id'] ?? 0);
            $quote_text = sanitize($_POST['quote_text'] ?? '');
            $source     = sanitize($_POST['source'] ?? '');

            if ($id > 0 && !empty($quote_text) && !empty($source)) {
                $stmt = $conn->prepare("UPDATE section_quotes SET quote_text = ?, source = ? WHERE id = ?");
                $stmt->bind_param("ssi", $quote_text, $source, $id);
                if ($stmt->execute()) {
                    log_activity('UPDATE', 'quotes', "Memperbarui quote ID $id", $id);
                    header("Location: manage_landing.php?success=" . urlencode("Quote berhasil diperbarui!"));
                } else {
                    header("Location: manage_landing.php?error=" . urlencode("Gagal memperbarui quote."));
                }
                $stmt->close();
            } else {
                header("Location: manage_landing.php?error=" . urlencode("Data quote tidak lengkap."));
            }
            exit;
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $conn->prepare("DELETE FROM section_quotes WHERE id = ?");
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    log_activity('DELETE', 'quotes', "Menghapus quote ID $id", $id);
                    header("Location: manage_landing.php?success=" . urlencode("Quote berhasil dihapus!"));
                } else {
                    header("Location: manage_landing.php?error=" . urlencode("Gagal menghapus quote."));
                }
                $stmt->close();
            }
            exit;
        }

        if ($action === 'toggle_active') {
            $id     = (int)($_POST['id'] ?? 0);
            $status = (int)($_POST['is_active'] ?? 0);

            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE section_quotes SET is_active = ? WHERE id = ?");
                $stmt->bind_param("ii", $status, $id);
                if ($stmt->execute()) {
                    $msg = $status === 1 ? "Quote berhasil diaktifkan!" : "Quote berhasil dinonaktifkan!";
                    header("Location: manage_landing.php?success=" . urlencode($msg));
                } else {
                    header("Location: manage_landing.php?error=" . urlencode("Gagal mengubah status quote."));
                }
                $stmt->close();
            }
            exit;
        }
    }
}

// Default redirect jika tidak ada aksi yang cocok
header("Location: manage_landing.php");
exit;