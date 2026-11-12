<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('company_id');
            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->restrictOnDelete();
            $table->date('billing_period_start');
            $table->date('billing_period_end');
            $table->char('currency', 3);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('status', 20);
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'billing_period_start', 'billing_period_end'], 'monthly_invoices_company_period_idx');
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_invoices');
    }
};
