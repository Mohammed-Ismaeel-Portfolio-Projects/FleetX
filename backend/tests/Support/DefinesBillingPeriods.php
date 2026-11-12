<?php

namespace Tests\Support;

use Carbon\CarbonInterface;

trait DefinesBillingPeriods
{
    /**
     * Half-open billing period [start, end) containing the given timestamp.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    protected function billingPeriodContaining(CarbonInterface $timestamp): array
    {
        $start = $timestamp->copy()->startOfMonth()->startOfDay();
        $end = $start->copy()->addMonth();

        return [$start, $end];
    }
}
