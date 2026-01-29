<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpWord\TemplateProcessor;
use App\Models\ProcessedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class DocumentController extends Controller
{
    /**
     * Crea un documento Word (.docx) a partire da contenuti strutturati.
     * Parametro: array $contents con titoli, paragrafi e tabelle.
     * Restituisce il percorso del file creato.
     */
    public function createWord(array $contents): string
    {
        // TODO: usare PhpOffice\PhpWord per creare il docx e salvarlo
        throw new \Exception('Non implementato: createWord');
    }

    /**
     * Genera un file Word a partire dal template nello storage di default e dai dati
     * estratti dall'AI salvati nel ProcessedFile.
     *
     * @param ProcessedFile $processedFile
     * @return string percorso del file creato (storage path)
     */
    public function generateFromTemplate(ProcessedFile $processedFile): string
    {
        // Percorsi
        $templatePath = 'templates/ift_template_fatture.docx';
        $disk = Storage::disk(config('filesystems.default'));

        // Copia template in locale dal disco configurato
        $tmpDir = storage_path('app/processed_words');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $tmpTemplate = $tmpDir . DIRECTORY_SEPARATOR . 'template_' . Str::uuid()->toString() . '.docx';

        try {
            if (!$disk->exists($templatePath)) {
                throw new \Exception('Template non trovato nello storage configurato.');
            }
            if (method_exists($disk, 'readStream')) {
                $stream = $disk->readStream($templatePath);
                if ($stream && is_resource($stream)) {
                    $out = fopen($tmpTemplate, 'w');
                    while (!feof($stream)) {
                        $chunk = fread($stream, 8192);
                        if ($chunk === false) break;
                        fwrite($out, $chunk);
                    }
                    fclose($out);
                    fclose($stream);
                } else {
                    $contents = $disk->get($templatePath);
                    file_put_contents($tmpTemplate, $contents);
                }
            } else {
                $contents = $disk->get($templatePath);
                file_put_contents($tmpTemplate, $contents);
            }

            // Prepara i dati AI
            $data = $processedFile->structured_json ?: [];
            // se structured_json è una stringa JSON, prova a decodificarla
            if (is_string($data)) {
                $decoded = json_decode($data, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data = $decoded;
                }
            }

            // Assicuriamoci di avere l'array $ai con i campi principali
            if (is_array($data) && array_key_exists('account_holder', $data)) {
                $ai = $data;
            } elseif (is_array($data) && isset($data[0]) && is_array($data[0]) && array_key_exists('account_holder', $data[0])) {
                // Se il modello restituisce un array di fatture (preferito), usa la prima
                $ai = $data[0];
            } elseif (is_array($data) && isset($data[0]) && is_array($data[0]) && array_key_exists('fornitore', $data[0])) {
                $ai = $data[0];
            } else {
                $ai = is_array($data) ? $data : [];
            }

            // Prepariamo la tabella items: se presente, inseriamo WordML con placeholder per cloneRow
            $items = $ai['items'] ?? [];

            // Carica template con TemplateProcessor
            $tp = new TemplateProcessor($tmpTemplate);
            $iva_italia_22 = isset($ai['importo_netto']) && is_numeric($ai['importo_netto']) ? round($ai['importo_netto'] * 0.22, 2) : 0;

            // mapping placeholders -> ai fields (senza ${})
            // Helper per formattare numeri come valuta con la virgola decimale
            $fmtMoney = function($value) {
                if (!is_numeric($value)) return $value;
                return number_format($value, 2, ',', '');
            };

            $map = [
                'account_holder'   => $ai['account_holder'] ?? $ai['fornitore'] ?? 'N/A',
                'address'          => $ai['address'] ?? $ai['indirizzo'] ?? 'N/A',
                'vat_number'       => $ai['vat_number'] ?? $ai['piva'] ?? 'N/A',
                'data_emissione'   => $this->formatDate($ai['date'] ?? ($ai['data_emissione'] ?? '')),
                'data_fattura'     => $this->formatDate($ai['data_emissione'] ?? ($ai['date'] ?? '')),
                'numero_fattura'   => $ai['numero_fattura'] ?? ($ai['invoice_number'] ?? 'N/A'),
                'importo_netto'    => $fmtMoney($ai['importo_netto'] ?? ($ai['amount_net'] ?? 'N/A')) . (isset($ai['valuta']) ? ' ' . $ai['valuta'] : ''),
                'iva_percentuale'  => $ai['iva_percentuale'] ?? ($ai['vat_percent'] ?? 'N/A'),
                'iva_importo'      => $fmtMoney($ai['iva_importo'] ?? ($ai['importo_iva'] ?? 'N/A')) . (isset($ai['valuta']) ? ' ' . $ai['valuta'] : ''),
                'totale_dovuto'    => $ai['totale_dovuto'] ?? ($ai['total_due'] ?? ($ai['totale'] ?? 'N/A')),
                'iva_italia_22'    => $fmtMoney($iva_italia_22) . (isset($ai['valuta']) ? ' ' . $ai['valuta'] : ''),
                'totale_imponibile_italia' => $fmtMoney(
                    ((isset($ai['importo_netto']) && is_numeric($ai['importo_netto']) ? $ai['importo_netto'] : 0)
                    + (is_numeric($iva_italia_22) ? $iva_italia_22 : 0))
                ) . (isset($ai['valuta']) ? ' ' . $ai['valuta'] : ''),
            ];

            foreach ($map as $key => $value) {
                $tp->setValue($key, $this->escapeForWord($value));
            }
            
            // Se ci sono items, costruisci una tabella valida e sostituisci il placeholder con setComplexBlock
            if (!empty($items) && is_array($items)) {
                $normalized = [];
                foreach ($items as $it) {
                    $descr = $it['descrizione'] ?? $it['description'] ?? $it['desc'] ?? '';
                    $impon = $it['imponibile'] ?? $it['imponibile_totale'] ?? $it['amount'] ?? $it['impon'] ?? '';
                    $ivaField = $it['%iva o art. esenzione'] ?? $it['iva_percentuale'] ?? $it['vat'] ?? $it['vat_percent'] ?? '';
                    $impIva = $it['importo_iva'] ?? $it['iva_importo'] ?? $it['vat_amount'] ?? $it['tax_amount'] ?? '';

                    $descr = is_string($descr) ? trim($descr) : $descr;
                    $impon = is_scalar($impon) ? trim((string)$impon) : $impon;
                    $ivaField = is_scalar($ivaField) ? trim((string)$ivaField) : $ivaField;
                    $impIva = is_scalar($impIva) ? trim((string)$impIva) : $impIva;

                    // format numeri se possibile, aggiungendo la valuta se presente
                    $currencySuffix = isset($ai['valuta']) ? ' ' . $ai['valuta'] : '';
                    if (is_numeric($impon)) {
                        $impon = $fmtMoney($impon) . $currencySuffix;
                    }
                    if (is_numeric($impIva)) {
                        $impIva = $fmtMoney($impIva) . $currencySuffix;
                    }

                    $normalized[] = [
                        'descrizione' => $descr,
                        'imponibile' => $impon,
                        'iva' => $ivaField,
                        'importo_iva' => $impIva,
                    ];
                }

                try {
                    $table = new \PhpOffice\PhpWord\Element\Table();
                    // intestazione
                    $table->addRow();
                    $table->addCell()->addText('Descrizione');
                    $table->addCell()->addText('Imponibile');
                    $table->addCell()->addText('% IVA / Esenzione');
                    $table->addCell()->addText('Importo IVA');

                    foreach ($normalized as $it) {
                        $table->addRow();
                        $table->addCell()->addText($this->escapeForWord((string)$it['descrizione']));
                        $table->addCell()->addText($this->escapeForWord((string)$it['imponibile']));
                        $table->addCell()->addText($this->escapeForWord((string)$it['iva']));
                        $table->addCell()->addText($this->escapeForWord((string)$it['importo_iva']));
                    }

                    $tp->setComplexBlock('tabella_items', $table);
                } catch (\Exception $e) {
                    Log::error('TemplateProcessor setComplexBlock failed', ['exception' => $e]);
                    $tp->setValue('tabella_items', '');
                }
            } else {
                // nessun item: svuota il placeholder per evitare residui
                $tp->setValue('tabella_items', '');
            }

            // salva file risultante
            $outPath = $tmpDir . DIRECTORY_SEPARATOR . 'invoice_' . Str::uuid()->toString() . '.docx';
            $tp->saveAs($outPath);

            // carica su disco locale o su GCS a seconda del caso; qui salviamo il percorso locale
            $processedFile->word_path = 'processed_words/' . basename($outPath);
            // assicurati che la directory storage/app/processed_words esista
            if (!is_dir(storage_path('app/processed_words'))) {
                mkdir(storage_path('app/processed_words'), 0755, true);
            }
            // sposta il file nella cartella storage/app/processed_words
            rename($outPath, storage_path('app/processed_words/' . basename($outPath)));

            $processedFile->save();

            // pulizia template temporaneo
            if (file_exists($tmpTemplate)) @unlink($tmpTemplate);

            return storage_path('app/processed_words/' . basename($outPath));
        } catch (\Exception $e) {
            Log::error('DocumentController::generateFromTemplate error', ['exception' => $e]);
            throw $e;
        }
    }

    /**
     * Carica il template .docx nello storage locale.
     * Richiesta: field "template" (multipart/form-data)
     */
    public function uploadTemplate(Request $request)
    {
        $request->validate([
            'template' => 'required|file|mimes:docx|max:5120',
        ]);

        $file = $request->file('template');
        if (!$file || !$file->isValid()) {
            return response()->json(['error' => 'File non valido'], 422);
        }

        $disk = Storage::disk(config('filesystems.default'));
        $targetPath = 'templates/ift_template_fatture.docx';
        $disk->putFileAs('templates', $file, 'ift_template_fatture.docx');

        return response()->json([
            'ok' => true,
            'path' => $targetPath,
        ], 200);
    }


    /**
     * Format date string to dd/mm/YYYY or return empty string on failure
     *
     * @param string|null $date
     * @return string
     */
    private function formatDate(?string $date): string
    {
        if (empty($date)) return '';
        try {
            $dt = Carbon::parse($date);
            return $dt->format('d/m/Y');
        } catch (\Exception $e) {
            Log::warning('formatDate failed to parse date', ['date' => $date, 'exception' => $e]);
            return '';
        }
    }

    /**
     * Escape di sicurezza per valori inseriti in Word (TemplateProcessor e tabelle).
     */
    private function escapeForWord($value): string
    {
        if (is_null($value)) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_scalar($value)) {
            return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1);
        }
        // fallback per array/oggetti
        return htmlspecialchars(json_encode($value, JSON_UNESCAPED_UNICODE), ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1);
    }

}
