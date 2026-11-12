<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Enums\CargoType;
use App\Enums\CustomerType;
use App\Enums\TrailerType;
use App\Models\PricingPolicy;
use App\Models\ShipmentOperationalActuals;
use App\Models\ShipmentOrderPriceBreakdown;
use App\Services\Pricing\Exceptions\InvalidPricingInputException;
use App\Services\Pricing\Exceptions\ServiceCatalogException;
use App\Services\Pricing\Exceptions\ShipmentFinalPricingException;

/**
 * Builds PricingInput for final shipment pricing from submission snapshot + operational actuals (Phase 5.5c).
 */
class FinalPricingInputFactory
{
    public function __construct(
        private readonly PricingInputFactory $inputFactory,
        private readonly ServiceCatalogResolver $serviceCatalogResolver,
    ) {}

    /**
     * Reconstruct submission baseline PricingInput from persisted snapshot JSON.
     *
     * @throws ShipmentFinalPricingException
     */
    public function buildBaselineInput(ShipmentOrderPriceBreakdown $snapshot): PricingInput
    {
        $input = $snapshot->breakdown_json['input'] ?? null;

        if (! is_array($input)) {
            throw new ShipmentFinalPricingException(
                'Submission price snapshot is missing pricing input.',
                422,
                'MISSING_SUBMISSION_SNAPSHOT',
            );
        }

        try {
            return $this->pricingInputFromSnapshotArray($input);
        } catch (\Throwable $e) {
            throw new ShipmentFinalPricingException(
                'Submission price snapshot contains invalid pricing input.',
                422,
                'MISSING_SUBMISSION_SNAPSHOT',
            );
        }
    }

    /**
     * Build actual PricingInput from trusted operational actuals and unchanged submission fields.
     *
     * @throws ShipmentFinalPricingException
     */
    public function buildActualInput(
        ShipmentOrderPriceBreakdown $snapshot,
        ShipmentOperationalActuals $actuals,
        PricingPolicy $policy,
    ): PricingInput {
        $baseline = $this->buildBaselineInput($snapshot);

        $this->assertRequiredActuals($actuals);

        $trailerType = $this->resolveTrailerType($baseline->trailerType, $actuals);
        $borderCrossing = $actuals->actual_border_crossing ?? $baseline->borderCrossing;
        $hazmat = $actuals->actual_hazmat ?? $baseline->hazmat;
        $resolvedServices = $this->resolveServices($baseline, $actuals, $policy);

        return $this->inputFactory->create(
            distance: (float) $actuals->actual_distance_km,
            cargoType: $baseline->cargoType,
            trailerType: $trailerType,
            cargoWeight: (float) $actuals->actual_cargo_weight,
            shipmentPriority: $baseline->shipmentPriority,
            customerType: CustomerType::from($baseline->customerType),
            resolvedServices: $resolvedServices,
            borderCrossing: (bool) $borderCrossing,
            hazmat: (bool) $hazmat,
            insuranceRequired: $baseline->insuranceRequired,
            loadingWaitingTime: (int) $actuals->actual_loading_wait_minutes,
            unloadingWaitingTime: (int) $actuals->actual_unloading_wait_minutes,
            trafficDelay: (int) ($actuals->actual_traffic_delay_minutes ?? 0),
            customerDelay: (int) ($actuals->actual_customer_delay_minutes ?? 0),
        );
    }

    /**
     * Build variance evaluation inputs from snapshot and operational actuals.
     *
     * @throws ShipmentFinalPricingException
     */
    public function buildVarianceInputs(
        ShipmentOrderPriceBreakdown $snapshot,
        ShipmentOperationalActuals $actuals,
    ): array {
        $this->assertRequiredActuals($actuals);
        $baselineInput = $this->buildBaselineInput($snapshot);

        return [
            new ShipmentPriceVarianceInputs(
                distanceKm: (float) $baselineInput->distance,
                cargoWeight: $baselineInput->cargoWeight,
                loadingWaitMinutes: $baselineInput->loadingWaitingTime,
                unloadingWaitMinutes: $baselineInput->unloadingWaitingTime,
            ),
            new ShipmentPriceVarianceInputs(
                distanceKm: (float) $actuals->actual_distance_km,
                cargoWeight: (float) $actuals->actual_cargo_weight,
                loadingWaitMinutes: (int) $actuals->actual_loading_wait_minutes,
                unloadingWaitMinutes: (int) $actuals->actual_unloading_wait_minutes,
            ),
        ];
    }

    /**
     * @throws ShipmentFinalPricingException
     */
    private function assertRequiredActuals(ShipmentOperationalActuals $actuals): void
    {
        if ($actuals->actual_distance_km === null) {
            throw new ShipmentFinalPricingException(
                'Operational actual distance is required for final pricing.',
                422,
                'MISSING_ACTUAL_DISTANCE',
            );
        }

        if ($actuals->actual_cargo_weight === null) {
            throw new ShipmentFinalPricingException(
                'Operational actual cargo weight is required for final pricing.',
                422,
                'MISSING_ACTUAL_WEIGHT',
            );
        }

        if ($actuals->actual_loading_wait_minutes === null) {
            throw new ShipmentFinalPricingException(
                'Operational actual loading wait time is required for final pricing.',
                422,
                'MISSING_ACTUAL_LOADING_WAIT',
            );
        }

        if ($actuals->actual_unloading_wait_minutes === null) {
            throw new ShipmentFinalPricingException(
                'Operational actual unloading wait time is required for final pricing.',
                422,
                'MISSING_ACTUAL_UNLOADING_WAIT',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws InvalidPricingInputException
     */
    private function pricingInputFromSnapshotArray(array $input): PricingInput
    {
        $cargoType = CargoType::tryFrom((string) ($input['cargoType'] ?? ''));
        $trailerType = TrailerType::tryFrom((string) ($input['trailerType'] ?? ''));

        if ($cargoType === null || $trailerType === null) {
            throw new InvalidPricingInputException('Invalid cargo or trailer type in submission snapshot.');
        }

        return new PricingInput(
            distance: (float) ($input['distance'] ?? 0),
            cargoType: $cargoType,
            trailerType: $trailerType,
            cargoWeight: (float) ($input['cargoWeight'] ?? 0),
            shipmentPriority: (string) ($input['shipmentPriority'] ?? 'standard'),
            customerType: (string) ($input['customerType'] ?? 'standard'),
            borderCrossing: (bool) ($input['borderCrossing'] ?? false),
            hazmat: (bool) ($input['hazmat'] ?? false),
            insuranceRequired: (bool) ($input['insuranceRequired'] ?? false),
            selectedServices: is_array($input['selectedServices'] ?? null) ? $input['selectedServices'] : [],
            loadingWaitingTime: (int) ($input['loadingWaitingTime'] ?? 0),
            unloadingWaitingTime: (int) ($input['unloadingWaitingTime'] ?? 0),
            trafficDelay: (int) ($input['trafficDelay'] ?? 0),
            customerDelay: (int) ($input['customerDelay'] ?? 0),
        );
    }

    /**
     * @return array<array{id: string, name: string, price: float}>
     *
     * @throws ShipmentFinalPricingException
     */
    private function resolveServices(
        PricingInput $baseline,
        ShipmentOperationalActuals $actuals,
        PricingPolicy $policy,
    ): array {
        $serviceIds = $actuals->actual_service_ids;

        if ($serviceIds === null || $serviceIds === []) {
            return $baseline->selectedServices;
        }

        try {
            return $this->serviceCatalogResolver->resolve($policy, $serviceIds);
        } catch (ServiceCatalogException $e) {
            throw new ShipmentFinalPricingException($e->getMessage(), 422, 'INVALID_ACTUAL_SERVICES');
        }
    }

    /**
     * @throws ShipmentFinalPricingException
     */
    private function resolveTrailerType(TrailerType $baseline, ShipmentOperationalActuals $actuals): TrailerType
    {
        if ($actuals->actual_trailer_type === null) {
            return $baseline;
        }

        $trailerType = TrailerType::tryFrom($actuals->actual_trailer_type);

        if ($trailerType === null) {
            throw new ShipmentFinalPricingException(
                'Operational actual trailer type is invalid.',
                422,
                'INVALID_ACTUAL_TRAILER_TYPE',
            );
        }

        return $trailerType;
    }
}
