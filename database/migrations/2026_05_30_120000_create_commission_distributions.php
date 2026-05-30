<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_commission_distributions', function (Blueprint $table) {
            $table->id();
            $table->string('source_user_supabase_id');
            $table->decimal('base_amount', 12, 2);
            $table->string('period', 16)->nullable();
            // MANUAL | EBS_WEBHOOK | OFFER_SIGNED
            $table->string('source', 32)->default('MANUAL');
            $table->string('source_reference', 255)->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->index('source_user_supabase_id');
            $table->index('period');
            $table->index('source');
        });

        Schema::create('crm_commission_distribution_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('distribution_id');
            $table->string('receiver_user_supabase_id');
            $table->string('receiver_role_at_time', 32);
            // 0 = self (sales who closed); 1 = direct parent; 2 = grandparent; etc.
            $table->unsignedTinyInteger('level');
            $table->decimal('rate', 6, 4);
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->foreign('distribution_id')
                ->references('id')
                ->on('crm_commission_distributions')
                ->cascadeOnDelete();

            $table->index('receiver_user_supabase_id');
            $table->index('distribution_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_commission_distribution_items');
        Schema::dropIfExists('crm_commission_distributions');
    }
};
