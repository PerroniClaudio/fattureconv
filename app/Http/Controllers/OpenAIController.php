<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class OpenAIController extends Controller
{
    private string $defaultPrompt = "Sei un esperto di fatture estere. Analizza il testo e restituisci un JSON con una proprietà 'fatture' che contiene un ARRAY di tutte le fatture trovate (anche se è una sola, deve essere comunque un array con un oggetto). Per ogni fattura estrai:
            - account_holder: nome dell'azienda fornitrice
            - address: indirizzo completo dell'azienda fornitrice
            - vat_number: partita IVA (se presente)
            - data_emissione: data in formato YYYY-MM-DD
            - numero_fattura: numero univoco della fattura
            - importo_netto: importo netto senza IVA (numero con 2 decimali)
            - iva_percentuale: percentuale IVA (es. 22)
            - iva_importo: importo IVA (numero con 2 decimali)
            - totale_dovuto: totale finale (numero con 2 decimali e valuta, es. EUR)
            - valuta: valuta usata (es. EUR, USD)
            - items: array di oggetti con descrizione, imponibile + valuta, iva_percentuale, importo_iva

            Ignora header, footer, loghi o testo irrilevante. Se un campo non è presente, usa 'N/A'.
            Rispondi SOLO con un JSON valido e sintatticamente corretto, senza testo aggiuntivo.
            NON usare blocchi di codice (niente ``` o ```json), non aggiungere spiegazioni.
            {\"fatture\":[{\"account_holder\":\"...\", \"data_emissione\":\"...\", ...}]}";

    private function openai()
    {
        $apiKey = config('services.openai.api_key');
        if (empty($apiKey)) {
            throw new Exception('OPENAI_API_KEY non configurata.');
        }

        return Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(120)
            ->baseUrl('https://api.openai.com');
    }

    private function extractOutputText(array $response): string
    {
        if (!empty($response['output_text']) && is_string($response['output_text'])) {
            return $response['output_text'];
        }

        $chunks = [];
        if (!empty($response['output']) && is_array($response['output'])) {
            foreach ($response['output'] as $item) {
                if (($item['type'] ?? '') !== 'message') {
                    continue;
                }
                foreach (($item['content'] ?? []) as $content) {
                    if (($content['type'] ?? '') === 'output_text' && isset($content['text'])) {
                        $chunks[] = $content['text'];
                    }
                }
            }
        }

        return trim(implode("\n", $chunks));
    }

    private function stripJsonFences(string $text): string
    {
        $trimmed = trim($text);
        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```[a-zA-Z0-9_-]*\\s*/', '', $trimmed);
            $trimmed = preg_replace('/\\s*```$/', '', $trimmed);
            $trimmed = trim($trimmed);
        }

        return $trimmed;
    }

    private function extractJsonSubstring(string $text): string
    {
        $start = null;
        $end = null;
        $firstArray = strpos($text, '[');
        $firstObj = strpos($text, '{');
        if ($firstArray === false && $firstObj === false) {
            return $text;
        }
        if ($firstArray === false) {
            $start = $firstObj;
            $end = strrpos($text, '}');
        } elseif ($firstObj === false) {
            $start = $firstArray;
            $end = strrpos($text, ']');
        } else {
            if ($firstArray < $firstObj) {
                $start = $firstArray;
                $end = strrpos($text, ']');
            } else {
                $start = $firstObj;
                $end = strrpos($text, '}');
            }
        }

        if ($start === null || $end === false || $end <= $start) {
            return $text;
        }

        return substr($text, $start, $end - $start + 1);
    }

    private function throwIfError($response, string $context): void
    {
        if ($response->successful()) {
            return;
        }

        $body = $response->json();
        $message = $body['error']['message'] ?? $response->body();
        throw new Exception($context . ': ' . $message);
    }

    public function callOpenAI(string $testo, ?string $prompt = null, ?string $model = null): array
    {
        $model = $model ?? config('services.openai.model', 'gpt-4o-mini');
        $promptFinale = ($prompt ?? $this->defaultPrompt) . "\n\nTesto della fattura: " . $testo;

        $payload = [
            'model' => $model,
            'input' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'input_text', 'text' => $promptFinale],
                    ],
                ],
            ],
            'temperature' => 0,
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'fatture',
                    'schema' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['fatture'],
                        'properties' => [
                            'fatture' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'additionalProperties' => false,
                                    'required' => [
                                        'account_holder',
                                        'address',
                                        'vat_number',
                                        'data_emissione',
                                        'numero_fattura',
                                        'importo_netto',
                                        'iva_percentuale',
                                        'iva_importo',
                                        'totale_dovuto',
                                        'valuta',
                                        'items',
                                    ],
                                    'properties' => [
                                        'account_holder' => ['type' => 'string'],
                                        'address' => ['type' => 'string'],
                                        'vat_number' => ['type' => 'string'],
                                        'data_emissione' => ['type' => 'string'],
                                        'numero_fattura' => ['type' => 'string'],
                                        'importo_netto' => ['type' => 'string'],
                                        'iva_percentuale' => ['type' => 'string'],
                                        'iva_importo' => ['type' => 'string'],
                                        'totale_dovuto' => ['type' => 'string'],
                                        'valuta' => ['type' => 'string'],
                                        'items' => [
                                            'type' => 'array',
                                            'items' => [
                                                'type' => 'object',
                                                'additionalProperties' => false,
                                                'required' => [
                                                    'descrizione',
                                                    'imponibile',
                                                    'iva_percentuale',
                                                    'importo_iva',
                                                ],
                                                'properties' => [
                                                    'descrizione' => ['type' => 'string'],
                                                    'imponibile' => ['type' => 'string'],
                                                    'iva_percentuale' => ['type' => 'string'],
                                                    'importo_iva' => ['type' => 'string'],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'strict' => true,
                ],
            ],
        ];

        $response = $this->openai()->post('/v1/responses', $payload);
        $this->throwIfError($response, 'Errore OpenAI (responses)');

        $data = $response->json();
        $text = $this->extractOutputText($data);
        if ($text === '') {
            throw new Exception('Risposta vuota da OpenAI.');
        }

        $decoded = json_decode($text, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $clean = $this->stripJsonFences($text);
            $decoded = json_decode($clean, true);
        }
        if (json_last_error() !== JSON_ERROR_NONE) {
            $extracted = $this->extractJsonSubstring($text);
            $decoded = json_decode($extracted, true);
        }
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Errore nel parsing JSON dalla risposta OpenAI: ' . json_last_error_msg() . '. Risposta: ' . $text);
        }

        if (is_array($decoded) && array_key_exists('fatture', $decoded) && is_array($decoded['fatture'])) {
            return $decoded['fatture'];
        }

        return $decoded ?: ['errore' => 'Nessun dato estratto'];
    }

    public function processWithOpenAI(string $gcsPath, string $mimeType = 'application/pdf'): array
    {
        $disk = Storage::disk(config('filesystems.default'));
        if (!$disk->exists($gcsPath)) {
            throw new Exception("File non trovato nello storage: {$gcsPath}");
        }

        $tmpDir = storage_path('app/temp_ocr');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $tmpFilename = Str::uuid()->toString() . '_' . basename($gcsPath);
        $localPath = $tmpDir . DIRECTORY_SEPARATOR . $tmpFilename;

        $fileId = null;

        try {
            $contents = $disk->get($gcsPath);
            file_put_contents($localPath, $contents);

            $upload = $this->openai()
                ->attach('file', file_get_contents($localPath), basename($localPath))
                ->post('/v1/files', [
                    'purpose' => 'user_data',
                ]);
            $this->throwIfError($upload, 'Errore OpenAI (files upload)');

            $fileId = $upload->json('id');
            if (empty($fileId)) {
                throw new Exception('Upload file OpenAI fallito: id non presente.');
            }

            $model = config('services.openai.ocr_model', config('services.openai.model', 'gpt-4o-mini'));
            $prompt = 'Estrai tutto il testo dal PDF. Rispondi SOLO con il testo, senza commenti o markup.';

            $payload = [
                'model' => $model,
                'input' => [
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'input_file', 'file_id' => $fileId],
                            ['type' => 'input_text', 'text' => $prompt],
                        ],
                    ],
                ],
                'temperature' => 0,
            ];

            $response = $this->openai()->post('/v1/responses', $payload);
            $this->throwIfError($response, 'Errore OpenAI (OCR)');

            $data = $response->json();
            $text = $this->extractOutputText($data);
            if ($text === '') {
                throw new Exception('OCR OpenAI: testo vuoto.');
            }

            return ['text' => $text];
        } catch (Exception $e) {
            Log::error('OpenAIController::processWithOpenAI error', ['exception' => $e, 'gcs_path' => $gcsPath]);
            throw $e;
        } finally {
            if ($fileId) {
                try {
                    $this->openai()->delete('/v1/files/' . $fileId);
                } catch (Exception $e) {
                    Log::warning('OpenAIController::processWithOpenAI cleanup failed', ['exception' => $e, 'file_id' => $fileId]);
                }
            }
            if (isset($localPath) && file_exists($localPath)) {
                @unlink($localPath);
            }
        }
    }
}
