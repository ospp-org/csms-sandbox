<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureStationExists
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Tenant|null $tenant */
        $tenant = $request->user();

        if ($tenant !== null && $tenant->station === null) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'NO_STATION',
                    'message' => 'Your account has no station configured. Please contact support@ospp-standard.org',
                ], 404);
            }

            return redirect('/dashboard/no-station');
        }

        return $next($request);
    }
}
