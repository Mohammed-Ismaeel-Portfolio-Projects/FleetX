<?php

namespace Tests\Feature\Tracking;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * INT-007 / INT-027 — tracking-states index must not dump JWT or identity debug logs.
 * Authorization rules remain admin/dispatcher only.
 */
class TrackingStateDebugLoggingTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $dispatcherRole;

    private Role $driverRole;

    private Role $clientRole;

    /** @var list<string> */
    private array $loggedMessages = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);

        $this->adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();
        $this->clientRole = Role::where('name', 'client')->firstOrFail();

        $this->loggedMessages = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event): void {
            $this->loggedMessages[] = (string) $event->message;
        });
    }

    public function test_unauthenticated_tracking_states_index_is_rejected(): void
    {
        $this->getJson('/api/v1/tracking-states')->assertUnauthorized();
    }

    public function test_admin_may_list_tracking_states(): void
    {
        $admin = $this->makeUser($this->adminRole);

        $this->withToken($this->tokenFor($admin))
            ->getJson('/api/v1/tracking-states')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_dispatcher_may_list_tracking_states(): void
    {
        $dispatcher = $this->makeUser($this->dispatcherRole);

        $this->withToken($this->tokenFor($dispatcher))
            ->getJson('/api/v1/tracking-states')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_client_cannot_list_tracking_states(): void
    {
        $client = $this->makeClient($this->makeCompany());

        $this->withToken($this->tokenFor($client))
            ->getJson('/api/v1/tracking-states')
            ->assertForbidden();
    }

    public function test_driver_cannot_list_tracking_states(): void
    {
        $driver = $this->makeUser($this->driverRole);

        $this->withToken($this->tokenFor($driver))
            ->getJson('/api/v1/tracking-states')
            ->assertForbidden();
    }

    public function test_tracking_states_index_does_not_log_jwt_or_identity_debug(): void
    {
        $admin = $this->makeUser($this->adminRole);
        $token = $this->tokenFor($admin);

        $this->loggedMessages = [];

        $this->withToken($token)
            ->getJson('/api/v1/tracking-states')
            ->assertOk();

        $combined = implode("\n", $this->loggedMessages);

        $this->assertStringNotContainsString('JWT PIPELINE DEBUG', $combined);
        $this->assertStringNotContainsString('Authorization header:', $combined);
        $this->assertStringNotContainsString('JWTAuth::getToken()', $combined);
        $this->assertStringNotContainsString('JWTAuth::parseToken()', $combined);
        $this->assertStringNotContainsString('TrackingStatePolicy::viewAny', $combined);
        $this->assertStringNotContainsString('Route reached: GET /api/v1/tracking-states', $combined);
        $this->assertStringNotContainsString('QueryTrackingStatesRequest::authorize', $combined);
        $this->assertStringNotContainsString('QueryTrackingStatesAction::execute', $combined);
        $this->assertStringNotContainsString($token, $combined);
        $this->assertStringNotContainsString('User ID: '.$admin->id, $combined);
    }

    public function test_forbidden_tracking_states_index_does_not_log_jwt_debug(): void
    {
        $client = $this->makeClient($this->makeCompany());
        $token = $this->tokenFor($client);

        $this->loggedMessages = [];

        $this->withToken($token)
            ->getJson('/api/v1/tracking-states')
            ->assertForbidden();

        $combined = implode("\n", $this->loggedMessages);

        $this->assertStringNotContainsString('JWT PIPELINE DEBUG', $combined);
        $this->assertStringNotContainsString('Authorization header:', $combined);
        $this->assertStringNotContainsString($token, $combined);
    }

    private function makeUser(Role $role): User
    {
        return User::factory()->create([
            'role_id' => $role->id,
            'password' => Hash::make('password'),
        ]);
    }

    private function makeClient(Company $company): User
    {
        return User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => $company->id,
            'password' => Hash::make('password'),
        ]);
    }

    private function makeCompany(): Company
    {
        return Company::factory()->create();
    }

    private function tokenFor(User $user): string
    {
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $login->assertOk();

        return $login->json('data.access_token');
    }
}
