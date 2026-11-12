<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DRIVER-EVAL-04 — explainable scoring fields on assignment_evaluations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignment_evaluations', function (Blueprint $table) {
            $table->decimal('overall_score', 5, 2)->nullable()->after('evidence_quality');
            $table->string('score_label', 32)->nullable()->after('overall_score');
            $table->string('confidence', 20)->nullable()->after('score_label');
            $table->string('scoring_version', 32)->nullable()->after('confidence');
            $table->json('category_scores')->nullable()->after('scoring_version');
            $table->json('scoring_metadata')->nullable()->after('category_scores');

            $table->index(['overall_score']);
            $table->index(['confidence']);
        });
    }

    public function down(): void
    {
        Schema::table('assignment_evaluations', function (Blueprint $table) {
            $table->dropIndex(['overall_score']);
            $table->dropIndex(['confidence']);
            $table->dropColumn([
                'overall_score',
                'score_label',
                'confidence',
                'scoring_version',
                'category_scores',
                'scoring_metadata',
            ]);
        });
    }
};
