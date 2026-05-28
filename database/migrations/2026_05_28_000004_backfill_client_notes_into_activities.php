<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('client_notes') || !Schema::hasTable('crm_client_activities')) {
            return;
        }

        // Copy every row from client_notes into crm_client_activities as a NOTE.
        // Use raw select+insert so we don't depend on a model.
        $rows = DB::table('client_notes')->get();
        foreach ($rows as $row) {
            $exists = DB::table('crm_client_activities')
                ->where('client_id', $row->company_id)
                ->where('user_id', $row->user_id)
                ->where('type', 'NOTE')
                ->where('description', $row->content)
                ->where('occurred_at', $row->created_at)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('crm_client_activities')->insert([
                'client_id' => $row->company_id,
                'user_id' => $row->user_id,
                'type' => 'NOTE',
                'description' => $row->content,
                'occurred_at' => $row->created_at,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at ?? $row->created_at,
            ]);
        }

        // Wipe the old table once data is preserved in activities.
        // Keep the table itself in place — dropping it would invalidate the
        // ClientNotesController route, which we leave wired for compatibility
        // even though the new UI no longer uses it.
        DB::table('client_notes')->truncate();
    }

    public function down(): void
    {
        // No-op — we don't reconstruct client_notes from activities, since
        // crm_client_activities is the canonical source going forward.
    }
};
