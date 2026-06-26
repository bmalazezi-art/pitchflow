<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('football_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('address')->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->decimal('price_per_hour', 10, 2)->default(0);
            $table->time('opening_time')->default('12:00');
            $table->time('closing_time')->default('01:00');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'slug']);
            $table->index(['organization_id', 'status']);
            $table->index(['city_id', 'status']);
        });

        Schema::create('employee_field_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('football_field_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'football_field_id']);
            $table->index(['organization_id', 'football_field_id'], 'employee_field_org_field_idx');
        });

        Schema::create('operating_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('football_field_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('opening_time')->default('12:00');
            $table->time('closing_time')->default('01:00');
            $table->boolean('is_closed')->default(false);
            $table->timestamps();
            $table->unique(['football_field_id', 'day_of_week']);
        });

        Schema::create('operating_hour_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('football_field_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('opening_time')->nullable();
            $table->time('closing_time')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->unique(['football_field_id', 'date']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('preferred_field_id')->nullable()->constrained('football_fields')->nullOnDelete();
            $table->string('name');
            $table->string('phone', 32);
            $table->string('phone_normalized', 32);
            $table->string('reliability_status', 24)->default('reliable')->index();
            $table->unsignedTinyInteger('reliability_score')->default(100);
            $table->unsignedInteger('total_reservations')->default(0);
            $table->unsignedInteger('completed_reservations')->default(0);
            $table->unsignedInteger('cancelled_reservations')->default(0);
            $table->unsignedInteger('late_cancellations')->default(0);
            $table->unsignedInteger('no_shows')->default(0);
            $table->timestamp('last_visit_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'phone_normalized']);
            $table->index(['organization_id', 'name']);
        });

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('football_field_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone', 32);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 24)->default('confirmed')->index();
            $table->string('payment_status', 16)->default('unpaid');
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->boolean('is_walk_in')->default(false);
            $table->text('notes')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'starts_at']);
            $table->index(['football_field_id', 'starts_at', 'ends_at']);
            $table->index(['organization_id', 'status', 'starts_at']);
        });

        Schema::create('reservation_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('football_field_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->timestamps();
            $table->unique(['football_field_id', 'starts_at']);
            $table->index(['organization_id', 'starts_at']);
        });

        Schema::create('customer_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'customer_id']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80)->index();
            $table->nullableMorphs('entity');
            $table->text('description')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['organization_id', 'created_at']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('plan_name');
            $table->decimal('price', 10, 2)->default(0);
            $table->string('billing_cycle', 16)->default('monthly');
            $table->string('status', 16)->default('trial')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('customer_notes');
        Schema::dropIfExists('reservation_slots');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('operating_hour_overrides');
        Schema::dropIfExists('operating_hours');
        Schema::dropIfExists('employee_field_assignments');
        Schema::dropIfExists('football_fields');
    }
};
