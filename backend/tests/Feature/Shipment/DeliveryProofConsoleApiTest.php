<?php

namespace Tests\Feature\Shipment;

use App\Models\Assignment;
use App\Models\Company;
use App\Models\DeliveryProof;
use App\Models\Driver;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\User;
use App\Support\DriverExecutionPhase;
use App\Support\DriverPodStorage;
use App\Support\ShipmentStatus;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeliveryProofConsoleApiTest extends TestCase
{
    use RefreshDatabase;

    private Role $clientRole;

    private Role $dispatcherRole;

    private Status $deliveredStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->clientRole = Role::where('name', 'client')->firstOrFail();
        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $this->deliveredStatus = Status::firstOrCreate(
            ['name' => ShipmentStatus::DELIVERED, 'type' => 'shipment'],
            ['name' => ShipmentStatus::DELIVERED, 'type' => 'shipment']
        );
        Storage::fake('local');
    }

    public function test_dispatcher_can_view_delivery_proof_evidence(): void
    {
        $company = Company::factory()->create();
        $shipment = Shipment::factory()->create([
            'customer_company_id' => $company->id,
            'status_id' => $this->deliveredStatus->id,
        ]);

        $proof = $this->createFullProof($shipment);

        $dispatcher = User::factory()->create(['role_id' => $this->dispatcherRole->id]);
        $token = $this->loginToken($dispatcher->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/shipments/'.$shipment->id.'/delivery-proof')
            ->assertOk()
            ->assertJsonPath('data.receiver.name', 'Ahmed Khalil')
            ->assertJsonPath('data.receiver.role', 'Warehouse Manager')
            ->assertJsonPath('data.condition.status', 'good')
            ->assertJsonPath('data.notes', 'Received in perfect condition.')
            ->assertJsonPath('data.location.latitude', 24.7136)
            ->assertJsonPath('data.location.longitude', 46.6753)
            ->assertJsonPath('data.photo.available', true)
            ->assertJsonPath('data.signature.available', true);
    }

    public function test_client_can_view_own_shipment_delivery_proof(): void
    {
        $company = Company::factory()->create();
        $clientUser = User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $this->clientRole->id,
            'password' => Hash::make('password'),
        ]);

        $shipment = Shipment::factory()->create([
            'customer_company_id' => $company->id,
            'status_id' => $this->deliveredStatus->id,
        ]);

        $this->createFullProof($shipment);

        $token = $this->loginToken($clientUser->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/delivery-proof')
            ->assertOk()
            ->assertJsonPath('data.receiver.name', 'Ahmed Khalil')
            ->assertJsonPath('data.condition.status', 'good')
            ->assertJsonPath('data.photo.available', true);
    }

    public function test_cross_company_client_cannot_access_delivery_proof(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $clientB = User::factory()->create([
            'company_id' => $companyB->id,
            'role_id' => $this->clientRole->id,
            'password' => Hash::make('password'),
        ]);

        $shipmentA = Shipment::factory()->create([
            'customer_company_id' => $companyA->id,
            'status_id' => $this->deliveredStatus->id,
        ]);

        $this->createFullProof($shipmentA);

        $token = $this->loginToken($clientB->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/client/shipments/'.$shipmentA->id.'/delivery-proof')
            ->assertNotFound();
    }

    private function createFullProof(Shipment $shipment): DeliveryProof
    {
        $photoUpload = UploadedFile::fake()->create('photo.jpg', 30, 'image/jpeg');
        $sigUpload = UploadedFile::fake()->create('signature.json', 5, 'application/json');

        $storage = app(DriverPodStorage::class);
        $photoKey = $storage->storePhoto($shipment->id, $photoUpload);
        $sigKey = $storage->storeSignature($shipment->id, $sigUpload);

        return DeliveryProof::create([
            'shipment_id' => $shipment->id,
            'receiver_name' => 'Ahmed Khalil',
            'receiver_role' => 'Warehouse Manager',
            'condition_status' => 'good',
            'delivery_notes' => 'Received in perfect condition.',
            'latitude' => 24.7136,
            'longitude' => 46.6753,
            'image_url' => $photoKey,
            'signature' => $sigKey,
            'confirmed_at' => now(),
        ]);
    }

    private function loginToken(string $email): string
    {
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'password',
        ]);
        $login->assertOk();

        return $login->json('data.access_token');
    }
}
