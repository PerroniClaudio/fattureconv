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
        // Validazione del file: obbligatorio, deve essere PDF, max 10MB
        $request->validate([
            'pdf' => 'required|file|mimes:pdf|max:10240',
        ]);

        $file = $request->file('pdf');
        $originalName = $file->getClientOriginalName();
        $filename = Str::uuid()->toString() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);

        // Creiamo il record nel DB con status 'pending'
        $processed = ProcessedFile::create([
            'original_filename' => $originalName,
            'status' => 'pending',
        ]);

        try {
            // Carichiamo sul disco GCS configurato (assumiamo disco 'gcs' esistente)
            $disk = Storage::disk('gcs');
            $objectPath = 'uploads/' . $filename;

            // putFileAs accetta l'UploadedFile direttamente
            $disk->putFileAs('uploads', $file, $filename);

            // Aggiorniamo il record con il path (object name) nel bucket
            $processed->update([
                'gcs_path' => $objectPath,
                'status' => 'uploaded',
            ]);

            // Avvia il job di generazione fattura in coda
            try {
                GenerateFattura::dispatch($processed->id);
            } catch (\Exception $e) {
                Log::error('UploadController::failed to dispatch GenerateFattura', ['exception' => $e, 'processed_id' => $processed->id]);
                // Non blocchiamo l'utente per il dispatch fallito; segnaliamo nel record
                $processed->update(['status' => 'upload_postprocess_failed', 'error_message' => substr($e->getMessage(), 0, 1000)]);
            }

            // Flash message e redirect indietro
            return redirect()->back()->with('status_message', "File caricato con successo: {$originalName}");
        } catch (\Exception $e) {
            // Salviamo l'errore nel record e ritorniamo errore
            $processed->update([
                'status' => 'error',
                'error_message' => $e->getMessage(),
            ]);

            // Log dell'eccezione per debug
            Log::error('UploadController::uploadLocal error', ['exception' => $e]);

            return redirect()->back()->with('error_message', 'Upload fallito: ' . $e->getMessage());
        }
    }
}
