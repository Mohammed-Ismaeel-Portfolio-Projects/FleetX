<?php

declare(strict_types=1);

namespace App\Services\Shipment;

use App\Enums\OperationalActualsCaptureSource;
use App\Models\Shipment;
use App\Models\ShipmentOperationalActuals;
use App\Models\User;
use App\Services\Shipment\Exceptions\ShipmentOperationalActualsException;
use App\Support\ShipmentStatus;
use Illuminate\Support\Facades\DB;

/**
 * Persist authoritative shipment operational actuals (Phase 5.5a).
 *
 * Does not calculate pricing, variance, or invoices.
 */
class ShipmentOperationalActualsCaptureService
{
    /**
     * @param  array<string, mixed>  $actuals  Validated operational actual field values.
     *
     * @throws ShipmentOperationalActualsException
     */
    public function capture(
        Shipment $shipment,
        User $actor,
        OperationalActualsCaptureSource $captureSource,
        array $actuals,
    ): ShipmentOperationalActuals {
        return DB::transaction(function () use ($shipment, $actor, $captureSource, $actuals) {
            $lockedShipment = Shipment::query()
                ->with(['status', 'operationalActuals'])
                ->whereKey($shipment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertShipmentEligible($lockedShipment);

            if ($lockedShipment->operationalActuals !== null) {
                throw new ShipmentOperationalActualsException(
                    'Operational actuals already exist for this shipment.',
                    409,
                    'SHIPMENT_OPERATIONAL_ACTUALS_ALREADY_EXISTS'
                );
            }

            $this->validateActualValues($actuals);

            $record = ShipmentOperationalActuals::create([
                'shipment_id' => $lockedShipment->id,
                'actual_distance_km' => $actuals['actual_distance_km'] ?? null,
                'actual_cargo_weight' => $actuals['actual_cargo_weight'] ?? null,
                'actual_loading_wait_minutes' => $actuals['actual_loading_wait_minutes'] ?? null,
                'actual_unloading_wait_minutes' => $actuals['actual_unloading_wait_minutes'] ?? null,
                'actual_traffic_delay_minutes' => $actuals['actual_traffic_delay_minutes'] ?? null,
                'actual_customer_delay_minutes' => $actuals['actual_customer_delay_minutes'] ?? null,
                'actual_border_crossing' => array_key_exists('actual_border_crossing', $actuals)
                    ? $actuals['actual_border_crossing']
                    : null,
                'actual_hazmat' => array_key_exists('actual_hazmat', $actuals)
                    ? $actuals['actual_hazmat']
                    : null,
                'actual_service_ids' => $actuals['actual_service_ids'] ?? null,
                'actual_trailer_type' => $actuals['actual_trailer_type'] ?? null,
                'capture_source' => $captureSource,
                'captured_at' => now(),
                'captured_by_user_id' => $captureSource === OperationalActualsCaptureSource::OPS_MANUAL
                    ? $actor->id
                    : null,
            ]);

            return $record->fresh(['shipment', 'capturedBy']);
        });
    }

    /**
     * @throws ShipmentOperationalActualsException
     */
    private function assertShipmentEligible(Shipment $shipment): void
    {
        if ($shipment->status?->name === ShipmentStatus::CANCELLED) {
            throw new ShipmentOperationalActualsException(
                'Operational actuals cannot be captured for a cancelled shipment.',
                422,
                'SHIPMENT_CANCELLED'
            );
        }

        if ($shipment->shipment_order_id === null) {
            throw new ShipmentOperationalActualsException(
                'Operational actuals require a linked shipment request.',
                422,
                'MISSING_SHIPMENT_ORDER'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $actuals
     *
     * @throws ShipmentOperationalActualsException
     */
    private function validateActualValues(array $actuals): void
    {
        if (array_key_exists('actual_distance_km', $actuals) && $actuals['actual_distance_km'] !== null) {
            if ((float) $actuals['actual_distance_km'] < 0) {
                throw new ShipmentOperationalActualsException(
                    'Actual distance must not be negative.',
                    422,
                    'INVALID_ACTUAL_DISTANCE'
                );
            }
        }

        if (array_key_exists('actual_cargo_weight', $actuals) && $actuals['actual_cargo_weight'] !== null) {
            if ((float) $actuals['actual_cargo_weight'] < 0) {
                throw new ShipmentOperationalActualsException(
                    'Actual cargo weight must not be negative.',
                    422,
                    'INVALID_ACTUAL_WEIGHT'
                );
            }
        }

        foreach ([
            'actual_loading_wait_minutes',
            'actual_unloading_wait_minutes',
            'actual_traffic_delay_minutes',
            'actual_customer_delay_minutes',
        ] as $minutesField) {
            if (! array_key_exists($minutesField, $actuals) || $actuals[$minutesField] === null) {
                continue;
            }

            if ((int) $actuals[$minutesField] < 0) {
                throw new ShipmentOperationalActualsException(
                    'Waiting and delay minutes must not be negative.',
                    422,
                    'INVALID_WAIT_MINUTES'
                );
            }
        }
    }
}
