<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class VerifyEmqxWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = $request->header('X-Webhook-Secret');

        if ($secret !== config('mqtt.webhook.secret')) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
