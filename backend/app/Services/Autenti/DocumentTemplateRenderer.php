<?php

namespace App\Services\Autenti;

use App\Models\DocumentTemplate;
use App\Models\User;
use App\Models\Company;
use Dompdf\Dompdf;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentTemplateRenderer
{
    public function render(DocumentTemplate $template, User $user, array $context = []): string
    {
        if ($template->type === 'pdf') {
            return storage_path('app/' . ltrim((string) $template->file_path, '/'));
        }

        $html = (string) $template->html_content;
        $tokens = $this->buildTokens($user, $context);
        foreach ($tokens as $key => $value) {
            $html = str_replace('{{' . $key . '}}', (string) $value, $html);
        }

        $dompdf = new Dompdf([
            'defaultFont' => 'DejaVu Sans',
        ]);
        $dompdf->loadHtml($html);
        $dompdf->render();

        $path = 'generated-documents/' . Str::uuid()->toString() . '.pdf';
        Storage::put($path, $dompdf->output());

        return storage_path('app/' . $path);
    }

    private function buildTokens(User $user, array $context): array
    {
        $address = is_array($user->address_json) ? $user->address_json : [];
        $documents = is_array($user->documents_json) ? $user->documents_json : [];
        $nameParts = preg_split('/\s+/', trim((string) $user->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $firstName = $nameParts[0] ?? '';
        $lastName = count($nameParts) > 1 ? $nameParts[count($nameParts) - 1] : '';
        $client = $context['client'] ?? null;
        $clientData = $this->normalizeClient($client);
        $clientAddress = is_array($clientData['address_json'] ?? null) ? $clientData['address_json'] : [];
        $tokens = [
            'user.id' => (string) $user->id,
            'user.keycloak_id' => (string) ($user->keycloak_id ?? ''),
            'user.name' => $user->name,
            'user.first_name' => $firstName,
            'user.last_name' => $lastName,
            'user.email' => $user->email,
            'user.phone' => $user->phone ?? '',
            'user.role' => $user->role_cached ?? '',
            'user.contract_status' => $user->contract_status ?? '',
            'user.crm_number' => $user->crm_number ?? '',
            'user.rank' => $user->rank ?? '',
            'user.type' => $user->type ?? '',
            'user.team_group_path' => $user->team_group_path ?? '',
            'user.hierarchical_code' => $user->hierarchical_code ?? '',
            'user.hierarchical_id' => $user->hierarchical_id ?? '',
            'client.id' => (string) ($clientData['id'] ?? ''),
            'client.name' => (string) ($clientData['name'] ?? ''),
            'client.nip' => (string) ($clientData['nip'] ?? ''),
            'client.regon' => (string) ($clientData['regon'] ?? ''),
            'client.krs' => (string) ($clientData['krs'] ?? ''),
            'client.email' => (string) ($clientData['email'] ?? ''),
            'client.phone' => (string) ($clientData['phone'] ?? ''),
            'client.website' => (string) ($clientData['website'] ?? ''),
            'client.address_line1' => (string) ($clientData['address_line1'] ?? ''),
            'client.address_line2' => (string) ($clientData['address_line2'] ?? ''),
            'client.postal_code' => (string) ($clientData['postal_code'] ?? ''),
            'client.city' => (string) ($clientData['city'] ?? ''),
            'client.country' => (string) ($clientData['country'] ?? ''),
            'client.industry' => (string) ($clientData['industry'] ?? ''),
            'client.vat_type' => (string) ($clientData['vat_type'] ?? ''),
            'client.employee_count' => (string) ($clientData['employee_count'] ?? ''),
            'client.benefits_enabled' => (string) (($clientData['benefits_enabled'] ?? false) ? '1' : '0'),
            'address.street' => (string) Arr::get($address, 'street', ''),
            'address.houseNr' => (string) Arr::get($address, 'houseNr', ''),
            'address.aptNr' => (string) Arr::get($address, 'aptNr', ''),
            'address.zipCode' => (string) Arr::get($address, 'zipCode', ''),
            'address.city' => (string) Arr::get($address, 'city', ''),
            'client.address.street' => (string) Arr::get($clientAddress, 'street', ''),
            'client.address.houseNr' => (string) Arr::get($clientAddress, 'houseNr', ''),
            'client.address.aptNr' => (string) Arr::get($clientAddress, 'aptNr', ''),
            'client.address.zipCode' => (string) Arr::get($clientAddress, 'zipCode', ''),
            'client.address.city' => (string) Arr::get($clientAddress, 'city', ''),
            'documents.nda' => (string) (Arr::get($documents, 'nda') ? '1' : '0'),
            'documents.cooperationAgreement' => (string) (Arr::get($documents, 'cooperationAgreement') ? '1' : '0'),
            'documents.careerPath' => (string) (Arr::get($documents, 'careerPath') ? '1' : '0'),
            'documents.otherFileName' => (string) Arr::get($documents, 'otherFileName', ''),
            'date' => now()->format('Y-m-d'),
            'datetime' => now()->format('Y-m-d H:i'),
        ];

        foreach ($context as $key => $value) {
            if (is_scalar($value)) {
                $tokens[$key] = $value;
            }
        }

        return $tokens;
    }

    private function normalizeClient(mixed $client): array
    {
        if ($client instanceof Company) {
            return $client->toArray();
        }

        if (is_array($client)) {
            return $client;
        }

        return [];
    }
}
