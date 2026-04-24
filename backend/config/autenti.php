<?php

return [
    'enabled' => env('AUTENTI_ENABLED', false),
    'base_uri' => env('AUTENTI_BASE_URI', 'https://api.autenti.com/api/v2'),
    'access_token' => env('AUTENTI_ACCESS_TOKEN'),
    'client_id' => env('AUTENTI_CLIENT_ID'),
    'client_secret' => env('AUTENTI_CLIENT_SECRET'),
    'default_language' => env('AUTENTI_DEFAULT_LANGUAGE', 'pl'),
    'webhook_secret' => env('AUTENTI_WEBHOOK_SECRET'),
    'template_map' => [
        'nda' => env('AUTENTI_TEMPLATE_NDA', 'nda'),
        'cooperationAgreement' => env('AUTENTI_TEMPLATE_COOPERATION', 'cooperation-agreement'),
        'careerPath' => env('AUTENTI_TEMPLATE_CAREER_PATH', 'career-path'),
    ],
];
