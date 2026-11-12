<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodsSeeder extends Seeder
{
    /**
     * Seed the payment_methods table.
     *
     * Idempotent: safe to run multiple times via updateOrCreate().
     */
    public function run(): void
    {
        $methods = [
            'Cash',
            'Bank Transfer',
        ];

        foreach ($methods as $name) {
            PaymentMethod::updateOrCreate(
                ['name' => $name],
                ['name' => $name]
            );
        }
    }
}
