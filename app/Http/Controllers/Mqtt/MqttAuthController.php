<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mqtt;

use App\Http\Controllers\Controller;
use App\Models\TenantStation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

final class MqttAuthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $username = $request->input('username', '');
        $password = $request->input('password', '');

        $station = TenantStation::where('mqtt_username', $username)->first();

        if ($station === null) {
            return new JsonResponse(['result' => 'deny']);
        }

        if (! Hash::check($password, $station->mqtt_password_hash)) {
            return new JsonResponse(['result' => 'deny']);
        }

        return new JsonResponse([
            'result' => 'allow',
            'is_superuser' => false,
        ]);
    }
}
