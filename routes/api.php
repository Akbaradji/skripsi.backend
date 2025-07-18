<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminPengajuanController;
use App\Http\Controllers\PengajuanMagangController;

// Login dan Logout Admin (tidak perlu middleware auth di logout, tapi lebih aman tetap pakai)
Route::post('admin/login', [AdminAuthController::class, 'login']);
Route::post('admin/logout', [AdminAuthController::class, 'logout'])->middleware('auth:sanctum');

// Register dan Login Mahasiswa/User
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum'); // untuk mahasiswa/user

// Group route yang harus login (autentikasi via sanctum)
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Route khusus Admin dan ada sebagian untuk user
    Route::middleware(['role:admin'])->group(function () {
        Route::get('admin/dashboard', function () {
            return response()->json(['message' => 'Selamat datang Admin!']);
        });
        Route::get('/admin/pengajuan/stats', [AdminPengajuanController::class, 'stats']);
        Route::get('/admin/pengajuan', [AdminPengajuanController::class, 'index']);
        Route::put('/pengajuan/{id}/status', [PengajuanMagangController::class, 'updateStatus']);
        Route::put('admin/pengajuan/bulk-update-status', [AdminPengajuanController::class, 'bulkUpdateStatus']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::delete('/pengajuan/{id}', [PengajuanMagangController::class, 'destroy']);
    });

    // Route khusus Mahasiswa
    Route::middleware(['role:mahasiswa'])->group(function () {
        Route::get('mahasiswa/dashboard', function () {
            return response()->json(['message' => 'Selamat datang Mahasiswa!']);
        });

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/pengajuan', [PengajuanMagangController::class, 'index']);
            Route::get('/pengajuan/{id}', [PengajuanMagangController::class, 'show']);
            Route::post('/pengajuan', [PengajuanMagangController::class, 'store']);
            Route::delete('/pengajuan/{id}', [PengajuanMagangController::class, 'destroy']);
        });

        // Tambahkan route mahasiswa lain di sini jika perlu
    });
    

});