<?php

declare(strict_types=1);

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

final class JwtService
{
    private readonly string $privateKey;
    private readonly string $publicKey;
    private readonly string $algorithm;
    private readonly int $ttl;

    public function __construct()
    {
        $this->algorithm = config('sandbox.jwt.algorithm');
        $this->ttl = (int) config('sandbox.jwt.ttl');

        $privatePath = config('sandbox.jwt.private_key_path');
        $publicPath = config('sandbox.jwt.public_key_path');

        $this->privateKey = file_exists($privatePath) ? file_get_contents($privatePath) : '';
        $this->publicKey = file_exists($publicPath) ? file_get_contents($publicPath) : '';
    }

    public function encode(string $tenantId, string $email): string
    {
        $now = time();

        $payload = [
            'iss' => 'csms-sandbox',
            'sub' => $tenantId,
            'email' => $email,
            'iat' => $now,
            'exp' => $now + $this->ttl,
        ];

        return JWT::encode($payload, $this->privateKey, $this->algorithm);
    }

    /**
     * @return array{sub: string, email: string, iat: int, exp: int}|null
     */
    public function decode(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->publicKey, $this->algorithm));

            return (array) $decoded;
        } catch (Throwable) {
            return null;
        }
    }
}
