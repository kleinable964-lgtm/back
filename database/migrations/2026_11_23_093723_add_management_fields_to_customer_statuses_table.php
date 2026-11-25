<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $defaultId = DB::table('customer_statuses')->where('is_default', true)
            ->orderBy('created_at')->orderBy('id')->value('id');
        if ($defaultId !== null) {
            DB::table('customer_statuses')->where('is_default', true)->where('id', '!=', $defaultId)
                ->update(['is_default' => false]);
        }

        Schema::table('customer_statuses', function (Blueprint $table) {
            $table->json('disabled_for')->nullable();
            $table->unsignedTinyInteger('default_guard')->nullable()
                ->virtualAs('case when is_default = 1 then 1 else null end');
            $table->unique('default_guard');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_statuses', function (Blueprint $table) {
            $table->dropUnique(['default_guard']);
            $table->dropColumn(['disabled_for', 'default_guard']);
        });
    }
};
