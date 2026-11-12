<?php

namespace App\Services\Auth;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Register a new client account.
     *
     * Public registration always creates a "client" account — role_id is
     * never accepted from the request, it is always resolved from the
     * seeded roles table.
     *
     * @return array{user: User, access_token: string, expires_in: int}
     */
    public function register(array $data): array
    {
        $clientRole = Role::where('name', 'client')->firstOrFail();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'], // hashed automatically via the model's cast
            'role_id' => $clientRole->id,
        ]);

        $user->load('role');

        $token = Auth::guard('api')->login($user);

        $this->logAudit($user, 'register', [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $clientRole->name,
        ]);

        return $this->respondWithToken($user->load(['role', 'driver', 'company']), $token);
    }

    /**
     * Attempt to authenticate a user and issue a token.
     *
     * @return array{user: User, access_token: string, expires_in: int}
     *
     * @throws ValidationException when credentials are invalid
     */
    public function login(array $credentials): array
    {
        $existingUser = User::where('email', $credentials['email'])->first();

        $token = Auth::guard('api')->attempt($credentials);

        if (! $token) {
            // Audit-log the failed attempt only if we have a real user to
            // attach it to: audit_logs.user_id is a NOT-NULL foreign key,
            // so an attempt against a non-existent email cannot be written
            // there. We fall back to the application log for that case
            // (schema is frozen for this project — no nullable user_id
            // column is available as an alternative).
            if ($existingUser) {
                $this->logAudit($existingUser, 'login_failed');
            } else {
                Log::warning('Failed login attempt for unknown email.', [
                    'email' => $credentials['email'],
                ]);
            }

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        /** @var User $user */
        $user = Auth::guard('api')->user();
        $user->load(['role', 'driver', 'company']);

        $this->logAudit($user, 'login');

        return $this->respondWithToken($user, $token);
    }

    /**
     * Log out the currently authenticated user.
     *
     * Per Phase 5 decision: per-token logout only (blacklists the current
     * token). Logout-from-all-devices is explicitly out of scope.
     */
    public function logout(): void
    {
        /** @var User|null $user */
        $user = Auth::guard('api')->user();

        Auth::guard('api')->logout();

        if ($user) {
            $this->logAudit($user, 'logout');
        }
    }

    /**
     * Rotate the current token: invalidate it and issue a new one.
     *
     * @return array{user: User, access_token: string, expires_in: int}
     */
    public function refresh(): array
    {
        $token = Auth::guard('api')->refresh();

        /** @var User $user */
        $user = Auth::guard('api')->user();
        $user->load(['role', 'driver', 'company']);

        return $this->respondWithToken($user, $token);
    }

    /**
     * Return the currently authenticated user.
     */
    public function me(): User
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        return $user->load(['role', 'driver', 'company']);
    }

    /**
     * Build the standard {user, access_token, expires_in} payload.
     */
    protected function respondWithToken(User $user, string $token): array
    {
        return [
            'user' => $user,
            'access_token' => $token,
            'expires_in' => Auth::guard('api')->factory()->getTTL() * 60,
        ];
    }

    /**
     * Write an authentication event into the existing audit_logs table.
     */
    protected function logAudit(User $user, string $action, array $newValues = []): void
    {
        AuditLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'old_values' => null,
            'new_values' => $newValues !== [] ? json_encode($newValues) : null,
        ]);
    }
}
