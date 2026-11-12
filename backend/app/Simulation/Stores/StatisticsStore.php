<?php

namespace App\Simulation\Stores;

/**
 * Statistics Store.
 * Stores runtime simulation statistics.
 */
class StatisticsStore
{
    private array $counters = [];
    private array $gauges = [];
    private array $histograms = [];

    /**
     * Increment counter.
     */
    public function incrementCounter(string $key, int $amount = 1): void
    {
        $this->counters[$key] = ($this->counters[$key] ?? 0) + $amount;
    }

    /**
     * Get counter value.
     */
    public function getCounter(string $key): int
    {
        return $this->counters[$key] ?? 0;
    }

    /**
     * Set counter value.
     */
    public function setCounter(string $key, int $value): void
    {
        $this->counters[$key] = $value;
    }

    /**
     * Get all counters.
     */
    public function allCounters(): array
    {
        return $this->counters;
    }

    /**
     * Set gauge value.
     */
    public function setGauge(string $key, float $value): void
    {
        $this->gauges[$key] = $value;
    }

    /**
     * Get gauge value.
     */
    public function getGauge(string $key): ?float
    {
        return $this->gauges[$key] ?? null;
    }

    /**
     * Increment gauge.
     */
    public function incrementGauge(string $key, float $amount): void
    {
        $this->gauges[$key] = ($this->gauges[$key] ?? 0) + $amount;
    }

    /**
     * Get all gauges.
     */
    public function allGauges(): array
    {
        return $this->gauges;
    }

    /**
     * Record histogram value.
     */
    public function recordHistogram(string $key, float $value): void
    {
        if (!isset($this->histograms[$key])) {
            $this->histograms[$key] = [];
        }
        $this->histograms[$key][] = $value;
    }

    /**
     * Get histogram data.
     */
    public function getHistogram(string $key): array
    {
        return $this->histograms[$key] ?? [];
    }

    /**
     * Get histogram statistics.
     */
    public function getHistogramStats(string $key): ?array
    {
        $values = $this->histograms[$key] ?? [];
        if (empty($values)) {
            return null;
        }

        sort($values);
        $count = count($values);
        $sum = array_sum($values);
        $avg = $sum / $count;
        $min = $values[0];
        $max = $values[$count - 1];
        $median = $values[floor($count / 2)];

        return [
            'count' => $count,
            'sum' => $sum,
            'avg' => $avg,
            'min' => $min,
            'max' => $max,
            'median' => $median,
        ];
    }

    /**
     * Get all histograms.
     */
    public function allHistograms(): array
    {
        return $this->histograms;
    }

    /**
     * Clear all statistics.
     */
    public function clear(): void
    {
        $this->counters = [];
        $this->gauges = [];
        $this->histograms = [];
    }

    /**
     * Get snapshot of all statistics.
     */
    public function snapshot(): array
    {
        return [
            'counters' => $this->counters,
            'gauges' => $this->gauges,
            'histograms' => array_map(fn($h) => $this->getHistogramStats(key($h)), $this->histograms),
        ];
    }
}
