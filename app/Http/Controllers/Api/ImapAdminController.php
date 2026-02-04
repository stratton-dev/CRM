<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmMailDebugLog;
use App\Models\CrmMailJob;
use App\Models\CrmMailSyncStat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ImapAdminController extends Controller
{
    public function health()
    {
        return response()->json(['data' => $this->nodeGet('/health')]);
    }

    public function metrics()
    {
        return response()->json(['data' => $this->nodeGet('/metrics')]);
    }

    public function logs(Request $request)
    {
        $userId = $request->integer('user_id');
        $limit = min($request->integer('limit', 200), 1000);
        $query = CrmMailDebugLog::query()->orderByDesc('id');
        if ($userId) $query->where('user_id', $userId);
        $logs = $query->limit($limit)->get();
        return response()->json(['data' => $logs]);
    }

    public function jobs(Request $request)
    {
        $userId = $request->integer('user_id');
        $status = $request->string('status')->toString();
        $limit = min($request->integer('limit', 200), 1000);
        $query = CrmMailJob::query()->orderByDesc('id');
        if ($userId) $query->where('user_id', $userId);
        if ($status) $query->where('status', $status);
        $jobs = $query->limit($limit)->get();
        return response()->json(['data' => $jobs]);
    }

    public function stats(Request $request)
    {
        $userId = $request->integer('user_id');
        $query = CrmMailSyncStat::query();
        if ($userId) $query->where('user_id', $userId);
        $stats = $query->get();
        return response()->json(['data' => $stats]);
    }

    private function nodeGet(string $path): array
    {
        $baseUrl = rtrim((string) env('IMAP_NODE_URL', ''), '/');
        if ($baseUrl === '') {
            throw new RuntimeException('IMAP node URL is not configured.');
        }
        $token = (string) env('IMAP_SERVICE_TOKEN', '');
        $response = Http::withHeaders([
            'X-IMAP-SERVICE-TOKEN' => $token,
        ])->timeout(10)->get($baseUrl.$path);

        if (!$response->successful()) {
            $message = $response->json('message') ?: $response->body();
            throw new RuntimeException(is_string($message) && $message !== '' ? $message : 'IMAP node request failed.');
        }

        return is_array($response->json()) ? $response->json() : ['data' => $response->body()];
    }
}
