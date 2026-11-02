<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_name');
            $table->text('description')->nullable();
            $table->unsignedInteger('weight')->default(10);
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->json('conditions');
            $table->string('action')->default('flag');
            $table->timestamps();
            $table->index(['is_active', 'priority']);
        });

        Schema::create('watchlists', function (Blueprint $table) {
            $table->id();
            $table->string('list_type');
            $table->string('entity_type');
            $table->string('entity_value');
            $table->string('reason')->nullable();
            $table->timestamp('added_at')->nullable();
            $table->timestamps();
            $table->index(['entity_type', 'entity_value']);
        });

        Schema::create('ml_models', function (Blueprint $table) {
            $table->id();
            $table->string('model_name');
            $table->string('algorithm')->nullable();
            $table->string('status')->default('candidate');
            $table->timestamps();
        });

        Schema::create('model_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('model_id')->constrained('ml_models')->cascadeOnDelete();
            $table->string('version_number');
            $table->json('metrics')->nullable();
            $table->timestamp('trained_at')->nullable();
            $table->timestamps();
        });

        Schema::create('risk_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('account_events')->cascadeOnDelete();
            $table->foreignId('model_run_id')->nullable()->constrained('model_runs')->nullOnDelete();
            $table->unsignedSmallInteger('score');
            $table->string('decision');
            $table->json('reasons')->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->unsignedSmallInteger('rule_score')->default(0);
            $table->decimal('model_probability', 5, 4)->nullable();
            $table->boolean('used_fallback')->default(false);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->json('features')->nullable();
            $table->timestamps();
            $table->index('score');
            $table->index('decision');
        });

        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('open');
            $table->string('priority')->default('medium');
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->nullable()->constrained('cases')->nullOnDelete();
            $table->foreignId('risk_score_id')->constrained('risk_scores')->cascadeOnDelete();
            $table->string('status')->default('open');
            $table->string('priority')->default('medium');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision')->nullable();
            $table->text('decision_reason')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'priority']);
        });

        Schema::create('case_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note');
            $table->boolean('is_ai_generated')->default(false);
            $table->timestamps();
        });

        Schema::create('case_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['case_attachments', 'case_notes', 'alerts', 'cases', 'risk_scores', 'model_runs', 'ml_models', 'watchlists', 'rules'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
