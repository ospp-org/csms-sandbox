<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Contracts\CertificateServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class CertificateController extends Controller
{
    public function download(Request $request, string $file, CertificateServiceInterface $certificateService): Response|RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();
        $station = $tenant->station;

        if ($file === 'ca') {
            try {
                $chain = $certificateService->getCaChain();
            } catch (\RuntimeException) {
                return redirect('/dashboard/setup')->with('error', 'CA chain not available.');
            }

            return new Response($chain, 200, [
                'Content-Type' => 'application/x-pem-file',
                'Content-Disposition' => 'attachment; filename="ospp-sandbox-ca.pem"',
            ]);
        }

        if ($file === 'cert') {
            if ($station->station_cert === null) {
                return redirect('/dashboard/setup')->with('error', 'Certificate not yet generated.');
            }

            return new Response($station->station_cert, 200, [
                'Content-Type' => 'application/x-pem-file',
                'Content-Disposition' => 'attachment; filename="station.pem"',
            ]);
        }

        if ($file === 'key') {
            if ($station->station_key === null) {
                return redirect('/dashboard/setup')->with('error', 'Certificate not yet generated.');
            }

            return new Response($station->station_key, 200, [
                'Content-Type' => 'application/x-pem-file',
                'Content-Disposition' => 'attachment; filename="station-key.pem"',
            ]);
        }

        abort(404);
    }

    public function regenerate(Request $request, CertificateServiceInterface $certificateService): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();
        $station = $tenant->station;

        if (! $certificateService->isConfigured()) {
            return redirect('/dashboard/setup')->with('error', 'PKI not configured. Certificate generation unavailable.');
        }

        $certificateService->revokeCert($station);
        $certificateService->generateStationCert($station);

        return redirect('/dashboard/setup')->with('success', 'Station certificate regenerated.');
    }
}
