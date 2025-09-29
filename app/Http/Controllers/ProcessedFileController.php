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
        $files = ProcessedFile::orderBy('created_at', 'desc')->limit(50)->get();
        return response()->json($files);
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
     * Scarica il file Word dal bucket (se presente) oppure dal storage locale
     */
    public function download($id)
    {
        $pf = ProcessedFile::find($id);
        if (!$pf) return abort(404);

        if (!empty($pf->word_path)) {
            try {
                $disk = Storage::disk('gcs');
                // prefer readStream
                if (method_exists($disk, 'readStream')) {
                    $stream = $disk->readStream($pf->word_path);
                    if ($stream === false) throw new \Exception('readStream returned false');
                    $basename = basename($pf->word_path);
                    return response()->stream(function() use ($stream) {
                        while (!feof($stream)) {
                            echo fread($stream, 8192);
                        }
                        if (is_resource($stream)) fclose($stream);
                    }, 200, [
                        'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'Content-Disposition' => 'attachment; filename="' . basename($pf->word_path) . '"'
                    ]);
                }

                // fallback: get and response
                $contents = $disk->get($pf->word_path);
                $basename = basename($pf->word_path);
                return response($contents, 200)
                    ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
                    ->header('Content-Disposition', 'attachment; filename="' . $basename . '"');
            } catch (\Exception $e) {
                Log::error('ProcessedFileController::download error', ['exception' => $e, 'id' => $id]);
                return abort(500);
            }
        }

        // fallback: se file locale in storage/app/processed_words
        $local = storage_path('app/processed_words/' . basename($pf->original_filename, '.pdf') . '.docx');
        if (file_exists($local)) {
            return response()->download($local);
        }

        return abort(404);
    }
}
