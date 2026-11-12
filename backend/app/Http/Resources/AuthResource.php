<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps the result of an auth operation (login/register/refresh):
 * the authenticated user plus the issued JWT payload.
 *
 * Usage: new AuthResource(['user' => $user, 'access_token' => $token, 'expires_in' => $seconds])
 */
class AuthResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => new UserResource($this->resource['user']),
            'access_token' => $this->resource['access_token'],
            'token_type' => 'Bearer',
            'expires_in' => $this->resource['expires_in'],
        ];
    }
}
