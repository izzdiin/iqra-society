<?php
/**
 * HALAMAN PEMBAYARAN TRIPAY (di IQRA sendiri, tanpa redirect ke Tripay)
 * URL: /payment/bayar.php?ref=MERCHANT_REF
 * User pilih metode pembayaran → tampil instruksi bayar
 */
require_once __DIR__ . '/../includes/auth_middleware.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$merchant_ref = sanitize($_GET['ref'] ?? '');
if (empty($merchant_ref)) die('Referensi transaksi tidak valid.');

// Ambil data transaksi
$stmt = $conn->prepare("SELECT * FROM transactions WHERE merchant_ref = ? AND user_id = ? LIMIT 1");
$uid = (int)$_SESSION['user_id'];
$stmt->bind_param("si", $merchant_ref, $uid);
$stmt->execute();
$trx = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$trx) die('Transaksi tidak ditemukan.');
if ($trx['payment_status'] === 'PAID') {
    header('Location: /pembelajar/riwayat.php?status=paid');
    exit;
}

// Ambil kredensial Tripay
$keys = [];
$kq = $conn->query("SELECT key_name, key_value FROM gateway_settings WHERE key_name IN ('tripay_merchant_code','tripay_api_key','tripay_private_key')");
while ($r = $kq->fetch_assoc()) $keys[$r['key_name']] = $r['key_value'];

$api_key = $keys['tripay_api_key'] ?? '';
$is_prod = (strpos($api_key, 'DEV-') !== 0);
$ch_url = $is_prod ? 'https://tripay.co.id/api/merchant/payment-channel' : 'https://tripay.co.id/api-sandbox/merchant/payment-channel';

// Ambil daftar channel pembayaran
$ch = curl_init($ch_url);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $api_key], CURLOPT_TIMEOUT => 15]);
$resp = curl_exec($ch);
curl_close($ch);
$ch_data = json_decode($resp, true);
$channels = ($ch_data['success'] ?? false) ? ($ch_data['data'] ?? []) : [];

// Kelompokkan per grup
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
<title>Pembayaran - IQRA Society</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">
<div class="max-w-2xl mx-auto px-4 py-8">
    <!-- Header -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-4">
        <h1 class="text-lg font-extrabold text-gray-900 mb-1">Pembayaran</h1>
        <p class="text-xs text-gray-500"><?= htmlspecialchars($trx['item_name']) ?> (<?= strtoupper($trx['item_type']) ?>)</p>
        <div class="mt-4 flex items-center justify-between bg-emerald-50 rounded-xl px-4 py-3">
            <span class="text-xs font-bold text-gray-600">Total Bayar</span>
            <span class="text-xl font-black text-emerald-800">Rp <?= number_format($amount, 0, ',', '.') ?></span>
        </div>
        <p class="text-[10px] text-gray-400 mt-2">Order ID: <?= htmlspecialchars($merchant_ref) ?></p>
    </div>

    <!-- Daftar Metode Pembayaran -->
    <div id="metode-list" class="space-y-4">
        <?php foreach ($grouped as $group => $list): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-3 bg-gray-50 border-b border-gray-100">
                <h3 class="text-xs font-extrabold text-gray-700 uppercase tracking-wide"><?= htmlspecialchars($group) ?></h3>
            </div>
            <div class="divide-y divide-gray-100">
                <?php foreach ($list as $m):
                    $fee_flat = (float)($m['fee_merchant']['flat'] ?? 0);
                    $fee_pct = (float)($m['fee_merchant']['percent'] ?? 0);
                    $fee = $fee_flat + ($amount * $fee_pct / 100);
                    $total = $amount + $fee;
                ?>
                <button onclick="pilihMetode('<?= $m['code'] ?>', '<?= htmlspecialchars($m['name'], ENT_QUOTES) ?>', <?= $fee ?>)"
                        class="w-full flex items-center gap-3 px-5 py-4 hover:bg-emerald-50/50 transition text-left">
                    <?php if (!empty($m['icon_url'])): ?>
                    <img src="<?= htmlspecialchars($m['icon_url']) ?>" alt="<?= htmlspecialchars($m['name']) ?>" class="w-12 h-8 object-contain">
                    <?php else: ?>
                    <div class="w-12 h-8 bg-gray-100 rounded flex items-center justify-center"><i class="fa-solid fa-credit-card text-gray-400"></i></div>
                    <?php endif; ?>
                    <div class="flex-1">
                        <p class="text-sm font-bold text-gray-800"><?= htmlspecialchars($m['name']) ?></p>
                        <p class="text-[11px] text-gray-500">
                            Biaya admin: <?= $fee > 0 ? 'Rp ' . number_format($fee, 0, ',', '.') : 'Gratis' ?>
                            <?php if ($fee > 0): ?> → Total Rp <?= number_format($total, 0, ',', '.') ?><?php endif; ?>
                        </p>
                    </div>
                    <i class="fa-solid fa-chevron-right text-gray-300 text-xs"></i>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Instruksi Pembayaran (hidden awal) -->
    <div id="instruksi" class="hidden bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-sm font-extrabold text-gray-900 mb-1">Instruksi Pembayaran</h3>
        <p id="instruksi-metode" class="text-xs text-gray-500 mb-4"></p>
        <div id="instruksi-body" class="space-y-3"></div>
        <div class="mt-6 flex gap-3">
            <button onclick="location.reload()" class="flex-1 py-3 rounded-xl border border-gray-200 text-xs font-bold text-gray-600 hover:bg-gray-50">Ganti Metode</button>
            <button onclick="cekStatus()" class="flex-1 py-3 rounded-xl bg-emerald-700 text-white text-xs font-bold hover:bg-emerald-800">Cek Status</button>
        </div>
        <p id="status-msg" class="text-xs text-center mt-3 text-gray-500"></p>
    </div>
</div>

<script>
const MERCHANT_REF = '<?= htmlspecialchars($merchant_ref) ?>';
const AMOUNT = <?= $amount ?>;

async function pilihMetode(code, name, fee) {
    if (!confirm('Bayar dengan ' + name + '?')) return;
    document.getElementById('metode-list').classList.add('hidden');
    const box = document.getElementById('instruksi');
    box.classList.remove('hidden');
    document.getElementById('instruksi-metode').textContent = name;
    document.getElementById('instruksi-body').innerHTML = '<p class="text-xs text-gray-500"><i class="fa-solid fa-spinner fa-spin"></i> Membuat transaksi...</p>';

    try {
        const res = await fetch('/payment/proses_tripay.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({merchant_ref: MERCHANT_REF, method: code})
        });
        const data = await res.json();
        if (data.success) {
            tampilInstruksi(data.data);
        } else {
            document.getElementById('instruksi-body').innerHTML = '<p class="text-xs text-red-600">' + (data.message || 'Gagal') + '</p>';
        }
    } catch(e) {
        document.getElementById('instruksi-body').innerHTML = '<p class="text-xs text-red-600">Kesalahan jaringan.</p>';
    }
}

function tampilInstruksi(d) {
    let html = '';
    const total = d.amount || AMOUNT;
    html += '<div class="bg-emerald-50 rounded-xl px-4 py-3 flex justify-between items-center"><span class="text-xs font-bold text-gray-600">Total Bayar</span><span class="text-lg font-black text-emerald-800">Rp ' + Number(total).toLocaleString('id-ID') + '</span></div>';

    if (d.qr_url) {
        html += '<div class="text-center"><img src="' + d.qr_url + '" alt="QRIS" class="w-48 h-48 mx-auto rounded-xl border border-gray-200"><p class="text-[11px] text-gray-500 mt-2">Scan QR code di atas</p></div>';
    }
    if (d.pay_code) {
        html += '<div><p class="text-[11px] font-bold text-gray-600 mb-1">Kode Bayar / VA Number</p><div class="flex items-center gap-2"><code class="flex-1 bg-gray-100 rounded-xl px-4 py-3 text-sm font-mono font-bold text-center">' + d.pay_code + '</code><button onclick="navigator.clipboard.writeText(\'' + d.pay_code + '\')" class="px-3 py-3 bg-gray-100 rounded-xl hover:bg-gray-200"><i class="fa-solid fa-copy text-xs"></i></button></div></div>';
    }
    if (d.instructions && d.instructions.length) {
        html += '<div><p class="text-[11px] font-bold text-gray-600 mb-2">Cara Bayar</p><ol class="text-xs text-gray-600 space-y-1.5 list-decimal list-inside">';
        d.instructions.forEach(s => { html += '<li>' + s.title + (s.steps ? '<ul class="ml-4 mt-1 space-y-1 list-disc list-inside text-gray-500">' + s.steps.map(x => '<li>' + x + '</li>').join('') + '</ul>' : '') + '</li>'; });
        html += '</ol></div>';
    }
    if (d.expired_time) {
        const exp = new Date(d.expired_time * 1000);
        html += '<p class="text-[11px] text-amber-600 text-center"><i class="fa-solid fa-clock"></i> Berlaku hingga ' + exp.toLocaleString('id-ID') + '</p>';
    }
    document.getElementById('instruksi-body').innerHTML = html;
}

async function cekStatus() {
    const msg = document.getElementById('status-msg');
    msg.textContent = 'Mengecek...';
    try {
        const res = await fetch('/payment/cek_status.php?ref=' + MERCHANT_REF, {credentials: 'same-origin'});
        const data = await res.json();
        if (data.status === 'PAID') {
            msg.innerHTML = '<span class="text-emerald-600 font-bold">Pembayaran berhasil!</span>';
            setTimeout(() => location.href = '/pembelajar/', 1500);
        } else {
            msg.textContent = 'Status: ' + data.status + ' (belum dibayar)';
        }
    } catch(e) { msg.textContent = 'Gagal mengecek status.'; }
}
// Auto cek tiap 10 detik
setInterval(cekStatus, 10000);
</script>
</body>
</html>
