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
        Schema::table('drivers', function (Blueprint $table) {
            // Drop user_id foreign key constraint first
            $table->dropForeign(['user_id']);
            
            // Drop user_id column
            $table->dropColumn('user_id');

            // Add is_active column after status_id
            $table->boolean('is_active')->default(true)->after('status_id');

            // Add audit fields after notes
            $table->unsignedBigInteger('created_by')->nullable()->after('notes');
            $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');

            // Add indexes for new columns
            $table->index('is_active');
            $table->index('created_by');
            $table->index('updated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            // Drop indexes for added columns
            $table->dropIndex(['is_active']);
            $table->dropIndex(['created_by']);
            $table->dropIndex(['updated_by']);

            // Drop added columns
            $table->dropColumn(['is_active', 'created_by', 'updated_by']);

            // Add back user_id column and foreign key
            $table->foreignId('user_id')->unique()->after('id')->constrained('users')->restrictOnDelete();
        });
    }
};
