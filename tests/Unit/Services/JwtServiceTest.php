<?php

declare(strict_types=1);

use App\Services\JwtService;

test('JwtService encodes and decodes a valid token', function (): void {
    $jwt = app(JwtService::class);

    $token = $jwt->encode('tenant-uuid-123', 'test@example.com');
    expect($token)->toBeString()->not->toBeEmpty();

    $payload = $jwt->decode($token);
    expect($payload)->not->toBeNull();
    expect($payload['sub'])->toBe('tenant-uuid-123');
    expect($payload['email'])->toBe('test@example.com');
    expect($payload['iss'])->toBe('csms-sandbox');
});

test('JwtService returns null for invalid token', function (): void {
    $jwt = app(JwtService::class);

    expect($jwt->decode('invalid.token.here'))->toBeNull();
    expect($jwt->decode(''))->toBeNull();
});

test('JwtService token has correct expiry', function (): void {
    $jwt = app(JwtService::class);

    $token = $jwt->encode('tenant-id', 'test@example.com');
    $payload = $jwt->decode($token);

    expect($payload['exp'] - $payload['iat'])->toBe((int) config('sandbox.jwt.ttl'));
});
