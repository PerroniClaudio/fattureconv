<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

/**
 * Modello Eloquent per la tabella processed_files.
 * Rappresenta un file PDF elaborato e i dati estratti.
 */
class ProcessedFile extends Model
{
    use HasFactory, SoftDeletes, Searchable;

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
        'merged_pdf_path',
        'status',
        'error_message',
        'month_reference',
    ];

    /**
     * Casts per gli attributi speciali.
     */
    protected $casts = [
        'structured_json' => 'array',
        'month_reference' => 'date',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function (ProcessedFile $file) {
            if (empty($file->month_reference)) {
                $file->month_reference = now()->subMonth()->startOfMonth();
            }
        });
    }

    /**
     * Define the searchable data for Laravel Scout.
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'original_filename' => $this->original_filename,
            'gcs_path' => $this->gcs_path,
            'extracted_text' => $this->extracted_text,
        ];
    }
}
