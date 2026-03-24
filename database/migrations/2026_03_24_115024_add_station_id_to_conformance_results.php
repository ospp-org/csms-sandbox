<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conformance_results', function (Blueprint $table) {
            $table->string('station_id')->nullable()->after('tenant_id');
        });

        // Backfill: set station_id from tenant's first station
        DB::statement("
            UPDATE conformance_results
            SET station_id = (
                SELECT station_id FROM tenant_stations
                WHERE tenant_stations.tenant_id = conformance_results.tenant_id
                ORDER BY created_at ASC LIMIT 1
            )
            WHERE station_id IS NULL
        ");

        Schema::table('conformance_results', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'protocol_version', 'action']);
            $table->unique(['station_id', 'protocol_version', 'action']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('conformance_results', function (Blueprint $table) {
            $table->dropUnique(['station_id', 'protocol_version', 'action']);
            $table->dropIndex(['tenant_id']);
            $table->unique(['tenant_id', 'protocol_version', 'action']);
            $table->dropColumn('station_id');
        });
    }
};
