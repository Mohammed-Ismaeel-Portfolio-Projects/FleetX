<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Enums\VarianceFactorCode;
use App\Enums\VarianceThresholdType;

/**
 * Single-factor material variance evaluation result (Phase 5.5b).
 */
readonly class ShipmentPriceVarianceFactorResult
{
    public function __construct(
        public VarianceFactorCode $factorCode,
        public ?float $baselineValue,
        public ?float $actualValue,
        public ?float $deltaValue,
        public string $deltaUnit,
        public ?float $thresholdValue,
        public ?VarianceThresholdType $thresholdType,
        public bool $material,
        public bool $billable,
        public string $direction,
        public ?string $reasonCode,
        public string $evaluationStatus,
        public ?string $customerMessage = null,
        public ?string $internalMessage = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'factor_code' => $this->factorCode->value,
            'baseline_value' => $this->baselineValue,
            'actual_value' => $this->actualValue,
            'delta_value' => $this->deltaValue,
            'delta_unit' => $this->deltaUnit,
            'threshold_value' => $this->thresholdValue,
            'threshold_type' => $this->thresholdType?->value,
            'material' => $this->material,
            'billable' => $this->billable,
            'direction' => $this->direction,
            'reason_code' => $this->reasonCode,
            'evaluation_status' => $this->evaluationStatus,
            'customer_message' => $this->customerMessage,
            'internal_message' => $this->internalMessage,
        ];
    }
}
