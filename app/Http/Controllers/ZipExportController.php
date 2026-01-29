<?php

namespace App\Http\Controllers;

use App\Models\ZipExport;
use Illuminate\Http\Request;
use App\Jobs\GenerateZipFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ZipExportController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // If the request expects JSON, return paginated JSON like ProcessedFileController
        if (request()->wantsJson()) {
            $status = request('status');
            $page = max(1, (int) request('page', 1));
            $perPage = max(1, min(200, (int) request('per_page', 10)));

            $query = ZipExport::query();
            if ($status) $query->where('status', $status);

            $total = $query->count();
            $items = $query->orderBy('created_at', 'desc')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            return response()->json([
                'items' => $items,
                'meta' => [
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'last_page' => (int) ceil($total / $perPage),
                ]
            ]);
        }

        // Otherwise render the blade view
        return view('zipfiles');
    }

 

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $zip = ZipExport::create([
            'zip_filename' => '',
            'gcs_path' => '',
            'status' => 'pending',
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
        ]);

        // Dispatch the job to generate the zip
        try {
            dispatch(new GenerateZipFile($zip));
        } catch (\Exception $e) {
            Log::error('ZipExportController::store dispatch error', ['exception' => $e, 'zip_export_id' => $zip->id]);
            $zip->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            return response()->json(['error' => 'Impossibile avviare il job'], 500);
        }

        return response()->json($zip, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(ZipExport $zipExport)
    {
        return response()->json($zipExport);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ZipExport $zipExport)
    {
        // not implemented
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ZipExport $zipExport)
    {
        // allow manual status updates for debugging (optional)
        $data = $request->only(['status','error_message','gcs_path']);
        $zipExport->update($data);
        return response()->json($zipExport);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ZipExport $zipExport)
    {
        $zipExport->delete();
        return response()->json(['deleted' => true]);
    }

    /**
     * Stream download the generated ZIP (from GCS or local fallback)
     */
    public function download($id)
    {
        $zip = ZipExport::find($id);
        if (!$zip) return abort(404);
        // Costruisci il nome del file di download
        $nameSource = $zip->zip_filename ?: ('export_' . $zip->id);
        // se zip_filename è vuoto, prova a costruire un nome dalle date
        if (empty($zip->zip_filename) && !empty($zip->start_date) && !empty($zip->end_date)) {
            $nameSource = 'export_' . $zip->start_date . '_' . $zip->end_date;
        }
        $baseName = pathinfo($nameSource, PATHINFO_FILENAME);
        $sanitizedBase = preg_replace('/[^A-Za-z0-9_\-\. ]+/', '_', $baseName);
        $downloadFilename = $sanitizedBase . '.zip';

        if (!empty($zip->gcs_path)) {
            try {
                $disk = Storage::disk(config('filesystems.default'));
                if (method_exists($disk, 'readStream')) {
                    $stream = $disk->readStream($zip->gcs_path);
                    if ($stream === false) throw new \Exception('readStream returned false');
                    return response()->stream(function() use ($stream) {
                        while (!feof($stream)) {
                            echo fread($stream, 8192);
                        }
                        if (is_resource($stream)) fclose($stream);
                    }, 200, [
                        'Content-Type' => 'application/zip',
                        'Content-Disposition' => 'attachment; filename="' . $downloadFilename . '"'
                    ]);
                }

                // fallback
                $contents = $disk->get($zip->gcs_path);
                return response($contents, 200)
                    ->header('Content-Type', 'application/zip')
                    ->header('Content-Disposition', 'attachment; filename="' . $downloadFilename . '"');
            } catch (\Exception $e) {
                Log::error('ZipExportController::download error', ['exception' => $e, 'zip_export_id' => $zip->id]);
                return abort(500);
            }
        }

        // Local fallback: check temp folder
        $local = storage_path('app/temp/' . ($zip->zip_filename ?: basename($zip->gcs_path ?? '')));
        if (file_exists($local)) {
            return response()->download($local, $downloadFilename, ['Content-Type' => 'application/zip']);
        }

        return abort(404);
    }
}
