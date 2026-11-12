<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D7 — Align delivery_proofs / issues shipment_id with UUID shipments.
 *
 * After 2026_07_02 shipment UUID rebuild, FKs were dropped but columns
 * remained bigint, making DeliveryProof / Issue unusable with live shipments.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('delivery_proofs') && Schema::hasColumn('delivery_proofs', 'shipment_id')) {
            DB::table('delivery_proofs')->delete();
            $this->dropConstraintIfExists('delivery_proofs', 'delivery_proofs_shipment_id_foreign');
            $this->dropConstraintIfExists('delivery_proofs', 'delivery_proofs_shipment_id_unique');
            Schema::table('delivery_proofs', function (Blueprint $table) {
                $table->dropColumn('shipment_id');
            });
            Schema::table('delivery_proofs', function (Blueprint $table) {
                $table->uuid('shipment_id')->after('id');
            });
            Schema::table('delivery_proofs', function (Blueprint $table) {
                $table->foreign('shipment_id')->references('id')->on('shipments')->restrictOnDelete();
                $table->unique('shipment_id');
            });
        }

        if (Schema::hasTable('issues') && Schema::hasColumn('issues', 'shipment_id')) {
            DB::table('issues')->delete();
            $this->dropConstraintIfExists('issues', 'issues_shipment_id_foreign');
            $this->dropIndexIfExists('issues', 'issues_shipment_id_index');
            Schema::table('issues', function (Blueprint $table) {
                $table->dropColumn('shipment_id');
            });
            Schema::table('issues', function (Blueprint $table) {
                $table->uuid('shipment_id')->after('id');
            });
            Schema::table('issues', function (Blueprint $table) {
                $table->foreign('shipment_id')->references('id')->on('shipments')->restrictOnDelete();
                $table->index('shipment_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('delivery_proofs') && Schema::hasColumn('delivery_proofs', 'shipment_id')) {
            DB::table('delivery_proofs')->delete();
            $this->dropConstraintIfExists('delivery_proofs', 'delivery_proofs_shipment_id_foreign');
            $this->dropConstraintIfExists('delivery_proofs', 'delivery_proofs_shipment_id_unique');
            Schema::table('delivery_proofs', function (Blueprint $table) {
                $table->dropColumn('shipment_id');
            });
            Schema::table('delivery_proofs', function (Blueprint $table) {
                $table->unsignedBigInteger('shipment_id')->after('id')->unique();
            });
        }

        if (Schema::hasTable('issues') && Schema::hasColumn('issues', 'shipment_id')) {
            DB::table('issues')->delete();
            $this->dropConstraintIfExists('issues', 'issues_shipment_id_foreign');
            $this->dropIndexIfExists('issues', 'issues_shipment_id_index');
            Schema::table('issues', function (Blueprint $table) {
                $table->dropColumn('shipment_id');
            });
            Schema::table('issues', function (Blueprint $table) {
                $table->unsignedBigInteger('shipment_id')->after('id')->index();
            });
        }
    }

    private function dropConstraintIfExists(string $table, string $constraint): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$constraint}");
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("DROP INDEX IF EXISTS {$index}");
    }
};
