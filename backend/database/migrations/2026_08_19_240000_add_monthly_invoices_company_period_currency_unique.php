<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_invoices', function (Blueprint $table) {
            $table->unique(
                ['company_id', 'billing_period_start', 'billing_period_end', 'currency'],
                'monthly_invoices_company_period_currency_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('monthly_invoices', function (Blueprint $table) {
            $table->dropUnique('monthly_invoices_company_period_currency_unique');
        });
    }
};
