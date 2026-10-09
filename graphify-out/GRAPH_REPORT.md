# Graph Report - absen_shalat  (2026-10-02)

## Corpus Check
- 84 files · ~40,879 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 21 file(s) not represented in the graph (top: (none) 15, .css 3, .example 1)

## Summary
- 457 nodes · 898 edges · 56 communities (21 shown, 35 thin omitted)
- Extraction: 97% EXTRACTED · 3% INFERRED · 0% AMBIGUOUS · INFERRED: 26 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `c5dd38a2`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- composer.json
- Illuminate\Http\Request
- ExportController.php
- Student
- User
- Illuminate\Database\Schema\Blueprint
- hyalite.js
- package.json
- StudentController
- require
- config
- AppServiceProvider
- logging.php
- Rekap Absensi Solat (Laravel)
- Illuminate\Support\Str
- ExampleTest
- students/index.blade.php
- require-dev
- psr-4
- extra
- scripts
- autoload-dev
- TestCase
- public/js/qr-scanner.js
- NativeFlowMigrationTest.php

## God Nodes (most connected - your core abstractions)
1. `Student` - 46 edges
2. `NativeFlowMigrationTest` - 42 edges
3. `Classroom` - 40 edges
4. `StudentController` - 26 edges
5. `ExportController` - 19 edges
6. `Attendance` - 19 edges
7. `StudentPortalController` - 15 edges
8. `StudentCardTemplateStorage` - 14 edges
9. `StudentCardPhotoProcessor` - 13 edges
10. `apply()` - 12 edges

## Surprising Connections (you probably didn't know these)
- `ExportController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/ExportController.php → app/Http/Controllers/Controller.php
- `StudentController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/StudentController.php → app/Http/Controllers/Controller.php
- `StudentCardTemplateController` --references--> `StudentCardTemplateStorage`  [EXTRACTED]
  app/Http/Controllers/StudentCardTemplateController.php → app/Services/StudentCardTemplateStorage.php
- `StudentController` --references--> `StudentCardPhotoProcessor`  [EXTRACTED]
  app/Http/Controllers/StudentController.php → app/Services/StudentCardPhotoProcessor.php
- `StudentController` --references--> `StudentCardTemplateStorage`  [EXTRACTED]
  app/Http/Controllers/StudentController.php → app/Services/StudentCardTemplateStorage.php

## Import Cycles
- None detected.

## Communities (56 total, 35 thin omitted)

### Community 0 - "composer.json"
Cohesion: 0.25
Nodes (7): description, keywords, license, minimum-stability, name, prefer-stable, type

### Community 1 - "Illuminate\Http\Request"
Cohesion: 0.07
Nodes (18): AttendanceController, AuthController, Controller, ImportController, ReportController, StudentCardTemplateController, StudentPortalController, UserController (+10 more)

### Community 2 - "ExportController.php"
Cohesion: 0.13
Nodes (16): ExportController, DateTimeImmutable, Illuminate\Support\Collection, PhpOffice\PhpSpreadsheet\Cell\Coordinate, PhpOffice\PhpSpreadsheet\Cell\DataType, PhpOffice\PhpSpreadsheet\Spreadsheet, PhpOffice\PhpSpreadsheet\Style\Alignment, PhpOffice\PhpSpreadsheet\Style\Border (+8 more)

### Community 3 - "Student"
Cohesion: 0.09
Nodes (8): AppSetting, Attendance, Classroom, Student, Illuminate\Database\Eloquent\Model, Illuminate\Database\Eloquent\Relations\BelongsTo, Illuminate\Database\Eloquent\Relations\HasMany, NativeFlowMigrationTest

### Community 4 - "User"
Cohesion: 0.22
Nodes (9): User, UserFactory, DatabaseSeeder, Illuminate\Database\Eloquent\Factories\Factory, Illuminate\Database\Eloquent\Factories\HasFactory, Illuminate\Database\Seeder, Illuminate\Foundation\Auth\User, Illuminate\Notifications\Notifiable (+1 more)

### Community 5 - "Illuminate\Database\Schema\Blueprint"
Cohesion: 0.11
Nodes (4): Illuminate\Database\Migrations\Migration, Illuminate\Database\Schema\Blueprint, Illuminate\Support\Facades\DB, Illuminate\Support\Facades\Schema

### Community 6 - "hyalite.js"
Cohesion: 0.15
Nodes (29): acquire(), acquireMap(), apply(), attach(), buildFilter(), buildMap(), detach(), edgeShadow() (+21 more)

### Community 7 - "package.json"
Cohesion: 0.08
Nodes (21): dependencies, @zxing/browser, @zxing/library, devDependencies, axios, esbuild, laravel-vite-plugin, vite (+13 more)

### Community 8 - "StudentController"
Cohesion: 0.13
Nodes (8): StudentController, RoleMiddleware, StudentPortalMiddleware, Closure, Illuminate\Foundation\Application, Illuminate\Foundation\Configuration\Exceptions, Illuminate\Foundation\Configuration\Middleware, Symfony\Component\HttpFoundation\Response

### Community 9 - "require"
Cohesion: 0.25
Nodes (8): require, dompdf/dompdf, endroid/qr-code, laravel/framework, laravel/tinker, php, phpoffice/phpspreadsheet, phpoffice/phpword

### Community 10 - "config"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 12 - "logging.php"
Cohesion: 0.40
Nodes (4): Monolog\Handler\NullHandler, Monolog\Handler\StreamHandler, Monolog\Handler\SyslogUdpHandler, Monolog\Processor\PsrLogMessageProcessor

### Community 13 - "Rekap Absensi Solat (Laravel)"
Cohesion: 0.40
Nodes (4): Pengujian, Peran, Rekap Absensi Solat (Laravel), Setup

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

### Community 47 - "TestCase"
Cohesion: 0.47
Nodes (3): Illuminate\Foundation\Testing\TestCase, ExampleTest, TestCase

### Community 48 - "public/js/qr-scanner.js"
Cohesion: 0.19
Nodes (16): En(), f(), fe(), i(), Ki(), n(), o(), os() (+8 more)

### Community 53 - "NativeFlowMigrationTest.php"
Cohesion: 0.10
Nodes (16): StudentCardPhotoProcessor, StudentCardTemplateStorage, StudentQrCodeGenerator, Dompdf\Dompdf, Dompdf\Options, Endroid\QrCode\Encoding\Encoding, Endroid\QrCode\ErrorCorrectionLevel, Endroid\QrCode\QrCode (+8 more)

## Knowledge Gaps
- **55 isolated node(s):** `name`, `type`, `description`, `keywords`, `license` (+50 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 157 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **35 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Student` connect `Student` to `StudentController`, `Illuminate\Http\Request`, `ExportController.php`, `NativeFlowMigrationTest.php`?**
  _High betweenness centrality (0.067) - this node is a cross-community bridge._
- **Why does `Classroom` connect `Student` to `Illuminate\Http\Request`, `ExportController.php`, `NativeFlowMigrationTest.php`?**
  _High betweenness centrality (0.045) - this node is a cross-community bridge._
- **Why does `NativeFlowMigrationTest` connect `Student` to `NativeFlowMigrationTest.php`, `TestCase`?**
  _High betweenness centrality (0.036) - this node is a cross-community bridge._
- **What connects `name`, `type`, `description` to the rest of the system?**
  _55 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Illuminate\Http\Request` be split into smaller, more focused modules?**
  _Cohesion score 0.07242063492063493 - nodes in this community are weakly interconnected._
- **Should `ExportController.php` be split into smaller, more focused modules?**
  _Cohesion score 0.13068181818181818 - nodes in this community are weakly interconnected._
- **Should `Student` be split into smaller, more focused modules?**
  _Cohesion score 0.08700564971751412 - nodes in this community are weakly interconnected._