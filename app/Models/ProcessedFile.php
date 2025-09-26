<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Modello Eloquent per la tabella processed_files.
 * Rappresenta un file PDF elaborato e i dati estratti.
 */
class ProcessedFile extends Model
{
    use HasFactory;

    protected $table = 'processed_files';

    /**
     * Attributi assegnabili in massa.
     */
    protected $fillable = [
        'original_filename',
        'gcs_path',
        'extracted_text',
        'structured_json',
        'word_path',
        'status',
        'error_message',
    ];

    /**
     * Casts per gli attributi speciali.
     */
    protected $casts = [
        'structured_json' => 'array',
    ];
}
