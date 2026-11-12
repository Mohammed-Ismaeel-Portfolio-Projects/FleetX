<?php

namespace Tests\Feature\Driver;

use App\Models\Driver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DriverDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_table_has_required_employee_columns(): void
    {
        $requiredColumns = [
            'employee_number',
            'first_name',
            'last_name',
            'date_of_birth',
            'nationality',
            'phone',
            'email',
            'address',
            'employment_date',
            'license_number',
            'license_class',
            'license_expiry_date',
            'medical_certificate_expiry',
            'years_of_experience',
            'status_id',
            'is_active',
            'emergency_contact_name',
            'emergency_contact_phone',
            'notes',
        ];

        foreach ($requiredColumns as $column) {
            $this->assertTrue(Schema::hasColumn('drivers', $column), sprintf('Missing drivers.%s column', $column));
        }

        $this->assertFalse(Schema::hasColumn('drivers', 'company_id'));
        $this->assertTrue(Schema::hasColumn('drivers', 'user_id'));
    }

    public function test_driver_factory_creates_employee_records_without_user_relation(): void
    {
        $driver = Driver::factory()->create();

        $this->assertSame('Available', $driver->status->name);
        $this->assertTrue($driver->is_active);
        $this->assertNull($driver->user_id);

        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'employee_number' => $driver->employee_number,
            'license_number' => $driver->license_number,
            'email' => $driver->email,
        ]);
    }
}