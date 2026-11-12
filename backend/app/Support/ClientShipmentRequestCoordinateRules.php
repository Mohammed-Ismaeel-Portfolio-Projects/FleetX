<?php

namespace App\Support;

/**
 * Shared writable coordinate validation for Client shipment-request create/update.
 */
final class ClientShipmentRequestCoordinateRules
{
    /**
     * @return list<string>
     */
    public static function fieldNames(): array
    {
        return [
            'pickup_latitude',
            'pickup_longitude',
            'delivery_latitude',
            'delivery_longitude',
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function createRules(): array
    {
        return [
            'pickup_latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:pickup_longitude'],
            'pickup_longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:pickup_latitude'],
            'delivery_latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:delivery_longitude'],
            'delivery_longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:delivery_latitude'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function updateRules(): array
    {
        return [
            'pickup_latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90', 'required_with:pickup_longitude'],
            'pickup_longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180', 'required_with:pickup_latitude'],
            'delivery_latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90', 'required_with:delivery_longitude'],
            'delivery_longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180', 'required_with:delivery_latitude'],
        ];
    }

    public static function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $n = is_numeric($value) ? (float) $value : null;

        return $n !== null && is_finite($n) ? $n : null;
    }
}
