<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthLogoutBlacklistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesSeeder::class);
    }

    public function test_logout_blacklists_current_token(): void
    {
        $role = Role::where('name', 'dispatcher')->firstOrFail();
        $user = User::factory()->create([
            'email' => 'disp-logout@fleetx.test',
            'password' => Hash::make('password'),
            'role_id' => $role->id,
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $login->assertOk();
        $token = $login->json('data.access_token');
        $this->assertIsString($token);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.role.name', 'dispatcher');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Successfully logged out.');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_me_rejects_invalid_token(): void
    {
        $this->withHeader('Authorization', 'Bearer not-a-valid-token')
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }
}
