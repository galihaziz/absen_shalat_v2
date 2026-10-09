# Dokumentasi Aplikasi Rekap Absensi Solat

## 1. Ringkasan

Rekap Absensi Solat adalah aplikasi web berbasis Laravel untuk mengelola data kelas dan siswa, mencatat kehadiran, memantau laporan, mengelola QR siswa, serta mencetak dan mengekspor dokumen. Aplikasi menyediakan antarmuka operasional untuk staf dan portal kartu untuk siswa.

## 2. Pengguna dan hak akses

| Peran | Hak akses utama |
|---|---|
| Admin | Semua fungsi staf, pengelolaan pengguna, impor siswa, dan pengaturan template kartu. |
| Kesiswaan | Absensi, laporan, data siswa/kelas, foto siswa, kartu QR, dan reset PIN portal siswa. |
| Absensi | Operasional absensi dan pemindaian QR. |
| Siswa | Login portal, melihat kartu miliknya, mencetak/mengunduh PDF, dan mengubah PIN. |

Route staf dilindungi autentikasi Laravel; fungsi manajemen tertentu memakai pembatasan role. Portal siswa menggunakan sesi terpisah dari akun staf.

## 3. Fitur aplikasi

### 3.1 Login dan akun

- Halaman utama `/` menampilkan login siswa.
- Halaman login staf tersedia melalui `/staff/login` (jalur lama `/login` juga tersedia).
- Login staf memakai username dan password.
- Login siswa memakai nomor induk dan PIN.
- Admin dapat meminta tautan reset password melalui email pemulihan yang terdaftar.
- Form login dan perubahan/reset password memiliki pembatasan request.

### 3.2 Portal siswa dan kartu

- Siswa melihat kartu yang terikat pada sesi siswa yang sedang masuk.
- Kartu menampilkan identitas dan QR; foto/template kartu digunakan jika tersedia.
- Kartu dapat dicetak dari browser atau diunduh sebagai PDF ukuran kartu.
- Tersedia halaman khusus untuk mengganti PIN setelah masuk.
- Siswa dapat keluar dari portal; rute kartu/PDF memerlukan sesi portal siswa.

**Catatan PIN:** konfigurasi saat ini menggunakan PIN awal bersama `siswa123` untuk siswa lama, siswa baru, dan reset staf. PIN tersimpan dalam bentuk hash. Karena PIN awal sama untuk semua siswa, siswa perlu menggantinya dan PIN awal tidak boleh dianggap sebagai faktor rahasia yang unik.

### 3.3 Data siswa dan kelas

- Admin/kesiswaan dapat menambah, memperbarui, mencari, dan menghapus data siswa.
- Daftar mendukung pencarian berdasarkan nama, nomor induk, dan kelas serta pemuatan bertahap.
- Pengelolaan kelas/rombel mencakup tambah dan hapus kelas.
- Data kategori siswa mencakup umum, IRMA, dan nonis.
- Foto siswa dapat diunggah, diganti, atau dihapus.
- Data siswa dapat dihapus satu per satu atau secara massal berdasarkan kelas, tingkat, atau jurusan; aksi massal menghapus siswa, riwayat absensi, dan foto terkait.
- Staf kesiswaan/admin dapat mengatur ulang PIN portal siswa.

### 3.4 Impor siswa

- Admin dapat mengimpor workbook `.xlsx` atau `.ods`.
- Import membaca beberapa sheet, mengenali variasi judul kolom umum, dan dapat memakai nama sheet sebagai petunjuk kelas.
- Data baru ditambahkan, data yang cocok diperbarui, serta siswa aktif yang tidak ada dalam data impor dapat ditandai alumni.
- Import dibatasi ukuran unggahan hingga 10 MiB dan dijalankan dalam transaksi database.

### 3.5 Absensi dan pemindaian QR

- Absensi dapat dicatat atau diperbarui melalui halaman operasional.
- Status yang tersedia: hadir (`H`), alpha (`A`), izin (`I`), sakit (`S`); status kosong dapat menghapus catatan.
- Halaman menampilkan ringkasan harian dan progres pencatatan kelas.
- Pemindaian QR menggunakan nomor induk; pemindaian mencatat hadir dan waktu scan.
- Pemindaian berulang pada hari yang sama tidak menggandakan catatan hadir.
- Rentang waktu pemindaian dapat dikonfigurasi; di luar jadwal scan ditolak.
- Siswa alumni tidak masuk sebagai sasaran scan.

### 3.6 Laporan

- Rekap absensi dapat difilter berdasarkan periode, kelas, dan pencarian nama.
- Periode laporan mendukung mingguan dan bulanan.
- Laporan menghitung jumlah status, persentase kehadiran, perbandingan tren periode, dan peringkat alpha kelas.
- Tersedia daftar siswa alpha beserta tanggal ketidakhadiran.
- Admin/kesiswaan dapat mengatur jam mulai dan akhir pemindaian.

### 3.7 QR siswa dan kartu massal

- Staf dapat melihat QR per siswa, membuat PDF kartu per siswa, atau memilih beberapa siswa.
- Pemilihan kartu manual dan pembuatan kartu massal mendukung kelompok kelas, tingkat, atau jurusan.
- Pembuatan PDF massal dibatasi maksimal 100 kartu per dokumen.
- Tersedia QR dalam format SVG serta pengelolaan template kartu (admin).

### 3.8 Cetak dan ekspor

- Rekap absensi dapat diekspor menjadi workbook Excel `.xlsx`.
- Form cetak mendukung dokumen A4 dalam `.xlsx` dan `.docx`.
- Ekspor dapat disaring per kelas/periode sesuai fiturnya.
- PDF kartu siswa dibuat menggunakan Dompdf; file workbook menggunakan PhpSpreadsheet dan dokumen Word memakai PhpWord.

## 4. Alur utama

### Staf mencatat absensi dengan QR

1. Staf masuk ke `/staff/login`.
2. Buka menu scan QR.
3. Aplikasi membaca nomor induk dan memvalidasi siswa aktif.
4. Jika scan diizinkan oleh jam operasional, aplikasi menyimpan status hadir dan waktu scan.
5. Ringkasan absensi diperbarui tanpa membuat catatan hadir ganda untuk siswa/tanggal yang sama.

### Siswa melihat kartu

1. Siswa membuka halaman utama atau `/siswa/login`.
2. Siswa memasukkan nomor induk dan PIN.
3. Setelah autentikasi berhasil, aplikasi membuat sesi portal siswa.
4. Halaman kartu hanya mengambil siswa dari sesi tersebut.
5. Siswa dapat mencetak, mengunduh PDF, mengubah PIN, atau keluar.

### Admin mengimpor daftar siswa

1. Admin membuka menu impor dan mengunggah workbook yang sesuai.
2. Aplikasi memvalidasi tipe/ukuran file dan membaca sheet.
3. Data diproses dalam transaksi; siswa diperbarui atau ditambahkan.
4. Aplikasi melaporkan jumlah data baru, pembaruan, dan siswa yang ditandai alumni.

## 5. Arsitektur teknis

- **Backend:** Laravel 11, PHP 8.2 atau lebih baru.
- **UI:** Blade, CSS, dan JavaScript pada `resources/views` serta `public`.
- **Database:** konfigurasi Laravel mendukung MySQL, dan test suite menggunakan SQLite in-memory.
- **Model/domain:** `User`, `Classroom`, `Student`, `Attendance`, dan `AppSetting`.
- **Relasi utama:** kelas memiliki banyak siswa; siswa memiliki banyak catatan absensi; absensi terkait satu siswa.
- **Pembuatan file:** PhpSpreadsheet (`.xlsx`), PhpWord (`.docx`), Dompdf (PDF), Endroid QR Code.
- **Routing:** `routes/web.php`; controller di `app/Http/Controllers`; middleware role dan portal siswa di `app/Http/Middleware`.

Tabel domain yang digunakan aplikasi mencakup `users`, `kelas`, `siswa`, `absensi`, serta tabel pengaturan aplikasi. Struktur sebenarnya mengikuti migrasi dan database yang dikonfigurasi.

## 6. Rute yang sering digunakan

| URL | Fungsi |
|---|---|
| `/` | Login siswa |
| `/staff/login` | Login staf |
| `/staff` | Halaman absensi staf setelah autentikasi |
| `/scan-qr` | Pemindaian QR absensi |
| `/laporan` | Laporan kehadiran |
| `/alpha` | Daftar alpha |
| `/data_siswa` | Manajemen siswa dan kelas |
| `/import_siswa` | Impor workbook (admin) |
| `/template_kartu` | Template kartu (admin) |
| `/siswa/login` | Login portal siswa |
| `/siswa/kartu` | Kartu siswa |
| `/siswa/pin` | Ubah PIN portal |

## 7. Keamanan dan batasan operasional

- Route staf memakai autentikasi dan role sesuai fungsi; form web menggunakan perlindungan CSRF Laravel.
- Endpoint login, scan, laporan, impor, PDF, dan ekspor menerapkan pembatasan request pada route tertentu.
- Password staf dan PIN siswa disimpan menggunakan hash Laravel.
- Tautan reset password dibuat dari `APP_URL`; jangan gunakan domain yang tidak dipercaya.
- `TRUSTED_PROXIES` harus kosong kecuali alamat/CIDR proxy yang benar-benar tepercaya diketahui. Jangan mengatur wildcard `*`.
- PIN default `siswa123` bersama adalah risiko residual yang disengaja saat ini. Berikan PIN secara hati-hati dan minta setiap siswa menggantinya.
- Throttle aplikasi tidak menggantikan proteksi DDoS volumetrik. Untuk deployment publik, gunakan CDN/WAF/reverse proxy dan cegah akses langsung ke origin bila memungkinkan.
- Jangan mengaktifkan `APP_DEBUG=true` pada production. Rahasia seperti `APP_KEY`, password database, SMTP, dan kredensial awal hanya disimpan di `.env`, bukan dibagikan atau dikomit.
- Tes otomatis perlu dijalankan pada lingkungan yang dapat mengeksekusi PHP. Keberhasilan pemeriksaan statis tidak sama dengan verifikasi production.

## 8. Konfigurasi dan menjalankan

Jalankan perintah Composer/Artisan dari folder `absen_shalat/`.

```powershell
cd C:\laragon\www\rekap_absen_solat\absen_shalat
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Atur koneksi database, akun awal, `APP_URL`, email/SMTP, dan domain sesuai lingkungan. Untuk proxy, isi `TRUSTED_PROXIES` dengan IP/CIDR proxy tepercaya saja. Setelah mengubah konfigurasi environment, bersihkan cache konfigurasi bila diperlukan:

```powershell
php artisan config:clear
php artisan test
```

## 9. Pengujian dan pemeliharaan

Test suite mencakup autentikasi/role, pencatatan absensi, laporan, QR, impor, ekspor, portal siswa, dan reset password. Setelah deploy atau perubahan skema, jalankan migrasi serta tes. Pantau log Laravel, kapasitas database, ukuran workbook unggahan, penyimpanan foto/template, sertifikat HTTPS, dan pembatasan trafik di edge.

## 10. Batas cakupan dokumentasi

Dokumen ini merangkum fitur pada source code dan route yang ditinjau. Infrastruktur hosting, aturan firewall/CDN, konfigurasi reverse proxy aktual, praktik operasional sekolah, dan pemulihan bencana tidak dapat dipastikan hanya dari repository.
