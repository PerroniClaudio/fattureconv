<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\TemplateProcessor;
use Illuminate\Support\Facades\Storage;
use App\Models\ProcessedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
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
     * Genera un file Word a partire dal template presente in GCS e dai dati
     * estratti da Vertex AI salvati nel ProcessedFile.
     *
     * @param ProcessedFile $processedFile
     * @return string percorso del file creato (storage path)
     */
    public function generateFromTemplate(ProcessedFile $processedFile): string
    {
        // Percorsi
        $templatePathInBucket = 'templates/ift_template_fatture.docx';
        $disk = Storage::disk('gcs');

        // Scarica template in locale (usa temporaryUrl se disponibile)
        $tmpDir = storage_path('app/processed_words');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $tmpTemplate = $tmpDir . DIRECTORY_SEPARATOR . 'template_' . Str::uuid()->toString() . '.docx';

        try {
            if (method_exists($disk, 'temporaryUrl')) {
                $url = $disk->temporaryUrl($templatePathInBucket, now()->addMinutes(30));
                $resp = \Illuminate\Support\Facades\Http::get($url);
                if (!$resp->successful()) {
                    throw new \Exception('Impossibile scaricare template da GCS, status ' . $resp->status());
                }
                file_put_contents($tmpTemplate, $resp->body());
            } else {
                // fallback: getStream / get
                if (method_exists($disk, 'readStream')) {
                    $stream = $disk->readStream($templatePathInBucket);
                    if ($stream && is_resource($stream)) {
                        $out = fopen($tmpTemplate, 'w');
                        while (!feof($stream)) {
                            $chunk = fread($stream, 8192);
                            if ($chunk === false) break;
                            fwrite($out, $chunk);
                        }
                        fclose($out);
                        if (is_resource($stream)) fclose($stream);
                    } else {
                        $contents = $disk->get($templatePathInBucket);
                        file_put_contents($tmpTemplate, $contents);
                    }
                } else {
                    $contents = $disk->get($templatePathInBucket);
                    file_put_contents($tmpTemplate, $contents);
                }
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
            } elseif (is_array($data) && isset($data[0]) && is_array($data[0]) && array_key_exists('fornitore', $data[0])) {
                $ai = $data[0];
            } else {
                $ai = is_array($data) ? $data : [];
            }

            // Prepariamo la tabella items: se presente, inseriamo WordML con placeholder per cloneRow
            $items = $ai['items'] ?? [];

            // Apri il docx e sostituisci il token ${tabella_items} con una tabella WordML contenente una singola riga
            $zip = new \ZipArchive();
            if ($zip->open($tmpTemplate) === true) {
                $docXml = $zip->getFromName('word/document.xml');
                if ($docXml !== false) {
                    // la placeholder ${tabella_items} potrebbe essere spezzata in più <w:t> run; proviamo prima la sostituzione semplice
                    $tableInserted = false;
                    if (strpos($docXml, '${tabella_items}') !== false) {
                        if (empty($items) || !is_array($items)) {
                            $docXml = str_replace('${tabella_items}', '', $docXml);
                        } else {
                            $tableRow =
                                '<w:tr>' .
                                    '<w:tc><w:p><w:r><w:t>${item_descrizione}</w:t></w:r></w:p></w:tc>' .
                                    '<w:tc><w:p><w:r><w:t>${item_imponibile}</w:t></w:r></w:p></w:tc>' .
                                    '<w:tc><w:p><w:r><w:t>${item_iva}</w:t></w:r></w:p></w:tc>' .
                                    '<w:tc><w:p><w:r><w:t>${item_importo_iva}</w:t></w:r></w:p></w:tc>' .
                                '</w:tr>';

                            // aggiungi un paragrafo vuoto prima e dopo per lasciare spazio verticale
                            $emptyPara = '<w:p><w:pPr><w:spacing w:before="120" w:after="120"/></w:pPr><w:r><w:t></w:t></w:r></w:p>';

                            $tableXml =  '<w:tbl>' .
                                '<w:tblPr><w:tblW w:w="0" w:type="auto"/></w:tblPr>' .
                                '<w:tr>' .
                                    '<w:tc><w:p><w:r><w:t>Descrizione</w:t></w:r></w:p></w:tc>' .
                                    '<w:tc><w:p><w:r><w:t>Imponibile</w:t></w:r></w:p></w:tc>' .
                                    '<w:tc><w:p><w:r><w:t>% IVA / Esenzione</w:t></w:r></w:p></w:tc>' .
                                    '<w:tc><w:p><w:r><w:t>Importo IVA</w:t></w:r></w:p></w:tc>' .
                                '</w:tr>' .
                                $tableRow .
                            '</w:tbl>';

                            $docXml = str_replace('${tabella_items}', $tableXml, $docXml);
                            $tableInserted = true;
                        }
                    }

                    // Se la sostituzione diretta non ha trovato la stringa (perché è spezzata in più <w:t>), cerchiamo la sequenza di testo composta
                    if (!$tableInserted && empty($items) === false && is_array($items)) {
                        // estrai tutti i testi w:t in ordine e costruisci un array di tokens con le loro posizioni
                        $pattern = '/(<w:t[^>]*>)(.*?)<\/w:t>/s';
                        preg_match_all($pattern, $docXml, $matches, PREG_OFFSET_CAPTURE);
                        $texts = $matches[2] ?? [];
                        $positions = $matches[0] ?? [];

                        // cerchiamo una sequenza di testo che, concatenata, formi ${tabella_items}
                        $target = '${tabella_items}';
                        $foundRange = null;
                        for ($start = 0; $start < count($texts); $start++) {
                            $concat = '';
                            for ($end = $start; $end < count($texts) && strlen($concat) < strlen($target) + 20; $end++) {
                                $concat .= $texts[$end][0];
                                if (strpos($concat, $target) !== false) {
                                    $foundRange = [$start, $end];
                                    break 2;
                                }
                            }
                        }

                        if ($foundRange !== null) {
                            list($s, $e) = $foundRange;
                            // determiniamo l'offset nella stringa originale per la prima e l'ultima occorrenza trovata
                            $firstMatch = $positions[$s][1];
                            $lastMatch = $positions[$e][1] + strlen($positions[$e][0]);

                            // costruisci WordML della tabella come sopra
                            $tableRow =
                                '<w:tr>' .
                                    '<w:tc><w:p><w:r><w:t>${item_descrizione}</w:t></w:r></w:p></w:tc>' .
                                    '<w:tc><w:p><w:r><w:t>${item_imponibile}</w:t></w:r></w:p></w:tc>' .
                                    '<w:tc><w:p><w:r><w:t>${item_iva}</w:t></w:r></w:p></w:tc>' .
                                    '<w:tc><w:p><w:r><w:t>${item_importo_iva}</w:t></w:r></w:p></w:tc>' .
                                '</w:tr>';

                            // aggiungi un paragrafo vuoto prima e dopo per lasciare spazio verticale
                            $emptyPara = '<w:p><w:pPr><w:spacing w:before="120" w:after="120"/></w:pPr><w:r><w:t></w:t></w:r></w:p>';

                            $tableXml = '<w:tbl>' .
                                '<w:tblPr><w:tblW w:w="0" w:type="auto"/></w:tblPr>' .
                                '<w:tr>' .
                                    '<w:tc><w:p><w:r><w:t>Descrizione</w:t></w:r></w:p></w:tc>' .
                                    '<w:tc><w:p><w:r><w:t>Imponibile</w:t></w:r></w:p></w:tc>' .
                                    '<w:tc><w:p><w:r><w:t>% IVA / Esenzione</w:t></w:r></w:p></w:tc>' .
                                    '<w:tc><w:p><w:r><w:t>Importo IVA</w:t></w:r></w:p></w:tc>' .
                                '</w:tr>' .
                                $tableRow .
                            '</w:tbl>';

                            // Calcolo preciso degli offset: troviamo la posizione esatta della sottostringa
                            // ricostruita all'interno dei token tra $s ed $e e sostituiamo solo quella porzione
                            $targetLen = strlen($target);
                            // ricostruisci il testo concatenato e trovi la posizione di target
                            $concat = '';
                            for ($i = $s; $i <= $e; $i++) {
                                $concat .= $matches[2][$i][0];
                            }
                            $posInConcat = strpos($concat, $target);
                            if ($posInConcat === false) {
                                // fallback: rimpiazzo l'intero intervallo (meno probabile)
                                $docXml = substr($docXml, 0, $firstMatch) . $tableXml . substr($docXml, $lastMatch);
                            } else {
                                // trova il token che contiene l'inizio del target
                                $remaining = $posInConcat;
                                $startToken = $s;
                                for ($i = $s; $i <= $e; $i++) {
                                    $len = strlen($matches[2][$i][0]);
                                    if ($remaining < $len) { $startToken = $i; break; }
                                    $remaining -= $len;
                                }

                                $startOffset = $matches[2][$startToken][1] + $remaining;

                                // calcola fine
                                $posEndInConcat = $posInConcat + $targetLen - 1;
                                $remainingEnd = $posEndInConcat;
                                $endToken = $s;
                                for ($i = $s; $i <= $e; $i++) {
                                    $len = strlen($matches[2][$i][0]);
                                    if ($remainingEnd < $len) { $endToken = $i; break; }
                                    $remainingEnd -= $len;
                                }
                                $endOffset = $matches[2][$endToken][1] + $remainingEnd + 1; // +1 perché end offset esclusivo

                                // sostituisci solo la sottostringa precisa
                                $docXml = substr($docXml, 0, $startOffset) . $tableXml . substr($docXml, $endOffset);
                            }
                            $tableInserted = true;
                        }
                    }

                    // Scrivi di nuovo document.xml
                    $zip->addFromString('word/document.xml', $docXml);
                }
                $zip->close();
            }

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
                $tp->setValue($key, htmlspecialchars($value));
            }
            
            // Se ci sono items, normalizza i nomi dei campi e poi clona la riga contenente ${item_descrizione} e popola i valori
            if (!empty($items) && is_array($items)) {
                // normalizza ogni item in un array con chiavi: descrizione, imponibile, iva, importo_iva
                $normalized = [];
                foreach ($items as $it) {
                    // supporta diversi nomi di chiave comuni
                    $descr = $it['descrizione'] ?? $it['description'] ?? $it['desc'] ?? '';
                    $impon = $it['imponibile'] ?? $it['imponibile_totale'] ?? $it['amount'] ?? $it['impon'] ?? '';
                    $ivaField = $it['%iva o art. esenzione'] ?? $it['iva_percentuale'] ?? $it['vat'] ?? $it['vat_percent'] ?? '';
                    $impIva = $it['importo_iva'] ?? $it['iva_importo'] ?? $it['vat_amount'] ?? $it['tax_amount'] ?? '';

                    // pulizia/format: rimuovi spazi indesiderati
                    $descr = is_string($descr) ? trim($descr) : $descr;
                    $impon = is_scalar($impon) ? trim((string)$impon) : $impon;
                    $ivaField = is_scalar($ivaField) ? trim((string)$ivaField) : $ivaField;
                    $impIva = is_scalar($impIva) ? trim((string)$impIva) : $impIva;

                    $normalized[] = [
                        'descrizione' => $descr,
                        'imponibile' => $impon,
                        'iva' => $ivaField,
                        'importo_iva' => $impIva,
                    ];
                }

                $count = count($normalized);
                try {
                    $tp->cloneRow('item_descrizione', $count);
                    $i = 1;
                    foreach ($normalized as $it) {
                        $tp->setValue("item_descrizione#{$i}", $it['descrizione']);
                        $tp->setValue("item_imponibile#{$i}", $it['imponibile']);
                        $tp->setValue("item_iva#{$i}", $it['iva']);
                        $tp->setValue("item_importo_iva#{$i}", $it['importo_iva']);
                        $i++;
                    }
                } catch (\Exception $e) {
                    Log::error('TemplateProcessor cloneRow failed', ['exception' => $e]);
                }
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

}
