<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('risk_scores', function (Blueprint $table) {
            $table->decimal('shadow_probability', 5, 4)->nullable()->after('model_probability');
            $table->foreignId('shadow_model_run_id')->nullable()->after('shadow_probability')->constrained('model_runs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('risk_scores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shadow_model_run_id');
            $table->dropColumn('shadow_probability');
        });
    }
};
