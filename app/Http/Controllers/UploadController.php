<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\ProcessedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Jobs\GenerateFattura;

class UploadController extends Controller
{
    /**
     * Carica un file PDF sul filesystem locale (storage/app/uploads) o su un disco configurato.
     * Richiesta: campo 'pdf' di tipo file.
     * Restituisce JSON con il percorso locale o un errore.
     */
    public function uploadLocal(Request $request)
    {
        // Support two flows:
        // 1) classic form submit with pdf[] files
        // 2) client-side FilePond uploads async that post single files to /upload/process
        //    and then submit processed_ids hidden field to this endpoint.

        if ($request->has('processed_ids')) {
            // Client has already uploaded files via FilePond; processed_ids is a comma separated list of ProcessedFile IDs
            $ids = array_filter(array_map('trim', explode(',', $request->input('processed_ids'))));
            if (empty($ids)) {
                return redirect()->back()->with('error_message', 'Nessun file processato ricevuto.');
            }

            // Optionally we could verify these IDs exist; for now just return success message
            return redirect()->back()->with('status_message', 'File caricati e in coda per l\'elaborazione: ' . implode(', ', $ids));
        }

        // Validazione: ora accettiamo un array di file PDF
        $request->validate([
            'pdf' => 'required|array|min:1',
            'pdf.*' => 'required|file|mimes:pdf|max:10240',
        ]);

        $files = $request->file('pdf');
        $disk = Storage::disk(config('filesystems.default'));

        $successes = [];
        $errors = [];

        foreach ($files as $file) {
            $originalName = $file->getClientOriginalName();
            $filename = Str::uuid()->toString() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);

            // Creiamo il record nel DB con status 'pending'
            $processed = ProcessedFile::create([
                'original_filename' => $originalName,
                'status' => 'pending',
            ]);

            try {
                $objectPath = 'uploads/' . $filename;

                // Carichiamo sul filesystem configurato (disco di default)
                $disk->putFileAs('uploads', $file, $filename);

                // Aggiorniamo il record con il path nel disco
                $processed->update([
                    'gcs_path' => $objectPath,
                    'status' => 'uploaded',
                ]);

                // Dispatch del job per ogni file
                try {
                    GenerateFattura::dispatch($processed->id);
                    $successes[] = $originalName;
                } catch (\Exception $e) {
                    Log::error('UploadController::failed to dispatch GenerateFattura', ['exception' => $e, 'processed_id' => $processed->id]);
                    $processed->update(['status' => 'upload_postprocess_failed', 'error_message' => substr($e->getMessage(), 0, 1000)]);
                    $errors[] = "{$originalName}: dispatch_failed - " . $e->getMessage();
                }
            } catch (\Exception $e) {
                // Salviamo l'errore nel record
                $processed->update([
                    'status' => 'error',
                    'error_message' => $e->getMessage(),
                ]);

                Log::error('UploadController::uploadLocal error', ['exception' => $e, 'file' => $originalName]);
                $errors[] = "{$originalName}: " . $e->getMessage();
            }
        }

        // Prepariamo i messaggi flash per l'utente
        $messages = [];
        if (!empty($successes)) {
            $messages[] = "File caricati e in coda per l'elaborazione: " . implode(', ', $successes);
        }
        if (!empty($errors)) {
            $messages[] = "Alcuni file hanno riscontrato errori: " . implode(' | ', $errors);
        }

        return redirect()->back()->with('status_message', implode(' -- ', $messages));
    }

    /**
     * Endpoint compatibile con FilePond server.process.
     * Riceve un singolo file per richiesta e ritorna l'ID del processed file come testo.
     */
    public function processFilePond(Request $request)
    {
        // Resolve uploaded file (accept several common field names or take first file present)
        $possibleKeys = ['file', 'filepond', 'files', 'upload', 'document'];
        $file = null;
        foreach ($possibleKeys as $k) {
            if ($request->hasFile($k)) {
                $file = $request->file($k);
                break;
            }
        }
        if (is_null($file)) {
            $allFiles = $request->files->all();
            if (!empty($allFiles)) {
                $first = reset($allFiles);
                $file = is_array($first) ? reset($first) : $first;
            }
        }

        if (!$file || !$file->isValid()) {
            // no file or invalid upload; return client error
            Log::warning('UploadController::processFilePond - no valid file in request', ['files' => array_keys($request->files->all())]);
            return response('no_file', 400);
        }

        // server-side validation: PDF and max size 10MB
        if ($file->getClientMimeType() !== 'application/pdf' || $file->getSize() > 10240 * 1024) {
            return response('invalid_file_type_or_size', 400);
        }

        $originalName = $file->getClientOriginalName();
        $filename = Str::uuid()->toString() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);

        // Creiamo il record nel DB con status 'pending'
        $processed = ProcessedFile::create([
            'original_filename' => $originalName,
            'status' => 'pending',
        ]);

        try {
            $disk = Storage::disk(config('filesystems.default'));
            $objectPath = 'uploads/' . $filename;

            $disk->putFileAs('uploads', $file, $filename);

            $processed->update([
                'gcs_path' => $objectPath,
                'status' => 'uploaded',
            ]);

            // Dispatch job (log only on failure)
            try {
                GenerateFattura::dispatch($processed->id);
            } catch (\Exception $e) {
                Log::error('UploadController::failed to dispatch GenerateFattura', ['exception' => $e, 'processed_id' => $processed->id]);
                $processed->update(['status' => 'upload_postprocess_failed', 'error_message' => substr($e->getMessage(), 0, 1000)]);
            }

            // return the created processed id (FilePond expects the id in the response body)
            return response((string) $processed->id, 200);
        } catch (\Exception $e) {
            Log::error('UploadController::processFilePond error', ['exception' => $e, 'file' => $originalName]);
            $processed->update(['status' => 'error', 'error_message' => $e->getMessage()]);
            return response('error', 500);
        }
    }
}
