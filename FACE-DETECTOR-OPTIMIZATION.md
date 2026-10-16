# Optimasi Face Detector

## Ringkasan

Optimasi difokuskan pada pengurangan pekerjaan yang tidak diperlukan saat presensi, penghematan render canvas, dan pengumpulan data performa pada perangkat pengguna. Perubahan ini mempertahankan face-api.js dan format descriptor wajah yang sudah tersimpan.

## Perubahan

### `public/js/face-scanner.js`

- Presensi hanya menjalankan deteksi kotak wajah. Model landmark tetap digunakan untuk panduan pose registrasi dan proses pengambilan descriptor, tetapi tidak lagi dijalankan pada setiap deteksi presensi karena hasil landmark pada tahap itu tidak digunakan.
- Animasi kotak wajah tidak lagi menjalankan `requestAnimationFrame` tanpa henti. Canvas dirender saat hasil deteksi berubah dan hanya melanjutkan animasi selama smoothing masih diperlukan.
- Smoothing kotak mengikuti perubahan dengan waktu respons yang menyesuaikan gerakan: lebih lembut ketika wajah stabil dan lebih cepat saat wajah bergerak.
- Setelah inferensi yang lambat, loop memberi waktu jeda tambahan secara adaptif agar penggunaan CPU tidak terus dipaksa pada perangkat lambat.
- Permintaan frame rate kamera untuk perangkat yang terdeteksi berdaya rendah diturunkan dari 24 menjadi 20 fps; resolusi detektor dan ambang deteksi tidak diturunkan.
- Pengukuran berjalan menampilkan rata-rata dan P95 waktu inferensi, waktu landmark, serta resolusi dan frame rate kamera aktual. Ringkasan menggunakan hingga 20 pengukuran terakhir dan diperbarui berkala.
- Saat kamera dihentikan, sampel performa dan animasi dibersihkan. Transform canvas juga dinormalkan sebelum dibersihkan agar perilaku clear tetap benar pada layar ber-DPI tinggi.

### `resources/views/attendance/face-scan.blade.php`

- Menambahkan elemen ringkasan performa yang muncul setelah pengukuran awal tersedia.

### `public/css/app.css`

- Menata teks ringkasan performa agar ringan dan terbaca di panel scan.

### `tests/Feature/FaceAttendanceTest.php`

- Memastikan elemen ringkasan performa tersedia pada halaman scan admin.

## Validasi

- `FaceAttendanceTest.php`: **14 lulus, 0 gagal**.
- Pemeriksaan editor untuk JavaScript, CSS, Blade, dan tes: **tidak menemukan error**.
- Pemeriksaan visual dengan kamera langsung pada perangkat Windows dan ponsel belum dijalankan di sesi ini. Nilai performa aktual perlu diamati dari ringkasan yang muncul setelah kamera berjalan.

## Batasan dan keputusan

- Model alternatif seperti MediaPipe dan pemindahan inferensi ke Web Worker belum diterapkan. Langkah tersebut dapat mengubah beban kerja dan kompatibilitas secara signifikan; perlu data inferensi nyata dari perangkat sasaran terlebih dahulu.
- Ukuran input detector tidak diturunkan karena dapat membuat wajah kecil atau jauh lebih sulit terdeteksi. Ringkasan waktu inferensi sekarang tersedia untuk mengukur apakah penyesuaian itu layak tanpa menebak.
- Ringkasan pada halaman menampilkan angka waktu model di browser. Hasil dapat berbeda menurut perangkat, browser, kondisi kamera, dan backend TensorFlow.js.
