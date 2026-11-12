<?php

namespace App\Services\Pricing;

use App\Models\PricingPolicy;
use App\Models\PricingServiceCatalog;
use App\Services\Pricing\Exceptions\ServiceCatalogException;

class ServiceCatalogResolver
{
    /**
     * Resolve selected service IDs into trusted service items.
     *
     * @param PricingPolicy $policy
     * @param array<int> $serviceIds
     * @return array<array{id: string, name: string, price: float}>
     *
     * @throws ServiceCatalogException
     */
    public function resolve(PricingPolicy $policy, array $serviceIds): array
    {
        if (empty($serviceIds)) {
            return [];
        }

        $services = PricingServiceCatalog::where('pricing_policy_id', $policy->id)
            ->whereIn('id', $serviceIds)
            ->get()
            ->keyBy('id');

        $resolved = [];

        foreach ($serviceIds as $id) {
            $service = $services->get($id);

            if (!$service) {
                throw new ServiceCatalogException(
                    "Service ID '{$id}' not found in policy #{$policy->id}."
                );
            }

            if (!$service->enabled) {
                throw new ServiceCatalogException(
                    "Service '{$service->name}' (ID: {$id}) is disabled in policy #{$policy->id}."
                );
            }

            $resolved[] = [
                'id' => (string) $service->id,
                'name' => $service->name,
                'price' => (float) $service->price,
            ];
        }

        return $resolved;
    }
}
