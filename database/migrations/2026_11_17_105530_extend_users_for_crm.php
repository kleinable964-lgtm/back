<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique();
            $table->string('role')->nullable()->index();
            $table->string('status')->default('pending')->index();
        });
        DB::table('users')->select('id')->orderBy('id')->chunkById(500, function ($users) {
            foreach ($users as $user) {
                DB::table('users')->where('id', $user->id)->update(['uuid' => (string) Str::uuid()]);
            }
        });
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropIndex(['role']);
            $table->dropIndex(['status']);
            $table->dropColumn(['uuid', 'role', 'status']);
        });
    }
};
