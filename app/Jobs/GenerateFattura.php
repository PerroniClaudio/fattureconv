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
        $currentStep = 'started';

        try {
            // 1) Estrai testo dal PDF e aggiorna il modello
            $currentStep = 'parsing_pdf';
            $processedFile->status = $currentStep;
            $processedFile->save();

            $pdfParser = new PdfParserController();
            $text = $pdfParser->extractAndOptimize($processedFile);

            // 2) Chiama Vertex AI per estrarre i dati strutturati
            $currentStep = 'calling_ai';
            $processedFile->status = $currentStep;
            $processedFile->save();

            $google = new GoogleCloudController();
            $response = $google->callVertexAI($text);

            // Salva risposta strutturata e testo
            $processedFile->structured_json = $response;
            $processedFile->extracted_text = $text;
            $processedFile->status = 'ai_completed';
            $processedFile->save();

            // 3) Genera file Word localmente usando DocumentController
            $currentStep = 'generating_word';
            $processedFile->status = $currentStep;
            $processedFile->save();

            $docController = new DocumentController();
            $localWordPath = $docController->generateFromTemplate($processedFile);

            // dopo la generazione locale
            $processedFile->status = 'word_generated';
            $processedFile->save();

            // 4) Carica il file Word su bucket GCS e salva il path nel modello
            $currentStep = 'uploading_word';
            $processedFile->status = $currentStep;
            $processedFile->save();

            if (file_exists($localWordPath)) {
                $disk = Storage::disk('gcs');
                $gcsPath = 'processed_words/' . basename($localWordPath);

                // Apri stream e carica
                $stream = fopen($localWordPath, 'r');
                if ($stream === false) {
                    throw new Exception('Impossibile aprire il file Word per l upload: ' . $localWordPath);
                }

                // preferiamo putStream se disponibile
                if (method_exists($disk, 'putStream')) {
                    call_user_func([$disk, 'putStream'], $gcsPath, $stream);
                } else {
                    $disk->put($gcsPath, $stream);
                }

                if (is_resource($stream)) fclose($stream);

                // Aggiorniamo il modello con il percorso nel bucket
                $processedFile->word_path = $gcsPath;
                $processedFile->status = 'completed';
                $processedFile->save();

                // Rimuoviamo il file locale se presente
                @unlink($localWordPath);
            } else {
                Log::warning('GenerateFattura: file Word generato non trovato', ['path' => $localWordPath]);
                $processedFile->status = 'word_missing';
                $processedFile->save();
            }

        } catch (Exception $e) {
            Log::error('GenerateFattura failed', ['exception' => $e, 'processed_file_id' => $this->processedFileId, 'step' => $currentStep]);
            // Aggiorna lo stato dell'entità in caso di errore
            try {
                $processedFile->status = 'error';
                $processedFile->error_message = '[' . $currentStep . '] ' . substr($e->getMessage(), 0, 1000);
                $processedFile->save();
            } catch (Exception $inner) {
                Log::error('GenerateFattura failed to update ProcessedFile after exception', ['exception' => $inner]);
            }
            return;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $processedFile = ProcessedFile::find($this->processedFileId);
        if ($processedFile) {
            $processedFile->status = 'failed';
            $processedFile->error_message = substr($exception->getMessage(), 0, 1000);
            $processedFile->save();
            
            Log::error('GenerateFattura job failed (failed method)', [
                'id' => $this->processedFileId,
                'error' => $exception->getMessage()
            ]);
        }
    }
}
