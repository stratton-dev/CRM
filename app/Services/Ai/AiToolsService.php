<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Models\Lead;
use App\Models\Client;
use App\Models\Meeting;
use App\Models\AiChatFile;
use App\Models\CrmMailConfig;
use App\Models\CrmDashboardEvent;
use App\Services\Ai\TextExtractionService;
use App\Services\Ai\FileUploadService;
use App\Services\Crm\CrmMailboxService;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AiToolsService
{
    public function __construct(
        private TextExtractionService $textExtraction,
        private FileUploadService     $fileUpload,
        private CrmMailboxService     $mailbox,
    ) {}

    public function getMyLeads(User $user, string $status = 'active'): array
    {
        try {
            $query = Lead::where('user_id', $user->id)
                ->with(['client:id,name,email,phone,company_name'])
                ->orderByDesc('created_at')
                ->limit(20);

            if ($status !== 'all') {
                $statusMap = [
                    'active' => ['new', 'in_progress', 'negotiation', 'proposal'],
                    'won'    => ['won', 'closed_won'],
                    'lost'   => ['lost', 'closed_lost'],
                ];
                if (isset($statusMap[$status])) {
                    $query->whereIn('status', $statusMap[$status]);
                }
            }

            $leads = $query->get();

            return [
                'success' => true,
                'count'   => $leads->count(),
                'leads'   => $leads->map(fn($l) => [
                    'id'           => $l->id,
                    'title'        => $l->title ?? $l->name ?? 'Lead #' . $l->id,
                    'status'       => $l->status,
                    'value'        => $l->value ?? $l->estimated_value ?? null,
                    'client_name'  => $l->client->name ?? $l->client_name ?? null,
                    'company'      => $l->client->company_name ?? $l->company ?? null,
                    'created_at'   => $l->created_at?->format('d.m.Y'),
                ])->toArray(),
            ];
        } catch (\Exception $e) {
            Log::warning('AiTools::getMyLeads error', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Nie udało się pobrać leadów: ' . $e->getMessage()];
        }
    }

    public function getClientCard(User $user, int $clientId): array
    {
        try {
            $client = Client::where('id', $clientId)->first();
            if (!$client) {
                return ['success' => false, 'error' => "Klient #$clientId nie znaleziony."];
            }

            return [
                'success' => true,
                'client'  => [
                    'id'           => $client->id,
                    'name'         => $client->name,
                    'email'        => $client->email,
                    'phone'        => $client->phone,
                    'company_name' => $client->company_name ?? $client->company ?? null,
                    'nip'          => $client->nip ?? null,
                    'address'      => $client->address ?? null,
                    'status'       => $client->status ?? null,
                    'notes'        => $client->notes ?? null,
                    'created_at'   => $client->created_at?->format('d.m.Y'),
                ],
            ];
        } catch (\Exception $e) {
            Log::warning('AiTools::getClientCard error', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Nie udało się pobrać karty klienta: ' . $e->getMessage()];
        }
    }

    public function getTodayMeetings(User $user): array
    {
        try {
            $today = Carbon::today();

            $events = CrmDashboardEvent::where('user_id', $user->id)
                ->whereDate('start_at', $today)
                ->orderBy('start_at')
                ->limit(10)
                ->get();

            return [
                'success'  => true,
                'date'     => $today->format('d.m.Y'),
                'count'    => $events->count(),
                'meetings' => $events->map(fn($e) => [
                    'id'    => $e->id,
                    'title' => $e->title,
                    'time'  => $e->start_at ? Carbon::parse($e->start_at)->format('H:i') : null,
                ])->toArray(),
            ];
        } catch (\Exception $e) {
            Log::warning('AiTools::getTodayMeetings error', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Nie udało się pobrać spotkań: ' . $e->getMessage()];
        }
    }

    public function createCalendarEvent(User $user, array $data): array
    {
        try {
            $startAt = $data['date'] ?? null;
            if ($startAt && !empty($data['time'])) {
                $startAt = $data['date'] . ' ' . $data['time'];
            }

            $event = CrmDashboardEvent::create([
                'user_id' => $user->id,
                'title'   => $data['title'] ?? 'Spotkanie',
                'start_at' => $startAt,
            ]);

            return [
                'success'  => true,
                'message'  => "Wydarzenie '{$event->title}' zostało dodane do kalendarza.",
                'event_id' => $event->id,
            ];
        } catch (\Exception $e) {
            Log::warning('AiTools::createCalendarEvent error', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Nie udało się dodać wydarzenia: ' . $e->getMessage()];
        }
    }

    public function sendEmailToClient(User $user, int $clientId, string $subject, string $body): array
    {
        try {
            $client = Client::find($clientId);
            if (!$client || !$client->email) {
                return ['success' => false, 'error' => "Klient #$clientId nie ma adresu email."];
            }

            \App\Models\CrmClientActivity::create([
                'user_id'    => $user->id,
                'client_id'  => $clientId,
                'type'       => 'email',
                'title'      => $subject,
                'description'=> mb_substr($body, 0, 500),
            ]);

            return [
                'success'   => true,
                'message'   => "Email do {$client->name} ({$client->email}) jest gotowy do wysłania. Otwórz skrzynkę i wyślij przez compose.",
                'to'        => $client->email,
                'subject'   => $subject,
                'client_id' => $clientId,
                'open_compose' => true,
            ];
        } catch (\Exception $e) {
            Log::warning('AiTools::sendEmailToClient error', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Błąd: ' . $e->getMessage()];
        }
    }

    public function sendInternalNotification(User $user, int $targetUserId, string $message): array
    {
        try {
            $target = User::find($targetUserId);
            if (!$target) {
                return ['success' => false, 'error' => "Użytkownik #$targetUserId nie znaleziony."];
            }

            \App\Models\Notification::create([
                'user_id'   => $targetUserId,
                'sender_id' => $user->id,
                'type'      => 'system',
                'title'     => 'Wiadomość od ' . $user->name,
                'body'      => $message,
                'data'      => json_encode(['from_ai' => true]),
                'read'      => false,
            ]);

            return [
                'success' => true,
                'message' => "Powiadomienie zostało wysłane do {$target->name}.",
            ];
        } catch (\Exception $e) {
            Log::warning('AiTools::sendInternalNotification error', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Błąd: ' . $e->getMessage()];
        }
    }

    public function getMyStats(User $user): array
    {
        try {
            $stats = [
                'user'    => $user->name,
                'role'    => $user->role_cached,
            ];

            if (class_exists(\App\Models\Lead::class)) {
                $stats['leads_total']  = Lead::where('user_id', $user->id)->count();
                $stats['leads_active'] = Lead::where('user_id', $user->id)
                    ->whereNotIn('status', ['won', 'lost', 'closed_won', 'closed_lost'])
                    ->count();
                $stats['leads_won_this_month'] = Lead::where('user_id', $user->id)
                    ->whereIn('status', ['won', 'closed_won'])
                    ->whereMonth('updated_at', now()->month)
                    ->count();
            }

            if (class_exists(\App\Models\CrmDashboardEvent::class)) {
                $stats['meetings_this_week'] = CrmDashboardEvent::where('user_id', $user->id)
                    ->whereBetween('start_at', [now()->startOfWeek(), now()->endOfWeek()])
                    ->count();
            }

            return ['success' => true, 'stats' => $stats];
        } catch (\Exception $e) {
            Log::warning('AiTools::getMyStats error', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Błąd: ' . $e->getMessage()];
        }
    }

    public function readUploadedFile(User $user, string $fileId): array
    {
        try {
            $record = AiChatFile::where('id', $fileId)->where('user_id', $user->id)->first();
            if (!$record) {
                return ['success' => false, 'error' => "Plik #{$fileId} nie znaleziony lub brak dostępu."];
            }

            $fullPath = Storage::disk('local')->path($record->stored_path);
            if (!file_exists($fullPath)) {
                return ['success' => false, 'error' => 'Plik wygasł lub został usunięty.'];
            }

            $content = $this->textExtraction->extract($fullPath, $record->mime_type);

            return [
                'success'    => true,
                'filename'   => $record->original_name,
                'content'    => $content,
                'char_count' => mb_strlen($content),
            ];
        } catch (\Exception $e) {
            Log::warning('AiTools::readUploadedFile error', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Błąd odczytu pliku: ' . $e->getMessage()];
        }
    }

    public function readEmailAttachment(User $user, string $messageUid, string $attachmentFilename, string $folder = 'INBOX'): array
    {
        try {
            $config = CrmMailConfig::where('user_id', $user->id)->first();
            if (!$config) {
                return ['success' => false, 'error' => 'Brak konfiguracji skrzynki pocztowej.'];
            }

            $attachmentData = $this->mailbox->getAttachment($config, strtolower($folder), $messageUid, $attachmentFilename);

            if (empty($attachmentData) || !isset($attachmentData['content'])) {
                return ['success' => false, 'error' => "Załącznik \"{$attachmentFilename}\" nie znaleziony w wiadomości {$messageUid}."];
            }

            $rawContent = base64_decode($attachmentData['content']);
            $mime       = $attachmentData['mimeType'] ?? 'application/octet-stream';

            $record   = $this->fileUpload->storeRaw($rawContent, $attachmentFilename, $mime, $user->id);
            $fullPath = Storage::disk('local')->path($record->stored_path);
            $content  = $this->textExtraction->extract($fullPath, $mime);

            return [
                'success'    => true,
                'file_id'    => (string) $record->id,
                'filename'   => $attachmentFilename,
                'content'    => $content,
                'char_count' => mb_strlen($content),
            ];
        } catch (\Exception $e) {
            Log::warning('AiTools::readEmailAttachment error', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Błąd pobierania załącznika: ' . $e->getMessage()];
        }
    }

    public function generatePdfSummary(User $user, string $title, string $contentMarkdown): array
    {
        try {
            $bodyHtml = $this->markdownToHtml($contentMarkdown);
            $html = '<!DOCTYPE html><html><head><meta charset="utf-8">
                <style>
                    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; line-height: 1.5; padding: 20px; color: #1a1a2e; }
                    h1 { color: #001f3d; border-bottom: 2px solid #C5A059; padding-bottom: 8px; }
                    h2 { color: #003366; margin-top: 20px; }
                    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
                    th { background: #001f3d; color: white; padding: 6px 10px; text-align: left; }
                    td { padding: 5px 10px; border-bottom: 1px solid #e2e8f0; }
                    tr:nth-child(even) td { background: #f8fafc; }
                </style>
                </head><body><h1>' . htmlspecialchars($title) . '</h1>' . $bodyHtml . '<p style="margin-top:40px;font-size:10px;color:#94a3b8;">Wygenerowano przez Asystenta AI Stratton Prime — ' . now()->format('d.m.Y H:i') . '</p></body></html>';

            $options = new Options();
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isRemoteEnabled', false);
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $pdfContent = $dompdf->output();

            $filename = 'podsumowanie_' . now()->format('Y-m-d_His') . '.pdf';
            $record   = $this->fileUpload->storeRaw($pdfContent, $filename, 'application/pdf', $user->id);

            return [
                'success'         => true,
                'file_id'         => (string) $record->id,
                'filename'        => $filename,
                'download_marker' => "[FILE:{$record->id}:{$filename}]",
            ];
        } catch (\Exception $e) {
            Log::warning('AiTools::generatePdfSummary error', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Błąd generowania PDF: ' . $e->getMessage()];
        }
    }

    private function markdownToHtml(string $md): string
    {
        $md = htmlspecialchars($md, ENT_QUOTES, 'UTF-8');
        $md = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $md);
        $md = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $md);
        $md = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $md);
        $md = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $md);
        $md = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $md);
        $md = preg_replace('/`(.+?)`/', '<code>$1</code>', $md);
        $md = preg_replace('/^- (.+)$/m', '<li>$1</li>', $md);
        $md = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $md);
        $md = nl2br($md);
        return $md;
    }

    public function generateCrmReport(User $user, string $reportType, array $filters = []): array
    {
        try {
            [$title, $html] = match($reportType) {
                'leads'       => $this->buildLeadsReport($user, $filters),
                'clients'     => $this->buildClientsReport($user, $filters),
                'sales_stats' => $this->buildSalesStatsReport($user, $filters),
                'meetings'    => $this->buildMeetingsReport($user, $filters),
                default       => throw new \InvalidArgumentException("Nieznany typ raportu: {$reportType}. Dostępne: leads, clients, sales_stats, meetings"),
            };

            $fullHtml = '<!DOCTYPE html><html><head><meta charset="utf-8">
                <style>
                    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.4; padding: 20px; color: #1a1a2e; }
                    h1 { color: #001f3d; border-bottom: 2px solid #C5A059; padding-bottom: 8px; font-size: 16px; }
                    h2 { color: #003366; margin-top: 16px; font-size: 13px; }
                    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
                    th { background: #001f3d; color: white; padding: 5px 8px; text-align: left; font-size: 10px; }
                    td { padding: 4px 8px; border-bottom: 1px solid #e2e8f0; }
                    tr:nth-child(even) td { background: #f8fafc; }
                    .footer { margin-top: 40px; font-size: 9px; color: #94a3b8; }
                </style>
                </head><body><h1>' . htmlspecialchars($title) . '</h1>' . $html . '<p class="footer">Wygenerowano przez Asystenta AI Stratton Prime — ' . now()->format('d.m.Y H:i') . '</p></body></html>';

            $options = new Options();
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isRemoteEnabled', false);
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($fullHtml);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $pdfContent = $dompdf->output();

            $safeName = str_replace(['/', '\\', ' '], '_', $reportType);
            $filename = "raport_{$safeName}_" . now()->format('Y-m-d') . '.pdf';
            $record   = $this->fileUpload->storeRaw($pdfContent, $filename, 'application/pdf', $user->id);

            return [
                'success'         => true,
                'file_id'         => (string) $record->id,
                'filename'        => $filename,
                'download_marker' => "[FILE:{$record->id}:{$filename}]",
            ];
        } catch (\Exception $e) {
            Log::warning('AiTools::generateCrmReport error', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Błąd generowania raportu: ' . $e->getMessage()];
        }
    }

    private function buildLeadsReport(User $user, array $filters): array
    {
        $query = Lead::where('user_id', $user->id)->with('client:id,name,company_name')->orderByDesc('created_at');
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
        $leads = $query->limit(200)->get();

        $rows = '';
        foreach ($leads as $lead) {
            $rows .= '<tr>'
                . '<td>' . htmlspecialchars($lead->title ?? $lead->name ?? 'Lead #' . $lead->id) . '</td>'
                . '<td>' . htmlspecialchars($lead->client->name ?? $lead->client_name ?? '—') . '</td>'
                . '<td>' . htmlspecialchars($lead->status ?? '—') . '</td>'
                . '<td>' . number_format((float)($lead->value ?? $lead->estimated_value ?? 0), 2, ',', ' ') . ' zł</td>'
                . '<td>' . ($lead->created_at?->format('d.m.Y') ?? '—') . '</td>'
                . '</tr>';
        }

        $html  = '<table><thead><tr><th>Lead</th><th>Klient</th><th>Status</th><th>Wartość</th><th>Data</th></tr></thead><tbody>' . $rows . '</tbody></table>';
        $html .= '<p><strong>Łącznie:</strong> ' . $leads->count() . ' leadów</p>';

        return ['Raport leadów — ' . $user->name, $html];
    }

    private function buildClientsReport(User $user, array $filters): array
    {
        $clients = Client::orderBy('name')->limit(200)->get(['id', 'name', 'email', 'phone', 'company_name', 'status', 'created_at']);

        $rows = '';
        foreach ($clients as $c) {
            $rows .= '<tr>'
                . '<td>' . htmlspecialchars($c->name ?? '—') . '</td>'
                . '<td>' . htmlspecialchars($c->company_name ?? '—') . '</td>'
                . '<td>' . htmlspecialchars($c->email ?? '—') . '</td>'
                . '<td>' . htmlspecialchars($c->phone ?? '—') . '</td>'
                . '<td>' . htmlspecialchars($c->status ?? '—') . '</td>'
                . '</tr>';
        }

        $html  = '<table><thead><tr><th>Imię i nazwisko</th><th>Firma</th><th>Email</th><th>Telefon</th><th>Status</th></tr></thead><tbody>' . $rows . '</tbody></table>';
        $html .= '<p><strong>Łącznie:</strong> ' . $clients->count() . ' klientów</p>';

        return ['Raport klientów', $html];
    }

    private function buildSalesStatsReport(User $user, array $filters): array
    {
        $leads     = Lead::where('user_id', $user->id);
        $wonLeads  = (clone $leads)->whereIn('status', ['won', 'closed_won'])->count();
        $lostLeads = (clone $leads)->whereIn('status', ['lost', 'closed_lost'])->count();
        $active    = (clone $leads)->whereIn('status', ['new', 'in_progress', 'negotiation', 'proposal'])->count();
        $totalVal  = (clone $leads)->whereIn('status', ['won', 'closed_won'])->sum('value');
        $total     = (clone $leads)->count();

        $html  = '<h2>Podsumowanie leadów</h2>';
        $html .= '<table><thead><tr><th>Metryka</th><th>Wartość</th></tr></thead><tbody>';
        $html .= '<tr><td>Łącznie leadów</td><td>' . $total . '</td></tr>';
        $html .= '<tr><td>Aktywne</td><td>' . $active . '</td></tr>';
        $html .= '<tr><td>Wygrane</td><td>' . $wonLeads . '</td></tr>';
        $html .= '<tr><td>Przegrane</td><td>' . $lostLeads . '</td></tr>';
        $html .= '<tr><td>Wartość wygranych</td><td>' . number_format((float)$totalVal, 2, ',', ' ') . ' zł</td></tr>';
        $html .= '</tbody></table>';

        return ['Raport statystyk sprzedaży — ' . $user->name, $html];
    }

    private function buildMeetingsReport(User $user, array $filters): array
    {
        $query = Meeting::where('user_id', $user->id)->orderByDesc('date');
        if (!empty($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }
        $meetings = $query->limit(200)->get();

        $rows = '';
        foreach ($meetings as $m) {
            $rows .= '<tr>'
                . '<td>' . htmlspecialchars($m->title ?? '—') . '</td>'
                . '<td>' . ($m->date ? Carbon::parse($m->date)->format('d.m.Y H:i') : '—') . '</td>'
                . '<td>' . htmlspecialchars($m->location ?? '—') . '</td>'
                . '<td>' . htmlspecialchars($m->status ?? '—') . '</td>'
                . '</tr>';
        }

        $html  = '<table><thead><tr><th>Tytuł</th><th>Data</th><th>Miejsce</th><th>Status</th></tr></thead><tbody>' . $rows . '</tbody></table>';
        $html .= '<p><strong>Łącznie:</strong> ' . $meetings->count() . ' spotkań</p>';

        return ['Raport spotkań — ' . $user->name, $html];
    }

    public function sendFileViaEmail(User $user, string $fileId, string $to, string $subject, string $body): array
    {
        try {
            $record = AiChatFile::where('id', $fileId)->where('user_id', $user->id)->first();
            if (!$record) {
                return ['success' => false, 'error' => "Plik #{$fileId} nie znaleziony."];
            }

            $config = CrmMailConfig::where('user_id', $user->id)->first();
            if (!$config) {
                return ['success' => false, 'error' => 'Brak konfiguracji skrzynki pocztowej.'];
            }

            $fullPath   = Storage::disk('local')->path($record->stored_path);
            $rawContent = file_get_contents($fullPath);
            if ($rawContent === false) {
                return ['success' => false, 'error' => 'Nie można odczytać pliku z dysku.'];
            }

            $result = $this->mailbox->sendMessage($config, [
                'to'          => $to,
                'subject'     => $subject,
                'body'        => $body,
                'attachments' => [[
                    'filename'     => $record->original_name,
                    'content'      => base64_encode($rawContent),
                    'content_type' => $record->mime_type,
                    'encoding'     => 'base64',
                ]],
            ]);

            return [
                'success'    => true,
                'message_id' => $result['messageId'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::warning('AiTools::sendFileViaEmail error', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Błąd wysyłki emaila: ' . $e->getMessage()];
        }
    }
}
