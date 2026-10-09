<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; color: #172a2c; font: 6pt "DejaVu Sans", sans-serif; }
    </style>
    <style>@page { size: 53.98mm 85.6mm; margin: 0; }</style>
    <?php echo '<style>' . file_get_contents(resource_path('css/qr-card-pdf.css')) . '</style>'; ?>
</head>
<body>
<x-student-qr-card :student="$student" :photo-src="$photoDataUri" :qr-src="$qrDataUri" :pdf="true" :template-src="$templateDataUri" />
</body>
</html>