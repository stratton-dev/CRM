<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Cienki wrapper na Supabase Admin API.
 *
 * Wymaga w config/supabase.php:
 *   - 'url' (SUPABASE_URL)
 *   - 'service_role_key' (SUPABASE_SERVICE_ROLE_KEY)
 *
 * Endpoint bazowy: {SUPABASE_URL}/auth/v1/admin
 *
 * NIGDY nie eksponuj service-role keya na klienta.
 */
class SupabaseAdminService
{
    private string $baseUrl;
    private string $serviceKey;

    public function __construct()
    {
        $url = rtrim((string) config('supabase.url'), '/');
        $this->baseUrl = $url !== '' ? $url . '/auth/v1/admin' : '';
        $this->serviceKey = (string) config('supabase.service_role_key', '');
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->serviceKey !== '';
    }

    /**
     * Tworzy uzytkownika w Supabase Auth. Zwraca pelny obiekt user (z 'id').
     *
     * @param array{email:string,password?:string,name?:string,role?:string,phone?:string,email_confirm?:bool} $data
     * @return array{id:string,email:string,raw:array}
     */
    public function createUser(array $data): array
    {
        $this->assertConfigured();

        $body = [
            'email' => $data['email'],
            'email_confirm' => $data['email_confirm'] ?? true,
        ];
        if (!empty($data['password'])) {
            $body['password'] = $data['password'];
        }
        if (!empty($data['phone'])) {
            $body['phone'] = $data['phone'];
        }
        if (!empty($data['role'])) {
            $body['app_metadata'] = [
                'role' => strtoupper((string) $data['role']),
            ];
        }
        if (!empty($data['name'])) {
            $body['user_metadata'] = [
                'name' => $data['name'],
            ];
        }

        $response = $this->request('post', '/users', $body);

        if (!$response->successful()) {
            $msg = $response->json('msg') ?? $response->json('message') ?? $response->body();
            throw new RuntimeException('Supabase createUser failed: ' . $msg, $response->status());
        }

        $json = $response->json() ?? [];
        $id = $json['id'] ?? ($json['user']['id'] ?? null);
        if (!is_string($id) || $id === '') {
            throw new RuntimeException('Supabase createUser returned no id');
        }

        return [
            'id' => $id,
            'email' => $json['email'] ?? ($json['user']['email'] ?? $data['email']),
            'raw' => $json,
        ];
    }

    /**
     * Aktualizuje rolę (app_metadata.role) lub inne pola uzytkownika w Supabase.
     */
    public function updateUser(string $supabaseId, array $patch): array
    {
        $this->assertConfigured();

        $body = [];
        if (array_key_exists('email', $patch)) {
            $body['email'] = $patch['email'];
        }
        if (array_key_exists('phone', $patch)) {
            $body['phone'] = $patch['phone'];
        }
        if (array_key_exists('password', $patch) && !empty($patch['password'])) {
            $body['password'] = $patch['password'];
        }
        if (array_key_exists('role', $patch)) {
            $body['app_metadata'] = ['role' => strtoupper((string) $patch['role'])];
        }
        if (array_key_exists('name', $patch)) {
            $body['user_metadata'] = ['name' => $patch['name']];
        }
        if (array_key_exists('ban_duration', $patch)) {
            $body['ban_duration'] = $patch['ban_duration'];
        }

        if (empty($body)) {
            return [];
        }

        $response = $this->request('put', '/users/' . $supabaseId, $body);

        if (!$response->successful()) {
            $msg = $response->json('msg') ?? $response->json('message') ?? $response->body();
            throw new RuntimeException('Supabase updateUser failed: ' . $msg, $response->status());
        }

        return $response->json() ?? [];
    }

    /**
     * Trwale usuwa uzytkownika z Supabase Auth.
     */
    public function deleteUser(string $supabaseId): void
    {
        $this->assertConfigured();

        $response = $this->request('delete', '/users/' . $supabaseId);

        if (!$response->successful() && $response->status() !== 404) {
            $msg = $response->json('msg') ?? $response->json('message') ?? $response->body();
            throw new RuntimeException('Supabase deleteUser failed: ' . $msg, $response->status());
        }
    }

    /**
     * Generuje recovery (password reset) link. Mozemy go wyslac userowi mailem.
     *
     * @return array{action_link:string,email:string,raw:array}
     */
    public function generatePasswordResetLink(string $email, ?string $redirectTo = null): array
    {
        $this->assertConfigured();

        $redirect = $redirectTo
            ?? config('supabase.password_reset_redirect')
            ?? rtrim((string) config('app.url'), '/') . '/reset-password';

        $body = [
            'type' => 'recovery',
            'email' => $email,
            'options' => ['redirect_to' => $redirect],
        ];

        $response = $this->request('post', '/generate_link', $body);

        if (!$response->successful()) {
            $msg = $response->json('msg') ?? $response->json('message') ?? $response->body();
            throw new RuntimeException('Supabase generate_link failed: ' . $msg, $response->status());
        }

        $json = $response->json() ?? [];
        $link = $json['action_link'] ?? ($json['properties']['action_link'] ?? null);
        if (!is_string($link) || $link === '') {
            throw new RuntimeException('Supabase generate_link returned no action_link');
        }

        return [
            'action_link' => $link,
            'email' => $email,
            'raw' => $json,
        ];
    }

    private function request(string $method, string $path, array $body = [])
    {
        $url = $this->baseUrl . $path;
        $request = Http::withHeaders([
            'apikey' => $this->serviceKey,
            'Authorization' => 'Bearer ' . $this->serviceKey,
            'Content-Type' => 'application/json',
        ])->acceptJson()->timeout(15);

        try {
            return match ($method) {
                'get'    => $request->get($url),
                'post'   => $request->post($url, $body),
                'put'    => $request->put($url, $body),
                'delete' => $request->delete($url),
                default  => throw new RuntimeException('Unsupported method: ' . $method),
            };
        } catch (\Throwable $e) {
            Log::error('Supabase Admin API error', [
                'method' => $method,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function assertConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException(
                'Supabase admin API not configured. Set SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY in env.'
            );
        }
    }
}
