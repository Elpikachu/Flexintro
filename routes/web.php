<?php

use App\Http\Controllers\FileController;
use App\Http\Controllers\ImportController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/files');

Route::get('/files', [FileController::class, 'index'])->name('files.index');
Route::get('/files/{path}', [FileController::class, 'show'])
    ->where('path', '.*')
    ->name('files.show');

Route::get('/import', [ImportController::class, 'index'])->name('import.index');
Route::get('/import/status', [ImportController::class, 'status'])->name('import.status');
Route::post('/import/start', [ImportController::class, 'start'])->name('import.start');
Route::post('/import/reset', [ImportController::class, 'reset'])->name('import.reset');
