<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

/**
 * Oddaje konfigurację modelu ARP (produkt, cennik, gwarancja, lejek, prowizje)
 * do frontendu. CZYSTY ODCZYT KONFIGURACJI — zero DB, zero zapisu.
 * Prowizje pochodzą z config/commission.php (jedno źródło).
 */
class ArpConfigController extends Controller
{
    public function show()
    {
        $arp = config('arp', []);
        $arp['commission'] = [
            'arp_partner_rate'   => config('commission.arp.partner_rate'),
            'ebs_partner_levels' => [
                config('commission.ebs_partner.l1_rate'),
                config('commission.ebs_partner.l2_rate'),
                config('commission.ebs_partner.l3_rate'),
            ],
        ];

        return response()->json($arp);
    }
}
