<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Smalot\PdfParser\Parser as PdfParser;
use Illuminate\Support\Facades\Storage;
use App\Models\ProcessedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class PdfParserController extends Controller
{
    /**
     * Estrae il testo da un file PDF locale e lo ottimizza per l'elaborazione successiva.
     * Parametro: percorso locale del PDF.
     * Restituisce una stringa con il testo ottimizzato.
     */
    public function extractAndOptimize(ProcessedFile $processedFile): string
    {
        // Otteniamo il path sul filesystem dal modello
        $gcsPath = $processedFile->gcs_path;
        if (empty($gcsPath)) {
            throw new \InvalidArgumentException('ProcessedFile senza gcs_path');
        }

        // Scarichiamo temporaneamente il file dal disco di default
        $disk = Storage::disk(config('filesystems.default'));
        $tmpDir = storage_path('app/temp_pdfs');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $tmpFilename = Str::uuid()->toString() . '_' . basename($gcsPath);
        $localPath = $tmpDir . DIRECTORY_SEPARATOR . $tmpFilename;

        try {
            // get / putStream depending on adapter; usare get to retrieve contents
            $contents = $disk->get($gcsPath);
            file_put_contents($localPath, $contents);

            // Parse PDF
            $parser = new PdfParser();
            $pdf = $parser->parseFile($localPath);

            // Estraiamo testo per pagina e normalizziamo
            $text = '';
            $pages = $pdf->getPages();
            foreach ($pages as $p) {
                $pageText = $p->getText();
                // Normalizzazione: rimuove spazi multipli, caratteri non stampabili
                $pageText = preg_replace('/\s+/u', ' ', $pageText);
                $pageText = trim($pageText);
                $text .= $pageText . "\n\n";
            }

            // Ulteriore pulizia: rimuovere caratteri di controllo
            $text = preg_replace('/[\x00-\x1F\x7F]+/u', '', $text);

            // Creiamo una struttura JSON minima (esempio: pagine => array testi)
            $structured = [];
            $i = 1;
            foreach ($pages as $p) {
                $pageText = preg_replace('/\s+/u', ' ', trim($p->getText()));
                $structured[] = ['page' => $i++, 'text' => $pageText];
            }

            // Salviamo i risultati nel modello
            $processedFile->update([
                'extracted_text' => $text,
                'structured_json' => $structured,
            ]);

            return $text;
        } catch (\Exception $e) {
            Log::error('PdfParserController::extractAndOptimize error', ['exception' => $e, 'gcs_path' => $gcsPath]);
            $processedFile->update([
                'status' => 'error',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            // Pulizia del file temporaneo
            if (isset($localPath) && file_exists($localPath)) {
                @unlink($localPath);
            }
        }
    }
}
