<?php

namespace Database\Seeders;

use App\Actions\ShipmentOrder\CalculateShipmentOrderPlannedRouteAction;
use App\Actions\ShipmentOrder\ConvertShipmentOrderToShipmentAction;
use App\Actions\Assignment\CreateAssignmentAction;
use App\Enums\CustomerType;
use App\Models\Assignment;
use App\Models\Company;
use App\Models\Driver;
use App\Models\FuelType;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentOrder;
use App\Models\Status;
use App\Models\TrackingState;
use App\Models\Trailer;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Support\EngineEnrollmentStatus;
use App\Support\ShipmentOrderStatus;
use App\Support\ShipmentStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure required base lookup seeders have executed
        $this->call([
            RolesSeeder::class,
            StatusesSeeder::class,
            ShipmentStatusesSeeder::class,
            ShipmentOrderStatusesSeeder::class,
            VehicleTypeSeeder::class,
            FuelTypeSeeder::class,
            VehicleStatusSeeder::class,
            PricingPolicySeeder::class,
        ]);

        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $driverRole = Role::where('name', 'driver')->firstOrFail();
        $clientRole = Role::where('name', 'client')->firstOrFail();

        // 2. Seed System Users (Admin & Dispatcher)
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@fleetx.com'],
            [
                'name' => 'Sarah Admin',
                'password' => Hash::make('password123'),
                'role_id' => $adminRole->id,
            ]
        );

        $dispatcherUser = User::firstOrCreate(
            ['email' => 'dispatcher@fleetx.io'],
            [
                'name' => 'Head Dispatcher',
                'password' => Hash::make('password123'),
                'role_id' => $dispatcherRole->id,
            ]
        );

        // 3. Seed Logistics Customer Companies
        $companiesData = [
            ['code' => 'CMP-1001', 'name' => 'Damascus Logistics Group', 'city' => 'Damascus', 'country' => 'SY', 'address' => 'Baghdad Street, Logistics Building 4', 'phone' => '+963 11 2234567', 'email' => 'contact@damascuslogistics.sy', 'customer_type' => CustomerType::CORPORATE],
            ['code' => 'CMP-1002', 'name' => 'Sham Freight & Express', 'city' => 'Damascus', 'country' => 'SY', 'address' => 'Mezzah Industrial Zone 12', 'phone' => '+963 11 6123456', 'email' => 'info@shamfreight.sy', 'customer_type' => CustomerType::STANDARD],
            ['code' => 'CMP-1003', 'name' => 'Aleppo Industrial Supplies', 'city' => 'Aleppo', 'country' => 'SY', 'address' => 'Sheikh Najjar Industrial City Zone 2', 'phone' => '+963 21 4455667', 'email' => 'ops@aleppoind.sy', 'customer_type' => CustomerType::VIP],
            ['code' => 'CMP-1004', 'name' => 'Tartus Port Terminal Services', 'city' => 'Tartus', 'country' => 'SY', 'address' => 'Port Main Gate Road 8', 'phone' => '+963 43 312345', 'email' => 'cargo@tartusport.sy', 'customer_type' => CustomerType::LONG_TERM],
            ['code' => 'CMP-1005', 'name' => 'Latakia Container Lines', 'city' => 'Latakia', 'country' => 'SY', 'address' => 'Harbor Avenue Dock 4', 'phone' => '+963 41 234567', 'email' => 'support@latakiacontainers.sy', 'customer_type' => CustomerType::CORPORATE],
            ['code' => 'CMP-1006', 'name' => 'Homs Trade & Transport', 'city' => 'Homs', 'country' => 'SY', 'address' => 'Hisyah Industrial City Block 5', 'phone' => '+963 31 554433', 'email' => 'dispatch@homstrade.sy', 'customer_type' => CustomerType::STANDARD],
            ['code' => 'CMP-1007', 'name' => 'Syrian Pharma Distributors', 'city' => 'Damascus', 'country' => 'SY', 'address' => 'Adra Industrial Zone Park 3', 'phone' => '+963 11 8877665', 'email' => 'contact@syrianpharma.sy', 'customer_type' => CustomerType::VIP],
            ['code' => 'CMP-1008', 'name' => 'Barada Commercial Corp', 'city' => 'Daraa', 'country' => 'SY', 'address' => 'Border Trade Zone 14', 'phone' => '+963 15 443322', 'email' => 'info@baradacorp.sy', 'customer_type' => CustomerType::LONG_TERM],
        ];

        $companies = [];
        $activeCompanyStatus = Status::where('type', 'company')->where('name', 'active')->first()
            ?? Status::firstOrCreate(['name' => 'active', 'type' => 'company']);

        foreach ($companiesData as $c) {
            $company = Company::updateOrCreate(
                ['company_code' => $c['code']],
                [
                    'company_name' => $c['name'],
                    'legal_name' => $c['name'] . ' Ltd',
                    'status_id' => $activeCompanyStatus->id,
                    'primary_email' => $c['email'],
                    'primary_phone' => $c['phone'],
                    'address_line_1' => $c['address'],
                    'postal_code' => '10115',
                    'city' => $c['city'],
                    'country' => $c['country'],
                    'currency_code' => 'SYP',
                    'customer_type' => $c['customer_type'] ?? CustomerType::STANDARD,
                    'is_active' => true,
                ]
            );

            $clientIndex = count($companies) + 1;
            $clientEmail = "client{$clientIndex}@fleetx.com";

            $clientUserData = [
                'name' => "{$c['name']} Manager",
                'password' => Hash::make('Client@123'),
                'role_id' => $clientRole->id,
            ];
            if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'company_id')) {
                $clientUserData['company_id'] = $company->id;
            }

            User::firstOrCreate(
                ['email' => $clientEmail],
                $clientUserData
            );

            $companies[] = $company;
        }

        // 4. Driver & Vehicle Statuses
        $driverAvailableStatus = Status::where('type', 'driver')->where('name', 'Available')->first()
            ?? Status::firstOrCreate(['name' => 'Available', 'type' => 'driver']);
        $driverAssignedStatus = Status::where('type', 'driver')->where('name', 'Assigned')->first()
            ?? Status::where('type', 'driver')->where('name', 'Busy')->first()
            ?? Status::firstOrCreate(['name' => 'Assigned', 'type' => 'driver']);

        $vehAvailableStatus = Status::where('type', 'vehicle')->where('name', 'Available')->first()
            ?? Status::firstOrCreate(['name' => 'Available', 'type' => 'vehicle']);
        $vehAssignedStatus = Status::where('type', 'vehicle')->where('name', 'Assigned')->first()
            ?? Status::firstOrCreate(['name' => 'Assigned', 'type' => 'vehicle']);
        $vehMaintenanceStatus = Status::where('type', 'vehicle')->where('name', 'Maintenance')->first()
            ?? Status::firstOrCreate(['name' => 'Maintenance', 'type' => 'vehicle']);

        $defaultVehicleType = VehicleType::where('name', 'Tractor Unit')->first()
            ?? VehicleType::firstOrCreate(['name' => 'Tractor Unit'], ['code' => 'TRACTOR_UNIT', 'description' => 'Heavy prime mover.', 'is_active' => true]);
        $defaultFuelType = FuelType::where('name', 'Diesel')->first()
            ?? FuelType::firstOrCreate(['name' => 'Diesel'], ['code' => 'DIESEL', 'is_active' => true]);

        // 5. Seed EXACTLY 30 Drivers (driver1@fleetx.com .. driver30@fleetx.com)
        // Demo-ready drivers are intentionally seeded first.
        // Driver numbering reflects assignment readiness:
        // - driver1@fleetx.com .. driver25@fleetx.com: Available / Ready for assignment (25 available drivers)
        //   - driver1@fleetx.com .. driver15@fleetx.com: Paired with Available Vehicles (VH-20011 .. VH-20025)
        //   - driver16@fleetx.com .. driver20@fleetx.com: Available drivers for maintenance slot allocation
        //   - driver21@fleetx.com .. driver25@fleetx.com: Free available pool drivers
        // - driver26@fleetx.com .. driver30@fleetx.com: Occupied / Assigned drivers for active shipments SHP-20001 .. SHP-20005
        $drivers = [];
        $driverNames = [
            1 => ['Tareq', 'Al-Hassan'], 2 => ['Omar', 'Al-Khatib'], 3 => ['Youssef', 'Al-Masri'],
            4 => ['Ahmad', 'Al-Sayed'], 5 => ['Khaled', 'Al-Bitar'], 6 => ['Bilal', 'Al-Hamwi'],
            7 => ['Fadi', 'Al-Shami'], 8 => ['Sami', 'Al-Halabi'], 9 => ['Ziad', 'Al-Ahmad'],
            10 => ['Hassan', 'Al-Naser'], 11 => ['Majd', 'Al-Din'], 12 => ['Rami', 'Kabbani'],
            13 => ['Hamza', 'Al-Zein'], 14 => ['Mustafa', 'Haddad'], 15 => ['Ibrahim', 'Nasser'],
            16 => ['Suleiman', 'Saleh'], 17 => ['Bassem', 'Farhat'], 18 => ['Nader', 'Mansour'],
            19 => ['Adnan', 'Abbas'], 20 => ['Faris', 'Karam'], 21 => ['Wael', 'Darwish'],
            22 => ['Ghassan', 'Qasim'], 23 => ['Monzer', 'Hamdan'], 24 => ['Zuhair', 'Barakat'],
            25 => ['Anas', 'Safi'], 26 => ['Marwan', 'Jaber'], 27 => ['Nabil', 'Shami'],
            28 => ['Qusai', 'Masri'], 29 => ['Riyad', 'Halabi'], 30 => ['Saad', 'Al-Din'],
        ];

        for ($i = 1; $i <= 30; $i++) {
            $empCode = sprintf('EMP-200%02d', $i);
            $driverEmail = "driver{$i}@fleetx.com";
            [$firstName, $lastName] = $driverNames[$i];

            // Drivers 1..25 are Available / ready for assignment.
            // Drivers 26..30 are Assigned to active operational shipments.
            $isAssigned = ($i >= 26);
            $statusId = $isAssigned ? $driverAssignedStatus->id : $driverAvailableStatus->id;

            $user = User::firstOrCreate(
                ['email' => $driverEmail],
                [
                    'name' => "{$firstName} {$lastName}",
                    'password' => Hash::make('Driver@123'),
                    'role_id' => $driverRole->id,
                ]
            );

            $driver = Driver::updateOrCreate(
                ['employee_number' => $empCode],
                [
                    'user_id' => $user->id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'date_of_birth' => '1988-04-12',
                    'nationality' => 'Syrian',
                    'phone' => sprintf('+963 944 200%03d', $i),
                    'email' => $user->email,
                    'license_number' => sprintf('LIC-SY-200%02d', $i),
                    'license_class' => 'CE',
                    'license_expiry_date' => now()->addYears(3),
                    'medical_certificate_expiry' => now()->addYears(2),
                    'years_of_experience' => 5 + ($i % 10),
                    'status_id' => $statusId,
                    'employment_date' => now()->subYears(3),
                    'is_active' => true,
                ]
            );

            $drivers[$i] = $driver;
        }

        // 6. Seed EXACTLY 25 Vehicles (VH-20001 .. VH-20025)
        // VH-20001 .. VH-20005: Assigned to 5 active shipments, paired with occupied drivers 26..30 (driver26@fleetx.com .. driver30@fleetx.com)
        // VH-20006 .. VH-20010: Maintenance vehicles, driver_id = NULL, status = Maintenance
        // VH-20011 .. VH-20025: Available vehicles, paired with available drivers 1..15 (driver1@fleetx.com .. driver15@fleetx.com)
        $makes = ['Volvo', 'MAN', 'Scania', 'Mercedes-Benz', 'DAF', 'Iveco', 'Renault'];
        $models = ['FH16 750', 'TGX 18.640', 'S500 V8', 'Actros 1853', 'XF 530', 'S-Way 570', 'T High 520'];

        $vehicles = [];
        for ($i = 1; $i <= 25; $i++) {
            $vhCode = sprintf('VH-200%02d', $i);
            $fleetNum = sprintf('FX-200%02d', $i);
            $plateNum = sprintf('SY-FX-20%02d', $i);
            $vin = sprintf('1FVFLEETX200000%02d', $i);

            if ($i <= 5) {
                // Assigned to 5 active shipments: paired with Assigned drivers 26..30
                $statusId = $vehAssignedStatus->id;
                $driverId = $drivers[$i + 25]->id; // driver26@fleetx.com .. driver30@fleetx.com
            } elseif ($i <= 10) {
                // Maintenance / Unavailable
                $statusId = $vehMaintenanceStatus->id;
                $driverId = null; // Maintenance vehicles MUST NOT be linked to active drivers!
            } else {
                // Available vehicles paired with available drivers 1..15 (driver1@fleetx.com .. driver15@fleetx.com)
                $statusId = $vehAvailableStatus->id;
                $driverId = $drivers[$i - 10]->id; // driver1@fleetx.com .. driver15@fleetx.com
            }

            $vehicle = Vehicle::updateOrCreate(
                ['operational_code' => $vhCode],
                [
                    'vehicle_type_id' => $defaultVehicleType->id,
                    'driver_id' => $driverId,
                    'fuel_type_id' => $defaultFuelType->id,
                    'fleet_number' => $fleetNum,
                    'plate_number' => $plateNum,
                    'vin' => $vin,
                    'manufacturer' => $makes[($i - 1) % count($makes)],
                    'model' => $models[($i - 1) % count($models)],
                    'production_year' => 2024,
                    'routing_class' => Vehicle::ROUTING_CLASS_HEAVY_TRUCK,
                    'gross_weight_kg' => 40000,
                    'payload_capacity_kg' => 24000,
                    'height_m' => 4.00,
                    'width_m' => 2.55,
                    'length_m' => 16.50,
                    'status_id' => $statusId,
                    'is_active' => true,
                ]
            );

            $vehicles[$i] = $vehicle;
        }

        // 7. Seed EXACTLY 40 Trailers (TR-20001 .. TR-20040)
        // TR-20001 .. TR-20005: Currently used by 5 active shipments
        // TR-20006 .. TR-20040: Available trailers (35 trailers)
        $trlAvailableStatus = Status::where('type', 'trailer')->where('name', 'Available')->first()
            ?? Status::firstOrCreate(['name' => 'Available', 'type' => 'trailer']);
        $trlAssignedStatus = Status::where('type', 'trailer')->where('name', 'In Use')->first()
            ?? Status::where('type', 'trailer')->where('name', 'Assigned')->first()
            ?? Status::firstOrCreate(['name' => 'In Use', 'type' => 'trailer']);

        $trailerTypesList = ['Reefer', 'Dry Van', 'Container Chassis', 'Flatbed', 'Tanker'];
        $trailers = [];

        for ($i = 1; $i <= 40; $i++) {
            $trCode = sprintf('TR-200%02d', $i);
            $fleetNum = sprintf('FT-200%02d', $i);
            $plateNum = sprintf('SY-FT-20%02d', $i);

            // Specific trailer type mapping for the 5 active shipments
            if ($i === 1) $tType = 'Reefer';
            elseif ($i === 2) $tType = 'Dry Van';
            elseif ($i === 3) $tType = 'Container Chassis';
            elseif ($i === 4) $tType = 'Flatbed';
            elseif ($i === 5) $tType = 'Reefer';
            else $tType = $trailerTypesList[($i - 1) % count($trailerTypesList)];

            $statusId = ($i <= 5) ? $trlAssignedStatus->id : $trlAvailableStatus->id;

            $trailer = Trailer::updateOrCreate(
                ['trailer_code' => $trCode],
                [
                    'fleet_number' => $fleetNum,
                    'plate_number' => $plateNum,
                    'trailer_type' => $tType,
                    'manufacturer' => 'Schmitz Cargobull',
                    'production_year' => 2024,
                    'payload_capacity_kg' => 24000,
                    'length_m' => 13.60,
                    'status_id' => $statusId,
                    'is_active' => true,
                ]
            );

            $trailers[$i] = $trailer;
        }

        // 8. Order & Shipment Statuses
        $soSubmittedStatus = Status::where('type', ShipmentOrderStatus::TYPE)->where('name', ShipmentOrderStatus::SUBMITTED)->firstOrFail();
        $soUnderReviewStatus = Status::where('type', ShipmentOrderStatus::TYPE)->where('name', ShipmentOrderStatus::UNDER_REVIEW)->firstOrFail();
        $soApprovedStatus = Status::where('type', ShipmentOrderStatus::TYPE)->where('name', ShipmentOrderStatus::APPROVED)->firstOrFail();

        $shpInTransitStatus = Status::where('type', 'shipment')->where('name', ShipmentStatus::IN_TRANSIT)->firstOrFail();
        $shpAssignedStatus = Status::where('type', 'shipment')->where('name', ShipmentStatus::ASSIGNED)->firstOrFail();

        $asgInOpStatus = Status::where('type', 'assignment')->where('name', 'In Operation')->first()
            ?? Status::where('type', 'assignment')->where('name', 'Assigned')->firstOrFail();

        // 9. Seed 10 Requests (REQ-20001 .. REQ-20010)
        // REQ-20001 .. REQ-20005: Approved & converted to active shipments SHP-20001 .. SHP-20005
        // REQ-20006 .. REQ-20010: Non-operational requests for dashboard workflow demo
        $requestsData = [
            // Operational Request 01
            [
                'req_num' => 'REQ-20001', 'company_idx' => 0, 'so_status' => $soApprovedStatus, 'convert' => true,
                'cargo' => 'Pharmaceuticals & Medical Supplies',
                'pickup' => 'Damascus', 'p_lat' => 33.5138, 'p_lng' => 36.2765,
                'delivery' => 'Aleppo', 'd_lat' => 36.2021, 'd_lng' => 37.1343,
                'priority' => 'high', 'driver_idx' => 26, 'veh_idx' => 1, 'trl_idx' => 1,
            ],
            // Operational Request 02
            [
                'req_num' => 'REQ-20002', 'company_idx' => 1, 'so_status' => $soApprovedStatus, 'convert' => true,
                'cargo' => 'Textiles & Cotton Goods',
                'pickup' => 'Damascus', 'p_lat' => 33.5138, 'p_lng' => 36.2765,
                'delivery' => 'Homs', 'd_lat' => 34.7324, 'd_lng' => 36.7137,
                'priority' => 'normal', 'driver_idx' => 27, 'veh_idx' => 2, 'trl_idx' => 2,
            ],
            // Operational Request 03
            [
                'req_num' => 'REQ-20003', 'company_idx' => 2, 'so_status' => $soApprovedStatus, 'convert' => true,
                'cargo' => 'Industrial Machinery & Spare Parts',
                'pickup' => 'Aleppo', 'p_lat' => 36.2021, 'p_lng' => 37.1343,
                'delivery' => 'Latakia', 'd_lat' => 35.5317, 'd_lng' => 35.7915,
                'priority' => 'urgent', 'driver_idx' => 28, 'veh_idx' => 3, 'trl_idx' => 3,
            ],
            // Operational Request 04
            [
                'req_num' => 'REQ-20004', 'company_idx' => 5, 'so_status' => $soApprovedStatus, 'convert' => true,
                'cargo' => 'Structural Steel & Construction Rebar',
                'pickup' => 'Homs', 'p_lat' => 34.7324, 'p_lng' => 36.7137,
                'delivery' => 'Tartus', 'd_lat' => 34.8890, 'd_lng' => 35.8866,
                'priority' => 'normal', 'driver_idx' => 29, 'veh_idx' => 4, 'trl_idx' => 4,
            ],
            // Operational Request 05
            [
                'req_num' => 'REQ-20005', 'company_idx' => 7, 'so_status' => $soApprovedStatus, 'convert' => true,
                'cargo' => 'Fresh Citrus & Dairy Cold Freight',
                'pickup' => 'Damascus', 'p_lat' => 33.5138, 'p_lng' => 36.2765,
                'delivery' => 'Daraa', 'd_lat' => 32.6256, 'd_lng' => 36.1054,
                'priority' => 'high', 'driver_idx' => 30, 'veh_idx' => 5, 'trl_idx' => 5,
            ],
            // Non-operational requests for request workflow UI
            [
                'req_num' => 'REQ-20006', 'company_idx' => 0, 'so_status' => $soSubmittedStatus, 'convert' => false,
                'cargo' => 'Agricultural Seeds & Fertilizers',
                'pickup' => 'Damascus', 'p_lat' => 33.5138, 'p_lng' => 36.2765,
                'delivery' => 'Hama', 'd_lat' => 35.1318, 'd_lng' => 36.7578,
                'priority' => 'normal',
            ],
            [
                'req_num' => 'REQ-20007', 'company_idx' => 3, 'so_status' => $soSubmittedStatus, 'convert' => false,
                'cargo' => 'Port Cargo Containers',
                'pickup' => 'Tartus', 'p_lat' => 34.8890, 'p_lng' => 35.8866,
                'delivery' => 'Aleppo', 'd_lat' => 36.2021, 'd_lng' => 37.1343,
                'priority' => 'normal',
            ],
            [
                'req_num' => 'REQ-20008', 'company_idx' => 4, 'so_status' => $soUnderReviewStatus, 'convert' => false,
                'cargo' => 'Solar Energy Hardware & Batteries',
                'pickup' => 'Latakia', 'p_lat' => 35.5317, 'p_lng' => 35.7915,
                'delivery' => 'Damascus', 'd_lat' => 33.5138, 'd_lng' => 36.2765,
                'priority' => 'high',
            ],
            [
                'req_num' => 'REQ-20009', 'company_idx' => 6, 'so_status' => $soUnderReviewStatus, 'convert' => false,
                'cargo' => 'Commercial Consumer Electronics',
                'pickup' => 'Damascus', 'p_lat' => 33.5138, 'p_lng' => 36.2765,
                'delivery' => 'Homs', 'd_lat' => 34.7324, 'd_lng' => 36.7137,
                'priority' => 'normal',
            ],
            [
                'req_num' => 'REQ-20010', 'company_idx' => 1, 'so_status' => $soApprovedStatus, 'convert' => false,
                'cargo' => 'Refined Chemical Drums',
                'pickup' => 'Damascus', 'p_lat' => 33.5138, 'p_lng' => 36.2765,
                'delivery' => 'Deir ez-Zor', 'd_lat' => 35.3359, 'd_lng' => 40.1408,
                'priority' => 'normal',
            ],
        ];

        /** @var CalculateShipmentOrderPlannedRouteAction $calculateRouteAction */
        $calculateRouteAction = app(CalculateShipmentOrderPlannedRouteAction::class);
        /** @var ConvertShipmentOrderToShipmentAction $convertAction */
        $convertAction = app(ConvertShipmentOrderToShipmentAction::class);
        /** @var CreateAssignmentAction $createAssignmentAction */
        $createAssignmentAction = app(CreateAssignmentAction::class);

        foreach ($requestsData as $item) {
            $company = $companies[$item['company_idx']];

            // Step A: Create ShipmentOrder
            $order = ShipmentOrder::updateOrCreate(
                ['request_number' => $item['req_num']],
                [
                    'customer_company_id' => $company->id,
                    'submitted_by_user_id' => $dispatcherUser->id,
                    'status_id' => $item['so_status']->id,
                    'submission_revision' => 1,
                    'reference_number' => 'REF-' . Str::after($item['req_num'], 'REQ-'),
                    'cargo_type' => $item['cargo'],
                    'cargo_description' => $item['cargo'] . ' freight container',
                    'cargo_weight' => 18500.00,
                    'cargo_volume' => 65.00,
                    'package_count' => 32,
                    'hazardous_cargo' => false,
                    'temperature_controlled' => str_contains($item['cargo'], 'Fresh') || str_contains($item['cargo'], 'Dairy') || str_contains($item['cargo'], 'Pharma'),
                    'pickup_company' => $company->company_name . ' Logistics Center',
                    'pickup_contact' => 'Logistics Manager',
                    'pickup_phone' => $company->primary_phone,
                    'pickup_address' => $company->address_line_1,
                    'pickup_city' => $item['pickup'],
                    'pickup_country' => 'SY',
                    'pickup_latitude' => $item['p_lat'],
                    'pickup_longitude' => $item['p_lng'],
                    'delivery_company' => 'Destination Hub ' . $item['delivery'],
                    'delivery_contact' => 'Receiving Operations',
                    'delivery_phone' => '+963 21 4455667',
                    'delivery_address' => 'Terminal 4, Logistics Zone',
                    'delivery_city' => $item['delivery'],
                    'delivery_country' => 'SY',
                    'delivery_latitude' => $item['d_lat'],
                    'delivery_longitude' => $item['d_lng'],
                    'requested_pickup_date' => now()->subDays(1)->format('Y-m-d'),
                    'requested_delivery_date' => now()->addDays(2)->format('Y-m-d'),
                    'special_instructions' => 'Handle in accordance with FLeetX standard freight guidelines.',
                    'priority' => $item['priority'],
                ]
            );

            // Step B: Calculate Planned Route using GraphHopper Routing Action
            $calculateRouteAction->execute($order);
            $order = $order->fresh();

            // STRICT GRAPHHOPPER VALIDATION REQUIREMENT FOR OPERATIONAL REQUESTS:
            if ($item['convert']) {
                $pointCount = is_array($order->planned_route_geometry) ? count($order->planned_route_geometry) : 0;
                if ($order->planned_route_status !== 'AVAILABLE' || $pointCount <= 2) {
                    throw new \RuntimeException("GraphHopper route calculation failed for operational request {$item['req_num']}. Status: {$order->planned_route_status}, Points: {$pointCount}");
                }
            }

            // Step C: Convert to Operational Shipment if specified
            if ($item['convert']) {
                $shipment = $order->fresh()->convertedShipment ?? $order->fresh()->linkedShipment;

                if ($shipment === null) {
                    // Ensure order is APPROVED for conversion domain action
                    $order->update(['status_id' => $soApprovedStatus->id]);
                    $order = $order->fresh(['status']);

                    $convertResult = $convertAction->execute($dispatcherUser, $order->id);

                    if (! $convertResult->success) {
                        throw new \RuntimeException("Shipment conversion failed for {$item['req_num']}: {$convertResult->message}");
                    }

                    $shipment = $order->fresh()->convertedShipment ?? $order->fresh()->linkedShipment;
                }

                $shpNumber = 'SHP-' . Str::after($item['req_num'], 'REQ-');
                $shipment->update([
                    'shipment_number' => $shpNumber,
                    'status_id' => $shpInTransitStatus->id,
                ]);

                // Step D: Create Assignment via CreateAssignmentAction
                $driver = $drivers[$item['driver_idx']];
                $vehicle = $vehicles[$item['veh_idx']];
                $trailer = $trailers[$item['trl_idx']];
                $asgCode = 'ASG-' . Str::after($item['req_num'], 'REQ-');

                $assignment = Assignment::create([
                    'assignment_code' => $asgCode,
                    'shipment_id' => $shipment->id,
                    'driver_id' => $driver->id,
                    'vehicle_id' => $vehicle->id,
                    'trailer_id' => $trailer->id,
                    'assigned_by' => $dispatcherUser->id,
                    'status_id' => $asgInOpStatus->id,
                    'assigned_at' => now()->subHours(6),
                    'ended_at' => null,
                    'is_active' => true,
                    'engine_enrollment_status' => EngineEnrollmentStatus::ENROLLED,
                    'created_by' => $dispatcherUser->id,
                    'updated_by' => $dispatcherUser->id,
                ]);

                // Step E: Create Live Telemetry State for Active Tracking Page & Live Fleet Map
                $geom = $order->planned_route_geometry;
                $currentCoord = is_array($geom) && count($geom) > 10 ? $geom[10] : [$item['p_lat'], $item['p_lng']];

                TrackingState::updateOrCreate(
                    ['assignment_id' => $assignment->id],
                    [
                        'shipment_id' => $shipment->id,
                        'vehicle_id' => $vehicle->id,
                        'driver_id' => $driver->id,
                        'latitude' => $currentCoord[0],
                        'longitude' => $currentCoord[1],
                        'speed' => 65.0,
                        'movement_status' => 'Moving',
                        'online_status' => 'Online',
                        'signal_quality' => 'Good',
                        'last_update_at' => now(),
                    ]
                );
            }
        }

        $this->command?->info('MasterDataSeeder: Successfully seeded Phase P2 controlled operational dataset (25 vehicles, 30 drivers, 40 trailers, 5 active shipments).');
    }
}
