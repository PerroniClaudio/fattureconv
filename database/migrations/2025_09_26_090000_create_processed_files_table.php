<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Crea la tabella `processed_files` per memorizzare i file elaborati e i dati estratti.
     * campi principali:
     * - original_filename: nome file caricato
     * - gcs_path: percorso nel bucket GCS (es. gs://... o object name)
     * - extracted_text: testo estratto dal PDF (testo raw)
     * - structured_json: JSON con i contenuti strutturati estratti (es. linee, campi)
     * - status: stato dell'elaborazione (pending, processing, completed, error)
     * - error_message: eventuale messaggio di errore
     */
    public function up(): void
    {
        Schema::create('processed_files', function (Blueprint $table) {
            $table->id();
            $table->string('original_filename');
            $table->string('gcs_path')->nullable();
            $table->text('extracted_text')->nullable();
            $table->json('structured_json')->nullable();
            $table->string('word_path')->nullable();
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processed_files');
    }
};
