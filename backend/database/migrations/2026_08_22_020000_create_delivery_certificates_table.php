<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('delivery_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('delivery_proof_id')->constrained('delivery_proofs')->cascadeOnDelete();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at');
            $table->string('file_path');
            $table->string('hash', 64);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_certificates');
    }
};
