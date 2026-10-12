<?php

#by galih

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\FaceAttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StudentPortalController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentCardTemplateController;
use App\Http\Middleware\StudentPortalMiddleware;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/staff/login', [AuthController::class, 'create'])->name('login');
Route::post('/staff/login', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
Route::get('/login', [AuthController::class, 'create'])->name('login.legacy');
Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('login.legacy.store');
Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->middleware('throttle:5,1')->name('password.email');
Route::get('/password/reset/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
Route::post('/password/reset', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::prefix('siswa')->name('student.portal.')->group(function () {
    Route::get('/login', [StudentPortalController::class, 'create'])->name('login');
    Route::post('/login', [StudentPortalController::class, 'store'])->middleware('throttle:5,1')->name('login.store');

    Route::middleware(StudentPortalMiddleware::class)->group(function () {
        Route::get('/kartu', [StudentPortalController::class, 'card'])->name('card');
        Route::get('/kartu/pdf', [StudentPortalController::class, 'pdf'])->middleware('throttle:5,1')->name('pdf');
        Route::get('/scan-wajah', [FaceAttendanceController::class, 'studentScanner'])->name('face');
        Route::post('/scan-wajah/absen', [FaceAttendanceController::class, 'recordStudent'])->middleware('throttle:20,1')->name('face.attendance');
        Route::post('/scan-wajah/registrasi', [FaceAttendanceController::class, 'registerStudent'])->middleware('throttle:5,1')->name('face.register');
        Route::delete('/scan-wajah/registrasi', [FaceAttendanceController::class, 'deleteStudentRegistration'])->name('face.delete');
        Route::get('/pin', [StudentPortalController::class, 'editPin'])->name('pin.edit');
        Route::post('/pin', [StudentPortalController::class, 'updatePin'])->middleware('throttle:5,1')->name('pin.update');
        Route::post('/logout', [StudentPortalController::class, 'destroy'])->name('logout');
    });
});

Route::get('/', [AttendanceController::class, 'home'])->name('student.domain.root');
Route::post('/', [StudentPortalController::class, 'store'])->middleware('throttle:5,1')->name('student.portal.root.login.store');

Route::middleware('auth')->group(function () {
    Route::get('/staff', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/proses_absen', [AttendanceController::class, 'save'])->name('attendance.save');
    Route::get('/scan-qr', [AttendanceController::class, 'scanner'])->name('attendance.scan');
    Route::post('/scan-qr', [AttendanceController::class, 'scan'])->middleware('throttle:60,1')->name('attendance.scan.record');
    Route::get('/scan-wajah', [FaceAttendanceController::class, 'scanner'])->name('face.scan');
    Route::post('/scan-wajah', [FaceAttendanceController::class, 'record'])->middleware('throttle:20,1')->name('face.attendance.record');
    Route::post('/delete_all', [AttendanceController::class, 'delete'])->middleware('role:admin,kesiswaan')->name('attendance.delete');
    Route::get('/cetak_absensi', [ExportController::class, 'printForm'])->name('attendance.print');
    Route::get('/export_a4', [ExportController::class, 'print'])->middleware('throttle:5,1')->name('attendance.print.download');

    Route::middleware('role:admin,kesiswaan')->group(function () {
        Route::get('/laporan', [ReportController::class, 'index'])->middleware('throttle:30,1')->name('reports.index');
        Route::post('/laporan/batas-scan', [ReportController::class, 'updateScanCutoff'])->name('reports.scan-cutoff.update');
        Route::get('/alpha', [ReportController::class, 'alpha'])->middleware('throttle:30,1')->name('reports.alpha');
        Route::get('/export_excel', [ExportController::class, 'excel'])->middleware('throttle:5,1')->name('reports.export');
    });

    Route::middleware('role:admin,kesiswaan')->group(function () {
        Route::get('/data_siswa', [StudentController::class, 'index'])->name('students.index');
        Route::get('/data_siswa/kartu/manual', [StudentController::class, 'qrSelection'])->name('students.qr.selection');
        Route::post('/data_siswa', [StudentController::class, 'store'])->name('students.store');
        Route::post('/data_siswa/foto', [StudentController::class, 'storePhoto'])->name('students.photo.store');
        Route::post('/data_siswa/{student}/pin/reset', [StudentController::class, 'resetPortalPin'])->name('students.pin.reset');
        Route::get('/data_siswa/{student}/qr', [StudentController::class, 'qr'])->middleware('throttle:60,1')->name('students.qr');
        Route::get('/data_siswa/{student}/qr.pdf', [StudentController::class, 'qrPdf'])->middleware('throttle:5,1')->name('students.qr.pdf');
        Route::get('/data_siswa/{student}/qr-haid.svg', [StudentController::class, 'qrHaidSvg'])->name('students.qr.haid.svg');
        Route::post('/data_siswa/qr/cetak', [StudentController::class, 'qrPdfBatch'])->middleware('throttle:3,1')->name('students.qr.batch');
        Route::post('/data_siswa/qr/cetak-massal', [StudentController::class, 'qrPdfMass'])->middleware('throttle:3,1')->name('students.qr.mass');
        Route::get('/data_siswa/{student}/qr.svg', [StudentController::class, 'qrSvg'])->name('students.qr.svg');
        Route::get('/data_siswa/{student}/foto', [StudentController::class, 'photo'])->name('students.photo');
        Route::delete('/data_siswa/hapus-massal', [StudentController::class, 'destroyMass'])->name('students.destroy.mass');
        Route::delete('/data_siswa/{student}', [StudentController::class, 'destroy'])->name('students.destroy');
        Route::post('/kelas', [StudentController::class, 'storeClass'])->name('classes.store');
        Route::delete('/kelas/{classroom}', [StudentController::class, 'destroyClass'])->name('classes.destroy');
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/data_siswa/{student}/wajah', [FaceAttendanceController::class, 'adminRegistration'])->name('students.face');
        Route::post('/data_siswa/{student}/wajah', [FaceAttendanceController::class, 'registerAdmin'])->middleware('throttle:5,1')->name('students.face.register');
        Route::delete('/data_siswa/{student}/wajah', [FaceAttendanceController::class, 'deleteAdminRegistration'])->name('students.face.delete');
        Route::get('/template_kartu', [StudentCardTemplateController::class, 'index'])->name('student-card-templates.index');
        Route::post('/template_kartu', [StudentCardTemplateController::class, 'store'])->name('student-card-templates.store');
        Route::delete('/template_kartu', [StudentCardTemplateController::class, 'destroy'])->name('student-card-templates.destroy');
        Route::get('/data_user', [UserController::class, 'index'])->name('users.index');
        Route::post('/data_user', [UserController::class, 'store'])->name('users.store');
        Route::delete('/data_user/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::get('/import_siswa', [ImportController::class, 'create'])->name('students.import');
        Route::post('/import_siswa', [ImportController::class, 'store'])->middleware('throttle:5,1')->name('students.import.store');
    });
});
