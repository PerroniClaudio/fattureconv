<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\ProcessedFile;
use setasign\Fpdi\Fpdi;
use CloudConvert\CloudConvert;
use CloudConvert\Models\Job;
use CloudConvert\Models\Task;
use Exception;

class MergePdfJob implements ShouldQueue
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
            Log::warning('MergePdfJob: ProcessedFile non trovato', ['id' => $this->processedFileId]);
            return;
        }

        if (!$processedFile->word_path || !$processedFile->gcs_path) {
            Log::warning('MergePdfJob: Word o PDF originale mancante', ['id' => $this->processedFileId]);
            return;
        }

        $currentStep = 'started';

        try {
            $disk = Storage::disk('gcs');

            // 1) Scarica il file Word da GCS (in memoria)
            $currentStep = 'downloading_word';
            $wordContent = $disk->get($processedFile->word_path);

            // 2) Converti Word in PDF usando CloudConvert
            $currentStep = 'converting_word_to_pdf';
            $pdfFromWordContent = $this->convertWordToPdfViaCloudConvert($wordContent, basename($processedFile->word_path));

            // 3) Scarica il PDF originale (in memoria)
            $currentStep = 'downloading_original_pdf';
            $originalPdfContent = $disk->get($processedFile->gcs_path);

            // 4) Unisci i PDF in memoria (PDF dal Word prima, poi originale)
            $currentStep = 'merging_pdfs';
            $mergedPdfContent = $this->mergePdfsInMemory([$pdfFromWordContent, $originalPdfContent]);

            // 5) Carica il PDF unito su GCS
            $currentStep = 'uploading_merged_pdf';
            $originalPdfName = basename($processedFile->gcs_path);
            $gcsMergedPath = 'merged-pdf/' . $this->processedFileId . '/' . $originalPdfName;
            $disk->put($gcsMergedPath, $mergedPdfContent);

            // 6) Aggiorna il modello
            $processedFile->merged_pdf_path = $gcsMergedPath;
            $processedFile->status = 'merged';
            $processedFile->save();

        } catch (Exception $e) {
            Log::error('MergePdfJob failed', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'processed_file_id' => $this->processedFileId,
                'step' => $currentStep
            ]);
            
            try {
                $processedFile->status = 'merge_error';
                $processedFile->error_message = '[' . $currentStep . '] ' . substr($e->getMessage(), 0, 1000);
                $processedFile->save();
            } catch (Exception $inner) {
                Log::error('MergePdfJob failed to update ProcessedFile after exception', ['exception' => $inner->getMessage()]);
            }
            return;
        }
    }

    /**
     * Converte DOCX in PDF usando CloudConvert API.
     */
    private function convertWordToPdfViaCloudConvert(string $wordContent, string $fileName): string
    {
        $jobId = null;
        
        try {
            // Inizializza CloudConvert
            $cloudconvert = new CloudConvert([
                'api_key' => config('services.cloudconvert.api_key'),
                'sandbox' => config('services.cloudconvert.sandbox', false)
            ]);
            
            Log::info('CloudConvert: Inizio conversione', ['filename' => $fileName]);
            
            // Crea il job di conversione
            $job = (new Job())
                ->addTask(
                    new Task('import/upload', 'upload-word-file')
                )
                ->addTask(
                    (new Task('convert', 'convert-to-pdf'))
                        ->set('input', 'upload-word-file')
                        ->set('output_format', 'pdf')
                        ->set('engine', 'office')
                        ->set('optimize_print', true)
                )
                ->addTask(
                    (new Task('export/url', 'export-pdf'))
                        ->set('input', 'convert-to-pdf')
                );
            
            // Crea il job su CloudConvert
            $job = $cloudconvert->jobs()->create($job);
            $jobId = $job->getId();
            
            Log::info('CloudConvert: Job creato', ['job_id' => $jobId]);
            
            // Trova il task di upload
            $uploadTask = $job->getTasks()->whereName('upload-word-file')[0];
            
            // Crea uno stream dal contenuto del file
            $stream = fopen('php://temp', 'r+');
            if (!$stream) {
                throw new Exception('Impossibile creare stream temporaneo per upload');
            }
            
            fwrite($stream, $wordContent);
            rewind($stream);
            
            try {
                // Upload del file
                Log::info('CloudConvert: Upload file in corso', ['job_id' => $jobId]);
                $cloudconvert->tasks()->upload($uploadTask, $stream, $fileName);
                
                // Chiudi lo stream solo se è ancora valido
                if (is_resource($stream)) {
                    fclose($stream);
                }
            } catch (Exception $e) {
                // Assicurati di chiudere lo stream in caso di errore
                if (is_resource($stream)) {
                    fclose($stream);
                }
                throw $e;
            }
            
            // Attendi il completamento del job
            Log::info('CloudConvert: Attesa completamento conversione', ['job_id' => $jobId]);
            $job = $cloudconvert->jobs()->wait($job);
            
            // Verifica lo status
            if ($job->getStatus() !== 'finished') {
                throw new Exception('CloudConvert job failed with status: ' . $job->getStatus());
            }
            
            Log::info('CloudConvert: Conversione completata', ['job_id' => $jobId]);
            
            // Trova il task di export
            $exportTask = $job->getTasks()->whereName('export-pdf')[0];
            $result = $exportTask->getResult();
            
            if (!isset($result->files) || count($result->files) === 0) {
                throw new Exception('CloudConvert: Nessun file PDF generato');
            }
            
            $file = $result->files[0];
            
            // Download del PDF convertito
            Log::info('CloudConvert: Download PDF', ['url' => $file->url]);
            $pdfContent = file_get_contents($file->url);
            
            if (!$pdfContent) {
                throw new Exception('CloudConvert: Impossibile scaricare il PDF convertito');
            }
            
            Log::info('CloudConvert: Conversione completata con successo', [
                'job_id' => $jobId,
                'pdf_size' => strlen($pdfContent)
            ]);
            
            return $pdfContent;
            
        } catch (Exception $e) {
            Log::error('CloudConvert: Errore durante la conversione', [
                'job_id' => $jobId,
                'filename' => $fileName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new Exception("Errore conversione Word->PDF con CloudConvert: " . $e->getMessage());
        }
    }

    /**
     * Unisce più PDF in memoria usando FPDI.
     */
    private function mergePdfsInMemory(array $pdfContents): string
    {
        $pdf = new Fpdi();
        $tempFiles = []; // Per tracciare i file temporanei da eliminare

        try {
            foreach ($pdfContents as $index => $pdfContent) {
                // Crea un file temporaneo su disco
                $tempPath = tempnam(sys_get_temp_dir(), 'pdf_merge_');
                file_put_contents($tempPath, $pdfContent);
                $tempFiles[] = $tempPath; // Traccia per pulizia

                Log::info('MergePDF: Processing PDF', [
                    'index' => $index,
                    'size' => strlen($pdfContent),
                    'temp_file' => $tempPath
                ]);

                $pageCount = $pdf->setSourceFile($tempPath);
                
                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $templateId = $pdf->importPage($pageNo);
                    $size = $pdf->getTemplateSize($templateId);
                    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $pdf->useTemplate($templateId);
                }
            }

            // Output in memoria
            $result = $pdf->Output('', 'S');

            Log::info('MergePDF: Merge completato', ['merged_size' => strlen($result)]);

            // Pulizia dei file temporanei
            foreach ($tempFiles as $tempFile) {
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }

            return $result;

        } catch (Exception $e) {
            // Pulizia in caso di errore
            foreach ($tempFiles as $tempFile) {
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }
            
            Log::error('MergePDF: Errore durante il merge', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
}