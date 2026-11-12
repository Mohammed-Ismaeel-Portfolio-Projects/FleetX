<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reverse ShipmentOrder traceability FK (C2.9-B3.2).
     *
     * Nullable for legacy/direct-created Shipments. UNIQUE enforces at most one
     * Shipment per ShipmentOrder when conversion links are established.
     */
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->uuid('shipment_order_id')->nullable()->unique()->after('customer_company_id');
            $table->foreign('shipment_order_id')
                ->references('id')
                ->on('shipment_orders')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropForeign(['shipment_order_id']);
            $table->dropUnique(['shipment_order_id']);
            $table->dropColumn('shipment_order_id');
        });
    }
};
