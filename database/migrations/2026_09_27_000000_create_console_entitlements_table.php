<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('console-integration.connection'))->create('console_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->string('environment', 32);
            $table->string('product', 64);
            $table->string('company_id', 64);
            $table->unsignedBigInteger('revision');
            $table->string('status', 32);
            $table->unsignedBigInteger('valid_until');
            $table->unsignedBigInteger('trial_ends_at')->nullable();
            $table->unsignedBigInteger('access_ends_at')->nullable();
            $table->string('payload_hash', 64);
            $table->unique(['environment', 'product', 'company_id'], 'console_entitlements_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::connection(config('console-integration.connection'))->dropIfExists('console_entitlements');
    }
};
