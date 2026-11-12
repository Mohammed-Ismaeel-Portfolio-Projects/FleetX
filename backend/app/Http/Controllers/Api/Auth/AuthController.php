<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\AuthResource;
use App\Http\Resources\UserResource;
use App\Actions\Auth\ResolveAuthIdentityAction;
use App\Core\ApiResponse;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(protected AuthService $authService)
    {
    }

    /**
     * POST /api/auth/register
     * Public — always creates a client account.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return (new AuthResource($result))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * POST /api/auth/login
     * Public.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        return (new AuthResource($result))->response();
    }

    /**
     * POST /api/auth/logout
     * Protected — invalidates the current token only.
     */
    public function logout(): JsonResponse
    {
        $this->authService->logout();

        return response()->json([
            'message' => 'Successfully logged out.',
        ]);
    }

    /**
     * POST /api/auth/refresh
     * Protected — rotates the current token for a new one.
     */
    public function refresh(): JsonResponse
    {
        $result = $this->authService->refresh();

        return (new AuthResource($result))->response();
    }

    /**
     * GET /api/auth/me
     * Protected.
     */
    public function me(): JsonResponse
    {
        $user = $this->authService->me();

        return (new UserResource($user))->response();
    }

    /**
     * GET /api/auth/identity
     * Protected — resolves Driver/Company linkage for the current token.
     * Unlinked driver/client users return linked=false (honest, no fabricated IDs).
     */
    public function identity(ResolveAuthIdentityAction $action): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::guard('api')->user();

        $result = $action->execute($user);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success($result->data, $result->message);
    }
}
