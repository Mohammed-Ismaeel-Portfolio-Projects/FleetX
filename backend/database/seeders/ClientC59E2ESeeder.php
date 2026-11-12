<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentOrder;
use App\Models\Status;
use App\Models\User;
use App\Enums\CustomerType;
use App\Support\ShipmentOrderStatus;
use App\Support\ShipmentStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

/**
 * Deterministic Client App C5.9 runtime fixtures.
 *
 * Does not weaken auth — normal hashed password + JWT login.
 * Safe to re-run (upsert by stable emails / company code).
 */
class ClientC59E2ESeeder extends Seeder
{
    public const CLIENT_EMAIL = 'client@fleetx.com';

    public const CLIENT_PASSWORD = 'Client@123';

    public const DISPATCHER_EMAIL = 'dispatcher@fleetx.com';

    public const DISPATCHER_PASSWORD = 'Dispatcher@123';

    public const COMPANY_CODE = 'C59-E2E';

    public const FOREIGN_COMPANY_CODE = 'C59-FOREIGN';

    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            StatusesSeeder::class,
            ShipmentStatusesSeeder::class,
            ShipmentOrderStatusesSeeder::class,
        ]);

        $clientRole = Role::query()->where('name', 'client')->firstOrFail();
        $dispatcherRole = Role::query()->where('name', 'dispatcher')->firstOrFail();

        $company = Company::query()->where('company_code', self::COMPANY_CODE)->first()
            ?? Company::factory()->create([
                'company_code' => self::COMPANY_CODE,
                'company_name' => 'C59 E2E Customer',
                'legal_name' => 'C59 E2E Customer Co',
                'customer_type' => CustomerType::STANDARD->value,
            ]);
        if ($company->customer_type === null) {
            $company->update(['customer_type' => CustomerType::STANDARD]);
        }

        $foreignCompany = Company::query()->where('company_code', self::FOREIGN_COMPANY_CODE)->first()
            ?? Company::factory()->create([
                'company_code' => self::FOREIGN_COMPANY_CODE,
                'company_name' => 'C59 Foreign',
                'legal_name' => 'C59 Foreign Co',
                'customer_type' => CustomerType::STANDARD->value,
            ]);
        if ($foreignCompany->customer_type === null) {
            $foreignCompany->update(['customer_type' => CustomerType::STANDARD]);
        }
        $dispatcher = User::query()->updateOrCreate(
            ['email' => self::DISPATCHER_EMAIL],
            [
                'name' => 'Dispatcher Demo',
                'password' => Hash::make(self::DISPATCHER_PASSWORD),
                'role_id' => $dispatcherRole->id,
                'company_id' => null,
            ]
        );

        $client = User::query()->updateOrCreate(
            ['email' => self::CLIENT_EMAIL],
            [
                'name' => 'Client Demo',
                'password' => Hash::make(self::CLIENT_PASSWORD),
                'role_id' => $clientRole->id,
                'company_id' => $company->id,
            ]
        );

        $inTransit = Status::query()
            ->where('type', ShipmentStatus::TYPE)
            ->where('name', ShipmentStatus::IN_TRANSIT)
            ->first()
            ?? Status::query()->where('type', ShipmentStatus::TYPE)->where('name', 'In Transit')->first()
            ?? Status::query()->where('type', 'shipment')->where('name', 'Draft')->firstOrFail();

        $shipment = Shipment::query()->updateOrCreate(
            ['shipment_number' => 'SHP-C59-E2E-001'],
            [
                'customer_company_id' => $company->id,
                'status_id' => $inTransit->id,
                'priority' => 'normal',
                'cargo_type' => 'General Cargo',
                'cargo_description' => 'C5.9 runtime fixture shipment',
                'pickup_company' => 'C59 Origin Hub',
                'pickup_city' => 'Berlin',
                'pickup_country' => 'DE',
                'pickup_address' => '1 Origin Street',
                'delivery_company' => 'C59 Destination Hub',
                'delivery_city' => 'Munich',
                'delivery_country' => 'DE',
                'delivery_address' => '2 Destination Ave',
                'planned_delivery_date' => now()->addDays(3)->toDateString(),
            ]
        );

        $foreignShipment = Shipment::query()->updateOrCreate(
            ['shipment_number' => 'SHP-C59-FOREIGN-001'],
            [
                'customer_company_id' => $foreignCompany->id,
                'status_id' => $inTransit->id,
                'priority' => 'normal',
                'cargo_type' => 'General Cargo',
                'pickup_city' => 'Paris',
                'pickup_country' => 'FR',
                'delivery_city' => 'Lyon',
                'delivery_country' => 'FR',
            ]
        );

        $submitted = Status::query()->firstOrCreate(
            ['name' => ShipmentOrderStatus::SUBMITTED, 'type' => ShipmentOrderStatus::TYPE],
            ['name' => ShipmentOrderStatus::SUBMITTED, 'type' => ShipmentOrderStatus::TYPE]
        );

        $request = ShipmentOrder::query()->updateOrCreate(
            ['request_number' => 'REQ-C59-E2E-001'],
            [
                'customer_company_id' => $company->id,
                'submitted_by_user_id' => $client->id,
                'status_id' => $submitted->id,
                'submission_revision' => 1,
                'cargo_type' => 'General Cargo',
                'cargo_description' => 'C5.9 runtime fixture request',
                'pickup_company' => 'C59 Pickup Co',
                'pickup_city' => 'Berlin',
                'pickup_country' => 'DE',
                'delivery_company' => 'C59 Delivery Co',
                'delivery_city' => 'Munich',
                'delivery_country' => 'DE',
            ]
        );

        $notification = Notification::query()->updateOrCreate(
            [
                'user_id' => $client->id,
                'title' => 'C59 E2E shipment update',
            ],
            [
                'message' => 'Your shipment SHP-C59-E2E-001 has an update.',
                'type' => 'shipment.status',
                'is_read' => false,
                'action_kind' => 'shipment',
                'action_id' => (string) $shipment->id,
            ]
        );

        $conversation = Conversation::query()
            ->where('type', Conversation::TYPE_DISPATCHER_CUSTOMER)
            ->where('shipment_id', $shipment->id)
            ->where('created_by', $dispatcher->id)
            ->first();

        if ($conversation === null) {
            $conversation = Conversation::query()->create([
                'type' => Conversation::TYPE_DISPATCHER_CUSTOMER,
                'shipment_id' => $shipment->id,
                'status' => Conversation::STATUS_OPEN,
                'created_by' => $dispatcher->id,
            ]);
        }

        ConversationParticipant::query()->firstOrCreate(
            [
                'conversation_id' => $conversation->id,
                'user_id' => $dispatcher->id,
            ],
            ['participant_role' => ConversationParticipant::ROLE_DISPATCHER]
        );

        ConversationParticipant::query()->firstOrCreate(
            [
                'conversation_id' => $conversation->id,
                'user_id' => $client->id,
            ],
            ['participant_role' => ConversationParticipant::ROLE_CLIENT]
        );

        $seedMessage = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('body', 'C59 E2E seed message from support')
            ->first();

        if ($seedMessage === null) {
            Message::query()->create([
                'conversation_id' => $conversation->id,
                'sender_user_id' => $dispatcher->id,
                'body' => 'C59 E2E seed message from support',
            ]);
        }

        $fixture = [
            'generated_at' => now()->toIso8601String(),
            'client_email' => self::CLIENT_EMAIL,
            'company_code' => self::COMPANY_CODE,
            'company_id' => (string) $company->id,
            'foreign_company_id' => (string) $foreignCompany->id,
            'shipment_id' => (string) $shipment->id,
            'foreign_shipment_id' => (string) $foreignShipment->id,
            'request_id' => (string) $request->id,
            'notification_id' => (int) $notification->id,
            'conversation_id' => (string) $conversation->id,
            // Password intentionally omitted — use env / documented seed credential.
        ];

        $path = storage_path('app/c59-client-e2e-fixture.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        $this->command?->info('C5.9 Client E2E fixtures ready ('.$path.').');
    }
}
