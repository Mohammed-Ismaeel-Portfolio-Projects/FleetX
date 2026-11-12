<?php

namespace App\Simulation\Lifecycle;

use App\Actions\Shipment\TransitionShipmentStatusAction;
use App\Enums\FinalPriceCalculationTrigger;
use App\Exceptions\DomainException;
use App\Exceptions\InvalidShipmentTransitionException;
use App\Models\Shipment;
use App\Support\ShipmentStatus;
use App\Support\ShipmentTransitionMap;
use App\Services\Pricing\ShipmentFinalPricingService;
use App\Simulation\Routing\Application\Services\PlannedRouteCache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bridges Engine operational lifecycle stages → canonical Shipment transitions (C2.9-C2.8 Step 2).
 *
 * Does not simulate movement or own operational stages. Invokes TransitionShipmentStatusAction only.
 * Engine-generated transitions use null changed_by_user_id (no fake user).
 */
class ShipmentLifecycleTransitionAdapter
{
    public function __construct(
        private readonly TransitionShipmentStatusAction $transitionAction,
    ) {}

    /**
     * Apply any required canonical transition for an operational stage change.
     *
     * Idempotent: already-satisfied targets and unsupported leaps are no-ops (logged).
     * Never throws into the simulation tick loop.
     */
    public function applyOperationalStage(string $shipmentId, ShipmentLifecycleStage $stage): void
    {
        $target = $this->canonicalTargetForStage($stage);

        if ($target === null) {
            return;
        }

        try {
            $shipment = Shipment::query()->with('status')->find($shipmentId);
        } catch (Throwable $e) {
            Log::warning('Engine lifecycle adapter failed to load shipment', [
                'shipment_id' => $shipmentId,
                'stage' => $stage->value,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if ($shipment === null) {
            return;
        }

        if ($shipment->status === null || $shipment->status->type !== ShipmentStatus::TYPE) {
            Log::warning('Engine lifecycle adapter skipped: shipment has non-shipment status', [
                'shipment_id' => $shipmentId,
                'stage' => $stage->value,
                'status_id' => $shipment->status_id,
            ]);

            return;
        }

        $from = $shipment->status->name;

        if ($from === $target) {
            return;
        }

        if (! ShipmentTransitionMap::canTransition($from, $target)) {
            Log::info('Engine lifecycle adapter skipped unsupported transition', [
                'shipment_id' => $shipmentId,
                'stage' => $stage->value,
                'from' => $from,
                'to' => $target,
            ]);

            return;
        }

        $actualPickupDate = $target === ShipmentStatus::IN_TRANSIT
            ? now()->toDateString()
            : null;
        $actualDeliveryDate = in_array($target, [ShipmentStatus::DELIVERED, ShipmentStatus::COMPLETED], true)
            ? now()->toDateString()
            : null;

        try {
            $this->transitionAction->execute(
                $shipment,
                $target,
                null,
                $actualPickupDate,
                $actualDeliveryDate
            );
        } catch (InvalidShipmentTransitionException|DomainException $e) {
            Log::warning('Engine lifecycle adapter transition rejected', [
                'shipment_id' => $shipmentId,
                'stage' => $stage->value,
                'from' => $from,
                'to' => $target,
                'error' => $e->getMessage(),
            ]);

            return;
        } catch (Throwable $e) {
            Log::error('Engine lifecycle adapter transition failed', [
                'shipment_id' => $shipmentId,
                'stage' => $stage->value,
                'from' => $from,
                'to' => $target,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if (in_array($target, [ShipmentStatus::DELIVERED, ShipmentStatus::COMPLETED], true)) {
            try {
                app(PlannedRouteCache::class)->invalidate($shipmentId);
            } catch (Throwable) {
                // Cache cleanup must not break the tick.
            }
        }

        if ($target === ShipmentStatus::COMPLETED) {
            try {
                $completedShipment = Shipment::query()->with('status')->find($shipmentId);
                if ($completedShipment !== null) {
                    app(ShipmentFinalPricingService::class)->tryFinalizeOnCompletion(
                        $completedShipment,
                        FinalPriceCalculationTrigger::SIMULATION,
                    );
                }
            } catch (Throwable $e) {
                Log::warning('Engine lifecycle adapter final pricing skipped', [
                    'shipment_id' => $shipmentId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Map operational stage → canonical business status request (or null = ops-only).
     *
     * Does not request Ready For Pickup / Picked Up / Out For Delivery.
     */
    public function canonicalTargetForStage(ShipmentLifecycleStage $stage): ?string
    {
        return match ($stage) {
            // Departed pickup / en route (incl. rest resume) → In Transit
            ShipmentLifecycleStage::Departed,
            ShipmentLifecycleStage::EnRouteDelivery => ShipmentStatus::IN_TRANSIT,

            // Delivery confirmed after unloading
            ShipmentLifecycleStage::Delivered => ShipmentStatus::DELIVERED,

            // Final completion
            ShipmentLifecycleStage::Completed => ShipmentStatus::COMPLETED,

            // Pickup / loading / rest / at delivery / unloading: ops-only (rest keeps In Transit)
            default => null,
        };
    }
}
