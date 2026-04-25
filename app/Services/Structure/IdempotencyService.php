<?php

namespace App\Services\Structure;

use App\Models\IdempotencyKey;
use Illuminate\Support\Arr;

class IdempotencyService
{
    public function hashPayload(array $payload): string
    {
        $normalized = $this->normalize($payload);
        return hash('sha256', json_encode($normalized, JSON_UNESCAPED_SLASHES));
    }

    public function find(string $key): ?IdempotencyKey
    {
        return IdempotencyKey::query()->where('key', $key)->first();
    }

    public function start(string $key, string $actorSupabaseId, string $requestHash): IdempotencyKey
    {
        return IdempotencyKey::create([
            'key' => $key,
            'actor_supabase_id' => $actorSupabaseId,
            'request_hash' => $requestHash,
            'status' => 'processing',
        ]);
    }

    public function complete(IdempotencyKey $record, array $response): void
    {
        $record->update([
            'status' => 'completed',
            'response_body' => $response,
        ]);
    }

    public function fail(IdempotencyKey $record, string $message): void
    {
        $record->update([
            'status' => 'failed',
            'response_body' => ['message' => $message],
        ]);
    }

    private function normalize(array $payload): array
    {
        $normalized = Arr::dot($payload);
        ksort($normalized);

        $result = [];
        foreach ($normalized as $key => $value) {
            $result[$key] = is_array($value) ? $this->normalize($value) : $value;
        }

        return $result;
    }
}
