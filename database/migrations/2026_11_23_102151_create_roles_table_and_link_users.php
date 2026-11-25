<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $unsupported = DB::table('users')->whereNotNull('role')->distinct()->pluck('role')
            ->diff(['admin', 'manager', 'agent']);
        if ($unsupported->isNotEmpty()) {
            throw new RuntimeException('Users have unsupported roles. Assign admin, manager or agent before running this migration.');
        }

        Schema::create('roles', function (Blueprint $table) {
            $table->string('name')->primary();
            $table->string('label');
            $table->timestamps(6);
        });
        $now = now();
        DB::table('roles')->insert([
            ['name' => 'admin', 'label' => 'Admin', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'manager', 'label' => 'Manager', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'agent', 'label' => 'Agent', 'created_at' => $now, 'updated_at' => $now],
        ]);
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('role')->references('name')->on('roles')->restrictOnUpdate()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role']);
        });
        Schema::dropIfExists('roles');
    }
};
