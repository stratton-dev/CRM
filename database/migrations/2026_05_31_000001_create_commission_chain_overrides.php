<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-relacja override prowizji.
 *
 * Dla każdej pary (sub_member, ancestor) trzymamy konkretną stawkę %.
 * Sub-member = osoba która podpisała deal (np. handlowiec).
 * Ancestor = ktoś w jej up-chain (po parent_supabase_id rekurencyjnie).
 *
 * Brak rekordu = 0% (ancestor nic nie dostaje z tego sub-membera).
 *
 * Stawka SELF (handlowiec ze swoich dealów) trzymana NADAL w
 * users.override_commission_rate. Nie duplikujemy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_commission_chain_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('sub_member_supabase_id');
            $table->string('ancestor_supabase_id');
            $table->decimal('rate', 6, 4);
            $table->timestamps();

            $table->unique(['sub_member_supabase_id', 'ancestor_supabase_id'], 'commission_chain_override_unique');
            $table->index('sub_member_supabase_id', 'commission_chain_sub_idx');
            $table->index('ancestor_supabase_id', 'commission_chain_anc_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_commission_chain_overrides');
    }
};
