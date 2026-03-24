<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_stations', function (Blueprint $table) {
            $table->text('station_cert')->nullable()->after('mqtt_password_encrypted');
            $table->text('station_key')->nullable()->after('station_cert');
            $table->timestamp('cert_expires_at')->nullable()->after('station_key');
            $table->timestamp('cert_issued_at')->nullable()->after('cert_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_stations', function (Blueprint $table) {
            $table->dropColumn(['station_cert', 'station_key', 'cert_expires_at', 'cert_issued_at']);
        });
    }
};
