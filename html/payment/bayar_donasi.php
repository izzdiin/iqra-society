<?php
/**
 * HALAMAN PEMBAYARAN DONASI TRIPAY (di IQRA sendiri)
 * URL: /payment/bayar_donasi.php?ref=ORDER_ID
 */
require_once __DIR__ . '/../includes/auth_middleware.php';

$order_id = sanitize($_GET['ref'] ?? '');
if (empty($order_id)) die('Referensi tidak valid.');

$stmt = $conn->prepare("SELECT * FROM donasi_transactions WHERE order_id = ? LIMIT 1");
$stmt->bind_param("s", $order_id);
$stmt->execute();
$trx = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$trx) die('Transaksi tidak ditemukan.');
if (strtolower($trx['transaction_status']) === 'settlement' || strtolower($trx['transaction_status']) === 'paid') {
    header('Location: /?donasi=sukses');
    exit;
}

$keys = [];
$kq = $conn->query("SELECT key_name, key_value FROM gateway_settings WHERE key_name IN ('tripay_api_key')");
while ($r = $kq->fetch_assoc()) $keys[$r['key_name']] = $r['key_value'];
$api_key = $keys['tripay_api_key'] ?? '';
$is_prod = (strpos($api_key, 'DEV-') !== 0);
$ch_url = ($is_prod ? 'https://tripay.co.id/api/' : 'https://tripay.co.id/api-sandbox/') . 'merchant/payment-channel';

$ch = curl_init($ch_url);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $api_key], CURLOPT_TIMEOUT => 15]);
$resp = curl_exec($ch); curl_close($ch);
$ch_data = json_decode($resp, true);
$channels = ($ch_data['success'] ?? false) ? ($ch_data['data'] ?? []) : [];

// Filter channel sesuai pengaturan admin (tripay_channels); bila tabel kosong, tampilkan semua
$allowed = null;
$rch = $conn->query("SELECT code FROM tripay_channels WHERE active = 1");
if ($rch && $rch->num_rows > 0) {
    $allowed = [];
    while ($r = $rch->fetch_assoc()) $allowed[$r['code']] = true;
}

$grouped = [];
foreach ($channels as $c) {
    if (!($c['active'] ?? false)) continue;
    if ($allowed !== null && !isset($allowed[$c['code'] ?? ''])) continue;
    $grouped[$c['group']][] = $c;
}
$amount = (float)$trx['amount'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pembayaran Donasi - IQRA Society</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">
<div class="max-w-2xl mx-auto px-4 py-8">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-4">
        <h1 class="text-lg font-extrabold text-gray-900 mb-1">Pembayaran Donasi</h1>
        <p class="text-xs text-gray-500">Donatur: <?= htmlspecialchars($trx['donor_name']) ?></p>
        <div class="mt-4 flex items-center justify-between bg-emerald-50 rounded-xl px-4 py-3">
            <span class="text-xs font-bold text-gray-600">Total Donasi</span>
            <span class="text-xl font-black text-emerald-800">Rp <?= number_format($amount, 0, ',', '.') ?></span>
        </div>
        <p class="text-[10px] text-gray-400 mt-2">Order ID: <?= htmlspecialchars($order_id) ?></p>
    </div>

    <div id="metode-list" class="space-y-4">
        <?php foreach ($grouped as $group => $list): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-3 bg-gray-50 border-b border-gray-100">
                <h3 class="text-xs font-extrabold text-gray-700 uppercase tracking-wide"><?= htmlspecialchars($group) ?></h3>
            </div>
            <div class="divide-y divide-gray-100">
                <?php foreach ($list as $m):
                    $fee = (float)($m['fee_merchant']['flat'] ?? 0) + ($amount * (float)($m['fee_merchant']['percent'] ?? 0) / 100);
                ?>
                <button onclick="pilihMetode('<?= $m['code'] ?>', '<?= htmlspecialchars($m['name'], ENT_QUOTES) ?>')"
                        class="w-full flex items-center gap-3 px-5 py-4 hover:bg-emerald-50/50 transition text-left">
                    <?php if (!empty($m['icon_url'])): ?>
                    <img src="<?= htmlspecialchars($m['icon_url']) ?>" class="w-12 h-8 object-contain">
                    <?php endif; ?>
                    <div class="flex-1">
                        <p class="text-sm font-bold text-gray-800"><?= htmlspecialchars($m['name']) ?></p>
                        <p class="text-[11px] text-gray-500">Biaya admin: <?= $fee > 0 ? 'Rp ' . number_format($fee, 0, ',', '.') : 'Gratis' ?></p>
                    </div>
                    <i class="fa-solid fa-chevron-right text-gray-300 text-xs"></i>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div id="instruksi" class="hidden bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-sm font-extrabold text-gray-900 mb-1">Instruksi Pembayaran</h3>
        <p id="instruksi-metode" class="text-xs text-gray-500 mb-4"></p>
        <div id="instruksi-body" class="space-y-3"></div>
        <button onclick="location.reload()" class="w-full mt-6 py-3 rounded-xl border border-gray-200 text-xs font-bold text-gray-600">Ganti Metode</button>
    </div>
</div>
<script>
const ORDER_ID = '<?= htmlspecialchars($order_id) ?>';
async function pilihMetode(code, name) {
    if (!confirm('Bayar dengan ' + name + '?')) return;
    document.getElementById('metode-list').classList.add('hidden');
    document.getElementById('instruksi').classList.remove('hidden');
    document.getElementById('instruksi-metode').textContent = name;
    document.getElementById('instruksi-body').innerHTML = '<p class="text-xs text-gray-500">Memproses...</p>';
    try {
        const res = await fetch('/payment/proses_donasi_tripay.php', {
            method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({order_id: ORDER_ID, method: code})
        });
        const data = await res.json();
        if (data.success) {
            let h = '';
            if (data.data.qr_url) h += '<div class="text-center"><img src="' + data.data.qr_url + '" class="w-48 h-48 mx-auto rounded-xl border"></div>';
            if (data.data.pay_code) h += '<div class="flex gap-2"><code class="flex-1 bg-gray-100 rounded-xl px-4 py-3 text-sm font-mono font-bold text-center">' + data.data.pay_code + '</code><button onclick="navigator.clipboard.writeText(\'' + data.data.pay_code + '\')" class="px-3 py-3 bg-gray-100 rounded-xl"><i class="fa-solid fa-copy text-xs"></i></button></div>';
            document.getElementById('instruksi-body').innerHTML = h || '<p>Gagal memuat instruksi.</p>';
        } else {
            document.getElementById('instruksi-body').innerHTML = '<p class="text-xs text-red-600">' + data.message + '</p>';
        }
    } catch(e) {
        document.getElementById('instruksi-body').innerHTML = '<p class="text-xs text-red-600">Kesalahan jaringan.</p>';
    }
}
</script>
</body>
</html>
