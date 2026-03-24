<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\CertificateServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantStation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SimulatorCertificateController extends Controller
{
    public function __invoke(Request $request, CertificateServiceInterface $certificateService): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();

        $stations = TenantStation::where('tenant_id', $tenant->id)
            ->whereNotNull('station_cert')
            ->orderBy('station_id')
            ->get();

        $stationData = [];
        foreach ($stations as $station) {
            $stationData[$station->station_id] = [
                'cert' => $station->station_cert,
                'key' => $station->station_key,
            ];
        }

        $ca = null;
        try {
            $ca = $certificateService->getCaChain();
        } catch (\RuntimeException) {
            // CA chain not available
        }

        return new JsonResponse([
            'sandbox_deviation' => 'Private keys are server-generated for testing only. In production, private keys never leave the station hardware.',
            'ca' => $ca,
            'stations' => $stationData,
        ]);
    }
}
