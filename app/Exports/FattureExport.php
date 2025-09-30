<?php

namespace App\Exports;

use App\Models\ProcessedFile;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;

class FattureExport implements FromArray
{
    private $start_date;
    private $end_date;


    public function __construct($start_date, $end_date)
    {
        $this->start_date = $start_date;
        $this->end_date = $end_date;
    }

    public function array(): array
    {
        $headers = [
            'ID',
            'Data Fattura',
            'Numero Fattura',
            'Ragione Sociale',
            'Partita IVA',
            'Indirizzo',
            'Totale',
            'Valuta'
        ];

        $data = ProcessedFile::where('created_at', '>=', $this->start_date)
            ->where('created_at', '<=', $this->end_date)
            ->whereNotNull('structured_json')
            ->get()
            ->map(function ($file) {
                $data = $file->structured_json;
                $data_fattura = Carbon::parse($data['data_emissione'] ?? null)->format('d/m/Y');

                return [
                    'ID' => $file->id,
                    'Data Fattura' =>  $data_fattura ?? 'N/A',
                    'Numero Fattura' => $data['numero_fattura'] . ' ' .  $data_fattura ?? 'N/A',
                    'Ragione Sociale' => $data['account_holder'] ?? 'N/A',
                    'Partita IVA' => $data['vat_number'] ?? 'N/A',
                    'Indirizzo' => $data['address'] ?? 'N/A',
                    'Totale' => isset($data['totale_dovuto']) 
                        ? number_format((float)str_replace(',', '.', preg_replace('/[^\d.,-]/', '', $data['totale_dovuto'])), 2, ',', '.')
                        : 'N/A',
                    'Valuta' => $data['valuta'] ?? 'N/A',
                ];
            })
            ->toArray();

        return array_merge([$headers], $data);
    }
}
