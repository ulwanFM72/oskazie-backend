<?php

use App\Http\Controllers\Api\Admin\AnnouncementController;
use App\Http\Controllers\Api\Admin\AttendanceController;
use App\Http\Controllers\Api\Admin\AuditLogController;
use App\Http\Controllers\Api\Admin\CandidateManagementController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\ElectionController;
use App\Http\Controllers\Api\Admin\ReportController;
use App\Http\Controllers\Api\Admin\VoterManagementController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\VoterController;
use Illuminate\Support\Facades\Route;

// ---- Publik ----
Route::get('/election/status', [PublicController::class, 'status']);
Route::get('/announcements', [PublicController::class, 'announcements']);
Route::get('/results', [PublicController::class, 'results']); // hanya jika dipublikasikan

// ---- Autentikasi ----
Route::prefix('auth')->group(function () {
    // Rate limit longgar (bukan penguncian) agar antrean bilik tidak terhambat.
    Route::post('/voter-login', [AuthController::class, 'voterLogin'])->middleware('throttle:30,1');
    Route::post('/admin-login', [AuthController::class, 'adminLogin'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

// ---- Pemilih ----
Route::middleware(['auth:sanctum', 'abilities:voter'])->prefix('voter')->group(function () {
    Route::get('/candidates', [VoterController::class, 'candidates']);
    Route::post('/vote', [VoterController::class, 'vote'])->middleware('throttle:20,1');
});

// ---- Admin ----
Route::middleware(['auth:sanctum', 'abilities:admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'show']);
    Route::get('/audit-logs', [AuditLogController::class, 'index']);

    // Pemilihan
    Route::post('/elections', [ElectionController::class, 'store']);
    Route::get('/elections/current', [ElectionController::class, 'current']);
    Route::patch('/elections/{election}', [ElectionController::class, 'update']);
    Route::patch('/elections/{election}/extend', [ElectionController::class, 'extend']);
    Route::patch('/elections/{election}/end', [ElectionController::class, 'endEarly']);
    Route::post('/elections/{election}/finalize', [ElectionController::class, 'finalize']);
    Route::post('/elections/{election}/publish', [ElectionController::class, 'publish']);
    Route::delete('/elections/{election}', [ElectionController::class, 'destroy']);

    // Laporan
    Route::get('/elections/{election}/turnout', [ReportController::class, 'turnout']);
    Route::get('/elections/{election}/results', [ReportController::class, 'results']);

    // Kehadiran dan Daftar Belum Memilih
    Route::get('/elections/{election}/voters', [AttendanceController::class, 'index']);
    Route::get('/elections/{election}/non-voters', [AttendanceController::class, 'nonVoters']);
    Route::get('/elections/{election}/non-voters/export', [AttendanceController::class, 'exportNonVoters']);
    Route::patch('/election-voters/{electionVoter}/attendance', [AttendanceController::class, 'update']);

    // Data pemilih
    Route::get('/voters', [VoterManagementController::class, 'index']);
    Route::post('/voters', [VoterManagementController::class, 'store']);
    Route::post('/voters/import', [VoterManagementController::class, 'import']);
    Route::patch('/voters/{voter}', [VoterManagementController::class, 'update']);
    Route::delete('/voters/{voter}', [VoterManagementController::class, 'destroy']);

    // Paslon (multipart + foto: gunakan POST dengan _method=PATCH)
    Route::get('/candidates', [CandidateManagementController::class, 'index']);
    Route::post('/candidates', [CandidateManagementController::class, 'store']);
    Route::patch('/candidates/{candidate}', [CandidateManagementController::class, 'update']);
    Route::delete('/candidates/{candidate}', [CandidateManagementController::class, 'destroy']);

    // Pengumuman
    Route::get('/announcements', [AnnouncementController::class, 'index']);
    Route::post('/announcements', [AnnouncementController::class, 'store']);
    Route::patch('/announcements/{announcement}', [AnnouncementController::class, 'update']);
    Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy']);
});
