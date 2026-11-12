<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Enums\FinalPriceBreakdownStatus;
use App\Enums\FinalPriceCalculationTrigger;
use App\Enums\FinalPriceChangeDirection;
use App\Enums\VarianceFactorCode;
use App\Models\PricingPolicy;
use App\Models\Shipment;
use App\Models\ShipmentFinalPriceBreakdown;
use App\Models\ShipmentOperationalActuals;
use App\Models\ShipmentOrderPriceBreakdown;
use App\Models\ShipmentPriceVariance;
use App\Models\User;
use App\Services\Pricing\Exceptions\InvalidPricingConfigException;
use App\Services\Pricing\Exceptions\InvalidPricingInputException;
use App\Services\Pricing\Exceptions\NoPricingPolicyException;
use App\Services\Pricing\Exceptions\ShipmentFinalPricingException;
use App\Support\ShipmentStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates authoritative final shipment pricing after completion (Phase 5.5c).
 */
class ShipmentFinalPricingService
{
    public function __construct(
        private readonly FinalPricingInputFactory $inputFactory,
        private readonly PricingEngine $pricingEngine,
        private readonly ShipmentPriceVarianceEvaluator $varianceEvaluator,
    ) {}

    /**
     * Attempt finalization after completion; logs and skips when preconditions are not met.
     */
    public function tryFinalizeOnCompletion(Shipment $shipment, FinalPriceCalculationTrigger $trigger): void
    {
        try {
            $this->finalize($shipment, null, $trigger);
        } catch (ShipmentFinalPricingException $e) {
            Log::info('Final pricing skipped on completion hook', [
                'shipment_id' => $shipment->id,
                'trigger' => $trigger->value,
                'error_code' => $e->errorCode,
                'message' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Final pricing failed on completion hook', [
                'shipment_id' => $shipment->id,
                'trigger' => $trigger->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @throws ShipmentFinalPricingException
     */
    public function finalize(
        Shipment $shipment,
        ?User $user,
        FinalPriceCalculationTrigger $trigger,
    ): ShipmentFinalPriceBreakdown {
        return DB::transaction(function () use ($shipment, $user, $trigger) {
            /** @var Shipment|null $locked */
            $locked = Shipment::query()
                ->with(['status', 'finalPriceBreakdown.variances'])
                ->lockForUpdate()
                ->find($shipment->id);

            if ($locked === null) {
                throw new ShipmentFinalPricingException('Shipment not found.', 404);
            }

            $existing = $locked->finalPriceBreakdown;
            if ($existing !== null && $existing->isFinalized()) {
                $existing->loadMissing('variances');

                return $existing;
            }

            $this->assertPreconditions($locked);

            $submissionSnapshot = ShipmentOrderPriceBreakdown::query()
                ->where('shipment_order_id', $locked->shipment_order_id)
                ->firstOrFail();

            /** @var ShipmentOperationalActuals $actuals */
            $actuals = ShipmentOperationalActuals::query()
                ->where('shipment_id', $locked->id)
                ->firstOrFail();

            $policy = PricingPolicy::query()->find($submissionSnapshot->pricing_policy_id);
            if ($policy === null) {
                throw new ShipmentFinalPricingException(
                    'Submission pricing policy is no longer available.',
                    503,
                    'PRICING_CONFIGURATION_ERROR',
                );
            }

            $policy->loadMissing('varianceRules');

            $baselineInput = $this->inputFactory->buildBaselineInput($submissionSnapshot);
            $actualInput = $this->inputFactory->buildActualInput($submissionSnapshot, $actuals, $policy);

            try {
                $candidateBreakdown = $this->pricingEngine->calculate($actualInput, $policy);
            } catch (InvalidPricingConfigException|NoPricingPolicyException $e) {
                throw new ShipmentFinalPricingException(
                    'Pricing configuration error. Please contact support.',
                    503,
                    'PRICING_CONFIGURATION_ERROR',
                );
            } catch (InvalidPricingInputException $e) {
                throw new ShipmentFinalPricingException($e->getMessage(), 422, 'INVALID_PRICING_INPUT');
            }

            [$baselineVariance, $actualVariance] = $this->inputFactory->buildVarianceInputs(
                $submissionSnapshot,
                $actuals,
            );

            $varianceResult = $this->varianceEvaluator->evaluate(
                $baselineVariance,
                $actualVariance,
                $policy->varianceRules->all(),
            );

            $this->assertVarianceResult($varianceResult);

            $submissionTotal = round((float) $submissionSnapshot->final_total, 2);
            $candidateTotal = round($candidateBreakdown->finalTotal, 2);
            $hasMaterialBillable = $this->hasMaterialBillableVariances($varianceResult);

            if (! $hasMaterialBillable) {
                if (abs($candidateTotal - $submissionTotal) >= 0.01) {
                    throw new ShipmentFinalPricingException(
                        'Final price differs from submission but no material variance explains the change.',
                        409,
                        'UNEXPLAINED_PRICE_DELTA',
                    );
                }

                $finalTotal = $submissionTotal;
                $priceDelta = 0.0;
                $changeDirection = FinalPriceChangeDirection::NONE;
                $materialChange = false;
                $storedBreakdown = $this->pricingEngine->calculate($baselineInput, $policy);
            } else {
                $this->assertMaterialVariancesAreBillable($varianceResult);

                if (abs($candidateTotal - $submissionTotal) < 0.01) {
                    $finalTotal = $submissionTotal;
                    $priceDelta = 0.0;
                    $changeDirection = FinalPriceChangeDirection::NONE;
                } else {
                    $finalTotal = $candidateTotal;
                    $priceDelta = round($finalTotal - $submissionTotal, 2);
                    $changeDirection = $priceDelta > 0
                        ? FinalPriceChangeDirection::INCREASE
                        : FinalPriceChangeDirection::DECREASE;
                }

                $materialChange = true;
                $storedBreakdown = $candidateBreakdown;
            }

            $submissionBreakdownForImpact = $this->pricingEngine->calculate($baselineInput, $policy);

            try {
                $finalSnapshot = ShipmentFinalPriceBreakdown::create([
                    'shipment_id' => $locked->id,
                    'shipment_order_id' => $locked->shipment_order_id,
                    'submission_price_breakdown_id' => $submissionSnapshot->id,
                    'pricing_policy_id' => $policy->id,
                    'operational_actuals_id' => $actuals->id,
                    'breakdown_json' => PricingBreakdownSerializer::toArray($storedBreakdown),
                    'submission_total' => $submissionTotal,
                    'final_total' => $finalTotal,
                    'price_delta' => $priceDelta,
                    'price_delta_percentage' => $this->percentageDelta($submissionTotal, $priceDelta),
                    'change_direction' => $changeDirection,
                    'material_change' => $materialChange,
                    'currency' => $submissionSnapshot->currency,
                    'calculated_at' => now(),
                    'calculated_by_user_id' => $user?->id,
                    'calculation_trigger' => $trigger,
                    'status' => FinalPriceBreakdownStatus::FINALIZED,
                ]);
            } catch (QueryException $e) {
                if ($this->isUniqueShipmentConstraintViolation($e)) {
                    $existingFinal = ShipmentFinalPriceBreakdown::query()
                        ->with('variances')
                        ->where('shipment_id', $locked->id)
                        ->first();

                    if ($existingFinal !== null && $existingFinal->isFinalized()) {
                        return $existingFinal;
                    }
                }

                throw $e;
            }

            $this->persistVariances(
                $finalSnapshot,
                $varianceResult,
                $submissionBreakdownForImpact,
                $candidateBreakdown,
                $actuals,
            );

            return $finalSnapshot->load('variances');
        });
    }

    /**
     * @throws ShipmentFinalPricingException
     */
    private function assertPreconditions(Shipment $shipment): void
    {
        $statusName = $shipment->status?->name;

        if ($statusName === ShipmentStatus::CANCELLED) {
            throw new ShipmentFinalPricingException(
                'Cancelled shipments cannot be final priced.',
                409,
                'SHIPMENT_CANCELLED',
            );
        }

        if ($statusName !== ShipmentStatus::COMPLETED) {
            throw new ShipmentFinalPricingException(
                'Shipment must be completed before final pricing.',
                409,
                'SHIPMENT_NOT_COMPLETED',
            );
        }

        if ($shipment->shipment_order_id === null) {
            throw new ShipmentFinalPricingException(
                'Shipment is not linked to a shipment order.',
                422,
                'MISSING_SHIPMENT_ORDER',
            );
        }

        $hasSnapshot = ShipmentOrderPriceBreakdown::query()
            ->where('shipment_order_id', $shipment->shipment_order_id)
            ->exists();

        if (! $hasSnapshot) {
            throw new ShipmentFinalPricingException(
                'Submission price snapshot is required for final pricing.',
                422,
                'MISSING_SUBMISSION_SNAPSHOT',
            );
        }

        $hasActuals = ShipmentOperationalActuals::query()
            ->where('shipment_id', $shipment->id)
            ->exists();

        if (! $hasActuals) {
            throw new ShipmentFinalPricingException(
                'Operational actuals are required for final pricing.',
                422,
                'MISSING_OPERATIONAL_ACTUALS',
            );
        }
    }

    /**
     * @throws ShipmentFinalPricingException
     */
    private function assertVarianceResult(ShipmentPriceVarianceEvaluationResult $result): void
    {
        foreach ($result->factors as $factor) {
            if ($factor->evaluationStatus === 'missing_value') {
                throw new ShipmentFinalPricingException(
                    'Required operational actual value is missing for variance evaluation.',
                    422,
                    'MISSING_VARIANCE_VALUE',
                );
            }

            if ($factor->evaluationStatus === 'rule_missing' && $factor->direction !== 'none') {
                throw new ShipmentFinalPricingException(
                    'Variance rule is missing for a material pricing factor.',
                    422,
                    'MISSING_VARIANCE_RULE',
                );
            }

            if ($factor->evaluationStatus === 'baseline_zero' && $factor->material) {
                throw new ShipmentFinalPricingException(
                    'Variance could not be evaluated safely for a zero baseline factor.',
                    422,
                    'UNSUPPORTED_VARIANCE_CONDITION',
                );
            }
        }
    }

    /**
     * @throws ShipmentFinalPricingException
     */
    private function assertMaterialVariancesAreBillable(ShipmentPriceVarianceEvaluationResult $result): void
    {
        foreach ($result->factors as $factor) {
            if (! $factor->material) {
                continue;
            }

            if (! $factor->billable || $factor->reasonCode === null) {
                throw new ShipmentFinalPricingException(
                    'Material variance factor is not billable or lacks a reason code.',
                    409,
                    'UNEXPLAINED_PRICE_DELTA',
                );
            }
        }
    }

    private function hasMaterialBillableVariances(ShipmentPriceVarianceEvaluationResult $result): bool
    {
        foreach ($result->factors as $factor) {
            if ($factor->material && $factor->billable) {
                return true;
            }
        }

        return false;
    }

    private function persistVariances(
        ShipmentFinalPriceBreakdown $finalSnapshot,
        ShipmentPriceVarianceEvaluationResult $varianceResult,
        PricingBreakdown $submissionBreakdown,
        PricingBreakdown $candidateBreakdown,
        ShipmentOperationalActuals $actuals,
    ): void {
        $sortOrder = 0;

        foreach ($varianceResult->factors as $factor) {
            ShipmentPriceVariance::create([
                'shipment_final_price_breakdown_id' => $finalSnapshot->id,
                'factor_code' => $factor->factorCode,
                'baseline_value' => $factor->baselineValue,
                'actual_value' => $factor->actualValue,
                'delta_value' => $factor->deltaValue,
                'delta_unit' => $factor->deltaUnit,
                'threshold_value' => $factor->thresholdValue,
                'threshold_type' => $factor->thresholdType,
                'threshold_scope' => $factor->thresholdValue !== null ? 'policy' : null,
                'material' => $factor->material,
                'billable' => $factor->billable,
                'price_impact' => $this->priceImpactForFactor(
                    $factor->factorCode,
                    $factor->material,
                    $submissionBreakdown,
                    $candidateBreakdown,
                    $varianceResult,
                ),
                'reason_code' => $factor->reasonCode,
                'customer_message' => $factor->customerMessage,
                'internal_message' => $factor->internalMessage,
                'data_source' => $actuals->capture_source->value,
                'sort_order' => $sortOrder++,
            ]);
        }
    }

    private function priceImpactForFactor(
        VarianceFactorCode $factorCode,
        bool $material,
        PricingBreakdown $submission,
        PricingBreakdown $candidate,
        ShipmentPriceVarianceEvaluationResult $varianceResult,
    ): float {
        if (! $material) {
            return 0.0;
        }

        $componentDelta = match ($factorCode) {
            VarianceFactorCode::DISTANCE => $candidate->freightCharge - $submission->freightCharge,
            VarianceFactorCode::WEIGHT => $candidate->weightSurcharge - $submission->weightSurcharge,
            VarianceFactorCode::WAITING_LOADING,
            VarianceFactorCode::WAITING_UNLOADING => $this->waitingChargeImpact(
                $factorCode,
                $submission,
                $candidate,
                $varianceResult,
            ),
        };

        $totalComponentDelta = $candidate->finalTotal - $submission->finalTotal;

        if (abs($totalComponentDelta) < 0.01) {
            return 0.0;
        }

        $materialFactors = array_values(array_filter(
            $varianceResult->factors,
            static fn ($factor) => $factor->material,
        ));

        if (count($materialFactors) === 1) {
            return round($totalComponentDelta, 2);
        }

        $totalComponentMagnitude = 0.0;
        foreach ($materialFactors as $materialFactor) {
            $totalComponentMagnitude += abs($this->rawComponentDelta(
                $materialFactor->factorCode,
                $submission,
                $candidate,
                $varianceResult,
            ));
        }

        if ($totalComponentMagnitude <= 0) {
            return round($totalComponentDelta / count($materialFactors), 2);
        }

        $share = abs($componentDelta) / $totalComponentMagnitude;

        return round($totalComponentDelta * $share, 2);
    }

    private function waitingChargeImpact(
        VarianceFactorCode $factorCode,
        PricingBreakdown $submission,
        PricingBreakdown $candidate,
        ShipmentPriceVarianceEvaluationResult $varianceResult,
    ): float {
        $waitingDelta = $candidate->waitingCharge - $submission->waitingCharge;

        if (abs($waitingDelta) < 0.01) {
            return 0.0;
        }

        $materialWaitingFactors = array_values(array_filter(
            $varianceResult->factors,
            static fn ($factor) => $factor->material && in_array(
                $factor->factorCode,
                [VarianceFactorCode::WAITING_LOADING, VarianceFactorCode::WAITING_UNLOADING],
                true,
            ),
        ));

        if (count($materialWaitingFactors) === 1) {
            return $waitingDelta;
        }

        $loadingDelta = 0.0;
        $unloadingDelta = 0.0;

        foreach ($varianceResult->factors as $factor) {
            if ($factor->factorCode === VarianceFactorCode::WAITING_LOADING) {
                $loadingDelta = abs((float) ($factor->deltaValue ?? 0));
            }
            if ($factor->factorCode === VarianceFactorCode::WAITING_UNLOADING) {
                $unloadingDelta = abs((float) ($factor->deltaValue ?? 0));
            }
        }

        $totalWaitDelta = $loadingDelta + $unloadingDelta;

        if ($totalWaitDelta <= 0) {
            return $waitingDelta / max(count($materialWaitingFactors), 1);
        }

        $factorDelta = $factorCode === VarianceFactorCode::WAITING_LOADING ? $loadingDelta : $unloadingDelta;

        return $waitingDelta * ($factorDelta / $totalWaitDelta);
    }

    private function rawComponentDelta(
        VarianceFactorCode $factorCode,
        PricingBreakdown $submission,
        PricingBreakdown $candidate,
        ShipmentPriceVarianceEvaluationResult $varianceResult,
    ): float {
        return match ($factorCode) {
            VarianceFactorCode::DISTANCE => $candidate->freightCharge - $submission->freightCharge,
            VarianceFactorCode::WEIGHT => $candidate->weightSurcharge - $submission->weightSurcharge,
            VarianceFactorCode::WAITING_LOADING,
            VarianceFactorCode::WAITING_UNLOADING => $this->waitingChargeImpact(
                $factorCode,
                $submission,
                $candidate,
                $varianceResult,
            ),
        };
    }

    private function percentageDelta(float $submissionTotal, float $priceDelta): ?float
    {
        if (abs($submissionTotal) < 0.0001) {
            return null;
        }

        return round(($priceDelta / $submissionTotal) * 100, 4);
    }

    private function isUniqueShipmentConstraintViolation(QueryException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'shipment_final_price_breakdowns_shipment_id_unique')
            || str_contains($message, 'duplicate key')
            || str_contains($message, 'unique constraint');
    }
}
