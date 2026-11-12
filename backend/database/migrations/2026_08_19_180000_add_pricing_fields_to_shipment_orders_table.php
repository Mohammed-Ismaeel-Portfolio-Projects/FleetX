<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_orders', function (Blueprint $table) {
            $table->string('trailer_type', 50)->nullable()->after('cargo_type');
            $table->json('selected_service_ids')->nullable()->after('temperature_controlled');
            $table->boolean('border_crossing')->default(false)->after('selected_service_ids');
            $table->boolean('insurance_required')->default(false)->after('border_crossing');
        });
    }

    public function down(): void
    {
        Schema::table('shipment_orders', function (Blueprint $table) {
            $table->dropColumn([
                'trailer_type',
                'selected_service_ids',
                'border_crossing',
                'insurance_required',
            ]);
        });
    }
};
