<?php

namespace App\Services\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SupabaseTokenService
{
    public function decode(string $token): array
    {
        $keySet = $this->getKeySet();

        try {
            $decoded = JWT::decode($token, $keySet);
        } catch (\Throwable $exception) {
            throw new RuntimeException('Invalid access token: ' . $exception->getMessage());
        }

        $payload = json_decode(json_encode($decoded), true);
        $this->validateClaims($payload);

        return $payload;
    }

    /**
     * Wyciaga role z pola app_metadata.role lub user_metadata.role
     * zwracajac tablice z jedna rola (Supabase przechowuje role jako skalar).
     */
    public function extractRoles(array $payload): array
    {
        $role = Arr::get($payload, 'app_metadata.role')
            ?? Arr::get($payload, 'user_metadata.role');

        if (!$role) {
            return [];
        }

        return [$role];
    }

    private function validateClaims(array $payload): void
    {
        $expectedIssuer = config('supabase.issuer') ?: $this->defaultIssuer();
        if ($expectedIssuer && Arr::get($payload, 'iss') !== $expectedIssuer) {
            throw new RuntimeException('Invalid token issuer.');
        }

        // Supabase tokeny maja role === "authenticated" – nie sprawdzamy audience
    }

    private function getKeySet(): array
    {
        $ttl = (int) config('supabase.jwks_cache_ttl', 3600);

        // Fresh cache hit — fast path, no network.
        $jwks = Cache::get('supabase.jwks');
        if (is_array($jwks) && !empty($jwks['keys'])) {
            return JWK::parseKeySet($jwks);
        }

        try {
            $jwks = $this->fetchJwks();
            Cache::put('supabase.jwks', $jwks, $ttl);
            // Long-lived "last known good" copy so a Supabase outage doesn't take
            // the whole API down (auth keeps verifying with the previous keys).
            Cache::put('supabase.jwks_stale', $jwks, 60 * 60 * 24 * 7);
            return JWK::parseKeySet($jwks);
        } catch (\Throwable $e) {
            $stale = Cache::get('supabase.jwks_stale');
            if (is_array($stale) && !empty($stale['keys'])) {
                return JWK::parseKeySet($stale);
            }
            // No keys at all → this is infrastructure-down, not a bad token.
            // Code 503 so the middleware returns 503 (retry) instead of 401 (logout).
            throw new RuntimeException('JWKS unavailable: ' . $e->getMessage(), 503);
        }
    }

    private function fetchJwks(): array
    {
        $url = config('supabase.jwks_url') ?: $this->defaultJwksUrl();
        if (!$url) {
            throw new RuntimeException('Brak konfiguracji SUPABASE_URL lub SUPABASE_JWKS_URL.');
        }

        $response = Http::timeout(5)->retry(2, 200)->get($url);
        if (!$response->ok()) {
            throw new RuntimeException('JWKS fetch HTTP ' . $response->status());
        }

        $data = $response->json();
        if (!is_array($data) || empty($data['keys'])) {
            throw new RuntimeException('JWKS response empty/invalid');
        }

        return $data;
    }

    private function defaultIssuer(): ?string
    {
        $url = rtrim((string) config('supabase.url'), '/');
        return $url ? $url . '/auth/v1' : null;
    }

    private function defaultJwksUrl(): ?string
    {
        $url = rtrim((string) config('supabase.url'), '/');
        return $url ? $url . '/auth/v1/.well-known/jwks.json' : null;
    }
}
