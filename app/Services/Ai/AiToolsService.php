<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Models\Lead;
use App\Models\Client;
use App\Models\CrmDashboardEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AiToolsService
{
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
}
