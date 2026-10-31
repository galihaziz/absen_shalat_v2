# Memperbaiki Error Import Siswa Setelah Deploy

## Gejala

Saat membuka `/import_siswa`, aplikasi menampilkan halaman error Laravel:

```text
ErrorException
Undefined variable $progressToken
resources/views/students/import.blade.php
```

Ini bukan error Nginx dan bukan karena versi PHP 8.4.27 pada tangkapan layar. Request sudah sampai ke Laravel, lalu Blade gagal dirender karena variabel yang dibutuhkan view tidak diberikan oleh controller.

## Penyebab

Fitur import di aplikasi terdiri dari beberapa bagian yang harus berasal dari versi yang sama:

- `app/Http/Controllers/ImportController.php` mengirim data ke halaman, memproses workbook, dan menyediakan endpoint progres bila fitur progres digunakan.
- `resources/views/students/import.blade.php` menampilkan form, pratinjau, dan/atau progres sesuai versi fitur.
- `routes/web.php` mendaftarkan endpoint import dan endpoint progres.
- `public/js/student-import-progress.js` menjalankan polling progres jika endpoint tersebut tersedia.

Pada source saat ini, `import.blade.php` menggunakan `$progressToken` dan route `students.import.progress`, tetapi `ImportController::create()` hanya mengembalikan view tanpa `$progressToken`. Controller juga harus cocok dengan route progres dan perilaku pratinjau yang dipakai oleh view. Keadaan ini biasanya terjadi karena ZIP berisi file dari beberapa revisi berbeda, proses ekstrak hanya menimpa sebagian file, atau controller sudah diganti tetapi view/route/JavaScript lama masih tertinggal.

## Cara pemulihan yang disarankan

Pulihkan satu versi fitur import yang lengkap dan konsisten. Jika ingin mempertahankan pratinjau, pemilihan baris, serta progres upload yang ada pada halaman, gunakan satu revisi source yang memang memiliki semua fitur tersebut. Jangan mencampur `ImportController.php`, `import.blade.php`, `routes/web.php`, dan JavaScript dari commit/ZIP yang berbeda.

### 1. Amankan data dan konfigurasi server

Sebelum mengganti file:

1. Backup database melalui aaPanel.
2. Salin `.env` dan folder `storage/` ke lokasi backup aman di luar folder ZIP.
3. Catat versi/commit source ZIP yang sedang dipakai.
4. Jangan menghapus folder project atau memilih opsi ekstrak yang membersihkan file yang tidak ada di ZIP.

### 2. Siapkan paket yang konsisten

Di komputer pengembangan, pilih satu branch/commit yang benar-benar akan digunakan, lalu buat ZIP dari satu working tree tersebut. Jangan menggabungkan file manual dari ZIP lama dan ZIP baru.

Untuk mempertahankan alur pratinjau/progres, pastikan implementasi dari revisi yang dipilih menyediakan seluruh bagian berikut secara serasi:

- `create()` mengirim token progres ke `students.import`.
- Method endpoint progres tersedia pada controller.
- Route `students.import.progress` menunjuk ke method tersebut.
- Method penyimpanan import membuat pratinjau dan endpoint konfirmasi yang digunakan oleh form.
- View dan `public/js/student-import-progress.js` memakai nama field serta format respons yang sama dengan controller.

Jika revisi yang dipilih sengaja menggunakan import sederhana tanpa progres/pratinjau, gunakan controller, view, routes, dan JavaScript yang memang satu paket untuk alur sederhana tersebut. Jangan hanya mengganti controller; itu dapat menghilangkan pratinjau atau menyebabkan error lanjutan.

### 3. Pasang file aplikasi tanpa menimpa data

1. Unggah ZIP lengkap yang sudah dipilih ke server.
2. Ekstrak ke folder project (misalnya `/www/wwwroot/absen_shalat/`), bukan langsung ke document root publik.
3. Pilih replace/overwrite file yang sama, tetapi jangan pilih opsi hapus folder tujuan atau hapus file lain.
4. Pastikan document root website tetap menunjuk ke `/www/wwwroot/absen_shalat/public`.
5. Jangan menimpa `.env`, `storage/`, atau database produksi. Jangan mengganti `APP_KEY`.

### 4. Bersihkan cache Laravel

Dari terminal aaPanel, jalankan perintah dengan PHP CLI yang sama dengan PHP website. Sesuaikan path PHP jika versi yang digunakan berbeda:

```sh
cd /www/wwwroot/absen_shalat
/www/server/php/82/bin/php artisan optimize:clear
/www/server/php/82/bin/php artisan route:list --path=import_siswa
/www/server/php/82/bin/php artisan view:clear
```

Periksa daftar route dan pastikan endpoint import yang dipakai view/controller tampil. Setelah pemeriksaan berhasil, cache konfigurasi produksi dapat dibuat kembali:

```sh
/www/server/php/82/bin/php artisan optimize
```

Jika Composer dependencies ikut berubah pada revisi tersebut, jalankan `composer install --no-dev --prefer-dist --optimize-autoloader` menggunakan PHP/Composer yang sesuai sebelum membuat cache.

### 5. Uji sebelum import data produksi

1. Buka `/import_siswa` dan pastikan halaman form muncul tanpa error.
2. Gunakan workbook salinan kecil untuk mencoba unggah dan pratinjau.
3. Pastikan filter, pemilihan baris, konfirmasi, dan progres berfungsi sesuai fitur yang dipakai.
4. Pastikan siswa hasil uji benar sebelum mengunggah workbook produksi.

## Jika hanya perlu menghentikan halaman debug

Di `.env` server, pastikan pengaturan produksi berikut dipakai:

```env
APP_ENV=production
APP_DEBUG=false
```

Kemudian bersihkan cache konfigurasi:

```sh
/www/server/php/82/bin/php artisan config:clear
```

Pengaturan ini menyembunyikan detail debug dari pengunjung, tetapi **tidak memperbaiki** mismatch `$progressToken`; source import tetap harus disinkronkan.

## Catatan keamanan

Tampilan debug pada tangkapan layar berarti detail exception aplikasi terlihat di browser. Setelah mengumpulkan pesan error yang diperlukan, matikan `APP_DEBUG` pada server produksi. Jangan membagikan screenshot debug yang memperlihatkan path server, data siswa, token, atau kredensial.
