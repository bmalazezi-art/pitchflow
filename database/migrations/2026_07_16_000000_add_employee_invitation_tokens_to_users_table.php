<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
            $table->string('invitation_token_hash', 128)->nullable()->after('invited_at')->unique();
            $table->timestamp('invitation_expires_at')->nullable()->after('invitation_token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['invitation_token_hash', 'invitation_expires_at']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
