<?php

namespace App\Support;

use App\Enums\CargoType;
use App\Enums\TrailerType;
use App\Models\ShipmentOrder;
use App\Support\ClientShipmentRequestCoordinateRules;

/**
 * Submission readiness validation for ShipmentOrder (C2.9-A3.3 §13.1 / C2.9-A3.4.3b).
 */
final class ShipmentOrderSubmissionValidator
{
    /**
     * @return array<string, list<string>>|null
     */
    public static function validate(ShipmentOrder $order): ?array
    {
        $errors = [];

        self::requireFilled($errors, 'cargo_type', $order->cargo_type);
        self::requireFilled($errors, 'cargo_description', $order->cargo_description);
        self::requireFilled($errors, 'trailer_type', $order->trailer_type);
        self::requirePricingCargoType($errors, $order->cargo_type);
        self::requirePricingTrailerType($errors, $order->trailer_type);
        self::requireFilled($errors, 'pickup_city', $order->pickup_city);
        self::requireFilled($errors, 'pickup_country', $order->pickup_country);
        self::requireFilled($errors, 'delivery_city', $order->delivery_city);
        self::requireFilled($errors, 'delivery_country', $order->delivery_country);

        self::requireCoordinatePair(
            $errors,
            'pickup_latitude',
            'pickup_longitude',
            $order->pickup_latitude,
            $order->pickup_longitude
        );
        self::requireCoordinatePair(
            $errors,
            'delivery_latitude',
            'delivery_longitude',
            $order->delivery_latitude,
            $order->delivery_longitude
        );

        if ($order->requested_pickup_date !== null
            && $order->requested_delivery_date !== null
            && $order->requested_delivery_date->lt($order->requested_pickup_date)) {
            $errors['requested_delivery_date'][] = 'The requested delivery date must be on or after the requested pickup date.';
        }

        return $errors === [] ? null : $errors;
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    private static function requirePricingCargoType(array &$errors, mixed $value): void
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return;
        }

        if (CargoType::tryFrom((string) $value) === null) {
            $errors['cargo_type'][] = 'The cargo type must be a supported pricing cargo type for submission.';
        }
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    private static function requirePricingTrailerType(array &$errors, mixed $value): void
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return;
        }

        if (TrailerType::tryFrom((string) $value) === null) {
            $errors['trailer_type'][] = 'The trailer type must be a supported pricing trailer type for submission.';
        }
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    private static function requireFilled(array &$errors, string $field, mixed $value): void
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            $errors[$field][] = 'The '.$field.' field is required for submission.';
        }
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    private static function requireCoordinatePair(
        array &$errors,
        string $latField,
        string $lngField,
        mixed $latitude,
        mixed $longitude
    ): void {
        $lat = ClientShipmentRequestCoordinateRules::toFloat($latitude);
        $lng = ClientShipmentRequestCoordinateRules::toFloat($longitude);

        if ($lat === null) {
            $errors[$latField][] = 'The '.$latField.' field is required for submission.';
        } elseif ($lat < -90 || $lat > 90) {
            $errors[$latField][] = 'The '.$latField.' must be between -90 and 90.';
        }

        if ($lng === null) {
            $errors[$lngField][] = 'The '.$lngField.' field is required for submission.';
        } elseif ($lng < -180 || $lng > 180) {
            $errors[$lngField][] = 'The '.$lngField.' must be between -180 and 180.';
        }
    }
}
