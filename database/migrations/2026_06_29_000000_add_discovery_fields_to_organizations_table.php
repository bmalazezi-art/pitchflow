<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->json('amenities')->nullable()->after('address');
            $table->timestamp('featured_from')->nullable()->after('approved_at');
            $table->timestamp('featured_until')->nullable()->after('featured_from');
            $table->index(['featured_from', 'featured_until']);
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropIndex(['featured_from', 'featured_until']);
            $table->dropColumn(['amenities', 'featured_from', 'featured_until']);
        });
    }
};
