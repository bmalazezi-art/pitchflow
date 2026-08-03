<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waiting_list_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('football_field_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('customer_name');
            $table->string('phone', 32);
            $table->string('email')->nullable();
            $table->string('status', 24)->default('waiting')->index();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['football_field_id', 'date', 'start_time', 'end_time', 'status'], 'waiting_slot_status_index');
            $table->index(['organization_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waiting_list_requests');
    }
};
