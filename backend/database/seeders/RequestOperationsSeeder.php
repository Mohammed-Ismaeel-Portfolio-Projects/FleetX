<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * RequestOperationsSeeder
 *
 * Operational requests and fleet resources are authoritatively managed by MasterDataSeeder
 * to guarantee coherent ShipmentOrder -> Shipment -> Assignment lifecycle relationships.
 * This class delegates to MasterDataSeeder for backward compatibility with seeder calls.
 */
class RequestOperationsSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(MasterDataSeeder::class);
    }
}
