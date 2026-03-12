<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_stations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('station_id', 50)->unique();
            $table->string('mqtt_username', 100)->unique();
            $table->string('mqtt_password_hash');
            $table->text('mqtt_password_encrypted');
            $table->string('protocol_version', 10)->default('0.1.0');
            $table->boolean('is_connected')->default(false);
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamp('last_boot_at')->nullable();
            $table->integer('bay_count')->nullable();
            $table->string('firmware_version', 50)->nullable();
            $table->string('station_model', 100)->nullable();
            $table->string('station_vendor', 100)->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index('mqtt_username');
            $table->index('station_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_stations');
    }
};
