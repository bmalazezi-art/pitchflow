<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_correction_requests', function (Blueprint $table) {
            $table->string('requested_action', 40)->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_correction_requests', function (Blueprint $table) {
            $table->dropColumn('requested_action');
        });
    }
};
