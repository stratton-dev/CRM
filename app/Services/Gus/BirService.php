<?php

namespace App\Services\Gus;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class BirService
{
    private const MF_API_URL = 'https://wl-api.mf.gov.pl/api/search/nip/';

    public function lookupByNip(string $nip): array
    {
        $clean = preg_replace('/\D+/', '', $nip);
        if (!is_string($clean) || strlen($clean) !== 10) {
            throw ValidationException::withMessages([
                'nip' => ['NIP musi mieć 10 cyfr.'],
            ]);
        }

        $date = now()->format('Y-m-d');
        $url  = self::MF_API_URL . $clean;

        $response = Http::timeout(10)
            ->withHeaders(['Accept' => 'application/json'])
            ->get($url, ['date' => $date]);

        if ($response->status() === 404 || $response->failed()) {
            throw new RuntimeException('Nie znaleziono podmiotu dla podanego NIP.', 404);
        }

        $subject = $response->json('result.subject');

        if (empty($subject) || empty($subject['name'])) {
            throw new RuntimeException('Brak danych dla podanego NIP.', 404);
        }

        $address = $subject['residenceAddress'] ?? $subject['workingAddress'] ?? '';

        // Parse address string e.g. "BIAŁOŁĘCKA 388, 03-253 WARSZAWA" → street, zip, city
        $street  = '';
        $houseNr = '';
        $zipCode = '';
        $city    = '';
        if ($address) {
            // Split on comma: "STREET NUMBER, ZIP CITY"
            $parts = array_map('trim', explode(',', $address, 2));
            if (count($parts) === 2) {
                // Separate street from house number: last token after last space
                $streetPart = $parts[0];
                if (preg_match('/^(.+?)\s+(\S+)$/', $streetPart, $m)) {
                    $street  = $m[1];
                    $houseNr = $m[2];
                } else {
                    $street = $streetPart;
                }
                // ZIP CODE + CITY: "00-000 CITY NAME"
                $zipCity = $parts[1];
                if (preg_match('/^(\d{2}-\d{3})\s+(.+)$/', $zipCity, $m)) {
                    $zipCode = $m[1];
                    $city    = $m[2];
                } else {
                    $city = $zipCity;
                }
            } else {
                $street = $address;
            }
        }

        return [
            'name'      => $subject['name'] ?? '',
            'address'   => $address,
            'street'    => $street,
            'houseNr'   => $houseNr,
            'zipCode'   => $zipCode,
            'city'      => $city,
            'regon'     => $subject['regon'] ?? null,
            'krs'       => $subject['krs'] ?? null,
            'nip'       => $subject['nip'] ?? $clean,
            'statusVat' => $subject['statusVat'] ?? null,
        ];
    }

}
