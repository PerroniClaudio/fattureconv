<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Google\Cloud\Storage\StorageClient;
use Google\Cloud\AIPlatform\V1\Client\PredictionServiceClient;
use Google\Cloud\AIPlatform\V1\Content;
use Google\Cloud\AIPlatform\V1\GenerateContentRequest;
use Google\Cloud\AIPlatform\V1\Part;
use Google\Cloud\AIPlatform\V1\GenerationConfig;
use Exception;

use Google\ApiCore\ApiException;

class GoogleCloudController extends Controller
{

    private $defaultPrompt = "Sei un esperto di fatture estere. Analizza il testo fornito e estrai SOLO le seguenti informazioni chiave:
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
            - items: array di oggetti con descrizione, imponibile + valuta, %iva o art. esenzione, importo iva

            Ignora header, footer, loghi o testo irrilevante. Se un campo non è presente, usa 'N/A'.
            Rispondi SOLO con un JSON valido, senza testo aggiuntivo, nemmeno ```json: 
            {\"fornitore\":\"...\", \"data_emissione\":\"...\", ...}"; // Prompt di default ottimizzato per fatture estere (riduce token)


    /**
     * Carica un file PDF sul bucket Google Cloud Storage configurato.
     *
     * Richiesta attesa: campo 'pdf' nel body della request (multipart/form-data).
     * Validare il file (tipo MIME, dimensione) e caricarlo sul disco GCS o usando StorageClient.
     * Restituisce JSON con percorso/object name o un codice di errore.
     */
    public function uploadToBucket(Request $request)
    {
        // TODO: validazione del file e upload su GCS
        return response()->json(['message' => 'Non implementato: uploadToBucket'], 501);
    }

    /**
     * Invia del testo a Vertex AI per l'estrazione/annotazione dei contenuti.
     *
     * @param string $text Il testo da analizzare/inviare a Vertex AI
     * @return mixed Risposta di Vertex AI (placeholder)
     */

    public function callVertexAI(string $testo, ?string $prompt = null, string $model = 'gemini-2.5-pro'): array
    {
        $projectId = config('google.key_file.project_id', 'tuo-progetto-default');
        $location = env('GOOGLE_CLOUD_LOCATION', 'eu-west8');

        // Costruisci il resource name del modello publisher (google) + modello richiesto
        // Esempio: projects/{project}/locations/{location}/publishers/google/models/gemini-2.5-pro
        $modelResource = sprintf('projects/%s/locations/%s/publishers/google/models/%s', $projectId, $location, $model);

        $promptFinale = ($prompt ?? $this->defaultPrompt) . "\n\nTesto della fattura: " . $testo;

        // Contenuto della richiesta: costruisci array di Part e usa setParts()
        $contentsParts = [new Part(['text' => $promptFinale])];
        $content = (new Content())
            ->setRole('user')
            ->setParts($contentsParts);

        // Config generazione: usa la classe GenerationConfig generata dai protobuf
        $generationConfig = new GenerationConfig();
        // Riduci aleatorietà e permetti una risposta più lunga (aumenta se necessario)
        $generationConfig->setTemperature(0.0);
        // Aumenta il limite massimo di token di output per evitare che la risposta venga troncata
        $generationConfig->setMaxOutputTokens(60000);
        // Genera un solo candidato (più semplice da parsare)
        $generationConfig->setCandidateCount(1);
        $generationConfig->setTopP(0.8);
        $generationConfig->setTopK(40);
        // Richiedi esplicitamente che la risposta sia in formato JSON (preview feature)
        $generationConfig->setResponseMimeType('application/json');

        // Costruisci la richiesta GenerateContentRequest usando setters
        $genReq = new GenerateContentRequest();
        $genReq->setModel($modelResource);
        $genReq->setContents([$content]);
        $genReq->setGenerationConfig($generationConfig);

        try {
            // Configura l'autenticazione per PredictionServiceClient usando il config
            $clientOptions = [];

            $keyFileConfig = config('google.key_file');

            if (empty($keyFileConfig) || !is_array($keyFileConfig)) {
                throw new \Exception('Google Cloud credentials not provided in config. Please check config/google.php configuration.');
            }

            // Basic validation - assicurati che i campi critici siano presenti
            if (empty($keyFileConfig['client_email']) || empty($keyFileConfig['private_key'])) {
                throw new \Exception('Google Cloud credentials incomplete: client_email or private_key missing in config.');
            }

            // Fix private key formatting if it contains literal \n (common issue with .env files)
            if (isset($keyFileConfig['private_key']) && strpos($keyFileConfig['private_key'], '\\n') !== false) {
                $keyFileConfig['private_key'] = str_replace('\\n', "\n", $keyFileConfig['private_key']);
            }

            $clientOptions['credentials'] = $keyFileConfig;
            // Force REST transport to avoid gRPC authentication loops and compatibility issues
            $clientOptions['transport'] = 'rest';

            $client = new PredictionServiceClient($clientOptions);
            $response = $client->generateContent($genReq);

            $candidates = $response->getCandidates();
            if (empty($candidates)) {
                return ['errore' => 'Nessun candidato restituito da Vertex AI'];
            }

            $candidate = $candidates[0];
            $parts = $candidate->getContent()->getParts();
            if (empty($parts)) {
                return ['errore' => 'Risposta vuota da Vertex AI'];
            }

            $testoRisposta = $parts[0]->getText();

            $dati = json_decode($testoRisposta, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Errore nel parsing JSON dalla risposta Vertex AI: ' . json_last_error_msg() . '. Risposta: ' . $testoRisposta);
            }

            return $dati ?: ['errore' => 'Nessun dato estratto'];
        } catch (ApiException $e) {
            throw new \Exception('Errore Vertex AI: ' . $e->getMessage());
        } catch (\Exception $e) {
            throw $e;
        } finally {
            // Chiudi il client (libera risorse) se necessario
            if (isset($client) && method_exists($client, 'close')) {
                $client->close();
            }
        }
    }
}
