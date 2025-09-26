<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\ProcessedFile;
use App\Http\Controllers\PdfParserController;
use App\Http\Controllers\GoogleCloudController;
use App\Http\Controllers\DocumentController;
use Exception;

class GenerateFattura implements ShouldQueue
{
    use Queueable;

    /**
     * ID del ProcessedFile da processare
     * @var int
     */
    protected int $processedFileId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $processedFileId)
    {
        $this->processedFileId = $processedFileId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Recupera il ProcessedFile
        $processedFile = ProcessedFile::find($this->processedFileId);
        if (!$processedFile) {
            Log::warning('GenerateFattura: ProcessedFile non trovato', ['id' => $this->processedFileId]);
            return;
        }

        try {
            // 1) Estrai testo dal PDF e aggiorna il modello
            $pdfParser = new PdfParserController();
            $text = $pdfParser->extractAndOptimize($processedFile);

            // 2) Chiama Vertex AI per estrarre i dati strutturati
            $google = new GoogleCloudController();
            $response = $google->callVertexAI($text);

            // Salva risposta strutturata e testo
            $processedFile->structured_json = $response;
            $processedFile->extracted_text = $text;
            $processedFile->status = 'processed';
            $processedFile->save();

            // 3) Genera file Word localmente usando DocumentController
            $docController = new DocumentController();
            $localWordPath = $docController->generateFromTemplate($processedFile);

            // 4) Carica il file Word su bucket GCS e salva il path nel modello
            if (file_exists($localWordPath)) {
                $disk = Storage::disk('gcs');
                $gcsPath = 'processed_words/' . basename($localWordPath);

                // Apri stream e carica
                $stream = fopen($localWordPath, 'r');
                if ($stream === false) {
                    throw new Exception('Impossibile aprire il file Word per l upload: ' . $localWordPath);
                }

                $disk->put($gcsPath, $stream);
                if (is_resource($stream)) fclose($stream);

                // Aggiorniamo il modello con il percorso nel bucket
                $processedFile->word_path = $gcsPath;
                $processedFile->save();

                // Rimuoviamo il file locale se presente
                @unlink($localWordPath);
            } else {
                Log::warning('GenerateFattura: file Word generato non trovato', ['path' => $localWordPath]);
            }

        } catch (Exception $e) {
            Log::error('GenerateFattura failed', ['exception' => $e, 'processed_file_id' => $this->processedFileId]);
            // Aggiorna lo stato dell'entità in caso di errore
            try {
                $processedFile->status = 'error';
                $processedFile->error_message = substr($e->getMessage(), 0, 1000);
                $processedFile->save();
            } catch (Exception $inner) {
                Log::error('GenerateFattura failed to update ProcessedFile after exception', ['exception' => $inner]);
            }
            // Rilanciare non necessario, la job può terminare qui e la coda gestirà i tentativi
            return;
        }
    }
}
