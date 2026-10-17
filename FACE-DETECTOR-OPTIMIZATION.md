# Optimasi Face Detector

## Ringkasan

Optimasi difokuskan pada pengurangan pekerjaan yang tidak diperlukan saat presensi, penghematan render canvas, dan pengumpulan data performa pada perangkat pengguna. Perubahan ini mempertahankan face-api.js dan format descriptor wajah yang sudah tersimpan.

## Perubahan

### `public/js/face-scanner.js`

- Presensi hanya menjalankan deteksi kotak wajah. Model landmark tetap digunakan untuk panduan pose registrasi dan proses pengambilan descriptor, tetapi tidak lagi dijalankan pada setiap deteksi presensi karena hasil landmark pada tahap itu tidak digunakan.
- Animasi kotak wajah tidak lagi menjalankan `requestAnimationFrame` tanpa henti. Canvas dirender saat hasil deteksi berubah dan hanya melanjutkan animasi selama smoothing masih diperlukan.
- Smoothing kotak menggunakan waktu respons yang lebih panjang agar gerakan tidak menyentak: 130 ms untuk perubahan kecil dan 75 ms saat wajah bergerak cepat, supaya kotak tetap halus tanpa terlalu tertinggal.
- Interval deteksi minimal dinaikkan menjadi 300 ms di perangkat berdaya rendah dan 220 ms di perangkat lain. Setelah setiap inferensi, loop sekarang memberi jeda setidaknya selama inferensi itu sendiri; sebelumnya jeda hanya 20% dari waktu inferensi sehingga model yang lambat tetap berjalan hampir tanpa henti dan memakai sekitar 80% waktu CPU.
- Setelah presensi tercatat, pemeriksaan keberadaan wajah diturunkan ke interval minimal 350 ms agar preview kamera tetap aktif tanpa menjalankan inferensi terus-menerus.
- Pemuatan model deteksi, landmark, dan pengenalan dimulai segera setelah halaman scan siap; inferensi deteksi awal dijalankan sebagai pemanasan agar jeda inisialisasi tidak terjadi saat wajah pertama kali dipindai.
- Presensi tetap menunggu dua deteksi stabil, tetapi setelahnya cukup mengambil satu descriptor. Penundaan buatan 900 ms dan jeda 350 ms serta inferensi descriptor kedua dihapus; endpoint dan pencocokan server tetap memvalidasi descriptor tersebut.
- Pengambilan descriptor memakai ukuran input detektor yang sama dengan perangkat, sehingga tidak menjalankan deteksi tambahan pada resolusi lebih tinggi di perangkat berdaya rendah.
- Permintaan frame rate kamera untuk perangkat yang terdeteksi berdaya rendah diturunkan dari 24 menjadi 20 fps; resolusi detektor dan ambang deteksi tidak diturunkan.
- Pengukuran berjalan menampilkan rata-rata dan P95 waktu inferensi, waktu landmark, serta resolusi dan frame rate kamera aktual. Ringkasan menggunakan hingga 20 pengukuran terakhir dan diperbarui berkala.
- Saat kamera dihentikan, sampel performa dan animasi dibersihkan. Transform canvas juga dinormalkan sebelum dibersihkan agar perilaku clear tetap benar pada layar ber-DPI tinggi.

### `resources/views/attendance/face-scan.blade.php`

- Menambahkan elemen ringkasan performa yang muncul setelah pengukuran awal tersedia.

### `public/css/app.css`

- Menata teks ringkasan performa agar ringan dan terbaca di panel scan.

### `tests/Feature/FaceAttendanceTest.php`

- Memastikan elemen ringkasan performa tersedia pada halaman scan admin.

### `app/Services/StudentFaceRecognition.php`

- Pencocokan hanya melacak dua kandidat terbaik, membandingkan jarak kuadrat tanpa mengurutkan seluruh daftar kandidat, dan menghentikan perhitungan descriptor lebih awal ketika jarak parsial sudah tidak mungkin masuk dua teratas.
- Ambang kecocokan, margin antar-kandidat, dan keputusan pengenalan tidak berubah.

### Indeks database absensi

- Migrasi menambahkan indeks pada `absensi.tanggal`, yang dipakai ringkasan harian, laporan berbasis rentang tanggal, dan penghapusan absensi bulanan. Indeks lama pada `(id_siswa, tanggal)` tetap dipertahankan untuk pencarian per siswa.

## Validasi

- Setelah optimasi pencocokan: **13 tes alur wajah lulus** (72 assertion). Satu tes UI yang tersisa masih mengharapkan kamera otomatis terbuka pada halaman scan staf, sedangkan UI saat ini meminta aktivasi kamera secara eksplisit.
- Tuning ulang loop deteksi untuk mencegah inferensi mengisi hampir seluruh jeda antar-frame; pemeriksaan sintaks JavaScript dijalankan setelah perubahan.
- Alur presensi satu descriptor: **lulus** (4 assertion). Pemeriksaan sintaks JavaScript setelah pemuatan model awal dan pemanasan detektor: **lulus**.
- Tes migrasi indeks tanggal absensi: **lulus**.
- Tes integrasi QR, laporan alpha, import, dan daftar siswa: **5 lulus** (59 assertion).
- Suite penuh pada baseline audit: **70 lulus, 8 gagal**. Kegagalan yang tersisa berupa ekspektasi tes yang tidak lagi sesuai dengan halaman login siswa, teks/markup UI, rentang tanggal dinamis, batas laju login saat tes, dan posisi sel ekspor; tidak ada yang menunjukkan exception runtime.
- Microbenchmark sintetis pencocokan (500 registrasi, masing-masing 5 descriptor 128 dimensi, 2 descriptor masuk, 25 pengulangan): **6.010 ms sebelum, 4.733 ms sesudah (1,27× lebih cepat)** dengan hasil siswa yang sama. Ini mengisolasi algoritme pencocokan dan bukan pengukuran end-to-end di database/perangkat produksi.
- Pemeriksaan editor untuk JavaScript, CSS, Blade, dan tes: **tidak menemukan error**.
- Pemeriksaan visual dengan kamera langsung pada perangkat Windows dan ponsel belum dijalankan di sesi ini. Nilai performa aktual perlu diamati dari ringkasan yang muncul setelah kamera berjalan.

## Batasan dan keputusan

- Model alternatif seperti MediaPipe dan pemindahan inferensi ke Web Worker belum diterapkan. Langkah tersebut dapat mengubah beban kerja dan kompatibilitas secara signifikan; perlu data inferensi nyata dari perangkat sasaran terlebih dahulu.
- Ukuran input detector tidak diturunkan karena dapat membuat wajah kecil atau jauh lebih sulit terdeteksi. Ringkasan waktu inferensi sekarang tersedia untuk mengukur apakah penyesuaian itu layak tanpa menebak.
- Ringkasan pada halaman menampilkan angka waktu model di browser. Hasil dapat berbeda menurut perangkat, browser, kondisi kamera, dan backend TensorFlow.js.
