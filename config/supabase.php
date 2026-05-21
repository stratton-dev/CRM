<?php

return [
    /*
     * URL projektu Supabase (np. https://abcxyz.supabase.co)
     * Wymagane do budowy adresu JWKS.
     */
    'url' => env('SUPABASE_URL', ''),

    /*
     * Opcjonalny nadpisany URL do endpointu JWKS.
     * Domyslnie: {SUPABASE_URL}/auth/v1/.well-known/jwks.json
     */
    'jwks_url' => env('SUPABASE_JWKS_URL'),

    /*
     * Czas cache JWKS w sekundach (domyslnie 1 godzina).
     */
    'jwks_cache_ttl' => env('SUPABASE_JWKS_CACHE_TTL', 3600),

    /*
     * Oczekiwany issuer tokenu JWT.
     * Domyslnie: {SUPABASE_URL}/auth/v1
     */
    'issuer' => env('SUPABASE_ISSUER'),

    /*
     * Mapowanie rol z app_metadata.role -> lokalna rola CRM.
     */
    'role_map' => [
        'admin'      => 'ADMIN',
        'ADMIN'      => 'ADMIN',
        'director'   => 'DIRECTOR',
        'DIRECTOR'   => 'DIRECTOR',
        'manager'    => 'MANAGER',
        'MANAGER'    => 'MANAGER',
        'sales'      => 'SALES',
        'SALES'      => 'SALES',
        'client_hr'  => 'CLIENT_HR',
        'CLIENT_HR'  => 'CLIENT_HR',
        'leadowiec'              => 'LEADOWIEC',
        'Leadowiec'              => 'LEADOWIEC',
        'LEADOWIEC'              => 'LEADOWIEC',
        'LEADOWIEC / Leadowiec'  => 'LEADOWIEC',
        'LEADOWIEC / LEADOWIEC'  => 'LEADOWIEC',
        'leadowiec / leadowiec'  => 'LEADOWIEC',
    ],

    /*
     * Priorytet ról – uzyty gdy uzytkownik ma kilka ról.
     */
    'role_priority' => [
        'ADMIN',
        'DIRECTOR',
        'MANAGER',
        'SALES',
        'CLIENT_HR',
        'LEADOWIEC',
    ],
];
