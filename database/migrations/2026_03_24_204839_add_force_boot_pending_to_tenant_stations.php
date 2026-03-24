<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_stations', function (Blueprint $table) {
            $table->boolean('force_boot_pending')->default(false)->after('force_boot_reject');
            $table->integer('boot_retry_interval')->nullable()->after('force_boot_pending');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_stations', function (Blueprint $table) {
            $table->dropColumn(['force_boot_pending', 'boot_retry_interval']);
        });
    }
};
