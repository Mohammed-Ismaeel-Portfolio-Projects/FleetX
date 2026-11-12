<?php

namespace App\Actions\Client;

use App\Core\ApiResult;
use App\Exceptions\GeocodingProviderException;
use App\Exceptions\GeocodingRateLimitException;
use App\Services\Geocoding\GeocodeProviderInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Forward-geocode a search query for map picker centering.
 */
class SearchGeocodeLocationsAction
{
    public function __construct(private GeocodeProviderInterface $geocoding)
    {
    }

    public function execute(string $query): ApiResult
    {
        $normalized = mb_strtolower(trim($query));
        if (mb_strlen($normalized) < 2) {
            return ApiResult::success([
                'status_code' => 'SUCCESS_EMPTY',
                'results' => [],
            ], 'Location search completed');
        }

        $cacheKey = 'geocode:search:'.md5($normalized);

        try {
            $hits = Cache::remember($cacheKey, now()->addHours(24), function () use ($normalized) {
                return $this->geocoding->search($normalized);
            });

            $statusCode = count($hits) > 0 ? 'SUCCESS_WITH_RESULTS' : 'SUCCESS_EMPTY';

            return ApiResult::success([
                'status_code' => $statusCode,
                'results' => $hits,
            ], 'Location search completed');
        } catch (GeocodingRateLimitException $e) {
            Cache::forget($cacheKey);

            return ApiResult::error(
                'Location search rate limit reached.',
                429,
                ['status_code' => 'RATE_LIMITED']
            );
        } catch (GeocodingProviderException $e) {
            Cache::forget($cacheKey);

            return ApiResult::error(
                'Location search service is currently unavailable.',
                503,
                ['status_code' => 'PROVIDER_UNAVAILABLE']
            );
        }
    }
}
