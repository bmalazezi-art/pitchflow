<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('country', 2)->default('XK');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['name', 'country']);
        });

        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('email');
            $table->string('phone', 32);
            $table->string('address');
            $table->string('status', 24)->default('pending')->index();
            $table->string('subscription_plan')->nullable();
            $table->unsignedSmallInteger('number_of_fields')->default(1);
            $table->string('timezone', 64)->default('Europe/Pristina');
            $table->string('currency', 3)->default('EUR');
            $table->string('locale', 8)->default('en');
            $table->unsignedSmallInteger('cancellation_window_minutes')->default(120);
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['city_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('cities');
    }
};
