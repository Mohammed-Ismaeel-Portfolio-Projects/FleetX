<?php

namespace Tests\Feature\Shipment;

use App\Models\Company;
use App\Models\DeliveryProof;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\User;
use App\Support\DriverPodStorage;
use App\Support\ShipmentStatus;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * INT-006 — Client POD retrieval from private local disk.
 */
class ClientShipmentPodTest extends TestCase
{
    use RefreshDatabase;

    private Role $clientRole;

    private Role $dispatcherRole;

    private Role $driverRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->clientRole = Role::where('name', 'client')->firstOrFail();
        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();
        Storage::fake('local');
    }

    public function test_owner_client_can_retrieve_pod_file(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);
        $bytes = $this->storePod($shipment, 'POD-BYTES-OWNER');

        $response = $this->actingAs($client, 'api')
            ->get('/api/v1/client/shipments/'.$shipment->id.'/pod', [
                'Accept' => '*/*',
            ]);

        $response->assertOk();
        $this->assertSame($bytes, $this->responseBytes($response));
        $this->assertStringContainsString(
            'proof-of-delivery',
            (string) $response->headers->get('Content-Disposition')
        );
        $this->assertStringNotContainsString('driver-pod/', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringNotContainsString(storage_path(), $response->getContent());
        $this->assertStringNotContainsString('"disk"', $response->getContent());
        $this->assertStringNotContainsString('image_url', $response->getContent());
    }

    public function test_client_retrieves_bytes_stored_by_driver_pod_storage(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);
        $upload = UploadedFile::fake()->create('pod.jpg', 24, 'image/jpeg');
        $key = app(DriverPodStorage::class)->storePhoto($shipment->id, $upload);
        DeliveryProof::query()->create([
            'shipment_id' => $shipment->id,
            'image_url' => $key,
            'signature' => 'driver-pod/'.$shipment->id.'/signature.png',
            'confirmed_at' => now(),
        ]);

        $response = $this->actingAs($client, 'api')
            ->get('/api/v1/client/shipments/'.$shipment->id.'/pod', [
                'Accept' => '*/*',
            ]);

        $response->assertOk();
        $this->assertSame(Storage::disk('local')->get($key), $this->responseBytes($response));
        $this->assertStringNotContainsString($key, $response->getContent());
    }

    public function test_owner_client_pod_status_is_available(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);
        $this->storePod($shipment, 'POD-BYTES-STATUS');

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/pod/status')
            ->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonMissingPath('data.image_url')
            ->assertJsonMissingPath('data.signature')
            ->assertJsonMissingPath('data.disk')
            ->assertJsonMissingPath('data.path')
            ->assertJsonMissingPath('data.key');
    }

    public function test_cross_company_client_cannot_retrieve_pod(): void
    {
        $ownerCompany = Company::factory()->create();
        $foreignCompany = Company::factory()->create();
        $ownerShipment = $this->makeShipment($ownerCompany);
        $this->storePod($ownerShipment, 'SECRET-POD-BYTES');
        $foreignClient = $this->makeClient($foreignCompany);

        $response = $this->actingAs($foreignClient, 'api')
            ->get('/api/v1/client/shipments/'.$ownerShipment->id.'/pod', [
                'Accept' => '*/*',
            ]);

        $response->assertNotFound();
        $this->assertStringNotContainsString('SECRET-POD-BYTES', $response->getContent());
        $this->assertStringNotContainsString('driver-pod/', $response->getContent());
    }

    public function test_cross_company_client_status_does_not_reveal_pod(): void
    {
        $ownerCompany = Company::factory()->create();
        $foreignCompany = Company::factory()->create();
        $ownerShipment = $this->makeShipment($ownerCompany);
        $this->storePod($ownerShipment, 'SECRET-POD-BYTES');
        $foreignClient = $this->makeClient($foreignCompany);

        $this->actingAs($foreignClient, 'api')
            ->getJson('/api/v1/client/shipments/'.$ownerShipment->id.'/pod/status')
            ->assertNotFound()
            ->assertJsonMissingPath('data.available');
    }

    public function test_missing_pod_returns_not_found_for_file_and_unavailable_status(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);

        $this->actingAs($client, 'api')
            ->get('/api/v1/client/shipments/'.$shipment->id.'/pod', [
                'Accept' => '*/*',
            ])
            ->assertNotFound()
            ->assertJsonPath('message', 'Proof of delivery is not available');

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/pod/status')
            ->assertOk()
            ->assertJsonPath('data.available', false);
    }

    public function test_guessed_shipment_id_does_not_return_file(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $this->storePod($this->makeShipment($company), 'HIDDEN');

        $response = $this->actingAs($client, 'api')
            ->get('/api/v1/client/shipments/'.(string) Str::uuid().'/pod', [
                'Accept' => '*/*',
            ]);

        $response->assertNotFound();
        $this->assertStringNotContainsString('HIDDEN', $response->getContent());
    }

    public function test_guessed_delivery_proof_id_is_not_an_access_path(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);
        $proof = $this->storeProofRecord($shipment, 'driver-pod/'.$shipment->id.'/photo.jpg', 'HIDDEN');

        $this->actingAs($client, 'api')
            ->get('/api/v1/client/shipments/'.$proof->id.'/pod', [
                'Accept' => '*/*',
            ])
            ->assertNotFound();
    }

    public function test_storage_query_parameters_are_rejected(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);
        $this->storePod($shipment, 'POD');

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/pod?path=driver-pod/secret.jpg')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['path']);
    }

    public function test_unlinked_client_is_forbidden(): void
    {
        $company = Company::factory()->create();
        $shipment = $this->makeShipment($company);
        $this->storePod($shipment, 'POD');
        $client = User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => null,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($client, 'api')
            ->get('/api/v1/client/shipments/'.$shipment->id.'/pod')
            ->assertForbidden();
    }

    public function test_driver_and_dispatcher_are_forbidden(): void
    {
        $company = Company::factory()->create();
        $shipment = $this->makeShipment($company);
        $this->storePod($shipment, 'POD');

        foreach ([$this->driverRole, $this->dispatcherRole] as $role) {
            $user = User::factory()->create([
                'role_id' => $role->id,
                'password' => Hash::make('password'),
            ]);
            $this->actingAs($user, 'api')
                ->get('/api/v1/client/shipments/'.$shipment->id.'/pod')
                ->assertForbidden();
        }
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/client/shipments/'.(string) Str::uuid().'/pod')
            ->assertUnauthorized();
    }

    public function test_shipment_show_still_excludes_delivery_proof_after_pod_exists(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);
        $this->storePod($shipment, 'POD-SHOW');

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id);

        $response->assertOk();
        $this->assertArrayNotHasKey('delivery_proof', $response->json('data'));
        $this->assertStringNotContainsString('driver-pod/', $response->getContent());
        $this->assertStringNotContainsString('image_url', $response->getContent());
    }

    public function test_client_tracking_still_works_when_pod_exists(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);
        $this->storePod($shipment, 'POD-TRACK');

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking')
            ->assertOk()
            ->assertJsonPath('data.shipment_id', $shipment->id)
            ->assertJsonMissingPath('data.delivery_proof');
    }

    public function test_unsafe_stored_key_is_not_served(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);
        DeliveryProof::query()->create([
            'shipment_id' => $shipment->id,
            'image_url' => storage_path('app/private/driver-pod/escape.jpg'),
            'signature' => 'driver-pod/'.$shipment->id.'/signature.png',
            'confirmed_at' => now(),
        ]);

        $this->actingAs($client, 'api')
            ->get('/api/v1/client/shipments/'.$shipment->id.'/pod')
            ->assertNotFound();
    }

    private function responseBytes($response): string
    {
        try {
            return $response->streamedContent();
        } catch (\Throwable) {
            return (string) $response->getContent();
        }
    }

    private function makeClient(Company $company): User
    {
        return User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => $company->id,
            'password' => Hash::make('password'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeShipment(Company $company, array $overrides = []): Shipment
    {
        $status = Status::firstOrCreate(
            ['name' => ShipmentStatus::DELIVERED, 'type' => ShipmentStatus::TYPE],
            ['name' => ShipmentStatus::DELIVERED, 'type' => ShipmentStatus::TYPE]
        );

        return Shipment::factory()->create(array_merge([
            'customer_company_id' => $company->id,
            'status_id' => $status->id,
            'priority' => 'normal',
            'pickup_city' => 'Warsaw',
            'pickup_country' => 'PL',
            'pickup_company' => 'Pickup Co',
            'delivery_city' => 'Krakow',
            'delivery_country' => 'PL',
            'delivery_company' => 'Delivery Co',
        ], $overrides));
    }

    private function storePod(Shipment $shipment, string $bytes): string
    {
        $key = 'driver-pod/'.$shipment->id.'/photo.jpg';
        $this->storeProofRecord($shipment, $key, $bytes);

        return $bytes;
    }

    private function storeProofRecord(Shipment $shipment, string $key, string $bytes): DeliveryProof
    {
        Storage::disk('local')->put($key, $bytes);

        return DeliveryProof::query()->create([
            'shipment_id' => $shipment->id,
            'image_url' => $key,
            'signature' => 'driver-pod/'.$shipment->id.'/signature.png',
            'confirmed_at' => now(),
        ]);
    }
}
