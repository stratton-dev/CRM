<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jawne (plaintext) hasło użytkownika do podglądu/edycji w panelu admina.
 *
 * UWAGA bezpieczeństwa: hasło logowania jest hashowane w Supabase Auth (nieodwracalne).
 * Ta kolumna przechowuje świadomie jawną kopię hasła nadanego przez system / admina,
 * żeby można je było odczytać i podyktować użytkownikowi. Ustawiane przy tworzeniu
 * i przy każdej zmianie hasła. Dla userów sprzed tej zmiany pozostaje NULL
 * (starego hasła nie da się odtworzyć).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'plain_password')) {
                $table->string('plain_password')->nullable()->after('password');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'plain_password')) {
                $table->dropColumn('plain_password');
            }
        });
    }
};
