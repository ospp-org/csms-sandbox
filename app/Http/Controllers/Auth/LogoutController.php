<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class LogoutController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['message' => 'Logged out']);
    }
}
