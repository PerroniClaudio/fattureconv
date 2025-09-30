<?php

namespace App\Jobs;

use App\Models\ProcessedFile;
use App\Models\ZipExport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use ZipArchive;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class GenerateZipFile implements ShouldQueue
{
    use Queueable;

    protected $zipExport;

    /**
     * Create a new job instance.
     */
    public function __construct(ZipExport $zipExport)
    {
        $this->zipExport = $zipExport;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $disk = Storage::disk('gcs');
            
            // 1. Crea una cartella temporanea
            $this->zipExport->update(['status' => 'creating_folder']);
            
            $tempDir = storage_path('app/temp/' . Str::uuid());
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // 2. Recupera i file ProcessedFile con created_at tra start_date e end_date e status 'completed' e li copia nella cartella temporanea
            $this->zipExport->update(['status' => 'downloading_files']);

            $files_to_zip = ProcessedFile::where('created_at', '>=', $this->zipExport->start_date)
                ->where('created_at', '<', Carbon::parse($this->zipExport->end_date)->addDay())
                ->where('status', 'completed')
                ->get();

            // Scarica e copia i file nella cartella temporanea
            foreach ($files_to_zip as $processedFile) {
                if ($processedFile->word_path) {
                    $fileName = basename($processedFile->original_filename);
                    $localPath = $tempDir . '/' . $fileName;
                    
                    // Scarica il file da GCS
                    $fileContent = $disk->get($processedFile->word_path);
                    file_put_contents($localPath, $fileContent);
                }
            }

            // 3. Crea un file zip con i file nella cartella temporanea
            $this->zipExport->update(['status' => 'creating_zip']);
            
            $zipFileName = 'export_' . $this->zipExport->id . '_' . date('Y-m-d_H-i-s') . '.zip';
            $zipPath = $tempDir . '/' . $zipFileName;
            
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
                throw new \Exception('Impossibile creare il file ZIP');
            }
            
            // Aggiungi tutti i file al ZIP
            $files = glob($tempDir . '/*');
            foreach ($files as $file) {
                if (is_file($file) && $file !== $zipPath) {
                    $zip->addFile($file, basename($file));
                }
            }
            $zip->close();

            // 4. Carica il file zip su GCS
            $this->zipExport->update(['status' => 'uploading']);
            
            $gcsPath = 'zip-exports/' . $zipFileName;
            $zipContent = file_get_contents($zipPath);
            $disk->put($gcsPath, $zipContent);

            // 5. Elimina i file temporanei
            $this->zipExport->update(['status' => 'cleaning_up']);
            
            // Elimina tutti i file nella cartella temporanea
            $files = glob($tempDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($tempDir);

            // 6. Aggiorna lo stato del record ZipExport a 'completed' e salva il percorso GCS
            $this->zipExport->update([
                'status' => 'completed', 
                'gcs_path' => $gcsPath,
                'completed_at' => now()
            ]);

            Log::info("ZIP export completato con successo", [
                'zip_export_id' => $this->zipExport->id,
                'gcs_path' => $gcsPath,
                'files_count' => $files_to_zip->count()
            ]);

        } catch (\Exception $e) {
            // In caso di errore, aggiorna lo stato e registra l'errore
            $this->zipExport->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);
            
            Log::error("Errore durante la creazione del ZIP export", [
                'zip_export_id' => $this->zipExport->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Pulizia in caso di errore
            if (isset($tempDir) && file_exists($tempDir)) {
                $files = glob($tempDir . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                rmdir($tempDir);
            }
            
            throw $e;
        }
    }
}