<?php

namespace App\Http\Controllers;

use App\Models\ProcessedFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArchiveController extends Controller
{
    /**
     * Restituisce l'elenco dei file processati per anno e mese.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $this->validatePeriod($request);

        $files = ProcessedFile::query()
            ->whereYear('created_at', $data['year'])
            ->whereMonth('created_at', $data['month'])
            ->orderByDesc('created_at')
            ->get([
                'id',
                'original_filename',
                'created_at',
                'word_path',
                'merged_pdf_path',
                'status',
            ]);

        return response()->json([
            'data' => $this->mapFiles($files),
        ]);
    }

    /**
     * Ricerca nei file archiviati con limitazione a anno e mese.
     */
    public function search(Request $request): JsonResponse
    {
        $data = $this->validatePeriod($request);
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:255'],
        ]);

        $results = ProcessedFile::search($validated['query'])
            ->query(function ($query) use ($data) {
                $query
                    ->whereYear('created_at', $data['year'])
                    ->whereMonth('created_at', $data['month']);
            })
            ->take(100)
            ->get();

        return response()->json([
            'data' => $this->mapFiles($results),
        ]);
    }

    /**
     * Valida l'anno e il mese presenti nella richiesta.
     */
    private function validatePeriod(Request $request): array
    {
        $currentYear = (int) now()->year + 1;

        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', "max:{$currentYear}"],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        return $validated;
    }

    /**
     * Modella la risposta JSON per l'archivio.
     */
    private function mapFiles($files): array
    {
        return $files->map(function (ProcessedFile $file) {
            return [
                'id' => $file->id,
                'name' => $file->original_filename,
                'created_at' => optional($file->created_at)->toIso8601String(),
                'status' => $file->status,
                'error_message' => $file->error_message,
                'word_available' => !empty($file->word_path),
                'pdf_available' => !empty($file->merged_pdf_path),
                'word_url' => $file->word_path
                    ? route('processed-files.download', $file->id)
                    : null,
                'pdf_url' => $file->merged_pdf_path
                    ? route('processed-files.download-merged', $file->id)
                    : null,
            ];
        })->values()->all();
    }
}
