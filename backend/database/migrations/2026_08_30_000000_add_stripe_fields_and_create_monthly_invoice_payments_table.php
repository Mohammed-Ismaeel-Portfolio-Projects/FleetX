<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_invoices', function (Blueprint $table) {
            $table->string('stripe_checkout_session_id')->nullable()->after('issued_at');
            $table->string('stripe_payment_intent_id')->nullable()->after('stripe_checkout_session_id');
            $table->timestamp('paid_at')->nullable()->after('stripe_payment_intent_id');

            $table->index('stripe_checkout_session_id', 'monthly_invoices_stripe_checkout_session_idx');
            $table->index('stripe_payment_intent_id', 'monthly_invoices_stripe_payment_intent_idx');
        });

        Schema::create('monthly_invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_invoice_id')
                ->constrained('monthly_invoices')
                ->restrictOnDelete();
            $table->uuid('company_id');
            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3);
            $table->string('stripe_checkout_session_id')->unique();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('status', 30)->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('monthly_invoice_id', 'monthly_inv_payments_invoice_idx');
            $table->index('company_id', 'monthly_inv_payments_company_idx');
            $table->index('stripe_payment_intent_id', 'monthly_inv_payments_intent_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_invoice_payments');

        Schema::table('monthly_invoices', function (Blueprint $table) {
            $table->dropIndex('monthly_invoices_stripe_checkout_session_idx');
            $table->dropIndex('monthly_invoices_stripe_payment_intent_idx');
            $table->dropColumn(['stripe_checkout_session_id', 'stripe_payment_intent_id', 'paid_at']);
        });
    }
};
