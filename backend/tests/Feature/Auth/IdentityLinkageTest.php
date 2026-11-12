<?php

namespace Tests\Feature\Auth;

use App\Models\Company;
use App\Models\Driver;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IdentityLinkageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesSeeder::class);
        $this->seed(\Database\Seeders\StatusesSeeder::class);
    }

    public function test_drivers_table_has_nullable_user_id(): void
    {
        $this->assertTrue(Schema::hasColumn('drivers', 'user_id'));
        $this->assertTrue(Schema::hasColumn('users', 'company_id'));
    }

    public function test_dispatcher_login_and_me_unchanged_shape(): void
    {
        $dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $user = User::factory()->create([
            'email' => 'disp-id@fleetx.test',
            'password' => Hash::make('password'),
            'role_id' => $dispatcherRole->id,
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $login->assertOk();
        $login->assertJsonPath('data.user.role.name', 'dispatcher');

        $token = $login->json('data.access_token');
        $me = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');

        $me->assertOk();
        $me->assertJsonPath('data.role.name', 'dispatcher');
        $me->assertJsonPath('data.driver', null);
        $me->assertJsonPath('data.company', null);
    }

    public function test_driver_user_resolves_exactly_one_linked_driver(): void
    {
        $driverRole = Role::where('name', 'driver')->firstOrFail();
        $user = User::factory()->create([
            'email' => 'drv-id@fleetx.test',
            'password' => Hash::make('password'),
            'role_id' => $driverRole->id,
        ]);
        $driver = Driver::factory()->create(['user_id' => $user->id]);
        Driver::factory()->create(); // unrelated, unlinked

        $token = $this->loginToken($user->email, 'password');

        $identity = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/identity');

        $identity->assertOk();
        $identity->assertJsonPath('data.linked', true);
        $identity->assertJsonPath('data.driver.id', $driver->id);
        $identity->assertJsonPath('data.company', null);

        $me = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');
        $me->assertJsonPath('data.driver.id', $driver->id);

        $own = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/drivers/'.$driver->id);
        $own->assertOk();
    }

    public function test_driver_cannot_show_unrelated_driver(): void
    {
        $driverRole = Role::where('name', 'driver')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $driverRole->id,
            'password' => Hash::make('password'),
        ]);
        Driver::factory()->create(['user_id' => $user->id]);
        $other = Driver::factory()->create();

        $token = $this->loginToken($user->email, 'password');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/drivers/'.$other->id)
            ->assertForbidden();
    }

    public function test_unlinked_driver_user_identity_is_honest(): void
    {
        $driverRole = Role::where('name', 'driver')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $driverRole->id,
            'password' => Hash::make('password'),
        ]);

        $token = $this->loginToken($user->email, 'password');

        $identity = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/identity');

        $identity->assertOk();
        $identity->assertJsonPath('data.linked', false);
        $identity->assertJsonPath('data.driver', null);
        $this->assertNotEmpty($identity->json('data.unlink_reason'));
    }

    public function test_client_user_resolves_own_company_only(): void
    {
        $clientRole = Role::where('name', 'client')->firstOrFail();
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        $user = User::factory()->create([
            'role_id' => $clientRole->id,
            'company_id' => $company->id,
            'password' => Hash::make('password'),
        ]);

        $token = $this->loginToken($user->email, 'password');

        $identity = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/identity');

        $identity->assertOk();
        $identity->assertJsonPath('data.linked', true);
        $identity->assertJsonPath('data.company.id', $company->id);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/companies/'.$company->id)
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/companies/'.$otherCompany->id)
            ->assertForbidden();
    }

    public function test_unlinked_client_identity_is_honest(): void
    {
        $clientRole = Role::where('name', 'client')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $clientRole->id,
            'company_id' => null,
            'password' => Hash::make('password'),
        ]);

        $token = $this->loginToken($user->email, 'password');

        $identity = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/identity');

        $identity->assertOk();
        $identity->assertJsonPath('data.linked', false);
        $identity->assertJsonPath('data.company', null);
    }

    private function loginToken(string $email, string $password): string
    {
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);
        $login->assertOk();

        return $login->json('data.access_token');
    }
}
