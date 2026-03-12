<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\EmqxPublishException;
use Illuminate\Support\Facades\Http;

final class EmqxApiPublisher
{
    private ?string $token = null;
    private ?int $tokenExpiresAt = null;

    private readonly string $baseUrl;
    private readonly string $username;
    private readonly string $password;

    public function __construct()
    {
        $this->baseUrl = config('services.emqx.api_url');
        $this->username = config('services.emqx.api_username');
        $this->password = config('services.emqx.api_password');
    }

    public function publish(string $topic, string $payload, int $qos = 1): void
    {
        $this->ensureAuthenticated();

        $response = Http::withToken($this->token)
            ->post("{$this->baseUrl}/publish", [
                'topic' => $topic,
                'payload' => $payload,
                'qos' => $qos,
                'retain' => false,
            ]);

        if ($response->failed()) {
            throw new EmqxPublishException(
                "Failed to publish to {$topic}: HTTP {$response->status()}"
            );
        }
    }

    private function ensureAuthenticated(): void
    {
        if ($this->token !== null && $this->tokenExpiresAt !== null && $this->tokenExpiresAt > time()) {
            return;
        }

        $response = Http::post("{$this->baseUrl}/login", [
            'username' => $this->username,
            'password' => $this->password,
        ]);

        $this->token = $response->json('token');
        $this->tokenExpiresAt = time() + 3500;
    }
}
