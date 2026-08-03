<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('waiting_list_requests', function (Blueprint $table) {
            $table->text('note')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('waiting_list_requests', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
