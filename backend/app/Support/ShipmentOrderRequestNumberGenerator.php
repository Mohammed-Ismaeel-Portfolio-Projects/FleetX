<?php

namespace App\Support;

use App\Models\ShipmentOrder;

/**
 * Server-side unique request_number generator for ShipmentOrder (C2.9-A3.4.2).
 * Must be called inside an active DB transaction with row lock semantics.
 */
final class ShipmentOrderRequestNumberGenerator
{
    public static function nextNumber(): string
    {
        $last = ShipmentOrder::query()
            ->where('request_number', 'like', 'REQ-%')
            ->orderByDesc('request_number')
            ->lockForUpdate()
            ->value('request_number');

        $next = 1000;
        if (is_string($last) && preg_match('/^REQ-(\d+)$/', $last, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return sprintf('REQ-%05d', $next);
    }
}
