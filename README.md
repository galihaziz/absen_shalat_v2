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

Deteksi memakai `face-api.js` dan model dari CDN, sehingga halaman memerlukan koneksi internet serta akses kamera melalui HTTPS atau `localhost`. Video diproses di browser; hanya descriptor wajah yang dikirim dan disimpan dalam bentuk terenkripsi Laravel pada `student_face_registrations`. Setiap registrasi memiliki kode UUID unik. Saat pendaftaran, server membandingkan sampel dengan registrasi siswa lain dan menolak wajah yang cocok berulang kali, dengan keterangan bahwa wajah sudah terdaftar pada NIS lain. Presensi scan wajah dan QR menggunakan tabel `absensi` dan status `H` yang sama, serta tunduk pada batas waktu scan yang dikonfigurasi.

Pencocokan descriptor bukan verifikasi liveness: foto atau video yang ditampilkan ke kamera berpotensi menipu sistem. Sebelum dipakai operasional, tetapkan persetujuan dan masa retensi/penghapusan biometrik sesuai kebijakan yang berlaku, lindungi `APP_KEY` dan akses database, serta evaluasi kebutuhan liveness dan pengujian akurasi pada perangkat sekolah.

## Pengujian

Jalankan `php artisan test`. Test menggunakan SQLite in-memory dan menguji login, otorisasi, simpan absensi, laporan, import, serta ekspor.