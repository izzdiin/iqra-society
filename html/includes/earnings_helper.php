<?php
/**
 * Helper Bagi Hasil (Revenue Share) - Sistem 1
 * Dipakai: payment/callback_*.php, admin/bagi_hasil.php, pengajar/keuangan.php
 */
if (!function_exists('earn_setting')) {
    function earn_setting($conn, $key, $default = '') {
        $st = $conn->prepare("SELECT key_value FROM gateway_settings WHERE key_name = ? LIMIT 1");
        if (!$st) return $default;
        $st->bind_param("s", $key);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        $st->close();
        return ($r && $r['key_value'] !== null && $r['key_value'] !== '') ? $r['key_value'] : $default;
    }
}

if (!function_exists('earn_rupiah')) {
    function earn_rupiah($n) {
        return 'Rp ' . number_format((float)$n, 0, ',', '.');
    }
}

// Ubah pending -> available bila available_at sudah lewat (dipanggil saat halaman dibuka)
if (!function_exists('earn_refresh_status')) {
    function earn_refresh_status($conn) {
        $conn->query("UPDATE seller_earnings SET status = 'available' WHERE status = 'pending' AND available_at IS NOT NULL AND available_at <= NOW()");
    }
}

// Catat bagi hasil saat transaksi PAID. Idempotent (abaikan bila transaction_id sudah ada).
// Return: id earning baru, atau 0 bila dilewati/gagal.
if (!function_exists('earn_record')) {
    function earn_record($conn, $merchant_ref) {
        // Ambil transaksi
        $st = $conn->prepare("SELECT id, user_id, item_type, item_id, item_name, amount FROM transactions WHERE merchant_ref = ? AND payment_status = 'PAID' LIMIT 1");
        if (!$st) return 0;
        $st->bind_param("s", $merchant_ref);
        $st->execute();
        $t = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$t || empty($t['item_type']) || empty($t['item_id'])) return 0;

        // Sudah tercatat?
        $st = $conn->prepare("SELECT id FROM seller_earnings WHERE transaction_id = ? LIMIT 1");
        $st->bind_param("i", $t['id']);
        $st->execute();
        $exists = $st->get_result()->num_rows > 0;
        $st->close();
        if ($exists) return 0;

        // Cari pemilik konten
        $teacher_id = 0;
        if ($t['item_type'] === 'kelas') {
            $st = $conn->prepare("SELECT instructor_id FROM section_kelas WHERE id = ? LIMIT 1");
            $st->bind_param("i", $t['item_id']);
            $st->execute();
            $r = $st->get_result()->fetch_assoc();
            $st->close();
            $teacher_id = (int)($r['instructor_id'] ?? 0);
        } elseif ($t['item_type'] === 'buku') {
            $st = $conn->prepare("SELECT user_id FROM section_buku WHERE id = ? LIMIT 1");
            $st->bind_param("i", $t['item_id']);
            $st->execute();
            $r = $st->get_result()->fetch_assoc();
            $st->close();
            $teacher_id = (int)($r['user_id'] ?? 0);
        }
        if ($teacher_id <= 0) return 0;

        // Pemilik admin -> bagi hasil hanya bila toggle admin_earn_enabled = 1
        $st = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
        $st->bind_param("i", $teacher_id);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        $st->close();
        $is_owner_admin = in_array($r['role'] ?? '', ['super_administrator', 'administrator'], true);
        if ($is_owner_admin && earn_setting($conn, 'admin_earn_enabled', '0') !== '1') return 0;

        $pct = (float)earn_setting($conn, 'commission_pct', '20');
        $pct = max(0, min(100, $pct));
        $gross = (float)$t['amount'];
        $net = round($gross * (100 - $pct) / 100, 2);

        $st = $conn->prepare("INSERT INTO seller_earnings (teacher_id, transaction_id, item_type, item_id, item_name, gross, commission_pct, net, status, available_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', DATE_ADD(NOW(), INTERVAL 7 DAY))");
        if (!$st) return 0;
        $st->bind_param("iiissddd", $teacher_id, $t['id'], $t['item_type'], $t['item_id'], $t['item_name'], $gross, $pct, $net);
        $ok = $st->execute();
        $eid = $ok ? $st->insert_id : 0;
        $st->close();
        return $eid;
    }
}

// Saldo pengajar: pending, available (dikurangi requested), reserved, lifetime
if (!function_exists('earn_balance')) {
    function earn_balance($conn, $teacher_id) {
        earn_refresh_status($conn);
        $b = ['pending' => 0, 'available' => 0, 'reserved' => 0, 'lifetime' => 0];
        $st = $conn->prepare("SELECT status, COALESCE(SUM(net),0) AS s FROM seller_earnings WHERE teacher_id = ? GROUP BY status");
        $st->bind_param("i", $teacher_id);
        $st->execute();
        $rs = $st->get_result();
        while ($r = $rs->fetch_assoc()) {
            $s = $r['status'];
            if (isset($b[$s])) $b[$s] = (float)$r['s'];
            if ($s !== 'void') $b['lifetime'] += (float)$r['s'];
        }
        $st->close();
        // Dana direservasi oleh penarikan yg masih requested
        $st = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS s FROM withdrawals WHERE teacher_id = ? AND status = 'requested'");
        $st->bind_param("i", $teacher_id);
        $st->execute();
        $b['reserved'] = (float)($st->get_result()->fetch_assoc()['s'] ?? 0);
        $st->close();
        $b['available'] = max(0, $b['available'] - $b['reserved']);
        return $b;
    }
}
