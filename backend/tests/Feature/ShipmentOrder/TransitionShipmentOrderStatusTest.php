<?php

namespace Tests\Feature\ShipmentOrder;

use App\Actions\ShipmentOrder\TransitionShipmentOrderStatusAction;
use App\Exceptions\DomainException;
use App\Exceptions\InvalidShipmentOrderTransitionException;
use App\Models\ShipmentOrder;
use App\Models\ShipmentOrderStatusHistory;
use App\Models\Status;
use App\Models\User;
use App\Support\ShipmentOrderStatus;
use Database\Seeders\ShipmentOrderStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransitionShipmentOrderStatusTest extends TestCase
{
    use RefreshDatabase;

    private TransitionShipmentOrderStatusAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ShipmentOrderStatusesSeeder::class);
        $this->action = app(TransitionShipmentOrderStatusAction::class);
    }

    private function orderInStatus(string $statusName): ShipmentOrder
    {
        return ShipmentOrder::factory()->withStatus($statusName)->create();
    }

    public function test_valid_transition_draft_to_submitted(): void
    {
        $user = User::factory()->create();
        $order = $this->orderInStatus(ShipmentOrderStatus::DRAFT);

        $result = $this->action->execute($order, ShipmentOrderStatus::SUBMITTED, $user->id);

        $this->assertSame(ShipmentOrderStatus::SUBMITTED, $result->status->name);
    }

    private function actor(): User
    {
        return User::factory()->create();
    }

    public function test_valid_transition_submitted_to_under_review(): void
    {
        $actor = $this->actor();
        $order = $this->orderInStatus(ShipmentOrderStatus::SUBMITTED);
        $result = $this->action->execute($order, ShipmentOrderStatus::UNDER_REVIEW, $actor->id);
        $this->assertSame(ShipmentOrderStatus::UNDER_REVIEW, $result->status->name);
    }

    public function test_valid_transition_submitted_to_cancelled(): void
    {
        $actor = $this->actor();
        $order = $this->orderInStatus(ShipmentOrderStatus::SUBMITTED);
        $result = $this->action->execute($order, ShipmentOrderStatus::CANCELLED, $actor->id, 'No longer needed');
        $this->assertSame(ShipmentOrderStatus::CANCELLED, $result->status->name);
    }

    public function test_valid_transition_under_review_to_changes_requested(): void
    {
        $actor = $this->actor();
        $order = $this->orderInStatus(ShipmentOrderStatus::UNDER_REVIEW);
        $result = $this->action->execute($order, ShipmentOrderStatus::CHANGES_REQUESTED, $actor->id, 'Need weight');
        $this->assertSame(ShipmentOrderStatus::CHANGES_REQUESTED, $result->status->name);
    }

    public function test_valid_transition_under_review_to_approved(): void
    {
        $actor = $this->actor();
        $order = $this->orderInStatus(ShipmentOrderStatus::UNDER_REVIEW);
        $result = $this->action->execute($order, ShipmentOrderStatus::APPROVED, $actor->id);
        $this->assertSame(ShipmentOrderStatus::APPROVED, $result->status->name);
    }

    public function test_valid_transition_under_review_to_rejected(): void
    {
        $actor = $this->actor();
        $order = $this->orderInStatus(ShipmentOrderStatus::UNDER_REVIEW);
        $result = $this->action->execute($order, ShipmentOrderStatus::REJECTED, $actor->id, 'Out of service area');
        $this->assertSame(ShipmentOrderStatus::REJECTED, $result->status->name);
    }

    public function test_valid_transition_changes_requested_to_submitted(): void
    {
        $actor = $this->actor();
        $order = $this->orderInStatus(ShipmentOrderStatus::CHANGES_REQUESTED);
        $result = $this->action->execute($order, ShipmentOrderStatus::SUBMITTED, $actor->id);
        $this->assertSame(ShipmentOrderStatus::SUBMITTED, $result->status->name);
    }

    public function test_valid_transition_changes_requested_to_cancelled(): void
    {
        $actor = $this->actor();
        $order = $this->orderInStatus(ShipmentOrderStatus::CHANGES_REQUESTED);
        $result = $this->action->execute($order, ShipmentOrderStatus::CANCELLED, $actor->id);
        $this->assertSame(ShipmentOrderStatus::CANCELLED, $result->status->name);
    }

    public function test_valid_transition_approved_to_converted(): void
    {
        $actor = $this->actor();
        $order = $this->orderInStatus(ShipmentOrderStatus::APPROVED);
        $result = $this->action->execute($order, ShipmentOrderStatus::CONVERTED, $actor->id);
        $this->assertSame(ShipmentOrderStatus::CONVERTED, $result->status->name);
    }

    public function test_invalid_transition_draft_to_approved(): void
    {
        $order = $this->orderInStatus(ShipmentOrderStatus::DRAFT);
        $this->expectException(InvalidShipmentOrderTransitionException::class);
        $this->action->execute($order, ShipmentOrderStatus::APPROVED, $this->actor()->id);
    }

    public function test_invalid_transition_draft_to_converted(): void
    {
        $order = $this->orderInStatus(ShipmentOrderStatus::DRAFT);
        $this->expectException(InvalidShipmentOrderTransitionException::class);
        $this->action->execute($order, ShipmentOrderStatus::CONVERTED, $this->actor()->id);
    }

    public function test_invalid_transition_submitted_to_approved(): void
    {
        $order = $this->orderInStatus(ShipmentOrderStatus::SUBMITTED);
        $this->expectException(InvalidShipmentOrderTransitionException::class);
        $this->action->execute($order, ShipmentOrderStatus::APPROVED, $this->actor()->id);
    }

    public function test_invalid_transition_submitted_to_rejected(): void
    {
        $order = $this->orderInStatus(ShipmentOrderStatus::SUBMITTED);
        $this->expectException(InvalidShipmentOrderTransitionException::class);
        $this->action->execute($order, ShipmentOrderStatus::REJECTED, $this->actor()->id);
    }

    public function test_invalid_transition_changes_requested_to_approved(): void
    {
        $order = $this->orderInStatus(ShipmentOrderStatus::CHANGES_REQUESTED);
        $this->expectException(InvalidShipmentOrderTransitionException::class);
        $this->action->execute($order, ShipmentOrderStatus::APPROVED, $this->actor()->id);
    }

    public function test_invalid_transition_approved_to_submitted(): void
    {
        $order = $this->orderInStatus(ShipmentOrderStatus::APPROVED);
        $this->expectException(InvalidShipmentOrderTransitionException::class);
        $this->action->execute($order, ShipmentOrderStatus::SUBMITTED, $this->actor()->id);
    }

    public function test_invalid_transition_rejected_to_submitted(): void
    {
        $order = $this->orderInStatus(ShipmentOrderStatus::REJECTED);
        $this->expectException(InvalidShipmentOrderTransitionException::class);
        $this->action->execute($order, ShipmentOrderStatus::SUBMITTED, $this->actor()->id);
    }

    public function test_invalid_transition_cancelled_to_submitted(): void
    {
        $order = $this->orderInStatus(ShipmentOrderStatus::CANCELLED);
        $this->expectException(InvalidShipmentOrderTransitionException::class);
        $this->action->execute($order, ShipmentOrderStatus::SUBMITTED, $this->actor()->id);
    }

    public function test_invalid_transition_converted_to_submitted(): void
    {
        $order = $this->orderInStatus(ShipmentOrderStatus::CONVERTED);
        $this->expectException(InvalidShipmentOrderTransitionException::class);
        $this->action->execute($order, ShipmentOrderStatus::SUBMITTED, $this->actor()->id);
    }

    public function test_successful_transition_creates_one_history_row(): void
    {
        $user = User::factory()->create();
        $order = $this->orderInStatus(ShipmentOrderStatus::DRAFT);
        $fromId = $order->status_id;

        $this->action->execute($order, ShipmentOrderStatus::SUBMITTED, $user->id, 'Ready');

        $this->assertDatabaseCount('shipment_order_status_history', 1);
        $history = ShipmentOrderStatusHistory::first();
        $this->assertSame($order->id, $history->shipment_order_id);
        $this->assertSame($fromId, $history->from_status_id);
        $this->assertSame(
            ShipmentOrderStatus::statusId(ShipmentOrderStatus::SUBMITTED),
            $history->to_status_id
        );
        $this->assertSame($user->id, $history->changed_by_user_id);
        $this->assertSame('Ready', $history->reason);
    }

    public function test_failed_transition_creates_no_history_and_preserves_status(): void
    {
        $order = $this->orderInStatus(ShipmentOrderStatus::DRAFT);
        $statusBefore = $order->status_id;

        try {
            $this->action->execute($order, ShipmentOrderStatus::APPROVED, $this->actor()->id);
        } catch (InvalidShipmentOrderTransitionException) {
            // expected
        }

        $this->assertDatabaseCount('shipment_order_status_history', 0);
        $this->assertSame($statusBefore, $order->fresh()->status_id);
    }

    public function test_target_status_must_be_shipment_order_type(): void
    {
        $order = $this->orderInStatus(ShipmentOrderStatus::DRAFT);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('not a valid shipment_order status');

        // "Draft" exists only under type=shipment in typical seed data
        $this->action->execute($order, 'Draft', $this->actor()->id);
    }

    public function test_order_with_shipment_status_id_rejects_transition(): void
    {
        $shipmentStatus = Status::create(['name' => 'In Transit', 'type' => 'shipment']);
        $order = ShipmentOrder::factory()->create(['status_id' => $shipmentStatus->id]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('not a shipment_order lifecycle status');

        $this->action->execute($order, ShipmentOrderStatus::SUBMITTED, $this->actor()->id);
    }

    public function test_transition_is_atomic_when_history_insert_fails(): void
    {
        $order = $this->orderInStatus(ShipmentOrderStatus::DRAFT);
        $statusBefore = $order->status_id;

        ShipmentOrderStatusHistory::creating(function () {
            throw new \RuntimeException('history blocked');
        });

        try {
            $this->action->execute($order, ShipmentOrderStatus::SUBMITTED, $this->actor()->id);
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertSame('history blocked', $e->getMessage());
        } finally {
            ShipmentOrderStatusHistory::flushEventListeners();
        }

        $this->assertDatabaseCount('shipment_order_status_history', 0);
        $this->assertSame($statusBefore, $order->fresh()->status_id);
    }

    public function test_shipment_order_has_status_history_relationship(): void
    {
        $user = User::factory()->create();
        $order = $this->orderInStatus(ShipmentOrderStatus::DRAFT);
        $this->action->execute($order, ShipmentOrderStatus::SUBMITTED, $user->id);

        $order->load('statusHistory.toStatus', 'latestStatusHistory');
        $this->assertCount(1, $order->statusHistory);
        $this->assertNotNull($order->latestStatusHistory);
        $this->assertSame(ShipmentOrderStatus::SUBMITTED, $order->latestStatusHistory->toStatus->name);
    }

    public function test_draft_creation_does_not_create_history(): void
    {
        ShipmentOrder::factory()->draft()->create();
        $this->assertDatabaseCount('shipment_order_status_history', 0);
    }

    public function test_transition_re_read_status_inside_transaction(): void
    {
        $order = $this->orderInStatus(ShipmentOrderStatus::DRAFT);

        DB::transaction(function () use ($order) {
            $this->action->execute($order, ShipmentOrderStatus::SUBMITTED, $this->actor()->id);
        });

        $this->assertSame(
            ShipmentOrderStatus::SUBMITTED,
            $order->fresh()->status->name
        );
    }
}
