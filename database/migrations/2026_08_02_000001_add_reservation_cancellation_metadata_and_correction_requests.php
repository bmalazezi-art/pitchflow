<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('cancelled_by_user_id')->nullable()->after('cancelled_by')->constrained('users')->nullOnDelete();
            $table->string('previous_status', 24)->nullable()->after('cancellation_reason');
            $table->text('cancellation_note')->nullable()->after('previous_status');
        });

        Schema::create('reservation_correction_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 80);
            $table->text('note')->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->string('review_action', 40)->nullable();
            $table->text('review_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_correction_requests');

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by_user_id');
            $table->dropColumn(['previous_status', 'cancellation_note']);
        });
    }
};
