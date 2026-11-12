<?php

namespace App\Http\Controllers;

use App\Core\ApiResponse;
use App\Models\Status;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lightweight status lookup for frontend selectors.
 * Returns filtered statuses by type and/or name — read-only, no mutation.
 */
class StatusLookupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string|max:50',
            'name' => 'nullable|string|max:100',
        ]);

        $query = Status::where('type', $request->input('type'));

        if ($request->filled('name')) {
            $query->where('name', $request->input('name'));
        }

        return ApiResponse::success($query->select(['id', 'name', 'type'])->get());
    }
}
