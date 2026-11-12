<?php

declare(strict_types=1);

namespace App\Services\Pricing;

/**
 * @phpstan-type FactorResultList list<ShipmentPriceVarianceFactorResult>
 */
readonly class ShipmentPriceVarianceEvaluationResult
{
    /**
     * @param  FactorResultList  $factors
     */
    public function __construct(
        public array $factors,
    ) {}

    public function hasMaterialVariance(): bool
    {
        foreach ($this->factors as $factor) {
            if ($factor->material) {
                return true;
            }
        }

        return false;
    }

    public function hasRuleMissing(): bool
    {
        foreach ($this->factors as $factor) {
            if ($factor->evaluationStatus === 'rule_missing') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (ShipmentPriceVarianceFactorResult $result) => $result->toArray(),
            $this->factors
        );
    }
}
