<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\MainDashboard;
use App\Livewire\ComintDashboard;
use App\Livewire\CsvUploader;
use App\Livewire\ContactManager;
use App\Http\Controllers\NetworkController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\GeointController;
use App\Http\Controllers\TenantController;
use App\Livewire\Geoint\GeointUnits;
use App\Livewire\Geoint\GeointVehicles;
use App\Livewire\Geoint\GeointGeofences;
use App\Livewire\Geoint\GeointAlerts;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\SettingController;

// ═══════════════════════════════════════
// 2FA Challenge (guest con session token)
// ═══════════════════════════════════════
Route::get('/two-factor', [TwoFactorController::class, 'showChallenge'])->name('two-factor.challenge');
Route::post('/two-factor', [TwoFactorController::class, 'challenge']);

// ═══════════════════════════════════════
// Rutas protegidas con auth
// ═══════════════════════════════════════
Route::middleware(['auth', 'active.user'])->group(function () {

    // Raíz redirige al dashboard general
    Route::get('/', fn() => redirect()->route('dashboard'));

    // Dashboard general — accesible a todos los autenticados
    Route::get('/dashboard', MainDashboard::class)->name('dashboard');

    // ── COMINT / CDR ──────────────────────────────
    Route::middleware('module:comint')->group(function () {
        Route::get('/comint/dashboard', ComintDashboard::class)->name('comint.dashboard');
        Route::get('/upload', CsvUploader::class)->name('upload');
        Route::get('/contacts', ContactManager::class)->name('contacts');
        Route::get('/network', [NetworkController::class, 'index'])->name('network');
        Route::get('/analysis', [AnalysisController::class, 'index'])->name('analysis');
        Route::get('/map', [MapController::class, 'index'])->name('map');
        Route::get('/report', [ReportController::class, 'index'])->name('report');
        Route::get('/report/pdf', [ReportController::class, 'pdf'])->name('report.pdf');
    });

    // ── APIs COMINT ────────────────────────────────
    Route::middleware('module:comint')->prefix('api')->group(function () {
        Route::get('/network-data', [NetworkController::class, 'data'])->name('api.network');
        Route::post('/network-snapshot', [NetworkController::class, 'saveSnapshot'])->name('api.network.snapshot');
        Route::get('/map-data', [MapController::class, 'data'])->name('api.map');
        Route::post('/map-snapshot', [MapController::class, 'saveSnapshot'])->name('api.map.snapshot');
    });

    // ── OTROS MÓDULOS ─────────────────────────────
    Route::middleware('module:osint')->get('/osint', [ModuleController::class, 'osint'])->name('osint');
    Route::middleware('module:incidencia')->get('/incidencia', [ModuleController::class, 'incidencia'])->name('incidencia');
    Route::middleware('module:casos')->get('/casos', [ModuleController::class, 'casos'])->name('casos');

    // ── GEOINT ────────────────────────────────────
    Route::middleware('module:geoint')->prefix('geoint')->name('geoint.')->group(function () {
        Route::get('/',          [GeointController::class, 'index'])->name('map');
        Route::get('/vehicles',  GeointVehicles::class)->name('vehicles');
        Route::get('/units',     GeointUnits::class)->name('units');
        Route::get('/geofences', GeointGeofences::class)->name('geofences');
        Route::get('/alerts',    GeointAlerts::class)->name('alerts');
        Route::get('/report',        [GeointController::class, 'report'])->name('report');
        Route::get('/report/export', [GeointController::class, 'exportPdf'])->name('report.export');
        Route::post('/report/snapshot', [GeointController::class, 'saveSnapshot'])->name('report.snapshot');
    });

    // ── APIs GEOINT ───────────────────────────────
    Route::middleware('module:geoint')->prefix('api/geoint')->group(function () {
        Route::get('/units',              [GeointController::class, 'apiUnits'])->name('api.geoint.units');
        Route::get('/unit/{id}/history',  [GeointController::class, 'apiUnitHistory'])->name('api.geoint.history');
        Route::get('/vehicle/{id}/points',[GeointController::class, 'apiVehiclePoints'])->name('api.geoint.vehicle.points');
        Route::get('/geofences',          [GeointController::class, 'apiGeofences'])->name('api.geoint.geofences');
    });

    // ── 2FA SETUP ─────────────────────────────────
    Route::get('/two-factor/setup', [TwoFactorController::class, 'showSetup'])->name('two-factor.setup');
    Route::post('/two-factor/enable', [TwoFactorController::class, 'enable'])->name('two-factor.enable');
    Route::post('/two-factor/disable', [TwoFactorController::class, 'disable'])->name('two-factor.disable');

    // ── ADMINISTRACIÓN (solo super_admin) ─────────
    Route::middleware('super.admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', fn() => redirect()->route('admin.users.index'))->name('index');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::patch('/users/{user}/toggle', [UserController::class, 'toggleActive'])->name('users.toggle');
        Route::patch('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');

        Route::get('/users/{user}/permissions', [PermissionController::class, 'show'])->name('users.permissions');
        Route::put('/users/{user}/permissions', [PermissionController::class, 'update'])->name('users.permissions.update');

        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
        Route::get('/audit/export', [AuditController::class, 'exportPdf'])->name('audit.export');
    });

    // ── CONFIG GLOBAL + INSTITUCIONES (solo super admin GLOBAL, sin tenant) ──
    // La configuración general (institution_name, force_2fa, session_lifetime) es
    // global; un admin de institución no debe modificarla.
    Route::middleware('super_admin_global')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');

        Route::get('/tenants',                 [TenantController::class, 'index'])->name('tenants.index');
        Route::post('/tenants',                [TenantController::class, 'store'])->name('tenants.store');
        Route::put('/tenants/{tenant}',        [TenantController::class, 'update'])->name('tenants.update');
        Route::patch('/tenants/{tenant}/toggle',[TenantController::class, 'toggle'])->name('tenants.toggle');
    });

});

require __DIR__.'/auth.php';
