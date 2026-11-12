<?php

namespace App\Services\Geocoding;

use App\Exceptions\GeocodingProviderException;
use App\Exceptions\GeocodingRateLimitException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Server-side GraphHopper Geocoding API client.
 * API key stays on the backend. Callers must not expose provider names to clients.
 */
final class GraphHopperGeocodingClient implements GeocodeProviderInterface
{
    /**
     * @return array{label: string|null, city: string|null, country: string|null, latitude: float, longitude: float}|null
     */
    public function reverse(float $latitude, float $longitude): ?array
    {
        try {
            $hits = $this->request([
                'reverse' => 'true',
                'point' => $latitude.','.$longitude,
                'limit' => 1,
            ]);

            $hit = $hits[0] ?? null;
            if (! is_array($hit)) {
                return [
                    'label' => null,
                    'city' => null,
                    'country' => null,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ];
            }

            return $this->mapHit($hit, $latitude, $longitude);
        } catch (\Throwable $e) {
            Log::warning('Reverse geocoding provider unavailable', [
                'error' => $e->getMessage(),
            ]);

            return [
                'label' => null,
                'city' => null,
                'country' => null,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ];
        }
    }

    /**
     * @return list<array{label: string|null, city: string|null, country: string|null, latitude: float, longitude: float}>
     */
    public function search(string $query): array
    {
        $hits = $this->request([
            'q' => $query,
            'limit' => 5,
        ]);

        $out = [];
        foreach ($hits as $hit) {
            if (! is_array($hit)) {
                continue;
            }
            $mapped = $this->mapHit($hit, null, null);
            if ($mapped !== null) {
                $out[] = $mapped;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, scalar>  $query
     * @return list<array<string, mixed>>
     *
     * @throws GeocodingRateLimitException
     * @throws GeocodingProviderException
     */
    private function request(array $query): array
    {
        $base = rtrim((string) config('routing.graphhopper.url', 'https://graphhopper.com/api/1'), '/');
        $apiKey = (string) config('routing.graphhopper.api_key', '');
        $timeout = (int) config('routing.graphhopper.timeout', 30);

        if ($apiKey === '') {
            Log::warning('Geocoding provider skipped: GraphHopper API key is not configured.');
            throw new GeocodingProviderException('Geocoding provider is not configured.');
        }

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->get($base.'/geocode', array_merge($query, [
                    'key' => $apiKey,
                    'locale' => 'en',
                ]));

            $status = $response->status();

            if ($status === 429) {
                Log::warning('Geocoding provider rate limit exceeded', ['status' => 429]);
                throw new GeocodingRateLimitException('Geocoding provider rate limit exceeded.');
            }

            if (! $response->successful()) {
                Log::warning('Geocoding provider request failed', ['status' => $status]);
                throw new GeocodingProviderException('Geocoding provider is unavailable.');
            }

            $hits = $response->json('hits');

            return is_array($hits) ? array_values($hits) : [];
        } catch (GeocodingRateLimitException|GeocodingProviderException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('Geocoding provider request exception', ['error' => $e->getMessage()]);
            throw new GeocodingProviderException('Geocoding provider request failed: '.$e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $hit
     * @return array{label: string|null, city: string|null, country: string|null, latitude: float, longitude: float}|null
     */
    private function mapHit(array $hit, ?float $fallbackLat, ?float $fallbackLng): ?array
    {
        $point = $hit['point'] ?? null;
        $lat = is_array($point) && is_numeric($point['lat'] ?? null) ? (float) $point['lat'] : $fallbackLat;
        $lng = is_array($point) && is_numeric($point['lng'] ?? null) ? (float) $point['lng'] : $fallbackLng;

        if ($lat === null || $lng === null) {
            return null;
        }

        $city = $this->nullableString($hit['city'] ?? $hit['name'] ?? null);
        $country = $this->nullableString($hit['country'] ?? null);
        $name = $this->nullableString($hit['name'] ?? null);
        $street = $this->nullableString($hit['street'] ?? null);
        $parts = array_values(array_unique(array_filter([$name, $street, $city, $country])));
        $label = $parts === [] ? null : implode(', ', $parts);

        return [
            'label' => $label,
            'city' => $city,
            'country' => $country,
            'latitude' => $lat,
            'longitude' => $lng,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
