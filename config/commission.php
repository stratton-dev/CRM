<?php

/**
 * Domyślne stawki MLM dla leadowców i agentów.
 *
 * Override per-relacja (crm_commission_chain_overrides) ZAWSZE wygrywa
 * z tymi defaultami. Brak override → użyj defaultu poniżej.
 */

return [
    'leadowiec' => [
        // Leadowiec sam ze swojego dealu (poziom 0).
        'self_rate' => env('COMMISSION_LEADOWIEC_SELF', 0.10),

        // Leadowiec 1 poziom wyżej w łańcuchu (jego rekruter).
        'l1_rate' => env('COMMISSION_LEADOWIEC_L1', 0.05),

        // Leadowiec 2 poziomy wyżej.
        'l2_rate' => env('COMMISSION_LEADOWIEC_L2', 0.02),

        // L3 i głębiej — wygaszone.
        'max_chain_depth' => 2,
    ],

    'agent' => [
        // Agent (SALES/MANAGER/DIRECTOR/ADMIN z is_agent_authorized=true)
        // dostaje to z każdej umowy podpisanej dla leadowca w jego pionie.
        'default_rate' => env('COMMISSION_AGENT_RATE', 0.10),
    ],
];
