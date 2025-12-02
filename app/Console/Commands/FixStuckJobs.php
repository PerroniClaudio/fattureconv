<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ProcessedFile;
use App\Models\ZipExport;
use Illuminate\Support\Facades\Log;

class FixStuckJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fix-stuck-jobs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix jobs that are stuck in intermediate states but have error messages';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for stuck ProcessedFile jobs...');

        // Find ProcessedFiles that have an error message but are not in a failed state
        // Assuming 'failed', 'error', 'merge_error' are the final error states
        // And 'completed', 'merged' are final success states
        // We look for anything else that has an error_message
        $stuckFiles = ProcessedFile::whereNotNull('error_message')
            ->where('error_message', '!=', '')
            ->whereNotIn('status', ['failed', 'error', 'merge_error', 'completed', 'merged'])
            ->get();

        $count = $stuckFiles->count();
        if ($count > 0) {
            $this->info("Found {$count} stuck ProcessedFiles.");
            foreach ($stuckFiles as $file) {
                $this->warn("Fixing ProcessedFile ID {$file->id} (Status: {$file->status})");
                $file->status = 'failed';
                $file->save();
                Log::warning("FixStuckJobs: ProcessedFile ID {$file->id} marked as failed. Original status: {$file->status}. Error: {$file->error_message}");
            }
        } else {
            $this->info('No stuck ProcessedFiles found.');
        }

        $this->info('Checking for stuck ZipExport jobs...');
        
        $stuckZips = ZipExport::whereNotNull('error_message')
            ->where('error_message', '!=', '')
            ->whereNotIn('status', ['failed', 'error', 'completed'])
            ->get();

        $zipCount = $stuckZips->count();
        if ($zipCount > 0) {
            $this->info("Found {$zipCount} stuck ZipExports.");
            foreach ($stuckZips as $zip) {
                $this->warn("Fixing ZipExport ID {$zip->id} (Status: {$zip->status})");
                $zip->status = 'failed';
                $zip->save();
                Log::warning("FixStuckJobs: ZipExport ID {$zip->id} marked as failed. Original status: {$zip->status}. Error: {$zip->error_message}");
            }
        } else {
            $this->info('No stuck ZipExports found.');
        }
    }
}
