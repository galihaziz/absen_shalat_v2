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

Halaman staf dapat dipasang sebagai PWA dari browser yang mendukung instalasi aplikasi. Setelah membuka `/staff/login`, pilih **Install app** atau **Tambahkan ke layar utama**; aplikasi yang terpasang akan dibuka dari `/staff` dan meminta login jika sesi staf belum aktif. PWA membutuhkan HTTPS pada hosting (localhost tetap didukung saat pengembangan). Saat offline, halaman absensi dan data staf tidak disimpan di cache; aplikasi hanya menampilkan pemberitahuan bahwa koneksi diperlukan.

Portal siswa memakai nomor induk dan PIN. Migrasi memberi PIN awal `siswa123` kepada siswa yang sudah ada; siswa baru/import dan reset staf juga memakai PIN awal tersebut. Siswa dapat mengganti PIN dari halaman **Ubah PIN**, dan staf admin/kesiswaan dapat meresetnya dari **Data Siswa**. Karena PIN awal sama untuk semua siswa, ini bukan rahasia unik: pastikan siswa menggantinya dan distribusikan nomor induk/PIN dengan hati-hati. Untuk perlindungan kuat, ganti kebijakan ini dengan PIN unik per siswa yang diberikan melalui saluran terverifikasi.

Seeder membuat atau memperbarui satu akun untuk masing-masing role (`admin`, `kesiswaan`, dan `absensi`) dari konfigurasi `.env`. Password disimpan dalam bentuk hash. Username setiap akun harus berbeda. Seeder dapat dijalankan ulang untuk menyelaraskan akun awal dengan konfigurasi `.env`.

Laravel memakai koneksi MySQL seperti aplikasi native: host `127.0.0.1`, database `rekap_absen_shalat3`, user `root`, dan password kosong secara default. Variabel native `DB_NAME` dan `DB_USER` juga didukung; nilai `DB_DATABASE` dan `DB_USERNAME` akan didahulukan jika tersedia. Atur nilai koneksi di `.env` bila Laragon Anda memakai konfigurasi berbeda.

Untuk mempertahankan database native yang sudah terisi, jalankan migrasi di database yang sama. Tabel native yang sudah ada akan dipakai; migrasi tambahan juga mengonversi tabel users bawaan Laravel yang mungkin sudah tercatat tanpa menghapus akun lamanya.

Setelah memperbarui aplikasi, jalankan `php artisan migrate` agar kolom nilai pengaturan dapat menyimpan katalog jurusan yang lebih besar. Kelas yang hanya berisi tingkat seperti `XI` atau `XII` tidak dianggap sebagai jurusan; pastikan label kelas pada workbook menyertakan rombelnya, misalnya `XI TL 1`.

Tabel Data Siswa, pilihan kartu manual, absensi, dan laporan memuat siswa bertahap sebanyak 15 baris saat tabel discroll; tombol pemuatan tetap tersedia sebagai alternatif jika scroll otomatis tidak didukung.

## Deploy Linux dengan aaPanel

1. Tambahkan website/domain di aaPanel dan aktifkan HTTPS. Atur document root ke folder `public` di dalam project (misalnya `/www/wwwroot/absen_shalat/public`), bukan ke root project. Pastikan file rewrite Laravel aktif: `public/.htaccess` untuk Apache, atau aturan `try_files $uri $uri/ /index.php?$query_string;` pada konfigurasi rewrite Nginx aaPanel. Pertahankan konfigurasi PHP-FPM yang dibuat panel.
2. Pilih PHP 8.2 atau lebih baru untuk website dan CLI. Aktifkan ekstensi `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `simplexml`, `xml`, `xmlreader`, dan `zip`. Verifikasi versi CLI dengan `php -v` dan modul dengan `php -m`; bila CLI panel memakai PHP lain, gunakan path biner PHP versi website pada perintah Artisan.
3. Unggah/checkout source project, lalu buat `.env` dari `.env.example`. Isi `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://domain-anda`, kredensial database MySQL/MariaDB, SMTP, email admin, serta username dan password awal yang kuat untuk setiap role. Jangan mengunggah `.env` ke folder publik, membagikannya, atau memasukkannya ke Git.
4. Buat database dan user database khusus di aaPanel. Sebelum migrasi, backup database lama jika ada. Untuk instalasi baru jalankan `php artisan migrate --seed --force`; untuk pembaruan database yang sudah dipakai jalankan `php artisan migrate --force` dan jalankan seeder hanya jika memang ingin menyelaraskan akun awal dari `.env`. Pastikan file `.env` tetap memakai `APP_KEY` yang sama setelah deploy pertama: key yang berubah membuat data terenkripsi aplikasi, termasuk data registrasi wajah, tidak dapat dibaca lagi.
5. Jalankan `composer install --no-dev --prefer-dist --optimize-autoloader`. Build aset sebelum atau saat deploy dengan `npm ci`, `npm run build`, dan `npm run build:qr-scanner`; salin folder `public/build` serta `public/js/qr-scanner.js` hasil build ke server. Node.js tidak dibutuhkan saat aplikasi berjalan.
6. Berikan user PHP-FPM hak tulis pada `storage/` dan `bootstrap/cache/` melalui pengaturan owner/group aaPanel; jangan memberi permission `777`. Setelah database, `.env`, dan permission siap, jalankan `php artisan storage:link` hanya bila fitur atau integrasi lain yang Anda pasang memang memerlukan disk publik. Foto siswa aplikasi ini tersimpan di disk privat.
7. Buat cron di **aaPanel > Cron** untuk menjalankan scheduler Laravel setiap menit. Gunakan biner PHP CLI dari versi yang sama dengan website dan direktori project yang benar; contoh perintah: `/www/server/php/82/bin/php /www/wwwroot/absen_shalat/artisan schedule:run >> /dev/null 2>&1`. Scheduler menjalankan backup database otomatis yang diatur dari aplikasi.
8. Setelah konfigurasi produksi final, jalankan `php artisan optimize` (atau `config:cache`, `route:cache`, dan `view:cache`), lalu uji login staff/siswa, absensi, upload foto, import workbook, laporan/ekspor, email reset password, dan backup. Jika `.env` berubah sesudah cache dibuat, jalankan kembali optimasi cache. Pantau `storage/logs/laravel.log` dan log PHP-FPM saat uji awal.

Catatan kompatibilitas: default koneksi memakai `utf8mb4_unicode_ci` agar dapat dipakai pada versi MySQL/MariaDB yang umum tersedia di hosting; `DB_COLLATION` dapat diatur eksplisit bila server membutuhkan collation lain. `TRUSTED_PROXIES` dibiarkan kosong secara default. Isi hanya alamat IP/CIDR proxy yang benar-benar berada di depan PHP; jangan gunakan `*`.

### Deploy atau update memakai file ZIP

Gunakan cara ini bila source aplikasi diunggah melalui **aaPanel > Files** dan bukan diambil dengan Git.

#### Membuat ZIP untuk upload

1. Di komputer pengembangan, dari root project jalankan `npm ci`, `npm run build`, dan `npm run build:qr-scanner` agar ZIP sudah berisi aset produksi.
2. Buat ZIP dari **isi folder project** (agar `artisan`, `composer.json`, dan folder `public/` berada langsung di root saat diekstrak). Sertakan `composer.json`, `composer.lock`, `package.json`, `package-lock.json`, `artisan`, `app/`, `bootstrap/`, `config/`, `database/migrations/`, `database/seeders/`, `public/` termasuk `public/build/` dan `public/js/qr-scanner.js`, `resources/`, `routes/`, serta file aplikasi lain yang diperlukan.
3. Jangan masukkan `.env`, `vendor/`, `node_modules/`, `.git/`, `storage/` dari komputer lokal, file database lokal `database/database.sqlite`, cache/log, atau file sementara ke ZIP. Jangan pernah menyertakan data siswa produksi atau kredensial.

#### Instalasi pertama dari ZIP

1. Di aaPanel, buat website/domain dan database MySQL/MariaDB. Ekstrak ZIP ke folder project, misalnya `/www/wwwroot/absen_shalat/`. **Jangan** ekstrak isi aplikasi langsung ke document root publik.
2. Atur document root website ke `/www/wwwroot/absen_shalat/public`. Untuk Nginx, pasang aturan Laravel `try_files $uri $uri/ /index.php?$query_string;` pada konfigurasi rewrite website. Dengan document root dan rewrite ini, URL seperti `/staff/login` diteruskan ke Laravel dan tidak berhenti sebagai 404 Nginx.
3. Di terminal aaPanel, masuk ke folder project dan pasang dependensi PHP menggunakan versi PHP CLI yang sama dengan website. Contoh:

   ```sh
   cd /www/wwwroot/absen_shalat
   export PATH=/www/server/php/82/bin:$PATH
   composer install --no-dev --prefer-dist --optimize-autoloader
   ```

   Jika aaPanel menyediakan perintah Composer sendiri, gunakan Composer tersebut bersama PHP 8.2+ yang sesuai.
4. Salin `.env.example` menjadi `.env` **di server**, isi konfigurasi produksi (terutama `APP_ENV=production`, `APP_DEBUG=false`, HTTPS `APP_URL`, database, SMTP, dan password akun awal), lalu buat key satu kali:

   ```sh
   /www/server/php/82/bin/php artisan key:generate
   ```

   Jangan jalankan `key:generate` lagi pada update. Lindungi `.env` dan jangan meletakkannya di dalam folder `public`.
5. Pastikan user PHP-FPM dapat menulis ke `storage/` dan `bootstrap/cache/`; jangan menggunakan permission `777`. Jalankan migrasi instalasi pertama:

   ```sh
   /www/server/php/82/bin/php artisan migrate --seed --force
   ```

6. Jalankan `php artisan optimize`, pasang cron scheduler seperti pada bagian deploy di atas, lalu uji `/staff/login`, login siswa, upload, import, dan laporan.

#### Memperbarui aplikasi memakai ZIP

1. Buat ZIP versi baru dengan langkah build yang sama. Sebelum update, backup database melalui aaPanel dan salin `.env` serta `storage/` dari server ke lokasi backup aman. Pastikan backup tidak ikut dibagikan.
2. Ekstrak ZIP baru ke **folder project yang sama**, pilih **replace/overwrite file yang sama** bila diminta. ZIP memang tidak memuat `.env`, `storage/`, `vendor/`, atau database lokal, jadi file konfigurasi dan data di server tidak ikut tertimpa. Jangan memilih opsi yang menghapus seluruh folder tujuan atau membersihkan file yang tidak ada di ZIP.
3. Dari terminal project, perbarui dependensi dan migrasi tanpa menjalankan seeder:

   ```sh
   cd /www/wwwroot/absen_shalat
   export PATH=/www/server/php/82/bin:$PATH
   composer install --no-dev --prefer-dist --optimize-autoloader
   /www/server/php/82/bin/php artisan optimize:clear
   /www/server/php/82/bin/php artisan migrate --force
   /www/server/php/82/bin/php artisan optimize
   ```

   Sesuaikan path PHP/Composer dengan versi yang aktif di aaPanel. Jangan jalankan `migrate:fresh`, jangan mengganti `.env` atau `APP_KEY`, dan jangan menjalankan seeder saat update kecuali memang ingin menyelaraskan akun awal dengan konfigurasi `.env`.
4. Setelah update, coba buka `/staff/login`. Jika muncul 404 Nginx, periksa kembali document root yang harus berakhir dengan `/public` dan aturan `try_files` Laravel. Jika aplikasi menampilkan error Laravel, periksa `storage/logs/laravel.log` dan log PHP-FPM.

## Email pemulihan password admin

Saat membuat atau mengubah akun melalui menu **Pengguna**, akun dengan role `admin` wajib memiliki email pemulihan. Role selain admin tetap menggunakan akun biasa dan tidak menyimpan email. Admin dapat memakai tautan **Lupa password admin?** di halaman login; tautan reset berlaku 60 menit dan hanya dikirim ke email admin yang terdaftar.

Untuk mengirim melalui Gmail, isi `.env` dengan `MAIL_MAILER=smtp`, `MAIL_HOST=smtp.gmail.com`, `MAIL_PORT=587`, `MAIL_ENCRYPTION=tls`, dan `MAIL_USERNAME`/`MAIL_FROM_ADDRESS` sesuai akun pengirim. Buat **Google App Password** (akun Google harus mengaktifkan verifikasi 2 langkah) lalu simpan App Password itu sebagai `MAIL_PASSWORD` di `.env` saja — jangan gunakan password login Gmail dan jangan bagikan atau commit rahasia tersebut. Pastikan `APP_URL` memakai alamat aplikasi yang dapat dibuka admin dari email. Setelah mengubah `.env`, jalankan `php artisan config:clear`.

## Peran

- `admin`: seluruh fitur, termasuk pengguna dan import.
- `kesiswaan`: absensi, laporan, siswa, dan kelas.
- `absensi`: pencatatan absensi.

## Import siswa

Import workbook `.xlsx`, `.xls`, atau `.ods` dimulai dengan pratinjau berhalaman agar tabel tetap ringan. Cari dengan beberapa kata sekaligus atau gabungkan filter kelas, angkatan, status baris, dan awalan nomor induk; pilihan tetap tersimpan saat hasil filter atau halaman berubah. Periksa kelas, nomor induk, nama, jenis kelamin, dan baris bermasalah sebelum konfirmasi. Pratinjau tidak mengubah database, dan hanya baris valid yang dipilih yang akan diimpor. Nomor induk menjadi kunci utama pencocokan: nama sama dengan nomor induk berbeda tetap menjadi siswa yang berbeda, sedangkan setiap nomor induk yang berulang dalam satu workbook ditandai bermasalah dan tidak dapat dipilih sampai file diperbaiki.

Sinkronisasi alumni hanya memengaruhi siswa aktif pada kelas yang terdeteksi di workbook, tidak semua kelas di sekolah. Opsi ini hanya tersedia setelah semua baris valid dipilih dan tidak ada baris bermasalah; siswa yang tidak tercantum pada kelas dalam cakupan itu akan ditandai sebagai alumni. Judul kolom umum seperti `No. Induk`, `NIS`, `NISN`, `Nama Lengkap`, `JK`, dan `Nama Kelas` juga dikenali. Simpan nomor induk sebagai teks di Excel untuk menjaga nol awalan; format angka seperti `000000` juga dibaca mengikuti tampilannya.

Nomor induk harus unik di database. Migrasi identitas siswa memeriksa data lama dan berhenti dengan daftar nomor contoh jika menemukan nomor induk ganda; bereskan duplikasi itu sebelum menjalankan migrasi kembali. Nama siswa yang sama dalam kelas dan jenis kelamin yang sama tetap diizinkan selama nomor induknya berbeda.

Pembacaan workbook memakai mode data-only, mengabaikan sel kosong, dan tidak memuat sheet bantu bernama `lulus`, `template`, atau `link`. Saat konfirmasi, pencarian data siswa dibatasi ke nomor induk/nama yang ada di workbook; proses sinkronisasi alumni juga dilakukan di database tanpa memuat seluruh daftar siswa ke memori PHP. Workbook yang rusak ditolak dengan pesan agar disimpan ulang sebagai XLSX, XLS, atau ODS.

## Unggah foto siswa massal

Di menu **Data Siswa**, pilih satu kelas atau satu jurusan per angkatan (misalnya PPLG kelas X), tentukan apakah nama file berisi nomor induk atau nama siswa, lalu pilih sampai 100 foto sekaligus. Nama file harus sama dengan nomor induk atau dapat berupa potongan nama minimal tiga karakter yang dimulai pada awal salah satu kata nama siswa (contoh `Nama_Sis.jpg` cocok dengan `Nama Siswa`). Pencocokan nama tidak membedakan huruf besar/kecil dan mengabaikan tanda baca sebagai pemisah. Jika potongan nama cocok dengan lebih dari satu siswa, gunakan nomor induk agar foto tidak salah pasang. Foto dikompres di browser sebelum dikirim satu per satu, lalu tetap diperiksa dan dioptimalkan oleh server agar ukurannya di bawah 100 KiB. Foto yang tidak cocok atau ambigu dilaporkan, sedangkan foto lain yang cocok tetap diproses.

Form foto siswa tunggal juga mengompres gambar di browser sebelum dikirim. Jika JavaScript aktif tetapi kompresi gagal, form menampilkan pesan dan tidak mengirim file besar diam-diam. Jika JavaScript tidak tersedia, server tetap memproses gambar; foto di atas 100 KiB memerlukan GD aktif. Jika penyimpanan foto gagal saat menambah atau mengubah data siswa, perubahan database dibatalkan agar data siswa tidak tersimpan setengah jalan.

## Checklist hosting untuk upload dan import

- Gunakan PHP 8.2+ dan aktifkan ekstensi `fileinfo`, `gd`, `zip`, `xml`, `xmlreader`, serta `mbstring`. GD dipakai untuk mengoptimalkan foto di server; kompresi browser membantu mengurangi ukuran dan beban upload.
- Untuk seluruh batas aplikasi, atur `upload_max_filesize=10M` dan `post_max_size=12M` atau lebih. Batas `upload_max_filesize` harus mencakup workbook import maksimal 10 MiB. `post_max_size` harus lebih besar dari batas file karena request juga berisi field form.
- Jika PHP menampilkan peringatan `Input variables exceeded 1000`, naikkan `max_input_vars` pada `php.ini` (misalnya menjadi `5000`) lalu restart PHP/web server. Peringatan ini berarti request berisi lebih banyak field daripada batas PHP dan field sisanya dapat diabaikan.
- Pada hosting PHP-FPM/FastCGI yang mengizinkan per-user INI, aplikasi menyediakan `public/.user.ini` dengan `max_input_vars=5000`. Perubahan `.user.ini` dapat memerlukan hingga lima menit untuk diterapkan (sesuai `user_ini.cache_ttl`). Jika PHP berjalan sebagai modul Apache atau hosting menonaktifkan per-user INI, atur nilai yang sama di `php.ini` atau panel hosting lalu restart PHP/web server.
- Jika memakai Nginx, reverse proxy, CDN, atau panel hosting dengan batas body request, naikkan juga batas upload pada lapisan tersebut (misalnya setidaknya 16 MB). Batas proxy lebih kecil dari PHP tetap akan menolak request sebelum Laravel menerimanya.
- Pastikan direktori `storage/app`, `storage/framework`, `storage/logs`, dan `bootstrap/cache` dapat ditulis oleh user PHP. Simpan `.env` dan `APP_KEY` dengan aman. Arahkan document root web ke folder `public`; foto siswa berada di disk lokal privat Laravel dan tidak membutuhkan symbolic link publik.
- Untuk workbook besar, minta hosting menyediakan `memory_limit` dan `max_execution_time` yang cukup (misalnya 256M dan 120 detik), karena ukuran XLSX/ODS terkompresi bukan ukuran memori setelah dibuka. Jika shared hosting menetapkan batas lebih rendah, pecah workbook menjadi beberapa file yang lebih kecil.
- Setelah mengubah `.user.ini`/pengaturan PHP di panel, tunggu konfigurasi diterapkan lalu coba satu foto dan workbook kecil terlebih dahulu. Error HTTP 413 berarti batas request di PHP atau reverse proxy masih terlalu rendah; error penyimpanan berarti permission/storage disk perlu diperiksa. Aplikasi menampilkan pesan khusus untuk penolakan `post_max_size`.

Implementasi mengikuti dokumentasi resmi:

- [Laravel 11 file validation](https://laravel.com/docs/11.x/validation#validating-files) dan [Laravel file storage](https://laravel.com/docs/11.x/filesystem#file-uploads)
- [PhpSpreadsheet: reading files and reducing loaded cells](https://phpspreadsheet.readthedocs.io/en/latest/topics/reading-files/)
- [PHP upload error codes](https://www.php.net/manual/en/features.file-upload.errors.php) dan [PHP core upload directives](https://www.php.net/manual/en/ini.core.php#ini.upload-max-filesize)

## Scan wajah

Menu **Scan Wajah** tersedia berdampingan dengan **Scan QR** untuk akun staf yang masuk. Deteksi kotak ringan berjalan berkala; setelah wajah stabil dan kualitas gambar memadai (jarak, posisi, pencahayaan, dan ketajaman), sistem mengambil dua descriptor dan hanya mencatat presensi jika keduanya cocok dengan siswa yang sama. Model landmark dan pengenalan dimuat saat diperlukan, resolusi/FPS kamera dibatasi, jeda deteksi menyesuaikan beban perangkat, dan pemindaian berhenti saat tab tidak terlihat. Hasil berhasil/gagal tampil sampai wajah keluar dari bingkai lalu scan wajah baru dimulai. Pada scan petugas tersedia mode **Wajah Umum** dan **Wajah Haid** (mode Haid hanya berlaku untuk siswi). Siswa yang masuk ke portalnya dapat membuka **Scan Wajah** dari navbar atau kartu siswa, mendaftarkan wajah sendiri, menghapus data wajah akunnya, dan melakukan presensi hanya dengan wajah yang cocok dengan akunnya. Admin dapat mendaftarkan ulang atau menghapus wajah seluruh siswa dari menu **Data Siswa**. Pendaftaran memakai NIS akun terpilih dan otomatis merekam lima pose terpandu (depan, kiri, kanan, atas, dan bawah); setiap pose hanya disimpan jika kualitas gambar memadai dan dua descriptor frame stabil cukup mirip.

Admin dan kesiswaan dapat membuka menu **Haid > 15 Hari** untuk melihat siswi yang catatan haid beruntunnya melewati 15 hari kalender. Sabtu dan Minggu tetap dihitung dalam durasi, walaupun pencatatan absensi hanya dilakukan pada hari sekolah. Peringatan juga ditampilkan di halaman absensi saat ada siswi yang memenuhi batas tersebut.

Deteksi memakai `face-api.js` dan model dari CDN, sehingga halaman memerlukan koneksi internet serta akses kamera melalui HTTPS atau `localhost`. Video diproses di browser; hanya descriptor wajah yang dikirim dan disimpan dalam bentuk terenkripsi Laravel pada `student_face_registrations`. Setiap registrasi memiliki kode UUID unik. Saat pendaftaran, server membandingkan sampel dengan registrasi siswa lain dan menolak wajah yang cocok berulang kali, dengan keterangan bahwa wajah sudah terdaftar pada NIS lain. Presensi scan wajah dan QR menggunakan tabel `absensi` dan status `H` yang sama, serta tunduk pada batas waktu scan yang dikonfigurasi.

Pencocokan descriptor bukan verifikasi liveness: foto atau video yang ditampilkan ke kamera berpotensi menipu sistem. Sebelum dipakai operasional, tetapkan persetujuan dan masa retensi/penghapusan biometrik sesuai kebijakan yang berlaku, lindungi `APP_KEY` dan akses database, serta evaluasi kebutuhan liveness dan pengujian akurasi pada perangkat sekolah.

## Pengujian

Jalankan `php artisan test`. Test menggunakan SQLite in-memory dan menguji login, otorisasi, simpan absensi, laporan, import, serta ekspor.