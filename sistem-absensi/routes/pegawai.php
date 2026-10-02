<?php

use Illuminate\Support\Facades\Route;

Route::prefix('pegawai')->middleware(['web','auth'])->group(function () {
    Route::get('/dashboard', function () { return view('pegawai.dashboard'); });

    Route::post('attendance/checkin', [App\Http\Controllers\AttendanceControllers::class, 'checkIn'])
        ->middleware('feature:attendance');
    Route::post('attendance/checkout', [App\Http\Controllers\AttendanceControllers::class, 'checkOut'])
        ->middleware('feature:attendance');

    Route::resource('pengajuan', App\Http\Controllers\SubmissionControllers::class)
        ->middleware('feature:approval');
});
