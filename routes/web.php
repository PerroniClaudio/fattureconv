<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UploadController;
use App\Models\ProcessedFile;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\ProcessedFileController;
use App\Http\Controllers\ZipExportController;

// Public routes for favicon and logo served from GCS (simplified)
Route::get('/favicon.ico', function () {
    $disk = Storage::disk('gcs');
    $path = 'assets/favicon-ift.png';

    try {
        if (! $disk->exists($path)) {
            return response('', 404);
        }

        $content = $disk->get($path);
        $mime = Storage::mimeType($path) ?: 'image/png';
        return response($content, 200)
            ->header('Content-Type', $mime)
            ->header('Cache-Control', 'public, max-age=86400');
    } catch (\Exception $e) {
        return response('Error fetching favicon', 500);
    }
});

Route::get('/logo', function () {
    $disk = Storage::disk('gcs');
    $path = 'assets/logo-header-ift.png';

    try {
        if (! $disk->exists($path)) {
            return response('', 404);
        }

        $content = $disk->get($path);
        $mime = Storage::mimeType($path) ?: 'image/png';
        return response($content, 200)
            ->header('Content-Type', $mime)
            ->header('Cache-Control', 'public, max-age=86400');
    } catch (\Exception $e) {
        return response('Error fetching logo', 500);
    }
});

Route::get('/', function () {
    return view('login');
});

Route::get('/app', function () {
    $processedFiles = ProcessedFile::orderBy('created_at', 'desc')->paginate(10);
    return view('welcome', compact('processedFiles'));
})->middleware('auth');

// Endpoint per upload PDF (multipart form POST)
Route::post('/upload', [UploadController::class, 'uploadLocal'])->name('upload');

// Endpoint compatibile FilePond (server.process) – riceve un singolo file per richiesta
Route::post('/upload/process', [UploadController::class, 'processFilePond'])->name('upload.process');

//Export 
Route::get('/export', function() {

    $start_date = request('start_date');
    $end_date = request('end_date');

    if (!$start_date || !$end_date) {
        return response()->json(['error' => 'start_date and end_date parameters are required'], 400);
    }

    $export = new \App\Exports\FattureExport($start_date, $end_date);
    return \Maatwebsite\Excel\Facades\Excel::download($export, 'fatture_export_' . $start_date . '_to_' . $end_date . '.xlsx');

})->name('export');


// API per la UI: lista processed files e download
Route::get('/api/processed-files', [ProcessedFileController::class, 'index']);
Route::post('/api/processed-files/statuses', [ProcessedFileController::class, 'statuses']);
Route::get('/api/processed-files/in-progress', [ProcessedFileController::class, 'inProgress']);
Route::get('/processed-files/{id}/download', [ProcessedFileController::class, 'download'])->name('processed-files.download');

// Zip exports UI + API

Route::get('/zip-exports', [ZipExportController::class, 'index'])->middleware('auth');
Route::post('/api/zip-exports', [ZipExportController::class, 'store']);
Route::get('/api/zip-exports', [ZipExportController::class, 'index']);
Route::get('/api/zip-exports/{id}', [ZipExportController::class, 'show']);
Route::get('/api/zip-exports/{id}/download', [ZipExportController::class, 'download']);

require __DIR__.'/auth.php';