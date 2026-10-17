# Graph Report - absen_shalat  (2026-10-16)

## Corpus Check
- 130 files · ~74,267 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 25 file(s) not represented in the graph (top: (none) 17, .css 4, .example 1)

## Summary
- 723 nodes · 1500 edges · 82 communities (28 shown, 54 thin omitted)
- Extraction: 98% EXTRACTED · 2% INFERRED · 0% AMBIGUOUS · INFERRED: 29 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `6943a9ca`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- composer.json
- Illuminate\Http\Request
- ExportController.php
- Student
- User
- Illuminate\Support\Facades\Schema
- hyalite.js
- package.json
- StudentController
- require
- config
- MajorCatalog
- logging.php
- Rekap Absensi Solat (Laravel)
- DatabaseBackupService.php
- ExampleTest
- attendance._rows
- students/index.blade.php
- require-dev
- psr-4
- extra
- scripts
- autoload-dev
- StudentFaceRegistration
- public/js/qr-scanner.js
- NativeFlowMigrationTest.php
- Dokumentasi Aplikasi Rekap Absensi Solat
- assets/app.js
- Optimasi Face Detector
- ImportController
- api.php
- Prototipe Lama Absensi Wajah
- qr-selection.blade.php

## God Nodes (most connected - your core abstractions)
1. `Student` - 78 edges
2. `Classroom` - 56 edges
3. `NativeFlowMigrationTest` - 56 edges
4. `StudentController` - 37 edges
5. `Attendance` - 26 edges
6. `User` - 22 edges
7. `StudentFaceRegistration` - 21 edges
8. `FaceAttendanceTest` - 21 edges
9. `ExportController` - 19 edges
10. `FaceAttendanceController` - 19 edges

## Surprising Connections (you probably didn't know these)
- `ExportController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/ExportController.php → app/Http/Controllers/Controller.php
- `ImportController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/ImportController.php → app/Http/Controllers/Controller.php
- `StudentController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/StudentController.php → app/Http/Controllers/Controller.php
- `StudentCardTemplateController` --references--> `StudentCardTemplateStorage`  [EXTRACTED]
  app/Http/Controllers/StudentCardTemplateController.php → app/Services/StudentCardTemplateStorage.php
- `StudentController` --references--> `StudentCardPhotoProcessor`  [EXTRACTED]
  app/Http/Controllers/StudentController.php → app/Services/StudentCardPhotoProcessor.php

## Import Cycles
- None detected.

## Communities (82 total, 54 thin omitted)

### Community 0 - "composer.json"
Cohesion: 0.25
Nodes (7): description, keywords, license, minimum-stability, name, prefer-stable, type

### Community 1 - "Illuminate\Http\Request"
Cohesion: 0.05
Nodes (25): AttendanceController, AuthController, Controller, DatabaseBackupController, FaceAttendanceController, PasswordResetController, ReportController, StudentCardTemplateController (+17 more)

### Community 2 - "ExportController.php"
Cohesion: 0.12
Nodes (17): ExportController, Worksheet, DateTimeImmutable, Illuminate\Support\Collection, PhpOffice\PhpSpreadsheet\Cell\Coordinate, PhpOffice\PhpSpreadsheet\Cell\DataType, PhpOffice\PhpSpreadsheet\IOFactory, PhpOffice\PhpSpreadsheet\Spreadsheet (+9 more)

### Community 3 - "Student"
Cohesion: 0.07
Nodes (9): Attendance, Classroom, Student, Illuminate\Database\Eloquent\Model, Illuminate\Database\Eloquent\Relations\BelongsTo, Illuminate\Database\Eloquent\Relations\HasMany, Illuminate\Database\Eloquent\Relations\HasOne, Illuminate\Support\Carbon (+1 more)

### Community 4 - "User"
Cohesion: 0.08
Nodes (19): User, UserFactory, DatabaseSeeder, Illuminate\Auth\Notifications\ResetPassword, Illuminate\Database\Eloquent\Factories\Factory, Illuminate\Database\Eloquent\Factories\HasFactory, Illuminate\Database\Seeder, Illuminate\Foundation\Auth\User (+11 more)

### Community 5 - "Illuminate\Support\Facades\Schema"
Cohesion: 0.06
Nodes (3): Illuminate\Database\Migrations\Migration, Illuminate\Database\Schema\Blueprint, Illuminate\Support\Facades\Schema

### Community 6 - "hyalite.js"
Cohesion: 0.15
Nodes (29): acquire(), acquireMap(), apply(), attach(), buildFilter(), buildMap(), detach(), edgeShadow() (+21 more)

### Community 7 - "package.json"
Cohesion: 0.08
Nodes (21): dependencies, @zxing/browser, @zxing/library, devDependencies, axios, esbuild, laravel-vite-plugin, vite (+13 more)

### Community 8 - "StudentController"
Cohesion: 0.11
Nodes (8): StudentController, RoleMiddleware, StudentPortalMiddleware, Closure, Illuminate\Foundation\Application, Illuminate\Foundation\Configuration\Exceptions, Illuminate\Foundation\Configuration\Middleware, Symfony\Component\HttpFoundation\Response

### Community 9 - "require"
Cohesion: 0.25
Nodes (8): require, dompdf/dompdf, endroid/qr-code, laravel/framework, laravel/tinker, php, phpoffice/phpspreadsheet, phpoffice/phpword

### Community 10 - "config"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 11 - "MajorCatalog"
Cohesion: 0.17
Nodes (7): AppServiceProvider, MajorCatalog, Illuminate\Notifications\Messages\MailMessage, Illuminate\Support\ServiceProvider, Symfony\Component\Mime\Email, Symfony\Component\Mime\Part\DataPart, Symfony\Component\Mime\Part\File

### Community 12 - "logging.php"
Cohesion: 0.40
Nodes (4): Monolog\Handler\NullHandler, Monolog\Handler\StreamHandler, Monolog\Handler\SyslogUdpHandler, Monolog\Processor\PsrLogMessageProcessor

### Community 13 - "Rekap Absensi Solat (Laravel)"
Cohesion: 0.29
Nodes (6): Email pemulihan password admin, Pengujian, Peran, Rekap Absensi Solat (Laravel), Scan wajah, Setup

### Community 14 - "DatabaseBackupService.php"
Cohesion: 0.19
Nodes (5): DatabaseBackupService, Illuminate\Support\Facades\Crypt, Illuminate\Support\Str, RuntimeException, Throwable

### Community 39 - "require-dev"
Cohesion: 0.25
Nodes (8): require-dev, fakerphp/faker, laravel/pint, laravel/sail, mockery/mockery, nunomaduro/collision, phpunit/phpunit, spatie/laravel-ignition

### Community 40 - "psr-4"
Cohesion: 0.33
Nodes (6): autoload, psr-4, AbsenShalat\\Models\\, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 41 - "extra"
Cohesion: 0.40
Nodes (5): dev-master, extra, branch-alias, laravel, dont-discover

### Community 42 - "scripts"
Cohesion: 0.40
Nodes (5): scripts, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd

### Community 43 - "autoload-dev"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 47 - "StudentFaceRegistration"
Cohesion: 0.19
Nodes (3): StudentFaceRegistration, StudentFaceRecognition, FaceAttendanceTest

### Community 48 - "public/js/qr-scanner.js"
Cohesion: 0.19
Nodes (16): En(), f(), fe(), i(), Ki(), n(), o(), os() (+8 more)

### Community 53 - "NativeFlowMigrationTest.php"
Cohesion: 0.10
Nodes (15): StudentCardPhotoProcessor, StudentCardTemplateStorage, StudentQrCodeGenerator, Dompdf\Dompdf, Dompdf\Options, Endroid\QrCode\Encoding\Encoding, Endroid\QrCode\ErrorCorrectionLevel, Endroid\QrCode\QrCode (+7 more)

### Community 56 - "Dokumentasi Aplikasi Rekap Absensi Solat"
Cohesion: 0.09
Nodes (22): 10. Batas cakupan dokumentasi, 1. Ringkasan, 2. Pengguna dan hak akses, 3.1 Login dan akun, 3.2 Portal siswa dan kartu, 3.3 Data siswa dan kelas, 3.4 Impor siswa, 3.5 Absensi dan pemindaian QR (+14 more)

### Community 57 - "assets/app.js"
Cohesion: 0.17
Nodes (14): cameraErrorMessage(), clearOverlay(), displayDate(), drawDetection(), loadModels(), refreshCameraList(), renderHistory(), runDetection() (+6 more)

### Community 58 - "Optimasi Face Detector"
Cohesion: 0.20
Nodes (9): Batasan dan keputusan, Optimasi Face Detector, Perubahan, `public/css/app.css`, `public/js/face-scanner.js`, `resources/views/attendance/face-scan.blade.php`, Ringkasan, `tests/Feature/FaceAttendanceTest.php` (+1 more)

### Community 60 - "api.php"
Cohesion: 0.43
Nodes (7): descriptorDistance(), getAdminKey(), identifyEmployee(), readPayload(), requireAdmin(), respond(), validateDescriptor()

### Community 61 - "Prototipe Lama Absensi Wajah"
Cohesion: 0.40
Nodes (4): Batasan dan keamanan, Kamera dan model wajah, Menjalankan di Laragon, Prototipe Lama Absensi Wajah

## Knowledge Gaps
- **88 isolated node(s):** `name`, `type`, `description`, `keywords`, `license` (+83 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 250 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **54 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Student` connect `Student` to `Illuminate\Http\Request`, `ExportController.php`, `StudentController`, `StudentFaceRegistration`, `NativeFlowMigrationTest.php`, `ImportController`?**
  _High betweenness centrality (0.083) - this node is a cross-community bridge._
- **Why does `Classroom` connect `Student` to `Illuminate\Http\Request`, `ExportController.php`, `StudentFaceRegistration`, `NativeFlowMigrationTest.php`, `ImportController`?**
  _High betweenness centrality (0.041) - this node is a cross-community bridge._
- **Why does `User` connect `User` to `Illuminate\Http\Request`, `Student`, `NativeFlowMigrationTest.php`, `StudentFaceRegistration`?**
  _High betweenness centrality (0.035) - this node is a cross-community bridge._
- **What connects `name`, `type`, `description` to the rest of the system?**
  _88 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Illuminate\Http\Request` be split into smaller, more focused modules?**
  _Cohesion score 0.051089108910891086 - nodes in this community are weakly interconnected._
- **Should `ExportController.php` be split into smaller, more focused modules?**
  _Cohesion score 0.12100840336134454 - nodes in this community are weakly interconnected._
- **Should `Student` be split into smaller, more focused modules?**
  _Cohesion score 0.0680379746835443 - nodes in this community are weakly interconnected._