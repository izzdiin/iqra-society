<?php
$page_title       = "Kebijakan Privasi - IQRA Society";
$page_description = "Kebijakan Privasi IQRA Society (iqrasociety.id) yang dikelola Yayasan Granada Madani Peduli: data pribadi yang dikumpulkan, tujuan penggunaan, pembagian kepada pihak ketiga, dan hak-hak pengguna sesuai UU Pelindungan Data Pribadi.";
$page_keywords    = "Kebijakan Privasi, IQRA Society, Pelindungan Data Pribadi, UU PDP";

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .legal-doc h2 { font-size: 1.25rem; font-weight: 700; color: #065f46; margin-top: 2rem; margin-bottom: 0.75rem; }
    .legal-doc h2:first-of-type { margin-top: 0.5rem; }
    .legal-doc p { color: #374151; line-height: 1.75; margin-bottom: 0.75rem; font-size: 0.95rem; }
    .legal-doc ul, .legal-doc ol { color: #374151; line-height: 1.75; margin-bottom: 0.75rem; font-size: 0.95rem; padding-left: 1.5rem; }
    .legal-doc ul { list-style: disc; }
    .legal-doc ol { list-style: decimal; }
    .legal-doc li { margin-bottom: 0.35rem; }
    .legal-doc table { width: 100%; border-collapse: collapse; margin: 1rem 0 1.25rem; font-size: 0.9rem; }
    .legal-doc th { background: #ecfdf5; color: #065f46; text-align: left; padding: 0.6rem 0.75rem; border: 1px solid #d1fae5; font-weight: 600; }
    .legal-doc td { padding: 0.6rem 0.75rem; border: 1px solid #e5e7eb; color: #374151; vertical-align: top; line-height: 1.6; }
    .legal-doc strong { color: #111827; }
</style>

<main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <article class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 md:p-10 legal-doc">
        <p class="text-xs font-semibold tracking-widest text-emerald-600 uppercase" style="margin-bottom:0.25rem;">IQRA Society &mdash; iqrasociety.id</p>
        <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-3">Kebijakan Privasi</h1>
        <p class="text-sm text-gray-500" style="margin-bottom:1.5rem;">
            Diterbitkan oleh <strong>Yayasan Granada Madani Peduli</strong>, pengelola IQRA Society<br>
            Jl. Bangka, Mampang Prapatan, Jakarta Selatan &bull; Email: <a href="mailto:support@iqrasociety.id" class="text-emerald-700 hover:underline">support@iqrasociety.id</a> &bull; WhatsApp: <a href="https://wa.me/6289646961458" class="text-emerald-700 hover:underline">+62 896-4696-1458</a><br>
            Berlaku efektif sejak <strong>1 Januari 2027</strong> &bull; Versi 1.0
        </p>

        <p><em>Kebijakan Privasi ini menjelaskan data pribadi apa saja yang dikumpulkan IQRA Society, untuk apa data itu digunakan, dengan siapa data dibagikan, berapa lama data disimpan, dan hak apa saja yang dimiliki pengguna. Kebijakan ini disusun mengacu pada Undang-Undang Nomor 27 Tahun 2022 tentang Pelindungan Data Pribadi (UU PDP), Undang-Undang tentang Informasi dan Transaksi Elektronik, serta peraturan penyelenggaraan sistem elektronik yang berlaku di Indonesia.</em></p>

        <h2>Pasal 1 &mdash; Pengendali Data dan Ruang Lingkup</h2>
        <p>Pengendali data pribadi dalam layanan ini adalah Yayasan Granada Madani Peduli, selaku pengelola IQRA Society, beralamat di Jl. Bangka, Mampang Prapatan, Jakarta Selatan (selanjutnya disebut &ldquo;IQRA&rdquo;, &ldquo;kami&rdquo;). Permintaan dan pertanyaan terkait data pribadi dapat disampaikan melalui email support@iqrasociety.id atau WhatsApp +62 896-4696-1458. Kebijakan ini berlaku untuk seluruh layanan di iqrasociety.id, termasuk pengunjung yang belum memiliki akun, pembelajar, pengajar, donatur, dan admin.</p>
        <p>Kebijakan ini tidak mengatur situs atau layanan pihak ketiga yang ditautkan dari platform &mdash; misalnya halaman pembayaran Midtrans atau Tripay dan ruang pertemuan Zoom, Google Meet, atau Jitsi. Layanan tersebut memiliki kebijakan privasinya sendiri.</p>

        <h2>Pasal 2 &mdash; Data Pribadi yang Kami Kumpulkan</h2>
        <p>Kami hanya mengumpulkan data yang diperlukan untuk menjalankan platform. Jenis data, sumbernya, dan kegunaannya diringkas dalam tabel berikut.</p>
        <table>
            <tr><th style="width:22%;">Kategori data</th><th>Contoh data</th><th style="width:26%;">Sumber</th></tr>
            <tr><td>Data akun</td><td>Nama lengkap, nama pengguna (username), alamat email, nomor telepon/WhatsApp, kata sandi (tersimpan dalam bentuk hash, bukan teks asli), peran pengguna, foto profil/avatar</td><td>Diisi pengguna saat pendaftaran dan pengaturan profil</td></tr>
            <tr><td>Data profil publik</td><td>Nama pengguna, foto, biodata singkat, dan tautan yang pengguna pilih untuk ditampilkan pada halaman profil publiknya</td><td>Diatur sendiri oleh pengguna</td></tr>
            <tr><td>Data transaksi</td><td>Kelas dan buku yang dibeli, kegiatan yang diikuti, nominal, status pembayaran, riwayat transaksi dan donasi</td><td>Terbentuk saat pengguna bertransaksi</td></tr>
            <tr><td>Data pembayaran</td><td>Metode pembayaran, kode referensi transaksi, dan status pembayaran dari gerbang pembayaran. Kami TIDAK menyimpan nomor kartu, PIN, atau kredensial perbankan pengguna</td><td>Midtrans dan Tripay sebagai pemroses pembayaran</td></tr>
            <tr><td>Data saldo dan bagi hasil (khusus pengajar)</td><td>Saldo hasil penjualan konten, perhitungan bagi hasil, riwayat dan pengajuan penarikan, serta data rekening/e-wallet tujuan penarikan yang diberikan pengajar</td><td>Sistem bagi hasil dan formulir penarikan</td></tr>
            <tr><td>Data pembelajaran</td><td>Kelas yang diikuti (enrollment), progres per sesi, posisi terakhir video yang ditonton, kehadiran/akses sesi live, dan isi diskusi kelas</td><td>Aktivitas pengguna di dalam kelas</td></tr>
            <tr><td>Data konten</td><td>Kelas, buku, kegiatan, artikel, dan berkas yang diunggah pengajar; komentar dan diskusi yang ditulis pengguna</td><td>Diunggah/ditulis pengguna</td></tr>
            <tr><td>Data donasi</td><td>Nominal donasi, program tujuan (bila dipilih), nama dan kontak donatur (bila diisi), dan status donasi</td><td>Formulir donasi dan gerbang pembayaran</td></tr>
            <tr><td>Data teknis dan keamanan</td><td>Alamat IP, jenis perangkat dan peramban (user-agent), waktu akses, catatan percobaan masuk (login), dan log aktivitas penting di panel admin</td><td>Tercatat otomatis oleh sistem</td></tr>
            <tr><td>Data komunikasi</td><td>Pesan WhatsApp dan email yang berkaitan dengan akun, transaksi, dan notifikasi layanan</td><td>Gateway WhatsApp dan layanan email kami</td></tr>
        </table>
        <p>Pengguna dapat menjelajahi sebagian konten publik tanpa akun. Pendaftaran akun diperlukan untuk membeli kelas atau buku, mengikuti proses pembelajaran secara tercatat, mengajukan konten sebagai pengajar, dan mengakses dasbor pribadi.</p>

        <h2>Pasal 3 &mdash; Tujuan dan Dasar Pemrosesan</h2>
        <p>Kami memproses data pribadi untuk tujuan berikut:</p>
        <ul>
            <li><strong>Penyediaan layanan:</strong> menyelenggarakan akun dan layanan inti &mdash; pendaftaran, masuk (login), dasbor per peran, dan profil pengguna;</li>
            <li><strong>Transaksi:</strong> memproses pembelian kelas dan buku, donasi, saldo, bagi hasil, dan penarikan dana pengajar, termasuk verifikasi status pembayaran;</li>
            <li><strong>Pembelajaran:</strong> menjalankan pembelajaran &mdash; menyimpan pendaftaran kelas, progres belajar, dan kelanjutan posisi video;</li>
            <li><strong>Notifikasi:</strong> mengirimkan pemberitahuan transaksi, pengingat kelas/kegiatan, dan informasi layanan melalui WhatsApp dan email;</li>
            <li><strong>Profil publik:</strong> menampilkan profil publik dan tautan hanya sejauh yang diatur sendiri oleh pengguna;</li>
            <li><strong>Keamanan dan akuntabilitas:</strong> mencegah penyalahgunaan, percobaan masuk tidak sah, dan kecurangan, serta mencatat aktivitas administratif untuk akuntabilitas;</li>
            <li><strong>Kepatuhan hukum:</strong> memenuhi kewajiban pencatatan transaksi dan kewajiban hukum lain yang berlaku.</li>
        </ul>
        <p>Dasar pemrosesan kami adalah, sesuai konteksnya: persetujuan pengguna; pelaksanaan perjanjian layanan (Syarat &amp; Ketentuan) antara pengguna dan IQRA; pemenuhan kewajiban hukum; dan kepentingan yang sah dalam menjaga keamanan dan keberlangsungan platform &mdash; sepanjang tidak mengesampingkan hak dan kebebasan pengguna sebagaimana diatur UU PDP. Pemasaran langsung, bila suatu saat dilakukan, hanya akan dikirim kepada pengguna yang telah memberikan persetujuan dan dapat dihentikan kapan saja.</p>

        <h2>Pasal 4 &mdash; Pembagian Data kepada Pihak Ketiga</h2>
        <p>Kami tidak menjual data pribadi pengguna. Data hanya dibagikan kepada pihak yang menjalankan fungsi tertentu untuk kami, sebatas data yang mereka butuhkan:</p>
        <table>
            <tr><th style="width:26%;">Penerima</th><th>Data yang diteruskan</th><th>Keperluan</th></tr>
            <tr><td>Midtrans dan Tripay (gerbang pembayaran)</td><td>Nama, email, nomor telepon, nominal dan rincian transaksi</td><td>Memproses pembayaran pembelian dan donasi serta melaporkan statusnya kepada kami</td></tr>
            <tr><td>Penyedia gateway WhatsApp</td><td>Nomor WhatsApp dan isi notifikasi</td><td>Mengirim notifikasi transaksi dan layanan</td></tr>
            <tr><td>Penyedia layanan email (SMTP)</td><td>Alamat email dan isi pemberitahuan</td><td>Mengirim email akun, transaksi, dan pemulihan kata sandi</td></tr>
            <tr><td>Zoom, Google Meet, Jitsi (untuk kelas live)</td><td>Nama tampilan dan data yang pengguna berikan langsung di platform tersebut</td><td>Menyelenggarakan sesi kelas daring; diatur oleh kebijakan masing-masing platform</td></tr>
            <tr><td>Pengajar (terbatas)</td><td>Nama dan progres pembelajar di kelas yang ia ampu</td><td>Menjalankan dan mengevaluasi pembelajaran</td></tr>
            <tr><td>Aparat/lembaga berwenang</td><td>Data sesuai permintaan yang sah</td><td>Hanya bila diwajibkan oleh hukum atau perintah yang sah</td></tr>
        </table>
        <p>Setiap pengguna juga perlu memahami bahwa informasi yang ia pilih untuk tampil di profil publiknya &mdash; nama pengguna, foto, dan tautan &mdash; memang dapat dilihat publik sesuai pengaturannya sendiri.</p>

        <h2>Pasal 5 &mdash; Penyimpanan dan Masa Retensi</h2>
        <p>Data disimpan pada server yang kami kelola selama akun aktif dan selama data masih diperlukan untuk tujuan pengumpulannya. Secara khusus: data transaksi dan donasi disimpan sekurang-kurangnya selama jangka waktu pencatatan yang diwajibkan peraturan perpajakan dan pembukuan yang berlaku; log keamanan disimpan dalam jangka terbatas untuk keperluan audit dan pencegahan penyalahgunaan; dan data pembelajaran disimpan selama kelas masih diikuti atau tersedia.</p>
        <p>Apabila akun dihapus atas permintaan pengguna, data pribadi yang tidak lagi wajib disimpan akan dihapus atau dianonimkan dalam jangka waktu yang wajar. Data yang wajib dipertahankan demi hukum, penyelesaian sengketa, atau pencegahan kecurangan dikecualikan dari penghapusan dan hanya digunakan untuk keperluan tersebut.</p>

        <h2>Pasal 6 &mdash; Keamanan Data</h2>
        <p>Kami menerapkan langkah teknis dan organisatoris yang sepadan dengan sistem kami, antara lain: kata sandi disimpan dalam bentuk hash satu arah; sesi masuk menggunakan cookie dengan atribut HttpOnly, Secure, dan SameSite; perlindungan terhadap pemalsuan permintaan lintas situs (CSRF); pembatasan percobaan masuk berulang; penggunaan pernyataan basis data terparametri (prepared statement) untuk mencegah injeksi; serta pencatatan aktivitas administratif penting. Akses ke panel admin dibatasi berdasarkan peran.</p>
        <p>Tidak ada sistem yang sepenuhnya kebal risiko. Pengguna wajib menjaga kerahasiaan kata sandinya sendiri dan segera melapor kepada kami bila mencurigai akunnya diakses pihak lain. Bila terjadi kegagalan perlindungan data pribadi yang berdampak bagi pengguna, kami akan melakukan pemberitahuan sesuai kewajiban dalam UU PDP.</p>

        <h2>Pasal 7 &mdash; Hak-Hak Pengguna</h2>
        <p>Sesuai UU PDP, pengguna berhak untuk: memperoleh informasi dan akses atas data pribadinya; memperbaiki data yang tidak akurat; meminta penghapusan data; menarik persetujuan pemrosesan yang berbasis persetujuan; membatasi atau mengajukan keberatan atas pemrosesan tertentu; memperoleh salinan data dalam format yang lazim (portabilitas), sepanjang layak secara teknis; dan mengajukan pengaduan kepada kami serta kepada otoritas yang berwenang.</p>
        <p>Permintaan dapat diajukan melalui kontak resmi pada bagian awal halaman ini dengan menyebutkan nama pengguna dan jenis permintaan. Kami dapat meminta verifikasi identitas seperlunya untuk memastikan data tidak diserahkan kepada pihak yang salah, dan akan menanggapi dalam jangka waktu sesuai ketentuan UU PDP. Sebagian permintaan dapat tidak dipenuhi sebagian apabila data terkait masih wajib kami simpan berdasarkan hukum; dalam hal demikian kami akan menjelaskan alasannya.</p>

        <h2>Pasal 8 &mdash; Data Anak</h2>
        <p>Layanan ini ditujukan untuk pengguna yang telah cakap hukum. Pengguna di bawah usia 18 tahun hanya boleh mendaftar dan bertransaksi dengan persetujuan orang tua atau wali yang sah, sesuai ketentuan data pribadi anak dalam UU PDP. Orang tua atau wali dapat menghubungi kami untuk meninjau, memperbaiki, atau meminta penghapusan data anak yang berada di bawah tanggung jawabnya.</p>

        <h2>Pasal 9 &mdash; Cookie dan Teknologi Serupa</h2>
        <p>Platform menggunakan cookie sesi yang diperlukan agar pengguna tetap masuk (login) dan agar fungsi keamanan bekerja. Cookie ini bersifat esensial dan tidak digunakan untuk iklan. Apabila di kemudian hari kami menambahkan layanan analitik atau teknologi pelacakan non-esensial, kami akan memperbarui pasal ini dan meminta persetujuan bila diwajibkan. Pengguna dapat menghapus cookie melalui pengaturan peramban, dengan konsekuensi harus masuk kembali dan sebagian fungsi dapat terganggu.</p>

        <h2>Pasal 10 &mdash; Perubahan Kebijakan Privasi</h2>
        <p>Kami dapat memperbarui Kebijakan Privasi ini dari waktu ke waktu. Perubahan material akan diumumkan melalui situs dan/atau WhatsApp/email, dengan mencantumkan tanggal berlaku versi baru. Pengguna yang tidak menyetujui perubahan dapat berhenti menggunakan layanan dan meminta penghapusan akun sebelum tanggal berlaku tersebut.</p>

        <p class="text-sm text-gray-500" style="margin-top:2rem;">Lihat juga: <a href="/syarat-ketentuan" class="text-emerald-700 font-medium hover:underline">Syarat &amp; Ketentuan IQRA Society</a></p>
    </article>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
