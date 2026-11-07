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

## Menjalankan

Salin pohon ini ke server, isi kembali berkas konfigurasi, lalu
bangun container dari `html/docker-compose.yml`. Skema database berada di
server MySQL terpisah dan tidak termasuk dalam repo ini.
