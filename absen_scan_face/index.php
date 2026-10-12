<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f7fb">
    <title>RuangHadir — Absensi Wajah</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar">
            <a class="brand" href="#" aria-label="RuangHadir beranda">
                <span class="brand-mark" aria-hidden="true">R</span>
                <span>ruang<span class="brand-accent">hadir</span></span>
            </a>

            <div class="workspace-label">RUANG KERJA</div>
            <div class="workspace-card">
                <span class="workspace-icon" aria-hidden="true">⌂</span>
                <span><strong>Kantor Utama</strong><small>Sistem absensi</small></span>
                <span class="workspace-menu" aria-hidden="true">···</span>
            </div>

            <div class="nav-label">MENU</div>
            <nav class="main-nav" aria-label="Navigasi utama">
                <button class="nav-item active" data-page="attendance" type="button">
                    <span class="nav-icon" aria-hidden="true">◉</span> Absensi
                </button>
                <button class="nav-item" data-page="registration" type="button">
                    <span class="nav-icon" aria-hidden="true">◎</span> Registrasi wajah
                </button>
                <button class="nav-item" data-page="history" type="button">
                    <span class="nav-icon" aria-hidden="true">▤</span> Riwayat
                </button>
            </nav>

            <div class="sidebar-bottom">
                <div class="privacy-card">
                    <span class="privacy-icon" aria-hidden="true">✳</span>
                    <strong>Privasi terjaga</strong>
                    <p>Wajah diproses di browser. Hanya data representasi wajah yang dikirim untuk pencocokan.</p>
                </div>
                <div class="profile">
                    <span class="avatar">RH</span>
                    <span><strong>RuangHadir</strong><small>Panel absensi</small></span>
                    <span class="workspace-menu" aria-hidden="true">···</span>
                </div>
            </div>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <div class="breadcrumb"><span>Ruang kerja</span><span class="crumb-separator">/</span><strong id="breadcrumb-current">Absensi</strong></div>
                <div class="topbar-right">
                    <span class="live-dot"></span><span class="system-status">Sistem siap</span>
                    <span class="topbar-divider"></span>
                    <span class="date-label" id="today-label"></span>
                    <span class="top-avatar">RH</span>
                </div>
            </header>

            <div class="content-wrap">
                <section class="page active" id="page-attendance">
                    <div class="page-heading">
                        <div>
                            <p class="eyebrow">SELAMAT DATANG</p>
                            <h1>Absensi hari ini</h1>
                            <p class="page-subtitle">Arahkan wajah ke kamera untuk mencatat kehadiranmu.</p>
                        </div>
                        <div class="today-card">
                            <span class="today-icon" aria-hidden="true">▦</span>
                            <span><small>HARI INI</small><strong id="today-card-date"></strong></span>
                        </div>
                    </div>

                    <div class="attendance-grid">
                        <section class="card camera-card">
                            <div class="card-heading">
                                <div>
                                    <h2>Verifikasi wajah</h2>
                                    <p>Pastikan wajah terlihat jelas dan berada di tengah.</p>
                                </div>
                                <span class="secure-badge"><span>✳</span> Aman &amp; privat</span>
                            </div>

                            <div class="camera-stage">
                                <video id="camera-video" autoplay muted playsinline></video>
                                <canvas id="camera-overlay"></canvas>
                                <div class="camera-placeholder" id="camera-placeholder">
                                    <span class="camera-placeholder-icon" aria-hidden="true">◎</span>
                                    <strong>Kamera belum aktif</strong>
                                    <span>Aktifkan kamera untuk memulai pemindaian.</span>
                                </div>
                                <div class="scan-corner corner-tl"></div><div class="scan-corner corner-tr"></div>
                                <div class="scan-corner corner-bl"></div><div class="scan-corner corner-br"></div>
                                <div class="camera-live" id="camera-live"><span></span> LIVE</div>
                                <div class="face-status" id="face-status"><span class="status-dot"></span><span id="face-status-text">Menunggu kamera</span></div>
                            </div>

                            <div class="camera-controls">
                                <label class="camera-select-wrap" for="camera-select">
                                    <span aria-hidden="true">▣</span>
                                    <select id="camera-select" aria-label="Pilih kamera">
                                        <option value="">Kamera default</option>
                                    </select>
                                    <span class="select-chevron" aria-hidden="true">⌄</span>
                                </label>
                                <button class="button button-outline" id="camera-toggle" type="button">
                                    <span aria-hidden="true">◉</span> Aktifkan kamera
                                </button>
                            </div>

                            <div class="recognition-result hidden" id="recognition-result" role="status"></div>
                            <button class="button button-primary attendance-button" id="attendance-button" type="button" disabled>
                                <span aria-hidden="true">✓</span> Catat kehadiran
                            </button>
                            <p class="camera-note"><span aria-hidden="true">ⓘ</span> Kamera harus diizinkan oleh browser. Gunakan localhost atau HTTPS.</p>
                        </section>

                        <aside class="right-column">
                            <section class="card today-summary">
                                <div class="card-heading compact-heading">
                                    <div><h2>Ringkasan hari ini</h2><p>Data kehadiran terbaru</p></div>
                                    <span class="summary-icon" aria-hidden="true">↗</span>
                                </div>
                                <div class="summary-count">
                                    <strong id="attendance-count">—</strong>
                                    <span>kehadiran tercatat</span>
                                </div>
                                <div class="summary-footer"><span class="summary-bar"><i></i></span><span id="summary-date"></span></div>
                            </section>
                            <section class="card guidance-card">
                                <h2>Tips pemindaian</h2>
                                <ul class="tips-list">
                                    <li><span class="tip-check">✓</span><span>Pastikan pencahayaan cukup</span></li>
                                    <li><span class="tip-check">✓</span><span>Lepas masker dan kacamata gelap</span></li>
                                    <li><span class="tip-check">✓</span><span>Posisikan wajah di dalam bingkai</span></li>
                                    <li><span class="tip-check">✓</span><span>Gunakan kamera yang fokus</span></li>
                                </ul>
                                <div class="help-banner"><span aria-hidden="true">✧</span><span>Butuh bantuan?<small>Hubungi administrator</small></span><span class="help-arrow">→</span></div>
                            </section>
                            <div class="privacy-note"><span aria-hidden="true">⌑</span><span><strong>Data wajahmu terlindungi</strong><small>Representasi wajah disimpan dengan akses terbatas.</small></span></div>
                        </aside>
                    </div>
                </section>

                <section class="page" id="page-registration">
                    <div class="page-heading">
                        <div>
                            <p class="eyebrow">PENGELOLAAN PENGGUNA</p>
                            <h1>Registrasi wajah</h1>
                            <p class="page-subtitle">Daftarkan wajah karyawan agar dapat digunakan untuk absensi.</p>
                        </div>
                    </div>
                    <div class="registration-grid">
                        <section class="card registration-form-card">
                            <div class="card-heading">
                                <div><h2>Data karyawan</h2><p>Isi identitas dan ambil tiga sampel wajah.</p></div>
                                <span class="step-number">01</span>
                            </div>
                            <form id="registration-form">
                                <label class="field-label" for="employee-id">ID karyawan</label>
                                <input class="text-input" id="employee-id" name="employee_id" autocomplete="off" maxlength="64" placeholder="Contoh: EMP-001" required>
                                <label class="field-label" for="employee-name">Nama lengkap</label>
                                <input class="text-input" id="employee-name" name="full_name" autocomplete="name" maxlength="120" placeholder="Masukkan nama karyawan" required>
                                <label class="field-label" for="registration-key">Kunci admin</label>
                                <input class="text-input" id="registration-key" name="admin_key" type="password" autocomplete="off" placeholder="Masukkan kunci admin" required>
                                <p class="field-hint">Kunci admin diatur administrator pada konfigurasi server.</p>
                                <div class="sample-progress">
                                    <div class="sample-progress-top"><strong>Sampel wajah</strong><span id="sample-count">0 dari 3</span></div>
                                    <div class="progress-track"><span id="sample-progress-bar"></span></div>
                                </div>
                                <div class="form-actions">
                                    <button class="button button-outline" id="sample-button" type="button" disabled>＋ Ambil sampel</button>
                                    <button class="button button-primary" id="register-button" type="submit" disabled>Simpan registrasi <span aria-hidden="true">→</span></button>
                                </div>
                                <div class="inline-message hidden" id="registration-message" role="status"></div>
                            </form>
                        </section>
                        <section class="card registration-camera-card">
                            <div class="card-heading">
                                <div><h2>Pratinjau kamera</h2><p>Pastikan wajah terlihat jelas.</p></div>
                                <span class="secure-badge"><span>✳</span> Diproses lokal</span>
                            </div>
                            <div class="camera-stage small-stage">
                                <video id="registration-video" autoplay muted playsinline></video>
                                <canvas id="registration-overlay"></canvas>
                                <div class="camera-placeholder" id="registration-placeholder">
                                    <span class="camera-placeholder-icon" aria-hidden="true">◎</span>
                                    <strong>Kamera belum aktif</strong>
                                    <span>Aktifkan kamera untuk mengambil sampel.</span>
                                </div>
                                <div class="scan-corner corner-tl"></div><div class="scan-corner corner-tr"></div>
                                <div class="scan-corner corner-bl"></div><div class="scan-corner corner-br"></div>
                                <div class="camera-live" id="registration-live"><span></span> LIVE</div>
                            </div>
                            <div class="camera-controls registration-camera-controls">
                                <button class="button button-outline" id="registration-camera-toggle" type="button"><span aria-hidden="true">◉</span> Aktifkan kamera</button>
                                <p><span class="status-dot" id="registration-face-dot"></span><span id="registration-face-text">Menunggu kamera</span></p>
                            </div>
                            <div class="notice-box"><span aria-hidden="true">ⓘ</span><span>Ambil tiga sampel dengan sedikit perubahan posisi wajah untuk meningkatkan konsistensi pencocokan.</span></div>
                        </section>
                    </div>
                </section>

                <section class="page" id="page-history">
                    <div class="page-heading">
                        <div>
                            <p class="eyebrow">REKAPITULASI</p>
                            <h1>Riwayat absensi</h1>
                            <p class="page-subtitle">Lihat catatan kehadiran terbaru. Akses memerlukan kunci admin.</p>
                        </div>
                    </div>
                    <section class="card history-card">
                        <div class="history-toolbar">
                            <div><h2>Catatan terbaru</h2><p>Maksimal 50 catatan terakhir</p></div>
                            <div class="history-actions">
                                <input class="text-input admin-key-history" id="history-key" type="password" autocomplete="off" placeholder="Kunci admin" aria-label="Kunci admin untuk melihat riwayat">
                                <button class="button button-outline" id="load-history" type="button">Muat riwayat</button>
                            </div>
                        </div>
                        <div class="table-wrap">
                            <table>
                                <thead><tr><th>ID KARYAWAN</th><th>NAMA</th><th>TANGGAL</th><th>WAKTU</th><th>STATUS</th></tr></thead>
                                <tbody id="history-body"><tr><td colspan="5" class="empty-table">Masukkan kunci admin untuk memuat riwayat.</td></tr></tbody>
                            </table>
                        </div>
                        <div class="inline-message hidden" id="history-message" role="status"></div>
                    </section>
                </section>
            </div>
            <footer class="page-footer"><span>© <span id="year-label"></span> RuangHadir</span><span>Absensi wajah · Versi awal</span></footer>
        </main>
    </div>
    <div class="toast hidden" id="toast" role="status" aria-live="polite"></div>
    <script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
    <script defer src="assets/app.js?v=3"></script>
</body>
</html>
