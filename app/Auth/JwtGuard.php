<?php

declare(strict_types=1);

namespace App\Auth;

use App\Services\JwtService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;

final class JwtGuard implements Guard
{
    private ?Authenticatable $user = null;
    private bool $resolved = false;

    public function __construct(
        private readonly JwtService $jwt,
        private readonly UserProvider $provider,
        private readonly Request $request,
    ) {}

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return ! $this->check();
    }

    public function user(): ?Authenticatable
    {
        if ($this->resolved) {
            return $this->user;
        }

        $this->resolved = true;

        $token = $this->request->bearerToken();
        if ($token === null) {
            return null;
        }

        $payload = $this->jwt->decode($token);
        if ($payload === null) {
            return null;
        }

        $this->user = $this->provider->retrieveById($payload['sub']);

        return $this->user;
    }

    public function id(): int|string|null
    {
        return $this->user()?->getAuthIdentifier();
    }

    /**
     * @param array<string, mixed> $credentials
     */
    public function validate(array $credentials = []): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        if ($user === null) {
            return false;
        }

        return $this->provider->validateCredentials($user, $credentials);
    }

    public function hasUser(): bool
    {
        return $this->user !== null;
    }

    public function setUser(Authenticatable $user): static
    {
        $this->user = $user;
        $this->resolved = true;

        return $this;
    }
}
