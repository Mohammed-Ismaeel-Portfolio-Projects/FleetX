<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricing_policies', function (Blueprint $table) {
            $table->decimal('tax_rate', 8, 4)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('pricing_policies', function (Blueprint $table) {
            $table->decimal('tax_rate', 5, 4)->default(0.0800)->change();
        });
    }
};
