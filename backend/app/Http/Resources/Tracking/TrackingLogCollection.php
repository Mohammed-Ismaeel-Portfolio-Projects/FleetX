<?php

namespace App\Http\Resources\Tracking;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class TrackingLogCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => TrackingLogResource::collection($this->collection),
            'meta' => [
                'current_page' => $this->currentPage(),
                'per_page' => $this->perPage(),
                'total' => $this->total(),
                'last_page' => $this->lastPage(),
                'from' => $this->firstItem(),
                'to' => $this->lastItem(),
            ],
        ];
    }
}
