# Prototipe Lama Absensi Wajah

Folder ini adalah prototipe mandiri dan tidak lagi menjadi aplikasi presensi yang didukung. Gunakan integrasi Laravel dari folder proyek utama melalui menu **Scan Wajah**; data pendaftaran dan kehadiran Laravel tersimpan pada database aplikasi yang sama. Jangan gunakan konfigurasi database atau endpoint pada prototipe ini untuk presensi operasional.

---

Dokumentasi berikut hanya menjelaskan cara menjalankan prototipe lama untuk referensi.

Fondasi aplikasi absensi berbasis browser dengan PHP 8.1+, MySQL, dan face-api.js. Deteksi serta pembuatan representasi wajah dilakukan di browser; server menyimpan representasi tersebut dan melakukan pencocokan sebelum mencatat absensi.

## Menjalankan di Laragon

1. Jalankan Apache dan MySQL dari Laragon.
2. Import [`database/schema.sql`](database/schema.sql) melalui phpMyAdmin atau jalankan dengan klien MySQL.
3. Salin `config.local.php.example` menjadi `config.local.php`, lalu atur koneksi database dan kunci admin yang panjang serta rahasia.
4. Buka `http://localhost/absen_scan_face/`.
5. Izinkan akses kamera. Buka menu **Registrasi wajah**, isi ID/nama dan kunci admin, aktifkan kamera, ambil tiga sampel, lalu simpan.
6. Buka menu **Absensi**, aktifkan kamera dan catat kehadiran.
7. Untuk riwayat, masukkan kunci admin di menu **Riwayat**.

Contoh konfigurasi lokal:

```php
<?php
return [
    'db_host' => '127.0.0.1',
    'db_name' => 'absensi_wajah',
    'db_user' => 'root',
    'db_password' => '',
    'registration_key' => 'buat-kunci-admin-acak-dan-panjang',
];
```

Konfigurasi `ATTENDANCE_DB_HOST`, `ATTENDANCE_DB_NAME`, `ATTENDANCE_DB_USER`, `ATTENDANCE_DB_PASSWORD`, dan `ATTENDANCE_REGISTRATION_KEY` dari environment server akan diprioritaskan jika tersedia.

## Kamera dan model wajah

Aplikasi menggunakan `getUserMedia`, sehingga dapat memakai kamera yang dikenali browser (kamera bawaan, USB, atau kamera perangkat seluler yang diizinkan). Jika muncul pesan timeout atau kamera gagal dibuka:

1. Izinkan kamera untuk situs ini dari ikon gembok/pengaturan situs di browser.
2. Tutup Zoom, Meet, aplikasi kamera, atau program lain yang mungkin sedang memakai kamera.
3. Periksa pengaturan **Settings > Privacy & security > Camera** di Windows dan izinkan akses kamera untuk aplikasi desktop.
4. Coba pilih perangkat lain pada daftar kamera dan muat ulang halaman.
5. Pastikan kamera terdeteksi di Device Manager dan coba buka aplikasi Camera Windows. Driver/perangkat yang gagal di Windows tidak bisa diperbaiki oleh kode website.

Dukungan aktual bergantung pada browser, driver, izin perangkat, dan penggunaan `localhost` atau HTTPS; tidak ada situs web yang dapat menjamin akses ke setiap jenis kamera/perangkat.

Library dan model wajah dimuat dari CDN sehingga koneksi internet diperlukan. Data frame/video tidak dikirim ke server; embedding 128 angka dikirim untuk registrasi/pencocokan. Absensi meminta tiga sampel, membatasi jarak pencocokan sampai 0,35, dan menolak jika sampel tidak konsisten atau kandidat karyawan terlalu berdekatan. Batas yang lebih ketat dapat meningkatkan penolakan wajah sah; angka ini perlu diuji pada kamera dan kondisi penggunaan sebenarnya.

## Batasan dan keamanan

- Ini adalah prototipe awal untuk pengembangan lokal. Belum ada login pengguna, verifikasi liveness/anti-spoofing, pembatasan percobaan, kebijakan retensi/penghapusan biometrik, ataupun pengaturan jam kerja.
- Pencocokan embedding saja tidak membuktikan bahwa orang hadir secara langsung; foto/video yang ditampilkan ke kamera dapat menipu sistem tanpa liveness detection.
- Sebelum dipakai sungguhan: tambahkan autentikasi admin dan pengguna, HTTPS, pengamanan jaringan/API, rate limiting, audit, persetujuan dan retensi data biometrik yang sesuai hukum/kebijakan organisasi, backup terenkripsi, serta proses penghapusan data.
- Endpoint pencatatan saat ini mengizinkan satu catatan per karyawan per hari. Jadwal shift, check-in/check-out, toleransi waktu, dan aturan keterlambatan belum diterapkan.
