<?php

namespace App\Services\Gus;

use GusApi\Exception\InvalidUserKeyException;
use GusApi\Exception\InvalidReportTypeException;
use GusApi\Exception\NotFoundException;
use GusApi\GusApi;
use GusApi\ReportTypes;
use GusApi\SearchReport;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class BirService
{
    public function lookupByNip(string $nip): array
    {
        $key = (string) config('services.gus.bir');
        if ($key === '') {
            throw new RuntimeException('Missing GUS API key.');
        }

        $clean = preg_replace('/\D+/', '', $nip);
        if (!is_string($clean) || strlen($clean) !== 10) {
            throw ValidationException::withMessages([
                'nip' => ['NIP musi mieć 10 cyfr.'],
            ]);
        }

        $gus = new GusApi($key);

        try {
            $gus->login();
            $reports = $gus->getByNip($clean);
        } catch (InvalidUserKeyException $exception) {
            throw new RuntimeException('Invalid GUS API key.');
        } catch (NotFoundException $exception) {
            throw new RuntimeException('No data found.', 404);
        }

        foreach ($reports as $report) {
            $data = [
                'name' => $report->getName(),
                'city' => $report->getCity(),
                'street' => $report->getStreet(),
                'houseNr' => $report->getPropertyNumber(),
                'aptNr' => $report->getApartmentNumber(),
                'zipCode' => $report->getZipCode(),
                'regon' => $report->getRegon(),
                'krs' => null,
                'email' => null,
                'phone' => null,
                'fax' => null,
                'website' => null,
                'gusRaw' => null,
            ];

            $fullReport = $this->getFullReportRow($gus, $report);
            if ($fullReport) {
                $data['email'] = $this->findFirstValue($fullReport, ['adresEmail']);
                $data['phone'] = $this->findFirstValue($fullReport, ['numerTelefonu']);
                $data['fax'] = $this->findFirstValue($fullReport, ['numerFaksu']);
                $data['website'] = $this->findFirstValue($fullReport, ['adresStronyinternetowej']);
                $data['krs'] = $this->findFirstValue($fullReport, ['krs', 'numerWRejestrzeEwidencji']);
                $data['gusRaw'] = $fullReport;
            }

            return $data;
        }

        throw new RuntimeException('No data found.', 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function getFullReportRow(GusApi $gus, SearchReport $report): array
    {
        $reportNames = $this->reportNamesFor($report);
        foreach ($reportNames as $reportName) {
            try {
                $rows = $gus->getFullReport($report, $reportName);
            } catch (InvalidReportTypeException $exception) {
                continue;
            } catch (NotFoundException $exception) {
                continue;
            }

            if (is_array($rows) && $rows !== []) {
                $first = $rows[0] ?? [];
                if (is_array($first)) {
                    return $first;
                }
            }
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    private function reportNamesFor(SearchReport $report): array
    {
        $type = $report->getType();

        if ($type === SearchReport::TYPE_JURIDICAL_PERSON) {
            return [ReportTypes::REPORT_ORGANIZATION];
        }

        if ($type === SearchReport::TYPE_LOCAL_ENTITY_JURIDICAL_PERSON) {
            return [ReportTypes::REPORT_ORGANIZATION_LOCAL];
        }

        if ($type === SearchReport::TYPE_LOCAL_ENTITY_NATURAL_PERSON) {
            return [ReportTypes::REPORT_PERSON_LOCAL];
        }

        return [
            ReportTypes::REPORT_PERSON_CEIDG,
            ReportTypes::REPORT_PERSON_AGRO,
            ReportTypes::REPORT_PERSON_OTHER,
            ReportTypes::REPORT_PERSON,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $needles
     */
    private function findFirstValue(array $data, array $needles): ?string
    {
        foreach ($data as $key => $value) {
            if (!is_string($value)) {
                continue;
            }
            $trimmed = trim($value);
            if ($trimmed === '') {
                continue;
            }
            foreach ($needles as $needle) {
                if (stripos((string) $key, $needle) !== false) {
                    return $trimmed;
                }
            }
        }

        return null;
    }
}
