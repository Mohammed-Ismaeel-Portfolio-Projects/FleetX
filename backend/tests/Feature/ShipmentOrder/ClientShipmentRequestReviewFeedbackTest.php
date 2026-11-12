<?php

namespace Tests\Feature\ShipmentOrder;

use App\Models\Company;
use App\Models\Role;
use App\Models\ShipmentOrder;
use App\Models\ShipmentOrderStatusHistory;
use App\Models\Status;
use App\Models\User;
use App\Support\ShipmentOrderStatus;
use Database\Seeders\RolesSeeder;
use Database\Seeders\ShipmentOrderStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * C5.3 — Client show review_feedback from shipment_order_status_history.reason.
 */
class ClientShipmentRequestReviewFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private Role $clientRole;

    private Status $statusDraft;

    private Status $statusSubmitted;

    private Status $statusUnderReview;

    private Status $statusChangesRequested;

    private Status $statusRejected;

    private Status $statusApproved;

    private Status $statusConverted;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->seed(ShipmentOrderStatusesSeeder::class);

        $this->clientRole = Role::where('name', 'client')->firstOrFail();

        $this->statusDraft = $this->orderStatus(ShipmentOrderStatus::DRAFT);
        $this->statusSubmitted = $this->orderStatus(ShipmentOrderStatus::SUBMITTED);
        $this->statusUnderReview = $this->orderStatus(ShipmentOrderStatus::UNDER_REVIEW);
        $this->statusChangesRequested = $this->orderStatus(ShipmentOrderStatus::CHANGES_REQUESTED);
        $this->statusRejected = $this->orderStatus(ShipmentOrderStatus::REJECTED);
        $this->statusApproved = $this->orderStatus(ShipmentOrderStatus::APPROVED);
        $this->statusConverted = $this->orderStatus(ShipmentOrderStatus::CONVERTED);
    }

    public function test_changes_requested_exposes_latest_reason(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeOrder($company, [
            'status_id' => $this->statusChangesRequested->id,
            'dispatcher_notes' => 'INTERNAL — must never leak',
        ]);

        $this->appendHistory($order, $this->statusUnderReview, $this->statusChangesRequested, 'Please provide cargo dimensions.');

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.$order->id);

        $response->assertOk()
            ->assertJsonPath('data.status.name', 'changes_requested')
            ->assertJsonPath('data.review_feedback.message', 'Please provide cargo dimensions.');
    }

    public function test_rejected_exposes_latest_reason(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeOrder($company, [
            'status_id' => $this->statusRejected->id,
            'dispatcher_notes' => 'Ops rejection memo',
        ]);

        $this->appendHistory(
            $order,
            $this->statusUnderReview,
            $this->statusRejected,
            'Requested service area is unsupported.'
        );

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.status.name', 'rejected')
            ->assertJsonPath('data.review_feedback.message', 'Requested service area is unsupported.');
    }

    public function test_submitted_hides_feedback(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeOrder($company, ['status_id' => $this->statusSubmitted->id]);

        $this->appendHistory($order, $this->statusDraft, $this->statusSubmitted, 'Should not surface for submitted');

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.$order->id);

        $response->assertOk()
            ->assertJsonPath('data.status.name', 'submitted')
            ->assertJsonPath('data.review_feedback', null);
    }

    public function test_draft_hides_feedback(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeOrder($company, [
            'status_id' => $this->statusDraft->id,
            'submission_revision' => 0,
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.$order->id);

        $response->assertOk()
            ->assertJsonPath('data.status.name', 'draft')
            ->assertJsonPath('data.review_feedback', null);
    }

    public function test_approved_and_converted_hide_feedback(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        foreach ([$this->statusApproved, $this->statusConverted] as $status) {
            $order = $this->makeOrder($company, ['status_id' => $status->id]);
            $this->appendHistory($order, $this->statusUnderReview, $status, 'Internal approval note');

            $this->actingAs($client, 'api')
                ->getJson('/api/v1/client/shipment-requests/'.$order->id)
                ->assertOk()
                ->assertJsonPath('data.status.name', $status->name)
                ->assertJsonPath('data.review_feedback', null);
        }
    }

    public function test_missing_reason_returns_null_feedback(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeOrder($company, ['status_id' => $this->statusChangesRequested->id]);

        $this->appendHistory($order, $this->statusUnderReview, $this->statusChangesRequested, null);
        $this->appendHistory($order, $this->statusUnderReview, $this->statusChangesRequested, '   ');

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.review_feedback', null);
    }

    public function test_latest_relevant_changes_requested_reason_is_selected(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeOrder($company, ['status_id' => $this->statusChangesRequested->id]);

        $older = $this->appendHistory(
            $order,
            $this->statusUnderReview,
            $this->statusChangesRequested,
            'Older change request'
        );
        $older->forceFill(['created_at' => now()->subMinutes(10)])->save();

        $newer = $this->appendHistory(
            $order,
            $this->statusUnderReview,
            $this->statusChangesRequested,
            'Latest change request'
        );
        $newer->forceFill(['created_at' => now()->subMinute()])->save();

        $this->appendHistory($order, $this->statusDraft, $this->statusSubmitted, 'Unrelated submitted reason');

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.review_feedback.message', 'Latest change request');
    }

    public function test_dispatcher_notes_never_leak(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeOrder($company, [
            'status_id' => $this->statusChangesRequested->id,
            'dispatcher_notes' => 'SECRET_DISPATCHER_NOTE',
        ]);

        $this->appendHistory(
            $order,
            $this->statusUnderReview,
            $this->statusChangesRequested,
            'Please update delivery contact.'
        );

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.$order->id);

        $response->assertOk()
            ->assertJsonPath('data.review_feedback.message', 'Please update delivery contact.');

        $data = $response->json('data');
        $content = $response->getContent();

        $this->assertArrayNotHasKey('dispatcher_notes', $data);
        $this->assertStringNotContainsString('SECRET_DISPATCHER_NOTE', $content);
        $this->assertStringNotContainsString('dispatcher_notes', $content);
    }

    public function test_raw_history_and_actors_never_leak(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeOrder($company, ['status_id' => $this->statusRejected->id]);

        $this->appendHistory(
            $order,
            $this->statusUnderReview,
            $this->statusRejected,
            'Outside service area.',
            $client->id
        );

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.$order->id);

        $response->assertOk()
            ->assertJsonPath('data.review_feedback.message', 'Outside service area.');

        $data = $response->json('data');
        $this->assertArrayNotHasKey('status_histories', $data);
        $this->assertArrayNotHasKey('status_history', $data);
        $this->assertArrayNotHasKey('history', $data);
        $this->assertArrayNotHasKey('changed_by', $data);
        $this->assertArrayNotHasKey('changed_by_user_id', $data);
        $this->assertArrayNotHasKey('actor', $data);
        $this->assertArrayNotHasKey('actor_id', $data);
        $this->assertArrayNotHasKey('reviewer', $data);
        $this->assertArrayNotHasKey('reviewer_id', $data);
        $this->assertArrayNotHasKey('from_status_id', $data);
        $this->assertArrayNotHasKey('to_status_id', $data);
    }

    public function test_company_isolation_remains_intact(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $clientA = $this->makeClient($companyA);
        $foreign = $this->makeOrder($companyB, ['status_id' => $this->statusChangesRequested->id]);
        $this->appendHistory(
            $foreign,
            $this->statusUnderReview,
            $this->statusChangesRequested,
            'Foreign company reason'
        );

        $this->actingAs($clientA, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.$foreign->id)
            ->assertNotFound();
    }

    public function test_foreign_uuid_remains_404(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.Str::uuid()->toString())
            ->assertNotFound();
    }

    public function test_list_does_not_include_review_feedback(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeOrder($company, ['status_id' => $this->statusChangesRequested->id]);
        $this->appendHistory(
            $order,
            $this->statusUnderReview,
            $this->statusChangesRequested,
            'List must not expose this'
        );

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests');

        $response->assertOk();
        $item = collect($response->json('data.data'))->firstWhere('id', $order->id);
        $this->assertNotNull($item);
        $this->assertArrayNotHasKey('review_feedback', $item);
        $this->assertArrayNotHasKey('dispatcher_notes', $item);
        $this->assertArrayNotHasKey('status_history', $item);
    }

    private function orderStatus(string $name): Status
    {
        return Status::where('name', $name)
            ->where('type', ShipmentOrderStatus::TYPE)
            ->firstOrFail();
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
    private function makeOrder(Company $company, array $overrides = []): ShipmentOrder
    {
        return ShipmentOrder::factory()->create(array_merge([
            'customer_company_id' => $company->id,
            'status_id' => $this->statusSubmitted->id,
            'submission_revision' => 1,
            'pickup_city' => 'Warsaw',
            'pickup_country' => 'PL',
            'pickup_company' => 'Pickup Co',
            'delivery_city' => 'Krakow',
            'delivery_country' => 'PL',
            'delivery_company' => 'Delivery Co',
            'requested_pickup_date' => now()->addDay()->toDateString(),
            'requested_delivery_date' => now()->addDays(3)->toDateString(),
        ], $overrides));
    }

    private function appendHistory(
        ShipmentOrder $order,
        Status $from,
        Status $to,
        ?string $reason,
        ?int $changedByUserId = null
    ): ShipmentOrderStatusHistory {
        return ShipmentOrderStatusHistory::query()->create([
            'shipment_order_id' => $order->id,
            'from_status_id' => $from->id,
            'to_status_id' => $to->id,
            'changed_by_user_id' => $changedByUserId,
            'reason' => $reason,
        ]);
    }
}
