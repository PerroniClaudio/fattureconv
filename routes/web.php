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
    return view('welcome');
});

// Endpoint per upload PDF (multipart form POST)
Route::post('/upload', [UploadController::class, 'uploadLocal'])->name('upload');

Route::get('/test-parser', function() {
    $document = ProcessedFile::first();

    
    $controller = new \App\Http\Controllers\PdfParserController();
    $text = $controller->extractAndOptimize($document);

    $googleController = new \App\Http\Controllers\GoogleCloudController();
    $response = $googleController->callVertexAI($text);

    $document->structured_json = $response;
    $document->extracted_text = $text;
    $document->status = 'processed';
    $document->save();

    return response()->json($response)->header('Content-Type', 'application/json');
})->name('test-parser');

Route::get('/generate-word', function() {

    $document = ProcessedFile::first();
    $documentcontroller = new \App\Http\Controllers\DocumentController();

    $wordPath = $documentcontroller->generateFromTemplate($document);

    return response()->download($wordPath);

});

// API per la UI: lista processed files e download
Route::get('/api/processed-files', [ProcessedFileController::class, 'index']);
Route::get('/processed-files/{id}/download', [ProcessedFileController::class, 'download'])->name('processed-files.download');

require __DIR__.'/auth.php';