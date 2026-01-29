<?php

use App\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\ProcessedFileController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\ZipExportController;
use App\Models\ProcessedFile;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

// Assets pubblici (favicon, logo)
Route::get('/favicon.ico', function () {
    $disk = Storage::disk(config('filesystems.default'));
    $path = 'assets/favicon-ift.png';

    try {
        if (!$disk->exists($path)) {
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
    $disk = Storage::disk(config('filesystems.default'));
    $path = 'assets/logo-header-ift.png';

    try {
        if (!$disk->exists($path)) {
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

// Login
Route::get('/', function () {
    if (auth()) {
        return redirect('/app');
    }
    return view('login');
})->name('login.page');

Route::get('/test-merge', function () {
    $job = new \App\Jobs\MergePdfJob(36);
    $job->handle();
    return 'Job executed';
});

/*
|--------------------------------------------------------------------------
| Authenticated Web Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    
    // Dashboard
    Route::get('/app', function () {
        $processedFiles = ProcessedFile::whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        return view('welcome', compact('processedFiles'));
    })->name('app.dashboard');

    // Exports page
    Route::get('/exports', function () {
        return view('exports');
    })->name('exports.page');

    // Upload
    Route::post('/upload', [UploadController::class, 'uploadLocal'])
        ->name('upload');
    
    Route::post('/upload/process', [UploadController::class, 'processFilePond'])
        ->name('upload.process');

    // Export
    Route::get('/export', function() {
        $start_date = request('start_date');
        $end_date = request('end_date');

        if (!$start_date || !$end_date) {
            return response()->json([
                'error' => 'start_date and end_date parameters are required'
            ], 400);
        }

        $export = new \App\Exports\FattureExport($start_date, $end_date);
        return \Maatwebsite\Excel\Facades\Excel::download(
            $export, 
            'fatture_export_' . $start_date . '_to_' . $end_date . '.xlsx'
        );
    })->name('export');

    // Download file
    Route::get('/processed-files/{id}/download', 
        [ProcessedFileController::class, 'download'])
        ->name('processed-files.download');
    Route::get('/processed-files/{id}/download-original',
        [ProcessedFileController::class, 'downloadOriginal'])
        ->name('processed-files.download-original');

    Route::get('/processed-files/{id}/download-merged',
        [ProcessedFileController::class, 'downloadMerged'])
        ->name('processed-files.download-merged');

    // Esportazione ZIP
    Route::get('/zip-exports', [ZipExportController::class, 'index'])
        ->name('zip-exports.index');

    // Archivio 

    Route::get('/archive', function () {
        return view('archive');
    })->name('archive.index');
});

/*
|--------------------------------------------------------------------------
| Authenticated API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('api')->middleware('auth')->group(function () {
    
    // Processed Files API
    Route::prefix('processed-files')->group(function () {
        Route::get('/', [ProcessedFileController::class, 'index'])
            ->name('api.processed-files.index');
        
        Route::post('/statuses', [ProcessedFileController::class, 'statuses'])
            ->name('api.processed-files.statuses');
        
        Route::get('/in-progress', [ProcessedFileController::class, 'inProgress'])
            ->name('api.processed-files.in-progress');
        
        Route::delete('/{id}', [ProcessedFileController::class, 'destroy'])
            ->name('api.processed-files.destroy');

        Route::post('/{id}/merge', [ProcessedFileController::class, 'merge'])
            ->name('api.processed-files.merge');

        Route::put('/{id}/month-reference', [ProcessedFileController::class, 'updateMonthReference'])
            ->name('api.processed-files.update-month-reference');
    });

    // Zip Exports API
    Route::prefix('zip-exports')->group(function () {
        Route::get('/', [ZipExportController::class, 'index'])
            ->name('api.zip-exports.index');
        
        Route::post('/', [ZipExportController::class, 'store'])
            ->name('api.zip-exports.store');
        
        Route::get('/{id}', [ZipExportController::class, 'show'])
            ->name('api.zip-exports.show');
        
        Route::get('/{id}/download', [ZipExportController::class, 'download'])
            ->name('api.zip-exports.download');
    });

    Route::get('/archive', [ArchiveController::class, 'index'])
        ->name('api.archive.index');
    Route::get('/archive/search', [ArchiveController::class, 'search'])
        ->name('api.archive.search');
});

/*
|--------------------------------------------------------------------------
| Auth Routes
|--------------------------------------------------------------------------
*/

require __DIR__.'/auth.php';
