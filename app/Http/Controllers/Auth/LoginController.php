<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Tenant;
use App\Services\JwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

final class LoginController extends Controller
{
    public function __invoke(LoginRequest $request, JwtService $jwt): JsonResponse
    {
        $tenant = Tenant::where('email', $request->validated('email'))->first();

        if ($tenant === null || ! Hash::check($request->validated('password'), $tenant->password)) {
            return new JsonResponse([
                'error' => 'INVALID_CREDENTIALS',
                'message' => 'Email or password is incorrect',
            ], 401);
        }

        $token = $jwt->encode($tenant->id, $tenant->email);

        return new JsonResponse([
            'token' => $token,
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'email' => $tenant->email,
                'protocol_version' => $tenant->protocol_version,
                'validation_mode' => $tenant->validation_mode,
            ],
        ]);
    }
}
