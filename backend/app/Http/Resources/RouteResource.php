<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RouteResource
 * 
 * Transforms route data for API responses.
 * Follows Laravel API Resource conventions.
 */
class RouteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'route' => [
                'id' => $this->resource['route']['id'] ?? null,
                'origin' => $this->resource['route']['origin'] ?? [],
                'destination' => $this->resource['route']['destination'] ?? [],
                'distance' => $this->resource['route']['distance'] ?? [],
                'duration' => $this->resource['route']['duration'] ?? [],
                'eta' => $this->resource['route']['eta'] ?? null,
                'geometry' => $this->resource['route']['geometry'] ?? [],
                'legs' => $this->resource['route']['legs'] ?? [],
                'steps' => $this->resource['route']['steps'] ?? [],
                'waypoints' => $this->resource['route']['waypoints'] ?? [],
                'status' => $this->resource['route']['status'] ?? null,
                'travel_mode' => $this->resource['route']['travel_mode'] ?? null,
                'provider' => $this->resource['route']['provider'] ?? null,
            ],
            'metadata' => $this->resource['metadata'] ?? [],
        ];
    }
}
