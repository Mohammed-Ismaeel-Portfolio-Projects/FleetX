<?php

namespace Database\Seeders;

use App\Enums\MonthlyInvoiceStatus;
use App\Models\Company;
use App\Models\MonthlyInvoice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Dedicated seeder for Stripe Sandbox E2E testing in Client Mobile / Web.
 * Creates/ensures exactly ONE FINALIZED unpaid invoice for an existing client user & company.
 */
class StripeTestInvoiceSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure basic seed data exists if database is fresh
        if (! User::query()->whereHas('role', fn ($q) => $q->where('name', 'client'))->exists()) {
            $this->call([
                RolesSeeder::class,
                StatusesSeeder::class,
                CompanySeeder::class,
                IdentityLinkSeeder::class,
            ]);
        }

        // 2. Find an existing client user with a company
        $clientUser = User::query()
            ->whereHas('role', fn ($q) => $q->where('name', 'client'))
            ->whereNotNull('company_id')
            ->with(['company', 'role'])
            ->first();

        if (! $clientUser) {
            // Fallback to client@fleetx.com or run ClientC59E2ESeeder
            $this->call(ClientC59E2ESeeder::class);
            $clientUser = User::query()
                ->where('email', 'client@fleetx.com')
                ->with(['company', 'role'])
                ->firstOrFail();
        }

        /** @var Company $company */
        $company = $clientUser->company ?? Company::query()->firstOrFail();

        if ($clientUser->company_id === null) {
            $clientUser->update(['company_id' => $company->id]);
        }

        // 3. Search for an existing unpaid FINALIZED invoice for this company (Idempotency)
        $invoice = MonthlyInvoice::query()
            ->where('company_id', $company->id)
            ->where('status', MonthlyInvoiceStatus::FINALIZED)
            ->whereNull('paid_at')
            ->first();

        if (! $invoice) {
            // Pick a billing period that doesn't violate unique constraint
            $periodStart = Carbon::parse('2026-08-01');
            $periodEnd = Carbon::parse('2026-08-31');
            $currency = 'USD';

            $existingForPeriod = MonthlyInvoice::findForBillingIdentity(
                (string) $company->id,
                $periodStart,
                $periodEnd,
                $currency
            );

            if ($existingForPeriod) {
                if ($existingForPeriod->status === MonthlyInvoiceStatus::DRAFT) {
                    $existingForPeriod->status = MonthlyInvoiceStatus::FINALIZED;
                    $existingForPeriod->issued_at = now();
                    $existingForPeriod->subtotal = 450.00;
                    $existingForPeriod->total = 450.00;
                    $existingForPeriod->stripe_checkout_session_id = null;
                    $existingForPeriod->stripe_payment_intent_id = null;
                    $existingForPeriod->paid_at = null;
                    $existingForPeriod->save();
                    $invoice = $existingForPeriod;
                } else {
                  // Pick next month to avoid unique constraint conflict
                  $periodStart = Carbon::parse('2026-09-01');
                  $periodEnd = Carbon::parse('2026-09-30');
                  
                  $invoice = MonthlyInvoice::query()
                      ->where('company_id', $company->id)
                      ->whereDate('billing_period_start', $periodStart->toDateString())
                      ->whereDate('billing_period_end', $periodEnd->toDateString())
                      ->where('currency', $currency)
                      ->first();

                  if (! $invoice) {
                      $invoice = MonthlyInvoice::create([
                          'company_id' => $company->id,
                          'billing_period_start' => $periodStart,
                          'billing_period_end' => $periodEnd,
                          'currency' => $currency,
                          'subtotal' => 450.00,
                          'total' => 450.00,
                          'status' => MonthlyInvoiceStatus::FINALIZED,
                          'issued_at' => now(),
                          'paid_at' => null,
                          'stripe_checkout_session_id' => null,
                          'stripe_payment_intent_id' => null,
                      ]);
                  } else {
                      $invoice->status = MonthlyInvoiceStatus::FINALIZED;
                      $invoice->issued_at = now();
                      $invoice->subtotal = 450.00;
                      $invoice->total = 450.00;
                      $invoice->stripe_checkout_session_id = null;
                      $invoice->stripe_payment_intent_id = null;
                      $invoice->paid_at = null;
                      $invoice->save();
                  }
                }
            } else {
                $invoice = MonthlyInvoice::create([
                    'company_id' => $company->id,
                    'billing_period_start' => $periodStart,
                    'billing_period_end' => $periodEnd,
                    'currency' => $currency,
                    'subtotal' => 450.00,
                    'total' => 450.00,
                    'status' => MonthlyInvoiceStatus::FINALIZED,
                    'issued_at' => now(),
                    'paid_at' => null,
                    'stripe_checkout_session_id' => null,
                    'stripe_payment_intent_id' => null,
                ]);
            }
        }

        // 4. Output summary to console
        $output = sprintf(
            "\n" .
            "===========================================\n" .
            "Stripe Test Invoice Created / Ready\n" .
            "===========================================\n" .
            "Invoice ID:   %d\n" .
            "Company ID:   %s\n" .
            "Company Name: %s\n" .
            "Status:       %s\n" .
            "Total:        %.2f\n" .
            "Currency:     %s\n" .
            "\n" .
            "Client User Credentials for Mobile App:\n" .
            "Email:        %s\n" .
            "Password:     Client@123 (if demo user)\n" .
            "===========================================\n",
            $invoice->id,
            $company->id,
            $company->company_name ?? $company->name ?? 'Client Company',
            $invoice->status->value ?? (string) $invoice->status,
            (float) $invoice->total,
            $invoice->currency,
            $clientUser->email
        );

        if ($this->command) {
            $this->command->info($output);
        } else {
            echo $output;
        }
    }
}
