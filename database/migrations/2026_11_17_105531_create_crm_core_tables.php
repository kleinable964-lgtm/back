<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->uuid('id')->primary();
        });

        Schema::create('desks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('category')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps(6);
        });

        Schema::create('desk_user', function (Blueprint $table) {
            $table->foreignUuid('desk_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['desk_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('domain')->unique();
            $table->string('ai_trading_mode')->nullable();
            $table->string('design_version')->nullable();
            $table->string('ai_version')->nullable();
            $table->string('arbitrage_version')->nullable();
            $table->string('festgeld_design_variant')->nullable();
            $table->string('festgeld_registration_flow')->nullable();
            $table->boolean('gold_arbitrage_visible')->default(false);
            $table->boolean('disable_crypto_withdrawals')->default(false);
            $table->boolean('disable_wire_withdrawals')->default(false);
            $table->boolean('require_kyc_for_withdrawals')->default(true);
            $table->boolean('clients_see_all_custom_assets')->default(false);
            $table->boolean('transactional_emails_enabled')->default(false);
            $table->boolean('forgot_password_enabled')->default(false);
            $table->boolean('ai_financial_advisor_enabled')->default(false);
            $table->json('transactional_email_templates')->nullable();
            $table->timestamps(6);
        });

        Schema::create('countries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->char('iso_code', 2)->unique();
        });

        Schema::create('customer_statuses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('color')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_ftd_status')->default(false);
            $table->integer('order_weight')->default(0);
            $table->timestamps(6);
        });

        Schema::create('affiliates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->index();
            $table->string('phone')->nullable();
            $table->string('latin_name_alias')->nullable();
            $table->string('additional_phone')->nullable();
            $table->ipAddress('register_ip')->nullable();
            $table->string('source')->nullable();
            $table->text('marketing_details')->nullable();
            $table->foreignUuid('brand_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('country_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('customer_status_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('affiliate_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('desk_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('signup_status')->default('pending')->index();
            $table->text('signup_rejection_reason')->nullable();
            $table->boolean('offering_ipos_enabled')->default(false);
            $table->boolean('offering_icos_enabled')->default(false);
            $table->boolean('offering_stocks_enabled')->default(false);
            $table->boolean('offering_commodities_enabled')->default(false);
            $table->timestamps(6);
            $table->index(['brand_id', 'email']);
            $table->index(['desk_id', 'assigned_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
        Schema::dropIfExists('affiliates');
        Schema::dropIfExists('customer_statuses');
        Schema::dropIfExists('countries');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('desk_user');
        Schema::dropIfExists('desks');
        Schema::dropIfExists('departments');
    }
};
