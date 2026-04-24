<?php

namespace App\Services\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class KeycloakTokenService
{
    public function decode(string $token): array
    {
        $keySet = $this->getKeySet();

        try {
            $decoded = JWT::decode($token, $keySet);
        } catch (\Throwable $exception) {
            throw new RuntimeException('Invalid access token.');
        }

        $payload = json_decode(json_encode($decoded), true);
        $this->validateClaims($payload);

        return $payload;
    }

    public function extractRoles(array $payload): array
    {
        $realmRoles = Arr::get($payload, 'realm_access.roles', []);
        $clientId = config('keycloak.client_id');
        $resourceRoles = $clientId
            ? Arr::get($payload, "resource_access.$clientId.roles", [])
            : [];

        return array_values(array_unique(array_merge($realmRoles, $resourceRoles)));
    }

    private function validateClaims(array $payload): void
    {
        $issuer = config('keycloak.issuer') ?: $this->defaultIssuer();
        if ($issuer && Arr::get($payload, 'iss') !== $issuer) {
            throw new RuntimeException('Invalid token issuer.');
        }

        $audience = config('keycloak.audience') ?: config('keycloak.client_id');
        $aud = Arr::get($payload, 'aud');
        if ($audience && $aud) {
            $audList = is_array($aud) ? $aud : [$aud];
            if (!in_array($audience, $audList, true)) {
                throw new RuntimeException('Invalid token audience.');
            }
        }
    }

    private function getKeySet(): array
    {
        $jwks = Cache::remember('keycloak.jwks', (int) config('keycloak.jwks_cache_ttl', 3600), function () {
            $url = config('keycloak.jwks_url') ?: $this->defaultJwksUrl();
            if (!$url) {
                throw new RuntimeException('Missing Keycloak JWKS URL.');
            }

            $response = Http::get($url);
            if (!$response->ok()) {
                throw new RuntimeException('Unable to fetch Keycloak JWKS.');
            }

            return $response->json();
        });

        return JWK::parseKeySet($jwks);
    }

    private function defaultIssuer(): ?string
    {
        $baseUrl = rtrim((string) config('keycloak.base_url'), '/');
        $realm = config('keycloak.realm');
        if (!$baseUrl || !$realm) {
            return null;
        }

        return $baseUrl.'/realms/'.$realm;
    }

    private function defaultJwksUrl(): ?string
    {
        $issuer = $this->defaultIssuer();
        if (!$issuer) {
            return null;
        }

        return $issuer.'/protocol/openid-connect/certs';
    }
}
