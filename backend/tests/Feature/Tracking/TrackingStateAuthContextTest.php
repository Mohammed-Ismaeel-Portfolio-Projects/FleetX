<?php

namespace Tests\Feature\Tracking;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class TrackingStateAuthContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracking_state_index_uses_api_guard_as_the_default_auth_context(): void
    {
        $adminRole = Role::create(['name' => 'admin']);
        $user = User::factory()->create(['role_id' => $adminRole->id]);
        $token = Auth::guard('api')->login($user);

        Auth::shouldUse('web');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/tracking-states');

        $response->assertOk()
            ->assertJsonPath('success', true);
    }
}
