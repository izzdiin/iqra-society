<?php
/**
 * Helper fulfillment & cek akses pembelian (kelas/buku).
 *
 * Dipakai oleh:
 * - payment/callback_tripay.php & payment/callback_midtrans.php
 *   (setelah transaksi dinyatakan PAID -> iqra_fulfill_paid())
 * - pages/detail_kelas.php & pages/detail_buku.php
 *   (cek hak akses user yang sedang login)
 *
 * Prinsip:
 * - Kelas berbayar: hak akses dicatat sebagai baris kelas_enrollments
 *   berstatus 'active' (pola yang sama dengan daftar_kelas.php untuk
 *   kelas gratis), lengkap dengan transaction_id sumbernya.
 * - Buku: tidak ada tabel kepemilikan terpisah; hak akses dibaca dari
 *   transaksi PAID milik user (item_type='buku').
 * - Semua fungsi idempoten: aman dipanggil berulang dari callback
 *   yang sama tanpa menggandakan data.
 */

if (!function_exists('iqra_fulfill_paid')) {

    /**
     * Jalankan fulfillment untuk satu transaksi yang sudah PAID.
     * $merchant_ref = transactions.merchant_ref (order_id gateway).
     * Mengembalikan true bila transaksi ditemukan & berstatus PAID.
     */
    function iqra_fulfill_paid($conn, $merchant_ref) {
        $stmt = $conn->prepare("SELECT id, user_id, item_type, item_id, payment_status FROM transactions WHERE merchant_ref = ? LIMIT 1");
        $stmt->bind_param("s", $merchant_ref);
        $stmt->execute();
        $trx = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$trx || $trx['payment_status'] !== 'PAID') {
            return false;
        }

        if ($trx['item_type'] === 'kelas') {
            iqra_enroll_kelas($conn, (int)$trx['user_id'], (int)$trx['item_id'], (int)$trx['id']);
        }
        // item_type 'buku': tidak ada baris yang perlu dibuat —
        // akses dibaca dari transaksi PAID ini (iqra_user_paid_item).

        return true;
    }

    /**
     * Catat/aktifkan enrollment kelas untuk user (idempoten).
     * Meniru pola daftar_kelas.php: enrollment aktif tidak diduplikat;
     * enrollment lama non-aktif diaktifkan kembali.
     */
    function iqra_enroll_kelas($conn, $user_id, $kelas_id, $transaction_id = null) {
        $stmt = $conn->prepare("SELECT id, status FROM kelas_enrollments WHERE kelas_id = ? AND user_id = ? LIMIT 1");
        $stmt->bind_param("ii", $kelas_id, $user_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing && $existing['status'] === 'active') {
            return (int)$existing['id'];
        }

        if ($existing) {
            $stmt = $conn->prepare("UPDATE kelas_enrollments SET status = 'active', completed_at = NULL, transaction_id = ? WHERE id = ?");
            $stmt->bind_param("ii", $transaction_id, $existing['id']);
            $stmt->execute();
            $stmt->close();
            return (int)$existing['id'];
        }

        $stmt = $conn->prepare("INSERT INTO kelas_enrollments (kelas_id, user_id, status, transaction_id) VALUES (?, ?, 'active', ?)");
        $stmt->bind_param("iii", $kelas_id, $user_id, $transaction_id);
        $stmt->execute();
        $new_id = $stmt->insert_id;
        $stmt->close();
        return (int)$new_id;
    }

    /** true bila user punya enrollment kelas berstatus active. */
    function iqra_kelas_enrolled($conn, $user_id, $kelas_id) {
        $stmt = $conn->prepare("SELECT id FROM kelas_enrollments WHERE kelas_id = ? AND user_id = ? AND status = 'active' LIMIT 1");
        $stmt->bind_param("ii", $kelas_id, $user_id);
        $stmt->execute();
        $found = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $found !== null;
    }

    /** true bila user punya transaksi PAID untuk item tertentu. */
    function iqra_user_paid_item($conn, $user_id, $item_type, $item_id) {
        $stmt = $conn->prepare("SELECT id FROM transactions WHERE user_id = ? AND item_type = ? AND item_id = ? AND payment_status = 'PAID' LIMIT 1");
        $stmt->bind_param("isi", $user_id, $item_type, $item_id);
        $stmt->execute();
        $found = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $found !== null;
    }
}
