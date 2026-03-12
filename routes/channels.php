<?php
declare(strict_types=1);

use App\Models\TenantStation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('station.{stationId}', function ($user, string $stationId) {
    $station = TenantStation::where('station_id', $stationId)->first();
    return $station !== null && $station->tenant_id === $user->id;
});
