# Dokumentasi Kode — IQRA Society (iqrasociety.id)

_Disusun dari inspeksi langsung ke `/usr/local/app/iqra` di server stbjkt (15 Okt 2026)._

## 1. Gambaran Umum

| Aspek | Keterangan |
|---|---|
| Jenis aplikasi | Web PHP native (tanpa framework), pola multi-page |
| Runtime | PHP 8.3 + Apache dalam Docker (`php:8.3-apache`), container `iqra`, port host `4700` → 80 |
| Database | MySQL/MariaDB via ekstensi `mysqli` (prepared statement), charset `utf8mb4` |
| Ekstensi PHP | gd (jpeg/freetype/webp), snmp, mysqli, pdo, pdo_mysql |
| Web server | Apache dengan `mod_rewrite`, `AllowOverride All` (pretty URL via `.htaccess`) |
| Frontend | Tailwind (CDN), Alpine.js, AOS (animasi), Swiper (carousel), Font Awesome |
| Pembayaran | Midtrans Snap (utama) + Tripay (callback tersedia) |
| Notifikasi | WhatsApp gateway via `wa.netter.my.id` (device id `iqra`), email via PHPMailer/SMTP |
| Lokasi kode | `/usr/local/app/iqra/html/` (docroot), `/usr/local/app/iqra/config.php` (konfigurasi, di-mount terpisah) |

## 2. Struktur Direktori

```
/usr/local/app/iqra/
├── config.php              # Koneksi DB, SMTP, WA gateway, session & helper (CSRF, sanitize)
└── html/                   # Docroot (di-mount ke /var/www/html)
    ├── index.php           # Homepage publik (52 KB, konten dinamis dari DB)
    ├── login.php / register.php / forgot-password.php / logout.php
    ├── process_donasi.php  # API donasi → Midtrans Snap (JSON)
    ├── .htaccess           # Pretty URL / routing
    ├── docker-compose.yml  # Service iqra (port 4700:80), Dockerfile (PHP 8.3)
    ├── struktur.md         # Peta struktur (usang, tidak sinkron dgn kondisi kini)
    ├── includes/           # auth_middleware.php, header.php, footer.php
    ├── pages/              # Halaman publik: kelas, detail_kelas, kegiatan, buku, tentang-kami, view_post
    ├── admin/              # Dashboard admin: manage_class, manage_landing, users, post, quotes, settings_gateway, wa_gateway
    ├── pengajar/           # Dashboard pengajar: create_class/activity/book/post, profile
    ├── pembelajar/         # Dashboard pembelajar
    ├── donatur/            # Dashboard donatur (dibuat 2026-10-15)
    ├── profile/            # Profil publik /username + kelola tautan (link-in-bio)
    ├── payment/            # payment_request.php, callback Midtrans & Tripay
    ├── asset/              # CSS/JS/gambar statis
    ├── uploads/            # Upload user: avatars/, buku/, kegiatan/
    ├── file/               # ⚠️ TinyFileManager 2.5.3 (lihat temuan keamanan)
    ├── v1/ v2/ v3/         # ⚠️ Salinan versi lama di web root (lihat temuan)
    ├── legaly/             # Salinan statis homepage legaly.id (tidak terpakai)
    └── PHPMailer/          # Library email

/usr/local/app/iqra/v2_old/   # Arsip versi lama (7,7 MB, di luar docroot)
```

## 3. Routing (`.htaccess`)

| URL | File |
|---|---|
| `/` | `index.php` |
| `/post/{slug}` | `pages/view_post.php` |
| `/kelas`, `/kelas/{id\|slug}` | `pages/kelas.php`, `pages/detail_kelas.php` |
| `/kegiatan`, `/buku`, `/tentang-kami` | `pages/*.php` |
| `/login`, `/register`, `/logout`, `/forgot-password` | file auth terkait |
| `/admin`, `/pengajar`, `/pembelajar`, `/donatur` | dashboard per role |
| `/profile/links`, `/{username}` | profil publik ala link-in-bio |

## 4. Peran Pengguna & Fitur

| Role | Akses |
|---|---|
| **admin** | Dashboard: kelola kelas (approve), kelola landing page, kelola pengguna, posting artikel, kelola quotes, pengaturan payment gateway, WA gateway |
| **pengajar** | Buat kelas, kegiatan, buku, postingan; kelola profil |
| **pembelajar** | Dashboard pembelajar, topup/donasi |
| **donatur** | Dashboard donatur (2026-10-15): ringkasan total/jumlah donasi, donasi bulan ini, riwayat transaksi + badge status, form donasi baru via Midtrans Snap |

Fitur utama:
- **Kelas**: daftar kelas, detail kelas (dukungan akses by id maupun slug), diskusi per kelas (`discussions`)
- **Donasi**: `process_donasi.php` menerima JSON → validasi → simpan transaksi pending → minta Snap Token Midtrans → kembalikan JSON; status final via webhook Midtrans (`payment/payment_callback_midtrans.php`); Tripay tersedia sebagai alternatif
- **Wallet/saldo**: tabel `wallets`, `transactions`, `transaksi`
- **Profil publik**: setiap user punya halaman `/{username}` + tautan (link-in-bio), riwayat username, pengaturan microsite
- **Konten landing dinamis**: section_quotes, section_program, section_kelas, section_kegiatan, section_buku, section_about, team, social_media — dikelola dari admin (`manage_landing.php`)
- **Keamanan auth**: password di-hash, proteksi CSRF (`generate_csrf_token`/`verify_csrf_token`), rate-limit login berbasis IP+user-agent (`login_attempts`), session cookie `httponly` + `secure` + `samesite=Lax`, helper `sanitize()`

## 5. Database (tabel yang dipakai kode)

`users`, `posts`, `section_kelas`, `profile_links`, `section_kegiatan`, `section_buku`, `section_program`, `section_quotes`, `transactions`, `transaksi`, `donasi_transactions`, `wallets`, `balance`, `discussions`, `section_about`, `login_attempts`, `gateway_settings`, `captcha_settings`, `profile_microsite_settings`, `profile_username_history`, `reserved_usernames`, `team`, `social_media`, `settings`, `key_value`, `programs`, `courses`, `events`, `newsletter`, `cover_color`.

**BARU 2026-10-17 — `activity_logs`**: mencatat setiap aksi CRUD (siapa, apa, kapan). Kolom: `id`, `user_id`, `username`, `fullname`, `role`, `action` (CREATE/UPDATE/DELETE/APPROVE/REJECT), `module`, `description`, `target_id`, `ip_address`, `created_at`. Ditulis via helper `log_activity()` di `config.php`. Ditampilkan di `admin/log.php` (filter modul/aksi/pencarian, pagination 25/hal); menu "Log Aktivitas" di sidebar admin (khusus super admin).

## 6. Konfigurasi Penting

- `config.php` memuat: kredensial database, kredensial SMTP, `WA_BASE_URL` + `WA_DEVICE_ID`, inisialisasi session aman, koneksi `$conn` (mysqli), helper CSRF & sanitasi.
- Fungsi `getWaHeaders()` didefinisikan di `config.php`; pernah terjadi konflik redeclare dengan `admin/wa_gateway.php` (lihat error_log lama, sudah tidak relevan dengan kode kini).
- Kredensial **tidak dicantumkan** di dokumen ini; kelola via `config.php` di server.

## 7. Riwayat Perubahan
- 2026-10-17: **Sistem log aktivitas** — tabel `activity_logs` + helper `log_activity($action, $module, $desc, $target_id)` di `config.php`; pencatatan ditambahkan ke: approve/reject kelas (`admin/approve_class.php`), ubah role user (`admin/users.php`), CRUD artikel (`admin/post.php`), CRUD quotes (`admin/process_quotes.php`), update profil (`admin/profile.php`, `pengajar/profile.php`). Halaman `admin/log.php` (khusus super admin) menampilkan log dengan filter modul/aksi/pencarian + pagination; link menu "Log Aktivitas" ditambahkan di `admin/menu.php`.
- 2026-10-17: **Fix double-escape judul kelas** di `admin/manage_class.php` — `htmlspecialchars` dipanggil 2x (saat bangun array + saat `json_encode` ke Alpine.js) sehingga "Qur'an" tampil "Qur&#039;an". Escape di level array dihapus; tampilan via `x-text` tetap aman dari XSS.
- 2026-10-17: **Fix upload foto profil** — bug silent: gagal upload di level PHP tidak menampilkan pesan error ke pengguna. Ditambahkan pesan error jelas per kode `UPLOAD_ERR_*` di `admin/profile.php` dan `pengajar/profile.php`. Limit dinaikkan 2MB → 5MB (validasi kode + `php_value upload_max_filesize 5M` & `post_max_size 8M` di `.htaccess`; container memakai mod_php).
- 2026-10-17: **Fix section pengurus homepage** — data `spesialisasi` di DB ter-escape 3x lipat (`syari&amp;amp;#039;ah`) sehingga tampil sebagai kode; dibersihkan langsung di DB. Tampilan kini memakai `html_entity_decode` sebelum `htmlspecialchars` agar tahan terhadap data lama. Card pengurus sekarang bisa diklik → link ke profil publik `/{username}` (query di `includes/header.php` ditambah kolom `username`).
- 2026-10-15: `donatur/index.php` dibuat — dashboard donatur (statistik, riwayat `donasi_transactions`, form donasi via `process_donasi.php` + Midtrans Snap), mengikuti pola UI `pembelajar/index.php`.
- 2026-10-15: `pembelajar/index.php` dijadikan dinamis (backup: `pembelajar/index_old.php`) — statistik banner, daftar kelas, buku, kegiatan mendatang, dan pengumuman kini diambil dari DB (`section_kelas`, `section_buku`, `section_kegiatan`, `posts`); menggantikan data contoh statis.
- 2026-10-15: filter status di dashboard pembelajar — hanya tampilkan kelas/buku/kegiatan berstatus `approved` dan artikel berstatus `published`; jadwal kegiatan kini menampilkan jam (`event_time`).
- 2026-10-15: kegiatan rutin (`execution_type='berulang'`) selalu ditampilkan di dashboard pembelajar walau `event_date` sudah lewat; tampil dengan ikon repeat + badge "Rutin • Setiap {hari}".
- 2026-10-15: tab Katalog Kelas / Koleksi Buku / Jadwal Kegiatan di dashboard pembelajar memakai pencarian, filter, dan pagination Alpine.js (sisi klien, data di-embed sebagai JSON): kelas (cari judul+pengajar, filter gratis/berbayar, 6/hal), buku (cari judul, filter gratis/berbayar, 8/hal), kegiatan (cari judul+lokasi, filter kategori dinamis + jenis rutin/sekali, 5/hal).
- 2026-10-15: BUGFIX dashboard pembelajar — data sempat tidak tampil setelah filter/pencarian ditambahkan. Penyebab: (1) JSON hasil `json_encode(..., JSON_HEX_*)` disisipkan langsung sebagai object literal di atribut `x-data` → syntax error; (2) `Object.assign` menonaktifkan getter (`get filtered()`). Perbaikan: JSON ditaruh di `<script type="application/json">` lalu `JSON.parse`, factory ditulis sebagai satu object literal. Pelajaran: JANGAN oper JSON hex-escaped sebagai literal JS di atribut; JANGAN gabungkan object berggetter via `Object.assign`/spread.
- 2026-10-15: sesi login (`login.php`): `is_logged_in, user_id, username, fullname, email, role, avatar, user_agent, ip_address` — TIDAK ada sesi no HP (kolom `users.no_hp` ada di DB). Dashboard pembelajar kini: tombol header tampilkan foto avatar dari sesi (`uploads/avatars/`, fallback ikon), form profil prefill no HP dari DB (no HP TIDAK ditampilkan di dropdown).
- 2026-10-15: BUGFIX "Edit Profil" di dropdown header tidak membuka tab Profil — penyebab: scope Alpine header terpisah dari scope tab (`main`), sehingga `activeTab` tak terjangkau. Perbaikan: satu scope `x-data="{ activeTab, userMenuOpen }"` dipindah ke `<body>`; link memakai `@click.prevent="activeTab='profil'; userMenuOpen=false"`.

## 2026-10-19 — LMS: tabel sesi/enrollment/progress + hapus kolom CTA
- Tambah kolom section_kelas: delivery_mode ENUM('rekaman','live','hybrid'), updated_at.
- Tabel baru: kelas_sessions (pertemuan: tipe video/meeting, video_url/file, durasi, meeting_platform/url/id/pass zoom-gmeet-jitsi, jadwal, is_free), kelas_enrollments (unique kelas_id+user_id), kelas_progress (completed_at, last_position_detik).
- Kolom useless teridentifikasi: cta_text (93% isi duplikat), cta_link (1/15 terisi, isi link generik), link_url (0/15 terisi). Dilepas dari: pages/detail_kelas.php (CTA jadi statik 'Daftar Kelas Sekarang', tombol external dihapus), index.php (kartu kelas selalu link internal), pengajar/create_class.php (hapus dari INSERT/UPDATE + form create/edit).

## 2026-10-19 — Owner filter panel pengajar
- Pengajar hanya lihat/edit/hapus konten miliknya sendiri; super_administrator & administrator tetap lihat semua.
- create_class.php: sudah benar sebelumnya (filter instructor_id + admin bypass).
- create_book.php: INSERT kini isi user_id; list/update/delete/status-guard difilter user_id utk non-admin; update/delete cek affected_rows.
- create_activity.php: INSERT kini isi user_id; list+count (paginasi) difilter user_id utk non-admin; update/delete/guard difilter.
- create_post.php & post.php: list sudah filter author_id, ditambah admin bypass; guard delete/toggle/update/edit kini bypass utk admin.

## 2026-10-19 — Sistem 1: Revenue Share (bagi hasil) LIVE
- Tabel: seller_earnings (teacher_id, transaction_id unique, item_type/id/name, gross, commission_pct, net, status pending/available/withdrawn/void, available_at +7 hari), withdrawals (teacher_id, amount, bank_*, status requested/approved/rejected/paid/cancelled, admin_note).
- Setting di gateway_settings: commission_pct (default 20), min_withdrawal (default 50000).
- Helper includes/earnings_helper.php: earn_setting, earn_rupiah, earn_refresh_status (pending->available otomatis), earn_record (idempotent, skip konten milik admin), earn_balance (available dikurangi reserved).
- Hook di payment/callback_midtrans.php & callback_tripay.php: catat earning saat PAID.
- Admin admin/bagi_hasil.php: statistik (GMV, komisi, dibayar, menunggu), pengaturan komisi/min WD, tabel penarikan (setujui/tolak+catatan/tandai dibayar, konsumsi FIFO + split), tabel penjualan (filter status, void pending). Menu + badge + notif lonceng.
- Pengajar pengajar/keuangan.php (link sidebar Keuangan sudah ada): kartu saldo, form tarik (validasi min & saldo), riwayat penarikan + batalkan, tabel konten terjual.

## 2026-10-19 — Toggle bagi hasil konten admin
- Setting baru gateway_settings: admin_earn_enabled (default 0/OFF).
- OFF: penjualan konten milik admin tidak mencatat earning (platform simpan 100%).
- ON: penjualan konten milik admin mencatat earning untuk akun admin tersebut (bisa ditarik seperti pengajar).
- Toggle ada di halaman Bagi Hasil > Pengaturan. Teruji ON/OFF via simulasi.

## 2026-10-19 — Biaya admin penarikan
- Setting withdrawal_fee (default 7500), bisa diubah di Bagi Hasil > Pengaturan.
- Kolom withdrawals.admin_fee: fee dicatat saat pengajuan (dipotong dari nominal; tarik 100rb -> diterima 92.5rb).
- Min penarikan diubah 50000 -> 100000.

## 2026-10-19 — Channel pembayaran TriPay
- Tabel tripay_channels (code, group, name, icon_url, fee_flat, fee_percent, min/max_amount, active).
- Tab TriPay settings_gateway: tombol 'Sinkronkan dari TriPay' (ambil dari API, simpan/upsert, flag active dipertahankan) + tabel channel dgn toggle aktif/nonaktif.
- payment/bayar.php & bayar_donasi.php: hanya tampilkan channel yg active=1 (bila tabel kosong -> tampilkan semua).

## 2026-10-19 — Laman Semua Transaksi
- admin/transaksi.php: gabungan donasi_transactions + transactions; filter tipe/status/rentang tanggal/no. transaksi; normalisasi status (lunas/belum/gagal); pagination 20/hal; menu 'Transaksi' di sidebar.

## 2026-10-20 — Diagnosis callback TriPay 'Invalid signature'
- Handler payment/callback_tripay.php BENAR (raw body php://input + HMAC-SHA256 dgn gateway_settings.tripay_private_key).
- Akar masalah: kredensial TriPay di gateway_settings TIDAK VALID lagi utk sandbox — uji buat transaksi ke api-sandbox dibalas HTTP 400 'Invalid API Key'; private key tersimpan juga tidak cocok dgn signature callback asli (transaksi DONASI-20261018060014-5661). Kunci tersimpan 2026-10-18 07:15 dan sempat bekerja (donasi 18 Okt 13:00 berhasil dibuat) → kemungkinan kunci di-regenerate/diganti di dashboard TriPay setelahnya.
- Perbaikan: isi ulang Merchant Code + API Key + Private Key SANDBOX terbaru dari dashboard TriPay ke Admin > Pengaturan Gateway, lalu tes ulang.
- Catatan: payment/payment_callback_tripay.php adalah kode mati — memakai konstanta TRIPAY_PRIVATE_KEY yang tidak pernah didefinisikan (akan fatal bila dipakai). URL callback resmi: /payment/callback_tripay.php.

## 2026-10-20 — Fix mapping status callback TriPay (lanjutan diagnosis kredensial)
- Setelah kredensial sandbox diperbarui user (17:38-17:39 WIB), signature LOLOS tapi callback FATAL 'Data truncated for column transaction_status': donasi_transactions.transaction_status adalah enum kosakata Midtrans (pending/settlement/capture/deny/cancel/expire/failure), sedangkan handler menulis lowercase status internal (paid/failed/dst).
- Fix di payment/callback_tripay.php (backup .bak-statusmap-20261020): mapping donasi eksplisit PAID->settlement, UNPAID->pending, EXPIRED->expire, FAILED->failure, REFUND->cancel; mapping transactions diperbaiki EXPIRED->EXPIRED (enum: UNPAID/PAID/EXPIRED/FAILED), REFUND->FAILED.
- Terverifikasi E2E: replay callback EXPIRED DONASI-20261018060014-5661 bertanda tangan valid -> {success:true}, status donasi menjadi 'expire'.

## Laman Kebijakan Privasi & Syarat & Ketentuan (2026-10-20)
- File baru: `html/pages/kebijakan-privasi.php` (10 pasal, acuan UU PDP 27/2022) dan `html/pages/syarat-ketentuan.php` (15 pasal). Keduanya halaman publik pola standar: set \$page_title/description/keywords lalu include `includes/header.php` & `includes/footer.php`; styling konten via blok <style> .legal-doc.
- URL cantik: `/kebijakan-privasi` dan `/syarat-ketentuan` (rule baru di .htaccess setelah rule tentang-kami; backup `.htaccess.bak-legal-20261020`).
- Link footer yg semula placeholder `href="#"` kini mengarah ke kedua URL tsb (backup `includes/footer.php.bak-legal-20261020`).
- Isi final dari draf docx v0.3 (artifact draft-privasi-syarat-iqra): penerbit Yayasan Granada Madani Peduli, Jl. Bangka, Mampang Prapatan, Jakarta Selatan; kontak support@iqrasociety.id / WA +62 896-4696-1458; berlaku efektif 1 Januari 2027 (launching). Sudah diputuskan: refund maks. pengajuan 14 hari kalender sejak transaksi, proses 10-30 hari kerja; bagi hasil dinamis 5%/10%/15%/0% per konten (CATATAN: sistem masih satu persen global commission_pct=20 — penyesuaian sistem terpisah); SLA penarikan 1x24 jam sejak disetujui, min Rp100.000, fee Rp7.500; akses kelas lifetime; forum sengketa berjenjang musyawarah 30 hari → BPSK (konsumen) → Pengadilan Negeri Jakarta Selatan.
- DITUNDA atas keputusan user (20 Okt): kebijakan pengembalian donasi final, batas usia/persetujuan orang tua rinci, jangka retensi pasti data transaksi utk pajak, konfirmasi forum sengketa, tinjauan penasihat hukum. Teks tayang memakai rumusan umum draf (donasi dinyatakan bukan zakat yg dikelola badan amil resmi kecuali dinyatakan tegas sebaliknya).
- Terverifikasi: lint PHP 8.3 bersih, HTTP 200 lokal (127.0.0.1:4700) & publik (https://iqrasociety.id), footer homepage memuat kedua tautan.

## 2026-10-21 — Handler pendaftaran kelas (daftar_kelas.php)
- File baru `html/daftar_kelas.php`: target form 'Daftar Kelas Sekarang' di `pages/detail_kelas.php` (sebelumnya 404). Aturan rewrite di `.htaccess`: `^kelas/daftar_kelas\.php$` -> handler root.
- Perilaku: wajib login + CSRF; kelas gratis langsung INSERT `kelas_enrollments` (status active, idempotent — enrollment cancelled/completed diaktifkan lagi); kelas berbayar diarahkan kembali ke halaman kelas (`?status=bayar`) untuk membayar via tombol Bayar/`payment/checkout.php`.
- `pages/detail_kelas.php`: banner status untuk `?status=terdaftar|sudah-terdaftar|bayar`. Backup: `.htaccess.bak-daftarkelas-20261021`, `pages/detail_kelas.php.bak-daftarkelas-20261021`. Terverifikasi E2E via HTTPS (sesi uji user tripay): gratis -> terdaftar, daftar ulang -> sudah-terdaftar, berbayar -> bayar, CSRF salah -> 403, tamu -> /login; baris tes dibersihkan.

## 2026-10-21 — Fix tombol Bayar mati di detail_kelas (beliItem terjebak branch 404)
- Akar masalah: satu-satunya definisi `beliItem()` di `pages/detail_kelas.php` berada di dalam blok `if (!$kelas) { ... exit; }`, sehingga halaman kelas yang valid tidak memuat fungsi itu — tombol Bayar/Beli memanggil fungsi tak terdefinisi (mati total). `pages/detail_buku.php` tidak terkena (definisinya di luar blok).
- Fix: blok `<script>` beliItem dipindah ke akhir halaman utama (sebelum `</body>` terakhir). Backup: `pages/detail_kelas.php.bak-beliitem-20261021`. Terverifikasi live: halaman kelas valid memuat 1 definisi beliItem, halaman tidak-ditemukan tetap utuh, POST `payment/checkout.php` (kelas 12) -> success tripay + bayar_url; transaksi tes dibersihkan.

## 2026-10-21 — Tombol Bayar di sticky bar mobile detail_kelas
- Masalah: widget pembelian ber-class `hidden lg:block` (desktop saja); sticky bar mobile hanya berisi form 'Daftar Kelas Sekarang' — kelas berbayar tidak punya tombol Bayar yang terjangkau di HP.
- Fix di `pages/detail_kelas.php` (sticky bar): kelas berbayar -> tombol Bayar amber `beliItem()` bila login, atau tautan 'Login untuk Membayar' bila tamu; kelas gratis -> tetap form daftar. Widget desktop tidak berubah. Backup: `pages/detail_kelas.php.bak-mobilebayar-20261021`. Terverifikasi live sbg tamu & sesi login uji.
