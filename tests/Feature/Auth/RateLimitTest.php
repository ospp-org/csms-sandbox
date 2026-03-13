<?php

declare(strict_types=1);

test('login rate limited after 5 attempts', function (): void {
    $response = null;

    for ($i = 0; $i < 6; $i++) {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'wrong@example.com',
            'password' => 'wrong',
        ]);
    }

    $response->assertStatus(429);
});

test('register rate limited after 5 attempts', function (): void {
    $response = null;

    for ($i = 0; $i < 6; $i++) {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test',
            'email' => "test{$i}@example.com",
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);
    }

    $response->assertStatus(429);
});
