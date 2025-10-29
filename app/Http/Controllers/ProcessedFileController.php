<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProcessedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProcessedFileController extends Controller
{
    /**
     * Restituisce gli ultimi ProcessedFile in formato JSON
     */
    public function index(Request $request)
    {
        // Support client-side pagination and optional status filter
        $status = $request->query('status');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(200, (int) $request->query('per_page', 10)));

        // I file con soft delete vengono automaticamente esclusi dal trait SoftDeletes
        $query = ProcessedFile::query();
        if ($status) {
            if ($status === 'completed') {
                $query->whereIn('status', ['completed', 'merged']);
            } else {
                $query->where('status', $status);
            }
        }

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
            ],
        ]);
    }

    /**
     * Return statuses for a set of IDs. Expects JSON body: { ids: [1,2,3] }
     */
    public function statuses(Request $request)
    {
        $ids = $request->input('ids', []);
        if (!is_array($ids) || empty($ids)) {
            return response()->json([], 200);
        }

        // I file con soft delete vengono automaticamente esclusi dal trait SoftDeletes
        $files = ProcessedFile::whereIn('id', $ids)->get(['id','status','word_path','error_message','structured_json','extracted_text','original_filename','gcs_path','created_at']);

        // return as object keyed by id for easier client updates
        $payload = [];
        foreach ($files as $f) {
            $payload[$f->id] = [
                'id' => $f->id,
                'status' => $f->status,
                'word_path' => $f->word_path,
                'error_message' => $f->error_message,
                'structured_json' => $f->structured_json,
                'extracted_text' => $f->extracted_text,
                'original_filename' => $f->original_filename,
                'gcs_path' => $f->gcs_path,
                'created_at' => optional($f->created_at)->toDateTimeString(),
            ];
        }

        return response()->json($payload, 200);
    }

    /**
     * Return a lightweight list of in-progress files for polling.
     * Optional query param: limit (default 50)
     */
    public function inProgress(Request $request)
    {
        $limit = (int) $request->query('limit', 50);
        $inProgressStatuses = ['pending','uploaded','processing','parsing_pdf','calling_ai','generating_word','uploading_word'];

        // I file con soft delete vengono automaticamente esclusi dal trait SoftDeletes
        $files = ProcessedFile::whereIn('status', $inProgressStatuses)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get(['id','status','original_filename','gcs_path','created_at','word_path','error_message']);

        return response()->json($files, 200);
    }

    /**
     * Scarica il file Word dal bucket (se presente) oppure dal storage locale
     */
    public function download($id)
    {
        $pf = ProcessedFile::find($id);
        if (!$pf) return abort(404);
        // costruiamo il nome del file di download a partire da original_filename
        $originalName = $pf->original_filename ?: basename($pf->word_path ?? '') ?: 'document';
        // rimuoviamo l'estensione originale e forziamo .docx
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        // sanifichiamo il nome per rimuovere caratteri problematici
        $sanitizedBase = preg_replace('/[^A-Za-z0-9_\-\. ]+/', '_', $baseName);
        $downloadFilename = $sanitizedBase . '.docx';

        if (!empty($pf->word_path)) {
            try {
                $disk = Storage::disk('gcs');
                // prefer readStream
                if (method_exists($disk, 'readStream')) {
                    $stream = $disk->readStream($pf->word_path);
                    if ($stream === false) throw new \Exception('readStream returned false');
                    return response()->stream(function() use ($stream) {
                        while (!feof($stream)) {
                            echo fread($stream, 8192);
                        }
                        if (is_resource($stream)) fclose($stream);
                    }, 200, [
                        'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        // forziamo il nome del download a original_filename.docx
                        'Content-Disposition' => 'attachment; filename="' . $downloadFilename . '"'
                    ]);
                }

                // fallback: get and response
                $contents = $disk->get($pf->word_path);
                return response($contents, 200)
                    ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
                    ->header('Content-Disposition', 'attachment; filename="' . $downloadFilename . '"');
            } catch (\Exception $e) {
                Log::error('ProcessedFileController::download error', ['exception' => $e, 'id' => $id]);
                return abort(500);
            }
        }

        // fallback: se file locale in storage/app/processed_words
        $local = storage_path('app/processed_words/' . basename($pf->original_filename, '.pdf') . '.docx');
        if (file_exists($local)) {
            // response()->download permette di specificare il nome file visualizzato
            return response()->download($local, $downloadFilename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);
        }

        return abort(404);
    }

    /**
     * Elimina (soft delete) un ProcessedFile
     */
    public function destroy($id)
    {
        $processedFile = ProcessedFile::find($id);
        
        if (!$processedFile) {
            return response()->json(['error' => 'File non trovato'], 404);
        }

        try {
            $processedFile->delete();
            Log::info('ProcessedFile eliminato', ['id' => $id, 'filename' => $processedFile->original_filename]);
            
            return response()->json([
                'success' => true,
                'message' => 'File eliminato con successo'
            ]);
        } catch (\Exception $e) {
            Log::error('Errore durante eliminazione ProcessedFile', [
                'id' => $id,
                'exception' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Errore durante l\'eliminazione del file'
            ], 500);
        }
    }
}
