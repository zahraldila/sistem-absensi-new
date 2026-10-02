<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuditLogControllers;

Route::prefix('api')->middleware(['api'])->group(function () {
    // Auth
    Route::post('login', [App\Http\Controllers\AuthenticationControllers::class, 'login']);
    Route::post('logout', [App\Http\Controllers\AuthenticationControllers::class, 'logout'])->middleware('auth:sanctum');

    // Attendance
    Route::post('attendance/checkin', [App\Http\Controllers\AttendanceControllers::class, 'checkIn'])
        ->middleware(['auth:sanctum', 'feature:attendance']);
    Route::post('attendance/checkout', [App\Http\Controllers\AttendanceControllers::class, 'checkOut'])
        ->middleware(['auth:sanctum', 'feature:attendance']);

    // Submissions
    Route::apiResource('submissions', App\Http\Controllers\SubmissionControllers::class)
        ->middleware(['auth:sanctum', 'feature:approval']);

    // Dashboard
    Route::get('dashboard/admin', [App\Http\Controllers\DashboardControllers::class, 'admin'])
        ->middleware(['auth:sanctum', 'privilege:lihat_dashboard']);

    // Audit Log
    Route::get('audit-log', [App\Http\Controllers\AuditLogControllers::class, 'index'])
        ->middleware(['auth:sanctum', 'privilege:lihat_log_aktivitas']);
});
