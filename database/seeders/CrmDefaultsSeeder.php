<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CrmDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $statusDefaults = [
            ['key' => 'NEW', 'label' => 'Nowy', 'sort_order' => 10],
            ['key' => 'IN_TALKS', 'label' => 'W rozmowach', 'sort_order' => 20],
            ['key' => 'OFFER_PREPARING', 'label' => 'Przygotowanie oferty', 'sort_order' => 30],
            ['key' => 'OFFER_GENERATED', 'label' => 'Oferta wygenerowana', 'sort_order' => 40],
            ['key' => 'CALCULATION_SENT', 'label' => 'Wysłano ofertę', 'sort_order' => 50],
            ['key' => 'SPECIAL_OFFER', 'label' => 'Oferta specjalna', 'sort_order' => 60],
            ['key' => 'RESIGNED', 'label' => 'Rezygnacja', 'sort_order' => 70],
            ['key' => 'SIGNED', 'label' => 'Podpisany', 'sort_order' => 80],
            ['key' => 'TERMINATED', 'label' => 'Umowa rozwiązana', 'sort_order' => 90],
        ];

        foreach ($statusDefaults as $status) {
            $exists = DB::table('crm_statuses')->where('key', $status['key'])->exists();
            if ($exists) {
                continue;
            }
            DB::table('crm_statuses')->insert([
                'key' => $status['key'],
                'label' => $status['label'],
                'description' => null,
                'sort_order' => $status['sort_order'],
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $eventDefaults = [
            ['key' => 'notifications.created', 'label' => 'Powiadomienie utworzone', 'description' => 'Powiadomienia użytkownik do użytkownika', 'active' => true],
            ['key' => 'news.published', 'label' => 'Aktualność opublikowana', 'description' => 'Broadcast aktualności', 'active' => true],
        ];

        foreach ($eventDefaults as $event) {
            $exists = DB::table('crm_events')->where('key', $event['key'])->exists();
            if ($exists) {
                continue;
            }
            DB::table('crm_events')->insert([
                'key' => $event['key'],
                'label' => $event['label'],
                'description' => $event['description'],
                'active' => $event['active'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $broadcasts = [
            [
                'name' => 'Wiadomości między użytkownikami',
                'event_key' => 'notifications.created',
                'description' => 'Domyślny broadcast wiadomości 1:1.',
                'enabled' => true,
                'targets' => [
                    ['target_type' => 'ROLE', 'target_value' => 'ADMIN'],
                    ['target_type' => 'ROLE', 'target_value' => 'DIRECTOR'],
                    ['target_type' => 'ROLE', 'target_value' => 'MANAGER'],
                    ['target_type' => 'ROLE', 'target_value' => 'SALES'],
                    ['target_type' => 'ROLE', 'target_value' => 'CLIENT_HR'],
                ],
            ],
            [
                'name' => 'Wiadomości do zespołów',
                'event_key' => 'notifications.created',
                'description' => 'Broadcasty zespołowe (target: ALL_TEAMS).',
                'enabled' => true,
                'targets' => [
                    ['target_type' => 'TEAM', 'target_value' => 'ALL_TEAMS'],
                ],
            ],
            [
                'name' => 'Wiadomości globalne (Superadmin)',
                'event_key' => 'notifications.created',
                'description' => 'Wysyłka do wszystkich ról z poziomu superadmina.',
                'enabled' => true,
                'targets' => [
                    ['target_type' => 'ROLE', 'target_value' => 'ADMIN'],
                    ['target_type' => 'ROLE', 'target_value' => 'DIRECTOR'],
                    ['target_type' => 'ROLE', 'target_value' => 'MANAGER'],
                    ['target_type' => 'ROLE', 'target_value' => 'SALES'],
                    ['target_type' => 'ROLE', 'target_value' => 'CLIENT_HR'],
                ],
            ],
            [
                'name' => 'Aktualności',
                'event_key' => 'news.published',
                'description' => 'Domyślny broadcast aktualności.',
                'enabled' => true,
                'targets' => [
                    ['target_type' => 'ROLE', 'target_value' => 'ADMIN'],
                    ['target_type' => 'ROLE', 'target_value' => 'DIRECTOR'],
                    ['target_type' => 'ROLE', 'target_value' => 'MANAGER'],
                    ['target_type' => 'ROLE', 'target_value' => 'SALES'],
                    ['target_type' => 'ROLE', 'target_value' => 'CLIENT_HR'],
                ],
            ],
        ];

        foreach ($broadcasts as $broadcast) {
            $exists = DB::table('crm_broadcasts')->where('name', $broadcast['name'])->exists();
            if ($exists) {
                continue;
            }
            $broadcastId = DB::table('crm_broadcasts')->insertGetId([
                'name' => $broadcast['name'],
                'event_key' => $broadcast['event_key'],
                'description' => $broadcast['description'],
                'enabled' => $broadcast['enabled'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            foreach ($broadcast['targets'] as $target) {
                DB::table('crm_broadcast_targets')->insert([
                    'broadcast_id' => $broadcastId,
                    'target_type' => $target['target_type'],
                    'target_value' => $target['target_value'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
