<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Tenant;
use App\Models\TenantStation;
use Illuminate\Http\Request;

trait ResolvesStation
{
    private function resolveStation(Request $request, Tenant $tenant): ?TenantStation
    {
        $stationId = $request->query('station');

        if ($stationId !== null) {
            $station = TenantStation::where('tenant_id', $tenant->id)
                ->where('station_id', $stationId)
                ->first();

            if ($station !== null) {
                return $station;
            }
        }

        return $tenant->station;
    }

    private function resolveStationOrFail(Request $request, Tenant $tenant): TenantStation
    {
        $station = $this->resolveStation($request, $tenant);

        if ($station === null) {
            abort(404, 'No station configured. Please contact support.');
        }

        return $station;
    }
}
