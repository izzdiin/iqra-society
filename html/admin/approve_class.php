<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
check_access(['super_administrator', 'administrator']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("CSRF Invalid");
    }

    $class_id = (int) ($_POST['class_id'] ?? 0);
    $action   = sanitize($_POST['action'] ?? '');

    $new_status = ($action === 'approve') ? 'approved' : 'rejected';

    $stmt = $conn->prepare("UPDATE section_kelas SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $class_id);
    $stmt->execute();
    $stmt->close();

    $t = $conn->prepare("SELECT title FROM section_kelas WHERE id = ?");
    $t->bind_param("i", $class_id); $t->execute();
    $r = $t->get_result()->fetch_assoc(); $t->close();
    $lbl = ($action === 'approve') ? 'Menyetujui' : 'Menolak';
    log_activity(strtoupper($action) === 'APPROVE' ? 'APPROVE' : 'REJECT', 'kelas',
        "$lbl kelas '" . ($r['title'] ?? ('ID ' . $class_id)) . "'", $class_id);

    header('Location: index.php');
    exit();
}