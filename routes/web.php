<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Dashboard;
use App\Livewire\CsvUploader;
use App\Livewire\ContactManager;
use App\Http\Controllers\NetworkController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AnalysisController;

// Dashboard
Route::get('/', Dashboard::class)->name('dashboard');

// CSV Upload
Route::get('/upload', CsvUploader::class)->name('upload');

// Network graph
Route::get('/network', [NetworkController::class, 'index'])->name('network');
Route::get('/api/network-data', [NetworkController::class, 'data'])->name('api.network');
Route::post('/api/network-snapshot', [NetworkController::class, 'saveSnapshot'])->name('api.network.snapshot');

// Map
Route::get('/map', [MapController::class, 'index'])->name('map');
Route::get('/api/map-data', [MapController::class, 'data'])->name('api.map');

// Contacts
Route::get('/contacts', ContactManager::class)->name('contacts');

// Analysis
Route::get('/analysis', [AnalysisController::class, 'index'])->name('analysis');

// Report
Route::get('/report', [ReportController::class, 'index'])->name('report');
Route::get('/report/pdf', [ReportController::class, 'pdf'])->name('report.pdf');
