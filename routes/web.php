<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UploadController;
use App\Models\ProcessedFile;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\ProcessedFileController;

Route::get('/', function () {
    return view('login');
});

Route::get('/app', function () {
    $processedFiles = ProcessedFile::orderBy('created_at', 'desc')->paginate(15);
    return view('welcome', compact('processedFiles'));
});

// Endpoint per upload PDF (multipart form POST)
Route::post('/upload', [UploadController::class, 'uploadLocal'])->name('upload');

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
Route::get('/processed-files/{id}/download', [ProcessedFileController::class, 'download'])->name('processed-files.download');

require __DIR__.'/auth.php';