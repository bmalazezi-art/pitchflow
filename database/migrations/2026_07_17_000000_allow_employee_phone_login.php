<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('phone_normalized', 32)->nullable()->after('phone');
        });

        foreach (DB::table('users')
            ->whereNotNull('phone')
            ->orderBy('id')
            ->cursor() as $user) {
            DB::table('users')->where('id', $user->id)->update([
                'phone_normalized' => $this->normalizePhone($user->phone),
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique(['organization_id', 'role', 'phone_normalized'], 'users_org_role_phone_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_org_role_phone_unique');
            $table->dropColumn('phone_normalized');
            $table->string('email')->nullable(false)->change();
        });
    }

    private function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $normalized = preg_replace('/[^\d+]/', '', trim($phone)) ?? '';

        if (str_starts_with($normalized, '00')) {
            $normalized = '+'.substr($normalized, 2);
        }

        return $normalized ?: null;
    }
};
