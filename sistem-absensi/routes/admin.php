<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApprovalControllers;

Route::prefix('admin')->middleware(['web', 'auth', 'admin.access', 'org.context'])->group(function () {

    // ── Super Admin Organization Selection & System Management ──────────────
    Route::get('/select-organization', [\App\Http\Controllers\OrganizationSelectionController::class, 'select'])->name('admin.organization.select');
    Route::post('/select-organization', [\App\Http\Controllers\OrganizationSelectionController::class, 'store'])->name('admin.organization.store');
    Route::get('/select-organization/create', [\App\Http\Controllers\OrganizationSelectionController::class, 'create'])->name('admin.organization.create');
    Route::post('/select-organization/create', [\App\Http\Controllers\OrganizationSelectionController::class, 'storeNew'])->name('admin.organization.storeNew');
    Route::post('/switch-organization', [\App\Http\Controllers\OrganizationSelectionController::class, 'switch'])->name('admin.organization.switch');

    // ── System Management (Super Admin: Categories, Features, Templates) ───────
    Route::prefix('system')->name('admin.system.')->group(function () {
        // Categories
        Route::get('/categories', [\App\Http\Controllers\Admin\FeatureSystemController::class, 'categoriesIndex'])->name('categories');
        Route::post('/categories', [\App\Http\Controllers\Admin\FeatureSystemController::class, 'categoryStore'])->name('categories.store');
        Route::put('/categories/{category}', [\App\Http\Controllers\Admin\FeatureSystemController::class, 'categoryUpdate'])->name('categories.update');
        Route::post('/categories/{category}/toggle', [\App\Http\Controllers\Admin\FeatureSystemController::class, 'categoryToggle'])->name('categories.toggle');

        // Features Catalog
        Route::get('/features', [\App\Http\Controllers\Admin\FeatureSystemController::class, 'featuresIndex'])->name('features');
        Route::post('/features', [\App\Http\Controllers\Admin\FeatureSystemController::class, 'featureStore'])->name('features.store');
        Route::put('/features/{feature}', [\App\Http\Controllers\Admin\FeatureSystemController::class, 'featureUpdate'])->name('features.update');
        Route::post('/features/{feature}/toggle', [\App\Http\Controllers\Admin\FeatureSystemController::class, 'featureToggle'])->name('features.toggle');

        // Templates
        Route::get('/templates', [\App\Http\Controllers\Admin\FeatureSystemController::class, 'templatesIndex'])->name('templates');
        Route::get('/templates/create', [\App\Http\Controllers\Admin\FeatureSystemController::class, 'templateCreate'])->name('templates.create');
        Route::post('/templates', [\App\Http\Controllers\Admin\FeatureSystemController::class, 'templateStore'])->name('templates.store');
        Route::get('/templates/{template}/edit', [\App\Http\Controllers\Admin\FeatureSystemController::class, 'templateEdit'])->name('templates.edit');
        Route::put('/templates/{template}', [\App\Http\Controllers\Admin\FeatureSystemController::class, 'templateUpdate'])->name('templates.update');
        Route::post('/templates/{template}/default', [\App\Http\Controllers\Admin\FeatureSystemController::class, 'templateSetDefault'])->name('templates.default');
    });

    // ── Organization Feature Overrides (Super Admin) ─────────────────────────
    Route::get('/organizations/{organization}/features', [\App\Http\Controllers\Admin\FeatureSystemController::class, 'orgFeaturesIndex'])->name('admin.organization.features');
    Route::put('/organizations/{organization}/features', [\App\Http\Controllers\Admin\FeatureSystemController::class, 'orgFeaturesUpdate'])->name('admin.organization.features.update');

    // ── Dashboard ────────────────────────────────────────────────────────────
    Route::get('/', [App\Http\Controllers\DashboardControllers::class, 'admin'])
        ->name('admin.dashboard')
        ->middleware('privilege:lihat_dashboard');

    Route::get('/dashboard', [App\Http\Controllers\DashboardControllers::class, 'admin'])
        ->name('admin.dashboard.index')
        ->middleware('privilege:lihat_dashboard');

    // ── Laporan Kehadiran ─────────────────────────────────────────────────────
    Route::get('/laporan-kehadiran', [App\Http\Controllers\AttendanceReportController::class, 'index'])
        ->name('admin.laporan-kehadiran')
        ->middleware(['privilege:lihat_laporan_kehadiran', 'feature:attendance']);

    // Export routes laporan
    Route::get('/laporan-kehadiran/export/excel', [App\Http\Controllers\AttendanceReportController::class, 'exportExcel'])
        ->name('admin.laporan-kehadiran.export.excel')
        ->middleware(['privilege:lihat_laporan_kehadiran', 'feature:attendance']);
    Route::get('/laporan-kehadiran/export/csv', [App\Http\Controllers\AttendanceReportController::class, 'exportCsv'])
        ->name('admin.laporan-kehadiran.export.csv')
        ->middleware(['privilege:lihat_laporan_kehadiran', 'feature:attendance']);
    Route::get('/laporan-kehadiran/export/pdf', [App\Http\Controllers\AttendanceReportController::class, 'exportPdf'])
        ->name('admin.laporan-kehadiran.export.pdf')
        ->middleware(['privilege:lihat_laporan_kehadiran', 'feature:attendance']);

    // ── Manajemen Akun ────────────────────────────────────────────────────────
    Route::get('/manajemen-akun', [App\Http\Controllers\Admin\EmployeeManagementController::class, 'index'])
        ->name('admin.manajemen-akun')
        ->middleware(['privilege:lihat_manajemen_akun', 'feature:employee']);

    Route::get('/employee-management', [App\Http\Controllers\Admin\EmployeeManagementController::class, 'index'])
        ->name('admin.employee-management.index')
        ->middleware(['privilege:lihat_manajemen_akun', 'feature:employee']);

    Route::get('/employee-management/create', [App\Http\Controllers\Admin\EmployeeManagementController::class, 'create'])
        ->name('admin.employee-management.create')
        ->middleware(['privilege:lihat_manajemen_akun', 'feature:employee']);

    Route::get('/employee-management/{pegawai}/edit', [App\Http\Controllers\Admin\EmployeeManagementController::class, 'edit'])
        ->name('admin.employee-management.edit')
        ->middleware(['privilege:lihat_manajemen_akun', 'feature:employee']);

    // Action routes Manajemen Akun
    Route::post('/employee-management', [App\Http\Controllers\Admin\EmployeeManagementController::class, 'store'])
        ->name('admin.employee-management.store')
        ->middleware(['privilege:tambah_pegawai', 'feature:employee']);
        
    Route::post('/employee-management/divisions', [App\Http\Controllers\Admin\EmployeeManagementController::class, 'storeDivision'])
        ->name('admin.employee-management.storeDivision')
        ->middleware(['privilege:tambah_pegawai', 'feature:employee', 'feature:division']);
        
    Route::post('/employee-management/roles', [App\Http\Controllers\Admin\EmployeeManagementController::class, 'storeRole'])
        ->name('admin.employee-management.storeRole')
        ->middleware(['privilege:tambah_pegawai', 'feature:employee', 'feature:position']);
        
    Route::post('/employee-management/export', [App\Http\Controllers\Admin\EmployeeExportController::class, 'export'])
        ->name('admin.employee-management.export')
        ->middleware(['privilege:export_pegawai', 'feature:employee']);
        
    Route::put('/employee-management/{pegawai}', [App\Http\Controllers\Admin\EmployeeManagementController::class, 'update'])
        ->name('admin.employee-management.update')
        ->middleware(['privilege:edit_pegawai', 'feature:employee']);

    // ── Persetujuan ───────────────────────────────────────────────────────────
    Route::get('/persetujuan', [ApprovalControllers::class, 'index'])
        ->name('admin.persetujuan')
        ->middleware(['privilege:lihat_persetujuan', 'feature:approval']);

    // Catatan absensi oleh admin
    Route::post('/persetujuan', [ApprovalControllers::class, 'store'])
        ->name('admin.persetujuan.store')
        ->middleware(['privilege:catat_absensi', 'feature:approval']);

    Route::get('/persetujuan/{approval}', [ApprovalControllers::class, 'show'])
        ->name('admin.persetujuan.detail')
        ->middleware(['privilege:lihat_persetujuan', 'feature:approval']);

    // Export & action routes Persetujuan
    Route::get('/persetujuan/export/excel', [App\Http\Controllers\ApprovalControllers::class, 'exportExcel'])
        ->name('admin.persetujuan.export.excel')
        ->middleware(['privilege:lihat_persetujuan', 'feature:approval']);
        
    Route::get('/persetujuan/export/csv', [App\Http\Controllers\ApprovalControllers::class, 'exportCsv'])
        ->name('admin.persetujuan.export.csv')
        ->middleware(['privilege:lihat_persetujuan', 'feature:approval']);
        
    Route::get('/persetujuan/export/pdf', [App\Http\Controllers\ApprovalControllers::class, 'exportPdf'])
        ->name('admin.persetujuan.export.pdf')
        ->middleware(['privilege:lihat_persetujuan', 'feature:approval']);
        
    Route::post('/persetujuan/{pengajuan}/approve', [ApprovalControllers::class, 'approve'])
        ->name('admin.persetujuan.approve')
        ->middleware(['privilege:approve_pengajuan', 'feature:approval']);
        
    Route::post('/persetujuan/{pengajuan}/reject', [ApprovalControllers::class, 'reject'])
        ->name('admin.persetujuan.reject')
        ->middleware(['privilege:reject_pengajuan', 'feature:approval']);
        
    Route::post('/persetujuan/{pengajuan}/process', [ApprovalControllers::class, 'process'])
        ->name('admin.persetujuan.process')
        ->middleware('feature:approval'); // Dilaporkan sebagai ambiguity karena ini proxy untuk approve/reject

    // ── Log Aktivitas ─────────────────────────────────────────────────────────
    // CATATAN: Route ini ada duplikat di routes/web.php (tanpa auth).
    // Route di sini menggunakan middleware privilege:lihat_log_aktivitas.
    // Route di web.php (didaftarkan belakangan) akan menimpa nama 'admin.log-aktivitas'.
    // Proteksi dilakukan di KEDUA route — lihat juga web.php.
    Route::get('/log-aktivitas', [App\Http\Controllers\AdminPlaceholderController::class, 'logAktivitas'])
        ->name('admin.log-aktivitas-protected')  // nama berbeda agar tidak ditimpa
        ->middleware('privilege:lihat_log_aktivitas');

    // ── Settings (Tampilan & Branding, Lokasi, Role & Hak Akses) ─────────────
    // Proteksi per-tab dilakukan di controller AdminPlaceholderController@tampilanBranding
    // karena semua tab menggunakan satu route yang sama.
    Route::get('/tampilan-branding', [App\Http\Controllers\AdminPlaceholderController::class, 'tampilanBranding'])
        ->name('admin.tampilan-branding');

    // Action routes Settings
    Route::post('/tampilan-branding/simpan', [App\Http\Controllers\AdminPlaceholderController::class, 'simpanBranding'])
        ->name('admin.tampilan-branding.simpan')
        ->middleware('privilege:kelola_tampilan_branding');
        
    Route::post('/tampilan-branding/reset', [App\Http\Controllers\AdminPlaceholderController::class, 'resetBranding'])
        ->name('admin.tampilan-branding.reset')
        ->middleware('privilege:kelola_tampilan_branding');
        
    Route::post('/tampilan-branding/logo', [App\Http\Controllers\AdminPlaceholderController::class, 'simpanLogo'])
        ->name('admin.tampilan-branding.logo')
        ->middleware('privilege:kelola_tampilan_branding');

    Route::get('/settings/lokasi', [App\Http\Controllers\AdminPlaceholderController::class, 'lokasiKantor'])
        ->name('admin.settings.lokasi')
        ->middleware(['privilege:kelola_lokasi_cabang', 'feature:location']);
        
    Route::post('/settings/lokasi/simpan', [App\Http\Controllers\AdminPlaceholderController::class, 'simpanLokasi'])
        ->name('admin.settings.lokasi.simpan')
        ->middleware(['privilege:kelola_lokasi_cabang', 'feature:location']);
        
    Route::delete('/settings/lokasi/{id}', [App\Http\Controllers\AdminPlaceholderController::class, 'hapusLokasi'])
        ->name('admin.settings.lokasi.hapus')
        ->middleware(['privilege:kelola_lokasi_cabang', 'feature:location']);
        
    Route::post('/settings/lokasi/hapus/{id}', [App\Http\Controllers\AdminPlaceholderController::class, 'hapusLokasi'])
        ->name('admin.settings.lokasi.hapus.post')
        ->middleware(['privilege:kelola_lokasi_cabang', 'feature:location']);
        
    Route::post('/settings/roles/simpan', [App\Http\Controllers\AdminPlaceholderController::class, 'simpanRolePrivilege'])
        ->name('admin.settings.roles.simpan')
        ->middleware('privilege:kelola_role_hak_akses');
        
    Route::post('/settings/roles/tambah', [App\Http\Controllers\AdminPlaceholderController::class, 'tambahRole'])
        ->name('admin.settings.roles.tambah')
        ->middleware('privilege:kelola_role_hak_akses');
        
    Route::delete('/settings/roles/{id}', [App\Http\Controllers\AdminPlaceholderController::class, 'hapusRole'])
        ->name('admin.settings.roles.hapus')
        ->middleware('privilege:kelola_role_hak_akses');
        
    Route::post('/settings/roles/hapus/{id}', [App\Http\Controllers\AdminPlaceholderController::class, 'hapusRole'])
        ->name('admin.settings.roles.hapus.post')
        ->middleware('privilege:kelola_role_hak_akses');

    // ── Misc ──────────────────────────────────────────────────────────────────
    Route::get('/pengaturan', [App\Http\Controllers\AdminPlaceholderController::class, 'pengaturan'])->name('admin.pengaturan');
    Route::get('/bantuan', [App\Http\Controllers\AdminPlaceholderController::class, 'bantuan'])->name('admin.bantuan');

    // AJAX / data endpoints — tidak dilindungi privilege (data pendukung dashboard)
    Route::get('/chart-statistik', [App\Http\Controllers\DashboardControllers::class, 'chartStatistik'])->name('admin.chart-statistik');
    
    Route::post('/jam-kerja', [App\Http\Controllers\DashboardControllers::class, 'simpanJamKerja'])
        ->name('admin.jam-kerja.simpan')
        ->middleware(['privilege:kelola_jadwal_kerja', 'feature:schedule']);

    // Admin resource routes (placeholders)
    Route::apiResource('employees', App\Http\Controllers\EmployeeControllers::class)->middleware('feature:employee');
    Route::apiResource('submissions', App\Http\Controllers\SubmissionControllers::class)->middleware('feature:approval');
    Route::post('approvals/{submission}', [App\Http\Controllers\ApprovalControllers::class, 'store'])->middleware('feature:approval');
});
