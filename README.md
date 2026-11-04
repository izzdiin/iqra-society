# IQRA Society

Kode sumber platform IQRA Society (Yayasan Granada Madani Peduli): LMS dan
manajemen yayasan berbasis PHP native — kelas, kegiatan, buku, donasi, dan
pembayaran (Midtrans + Tripay). Dokumentasi lengkap proyek ada di
`DOKUMENTASI.md`.

## Struktur

- `html/` — aplikasi utama (docroot): halaman publik (`pages/`), panel
  `admin/`, `pengajar/`, `pembelajar/`, `donatur/`, alur `payment/`,
  `includes/` (termasuk `auth_middleware.php`), dan `PHPMailer/`.
- `html/Dockerfile` + `html/docker-compose.yml` — resep container
  (PHP 8.3 + Apache).
- `config.php` — konfigurasi utama (di luar docroot saat produksi).

## Yang TIDAK disertakan di repo ini

- Semua berkas cadangan (`*.bak*`) dan folder versi lama di luar `html/`
  (`v2_old/`).
- Direktori varian/prototipe `html/v1/`, `html/v2/`, `html/v3/` dan
  pengelola berkas `html/file/` (tetap ada di server, tidak di repo).
- Berkas gambar biner di `html/asset/img/` (logo, favicon, ikon, foto) —
  hanya kode dan dokumen teks yang disimpan di repo ini.
- Isi `html/uploads/` (berkas unggahan pengguna).
- Log (`callback.log`, `error_log`) dan salinan berkas bertanda waktu.
- **Kredensial asli.** Nilai sensitif di `config.php` sudah dikosongkan — isi dengan nilai lingkungan
  masing-masing sebelum menjalankan. Kunci Midtrans/Tripay serta token
  gateway WA tidak pernah ada di berkas: nilainya tersimpan di database
  (tabel pengaturan) dan diatur dari panel admin.

## Menjalankan

Salin pohon ini ke server, isi kembali berkas konfigurasi di atas, lalu
bangun container dari `html/docker-compose.yml`. Skema database berada di
server MySQL terpisah dan tidak termasuk dalam repo ini.
