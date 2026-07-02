<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('role')->index();
            $table->json('permissions')->nullable()->after('preferred_language');
            $table->timestamp('invited_at')->nullable()->after('last_login_at');
            $table->timestamp('invitation_accepted_at')->nullable()->after('invited_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['status', 'permissions', 'invited_at', 'invitation_accepted_at']);
        });
    }
};
