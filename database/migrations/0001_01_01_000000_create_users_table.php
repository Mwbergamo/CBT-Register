<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Associates sign in exclusively via Microsoft 365 SSO (see
     * App\Http\Controllers\Auth\AzureLoginController), so there is no local
     * password. The `password` column is kept (nullable) only because a few
     * framework/package internals expect the column to exist on the users
     * table; it is never set or used.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();

            // Microsoft 365 / Entra ID identity.
            $table->string('azure_oid')->unique()->nullable();
            $table->string('azure_upn')->nullable();

            // 'associate' can check customers out; 'manager' additionally
            // sees void/void-invoice tooling. Kept simple on purpose — real
            // authorization (who is "approved" to use the register) lives in
            // your Azure AD group/app assignment, not here.
            $table->string('role')->default('associate');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();

            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
