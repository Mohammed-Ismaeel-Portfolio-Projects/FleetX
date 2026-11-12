<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DRIVER-EVAL-05 — promote driver_scores to authoritative driver performance projection.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_scores', function (Blueprint $table) {
            // NULL = no eligible evaluations (not a zero / poor score).
            $table->decimal('score', 5, 2)->nullable()->default(null)->change();

            $table->string('score_label', 32)->nullable()->after('score');
            $table->string('confidence', 20)->nullable()->after('score_label');
            $table->string('maturity', 20)->nullable()->after('confidence');
            $table->string('trend', 32)->nullable()->after('maturity');
            $table->decimal('recent_score', 5, 2)->nullable()->after('trend');
            $table->decimal('previous_score', 5, 2)->nullable()->after('recent_score');
            $table->decimal('trend_delta', 6, 2)->nullable()->after('previous_score');

            $table->unsignedInteger('evaluated_assignments_count')->default(0)->after('late_deliveries');
            $table->unsignedInteger('incomplete_assignments_count')->default(0)->after('evaluated_assignments_count');

            $table->json('category_scores')->nullable()->after('incomplete_assignments_count');
            $table->string('aggregation_version', 32)->nullable()->after('category_scores');
            $table->json('aggregation_metadata')->nullable()->after('aggregation_version');
            $table->timestamp('last_evaluated_at')->nullable()->after('aggregation_metadata');
            $table->timestamp('aggregated_at')->nullable()->after('last_evaluated_at');

            $table->index(['maturity']);
            $table->index(['trend']);
            $table->index(['last_evaluated_at']);
        });
    }

    public function down(): void
    {
        Schema::table('driver_scores', function (Blueprint $table) {
            $table->dropIndex(['maturity']);
            $table->dropIndex(['trend']);
            $table->dropIndex(['last_evaluated_at']);
            $table->dropColumn([
                'score_label',
                'confidence',
                'maturity',
                'trend',
                'recent_score',
                'previous_score',
                'trend_delta',
                'evaluated_assignments_count',
                'incomplete_assignments_count',
                'category_scores',
                'aggregation_version',
                'aggregation_metadata',
                'last_evaluated_at',
                'aggregated_at',
            ]);
            $table->decimal('score', 5, 2)->default(0)->nullable(false)->change();
        });
    }
};
