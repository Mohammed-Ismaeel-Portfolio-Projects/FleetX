<?php

namespace App\ValueObjects;

/**
 * Address Value Object
 * 
 * Immutable value object representing a physical address.
 * Used throughout the system for Company, Shipment, Waypoint, BorderCrossing.
 */
class Address
{
    public function __construct(
        public readonly string $street,
        public readonly string $city,
        public readonly ?string $state,
        public readonly string $postalCode,
        public readonly string $country,
        public readonly string $countryCode, // ISO 3166-1 alpha-2
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null
    ) {}

    /**
     * Create from array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            street: $data['street'],
            city: $data['city'],
            state: $data['state'] ?? null,
            postalCode: $data['postalCode'],
            country: $data['country'],
            countryCode: $data['countryCode'],
            latitude: $data['latitude'] ?? null,
            longitude: $data['longitude'] ?? null
        );
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'street' => $this->street,
            'city' => $this->city,
            'state' => $this->state,
            'postalCode' => $this->postalCode,
            'country' => $this->country,
            'countryCode' => $this->countryCode,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }

    /**
     * Check equality with another address.
     */
    public function equals(self $other): bool
    {
        return $this->street === $other->street
            && $this->city === $other->city
            && $this->state === $other->state
            && $this->postalCode === $other->postalCode
            && $this->country === $other->country
            && $this->countryCode === $other->countryCode;
    }
}
