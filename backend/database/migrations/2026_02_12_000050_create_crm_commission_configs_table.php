<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_commission_configs', function (Blueprint $table) {
            $table->id();
            $table->decimal('sales_commission_first_month_lt14', 6, 4)->default(0.90);
            $table->decimal('sales_commission_first_month_gt14', 6, 4)->default(0.80);
            $table->decimal('sales_commission_renewal', 6, 4)->default(0.04);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_commission_configs');
    }
};
