<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ukuran upload terlalu besar</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ md5_file(public_path('css/app.css')) }}">
</head>
<body>
    <main class="page-shell">
        <section class="panel narrow-panel">
            <h1>Upload melebihi batas server</h1>
            <p role="alert">{{ $message }}</p>
            <p>Kembali ke halaman sebelumnya dan coba unggah file yang lebih kecil. Jika berlanjut, minta pengelola hosting menaikkan batas upload PHP dan web server.</p>
        </section>
    </main>
</body>
</html>
