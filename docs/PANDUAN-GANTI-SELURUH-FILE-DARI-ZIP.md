# Ganti Aplikasi dari ZIP Baru (MySQL)

> **Reset total menghapus seluruh data lama.** Lakukan hanya jika data siswa, absensi, akun, foto, dan pengaturan lama memang tidak diperlukan.

1. **Backup dulu** database MySQL dari aaPanel. Simpan jika sewaktu-waktu perlu pemulihan.
2. Di **aaPanel > Databases**, hapus **hanya database aplikasi ini**. Pastikan namanya cocok dengan `DB_DATABASE` pada `.env` lama. Jangan hapus database situs lain.
3. Di **aaPanel > Files**, hapus folder project lama, termasuk `.env` dan `storage/`.
4. Buat database MySQL baru beserta user dan password di aaPanel.
5. Unggah dan ekstrak ZIP baru ke folder project. Pastikan `artisan`, `composer.json`, `app/`, dan `public/` berada langsung di folder tersebut.
6. Pastikan document root website tetap mengarah ke folder project `public/`, misalnya `/www/wwwroot/absen_shalat/public`. Aktifkan rewrite Laravel Nginx agar URL `/staff/login` tidak menjadi 404.
7. Buat `.env` baru dari `.env.example`. Atur `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, database MySQL baru, SMTP, serta password akun awal yang kuat.
8. Di terminal aaPanel, sesuaikan versi/path PHP dengan yang digunakan website, lalu jalankan:

   ```sh
   cd /www/wwwroot/absen_shalat
   export PATH=/www/server/php/82/bin:$PATH
   composer install --no-dev --prefer-dist --optimize-autoloader
   php artisan key:generate
   php artisan migrate --seed --force
   php artisan optimize
   ```

9. Pastikan `storage/` dan `bootstrap/cache/` dapat ditulis oleh PHP-FPM. Jangan gunakan permission `777`.
10. Uji `/staff/login`, login dengan akun baru, dan fitur utama. Hapus backup database hanya setelah yakin instalasi baru berjalan baik.

**Jangan pulihkan `.env` atau database lama** jika benar-benar ingin reset total. Perintah `key:generate` hanya dijalankan untuk instalasi baru; jangan jalankan lagi setelah aplikasi dipakai.
