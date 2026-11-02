<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->decimal('avg_balance', 15, 2)->default(0);
            $table->json('usage_pattern')->nullable();
            $table->timestamps();
        });

        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('account_number')->unique();
            $table->string('type')->default('current');
            $table->string('status')->default('active');
            $table->decimal('balance', 15, 2)->default(0);
            $table->decimal('transfer_limit', 15, 2)->nullable();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_fingerprint')->unique();
            $table->string('device_type')->nullable();
            $table->timestamp('sim_changed_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->string('last_location')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_trusted')->default(false);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->unique(['customer_id', 'device_id']);
        });

        Schema::create('beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('beneficiary_account');
            $table->string('beneficiary_name')->nullable();
            $table->string('bank_name')->nullable();
            $table->timestamp('added_at')->nullable();
            $table->boolean('is_frozen')->default(false);
            $table->timestamps();
            $table->index('beneficiary_account');
        });

        Schema::create('account_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type');
            $table->string('ip_address', 45)->nullable();
            $table->json('details')->nullable();
            $table->timestamp('event_time');
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->timestamps();
            $table->index(['account_id', 'event_time']);
            $table->index('event_type');
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('beneficiary_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('channel')->default('mobile');
            $table->string('ip_address', 45)->nullable();
            $table->string('city')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('transaction_time');
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->timestamps();
            $table->index(['account_id', 'transaction_time']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        foreach (['transactions', 'account_events', 'beneficiaries', 'customer_devices', 'devices', 'accounts', 'customers'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
