<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flaga uprawniająca SALES/MANAGER/DIRECTOR/ADMIN do bycia AGENTEM
 * w schemacie MLM leadowców. Tylko osoby z is_agent_authorized=true mogą:
 *  - być znajdowane przez chain walk gdy LEADOWIEC przynosi deal
 *  - dostawać domyślne 10% z dealu leadowca
 *  - posiadać leadowców i prowadzić spotkania w ich imieniu
 *
 * Toggle dostępny tylko dla ADMIN przez UI w widoku Struktura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'is_agent_authorized')) {
                $table->boolean('is_agent_authorized')->default(false)->after('is_blocked');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_agent_authorized')) {
                $table->dropColumn('is_agent_authorized');
            }
        });
    }
};
