# Rekap Absensi Solat (Laravel)

Aplikasi Laravel untuk mengelola kelas, siswa, absensi hari kerja, laporan kehadiran, import siswa, serta ekspor Excel/Word. Tabel domain mengikuti database MVC native: `users`, `kelas`, `siswa`, dan `absensi`.

## Setup

1. Jalankan `composer install` di folder ini.
2. Salin `.env.example` menjadi `.env`, lalu jalankan `php artisan key:generate`.
3. Atur username dan password awal untuk tiga role di `.env`: `APP_INITIAL_ADMIN_*`, `APP_INITIAL_KESISWAAN_*`, dan `APP_INITIAL_ABSENSI_*`. Isi juga `APP_INITIAL_ADMIN_EMAIL` dengan email pemulihan admin yang valid.
4. Untuk database baru, atur koneksi pada `.env`, lalu jalankan `php artisan migrate --seed`.
5. Jalankan `php artisan serve` dan masuk menggunakan akun admin yang sudah dikonfigurasi.

Untuk aplikasi di balik reverse proxy, biarkan `TRUSTED_PROXIES` kosong kecuali Anda mengetahui alamat IP/CIDR proxy yang benar-benar menghadap aplikasi. Jika ada, masukkan hanya alamat tersebut dipisahkan koma; jangan gunakan `*`. Atur `APP_URL` ke alamat publik tepercaya dengan HTTPS, karena tautan reset password dibuat dari nilai ini dan tidak mengikuti host dari request.

Alamat utama aplikasi (`/`) selalu membuka login siswa dengan nomor induk, termasuk saat diakses melalui domain ngrok. Halaman staff tetap tersedia melalui `/staff/login`. Setelah login, siswa hanya dapat melihat kartu miliknya sendiri, mencetak kartu, atau mengunduh PDF melalui portal siswa.

Portal siswa memakai nomor induk dan PIN. Migrasi memberi PIN awal `siswa123` kepada siswa yang sudah ada; siswa baru/import dan reset staf juga memakai PIN awal tersebut. Siswa dapat mengganti PIN dari halaman **Ubah PIN**, dan staf admin/kesiswaan dapat meresetnya dari **Data Siswa**. Karena PIN awal sama untuk semua siswa, ini bukan rahasia unik: pastikan siswa menggantinya dan distribusikan nomor induk/PIN dengan hati-hati. Untuk perlindungan kuat, ganti kebijakan ini dengan PIN unik per siswa yang diberikan melalui saluran terverifikasi.

Seeder membuat atau memperbarui satu akun untuk masing-masing role (`admin`, `kesiswaan`, dan `absensi`) dari konfigurasi `.env`. Password disimpan dalam bentuk hash. Username setiap akun harus berbeda. Seeder dapat dijalankan ulang untuk menyelaraskan akun awal dengan konfigurasi `.env`.

Laravel memakai koneksi MySQL seperti aplikasi native: host `127.0.0.1`, database `rekap_absen_shalat3`, user `root`, dan password kosong secara default. Variabel native `DB_NAME` dan `DB_USER` juga didukung; nilai `DB_DATABASE` dan `DB_USERNAME` akan didahulukan jika tersedia. Atur nilai koneksi di `.env` bila Laragon Anda memakai konfigurasi berbeda.

Untuk mempertahankan database native yang sudah terisi, jalankan migrasi di database yang sama. Tabel native yang sudah ada akan dipakai; migrasi tambahan juga mengonversi tabel users bawaan Laravel yang mungkin sudah tercatat tanpa menghapus akun lamanya.

## Email pemulihan password admin

Saat membuat atau mengubah akun melalui menu **Pengguna**, akun dengan role `admin` wajib memiliki email pemulihan. Role selain admin tetap menggunakan akun biasa dan tidak menyimpan email. Admin dapat memakai tautan **Lupa password admin?** di halaman login; tautan reset berlaku 60 menit dan hanya dikirim ke email admin yang terdaftar.

Untuk mengirim melalui Gmail, isi `.env` dengan `MAIL_MAILER=smtp`, `MAIL_HOST=smtp.gmail.com`, `MAIL_PORT=587`, `MAIL_ENCRYPTION=tls`, dan `MAIL_USERNAME`/`MAIL_FROM_ADDRESS` sesuai akun pengirim. Buat **Google App Password** (akun Google harus mengaktifkan verifikasi 2 langkah) lalu simpan App Password itu sebagai `MAIL_PASSWORD` di `.env` saja — jangan gunakan password login Gmail dan jangan bagikan atau commit rahasia tersebut. Pastikan `APP_URL` memakai alamat aplikasi yang dapat dibuka admin dari email. Setelah mengubah `.env`, jalankan `php artisan config:clear`.

## Peran

- `admin`: seluruh fitur, termasuk pengguna dan import.
- `kesiswaan`: absensi, laporan, siswa, dan kelas.
- `absensi`: pencatatan absensi.

## Pengujian

Jalankan `php artisan test`. Test menggunakan SQLite in-memory dan menguji login, otorisasi, simpan absensi, laporan, import, serta ekspor.