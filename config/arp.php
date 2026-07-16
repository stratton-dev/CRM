<?php

/*
|--------------------------------------------------------------------------
| Model sprzedażowy Audyt Rezerw Płacowych (ARP) → EBS
|--------------------------------------------------------------------------
| Jedyne źródło prawdy o modelu ARP po stronie backendu (produkt 1: płatny
| audyt → produkt 2: wdrożenie EBS). CZYSTA KONFIGURACJA — bez tabel, bez
| migracji, bez ruszania danych. Frontend ma lustrzany plik src/config/arp.ts;
| endpoint GET /v1/arp-config oddaje to samo, żeby UI miał jedno źródło.
|
| Lejek ARP jest ZMAPOWANY na istniejące wartości enuma crm_client_profiles.status
| (NIE dodajemy wartości do typu — to wymagałoby ALTER TYPE). Kanban prezentuje
| te statusy pod etykietami ARP; istniejące dane klientów pozostają nietknięte.
*/

return [

    'contact' => [
        'name'  => 'Maciej Hagno',
        'title' => 'Dyrektor Handlowy',
        'phone' => '883 408 132',
    ],

    // Idealny profil klienta — filtr kwalifikacyjny w rozmowie 15 min.
    'icp' => [
        'min_employees' => 25,
        'max_employees' => 500,
        'contract_types' => ['UoP', 'zlecenie'],
        'note' => 'Poniżej 25 zatrudnionych zwykle odmawiamy.',
    ],

    // Pakiety audytu (ceny netto, płatne z góry przy podpisaniu umowy audytu).
    'packages' => [
        [
            'code' => 'ARP_50',
            'label' => 'ARP 50',
            'headcount_min' => 0,
            'headcount_max' => 50,
            'price_net' => 3900,
            'guaranteed_reserve_yearly' => 39000,
        ],
        [
            'code' => 'ARP_150',
            'label' => 'ARP 150',
            'headcount_min' => 51,
            'headcount_max' => 150,
            'price_net' => 6900,
            'guaranteed_reserve_yearly' => 69000,
        ],
        [
            'code' => 'ARP_300',
            'label' => 'ARP 300+',
            'headcount_min' => 151,
            'headcount_max' => 500,
            'price_net' => 11900,
            'guaranteed_reserve_yearly' => 119000,
        ],
    ],

    // Gwarancja 10× — zwrot 100% jeśli rezerwy < 10× ceny audytu rocznie.
    'guarantee' => [
        'multiplier' => 10,
        'refund_days' => 7,
        'label' => 'Gwarancja 10×',
        'description' => 'Jeśli raport nie wykaże legalnych rezerw wartych co najmniej 10-krotność ceny audytu rocznie — zwracamy 100% ceny w 7 dni, bez pytań.',
    ],

    // „Audyt za 0 zł" — zaliczenie ceny audytu na poczet wdrożenia EBS.
    'credit' => [
        'full_within_days' => 30, // 100% zaliczenia przy umowie EBS w 30 dni
        'half_within_days' => 60, // 50% w 31–60 dni; po 60 wygasa
        'label' => 'Audyt za 0 zł',
        'description' => 'Przy podpisaniu umowy wdrożeniowej EBS w 30 dni od prezentacji raportu — 100% ceny audytu zaliczone na wdrożenie (31–60 dni: 50%; po 60 dniach wygasa).',
    ],

    // Pakiet Założycielski — pierwszych 10 firm w cenie ARP 50.
    'founder_pack' => [
        'count' => 10,
        'price_net' => 3900,
        'label' => 'Pakiet Założycielski',
        'description' => 'Pierwszych 10 firm — każdy pakiet w cenie ARP 50 w zamian za zgodę na anonimowe case study.',
    ],

    // Lejek ARP zmapowany na ISTNIEJĄCE statusy enuma (bez zmian w DB).
    // Kanban prezentuje statusy pod tymi etykietami/kolejnością.
    'funnel' => [
        ['key' => 'lead',          'label' => 'Lead',                'status' => 'NEW',              'description' => 'Nowe zgłoszenie z formularza / outbound.'],
        ['key' => 'qualification', 'label' => 'Kwalifikacja (15 min)', 'status' => 'IN_TALKS',        'description' => 'Rozmowa kwalifikacyjna — weryfikacja ICP.'],
        ['key' => 'audit_sold',    'label' => 'Audyt sprzedany',     'status' => 'OFFER_PREPARING',  'description' => 'Umowa audytu + faktura pro forma, płatność.'],
        ['key' => 'post_visit',    'label' => 'Po wizycie',          'status' => 'OFFER_GENERATED',  'description' => 'Wizyta u klienta, dane listy płac zebrane (bez nazwisk).'],
        ['key' => 'report',        'label' => 'Raport / prezentacja', 'status' => 'CALCULATION_SENT', 'description' => 'Raport Rezerw gotowy, prezentacja z księgową.'],
        ['key' => 'decision',      'label' => 'Decyzja / zaliczenie', 'status' => 'SPECIAL_OFFER',    'description' => 'Decyzja o wdrożeniu; zaliczenie audytu ważne 30 dni.'],
        ['key' => 'ebs_signed',    'label' => 'Wdrożenie EBS',       'status' => 'SIGNED',           'description' => 'Umowa wdrożeniowa EBS podpisana (audyt gratis).'],
        ['key' => 'resigned',      'label' => 'Rezygnacja',          'status' => 'RESIGNED',         'description' => 'Klient zrezygnował.'],
        ['key' => 'terminated',    'label' => 'Zakończony',          'status' => 'TERMINATED',       'description' => 'Współpraca zakończona.'],
    ],

    // Pola formularza kwalifikacyjnego (15 min). Zapisywane w istniejącej,
    // nullable kolumnie crm_client_profiles.analysis_json (bez nowych kolumn).
    'qualification_fields' => [
        ['key' => 'employees_uop',   'label' => 'Pracownicy na UoP',            'type' => 'number'],
        ['key' => 'employees_uz',    'label' => 'Zleceniobiorcy (oskładkowani)', 'type' => 'number'],
        ['key' => 'goal',            'label' => 'Główny cel klienta',           'type' => 'text'],
        ['key' => 'wage_structure',  'label' => 'Struktura wynagrodzeń',        'type' => 'text'],
        ['key' => 'biggest_challenge','label' => 'Największe wyzwanie kadry/płac','type' => 'text'],
        ['key' => 'qualified',       'label' => 'Kwalifikuje się (ICP)',        'type' => 'boolean'],
    ],

    // Prowizje ARP/EBS mają jedno źródło w config/commission.php
    // (klucze 'arp' i 'ebs_partner'); ArpConfigController dokłada je do odpowiedzi.

    // Podstawa prawna (do materiałów / AI).
    'legal' => [
        'basis' => '§ 2 ust. 1 pkt 26 rozporządzenia składkowego MPiPS z 18.12.1998 r.',
        'announcement' => 'obwieszczenie MRPiPS z 3.03.2025 r. (Dz.U. poz. 316)',
        'zus_interpretation' => 'DI/100000/43/703/2025',
        'law_firm' => 'Kancelaria Żuk Pośpiech Sp. k.',
    ],
];
