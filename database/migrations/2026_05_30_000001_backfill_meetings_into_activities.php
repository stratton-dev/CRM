<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('meetings') || !Schema::hasTable('crm_client_activities')) {
            return;
        }

        $hasOfferStatus = Schema::hasColumn('meetings', 'offer_status');
        $hasResumeAt = Schema::hasColumn('meetings', 'resume_at');
        $hasNote = Schema::hasColumn('meetings', 'note');

        DB::table('meetings')->orderBy('id')->chunkById(500, function ($meetings) use ($hasOfferStatus, $hasResumeAt, $hasNote) {
            foreach ($meetings as $m) {
                $occurredAt = null;
                if ($hasResumeAt && !empty($m->resume_at)) {
                    $occurredAt = $m->resume_at;
                } elseif (!empty($m->valid_until)) {
                    $occurredAt = $m->valid_until;
                } else {
                    $occurredAt = $m->created_at ?? now();
                }

                // Skip if already backfilled (idempotency by client+user+occurred_at+type).
                $exists = DB::table('crm_client_activities')
                    ->where('client_id', $m->client_id)
                    ->where('user_id', $m->user_id)
                    ->where('type', 'MEETING')
                    ->where('occurred_at', $occurredAt)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $parts = ['Spotkanie'];
                if (!empty($m->status)) {
                    $parts[] = '(status: ' . $m->status . ')';
                }
                if ($hasOfferStatus && !empty($m->offer_status)) {
                    $parts[] = '— oferta: ' . $m->offer_status;
                }
                if ($hasNote && !empty($m->note)) {
                    $parts[] = "\n" . trim((string) $m->note);
                }
                $description = implode(' ', $parts);

                DB::table('crm_client_activities')->insert([
                    'client_id' => $m->client_id,
                    'user_id' => $m->user_id,
                    'type' => 'MEETING',
                    'description' => $description,
                    'occurred_at' => $occurredAt,
                    'is_completed' => isset($m->status) && $m->status === 'completed',
                    'created_at' => $m->created_at ?? now(),
                    'updated_at' => $m->updated_at ?? now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // No-op — activities are now the canonical source. Rolling back would
        // not reconstruct distinct meeting state (valid_until vs resume_at vs
        // offer_status) that lived only on the meetings row.
    }
};
