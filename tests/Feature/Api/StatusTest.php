<?php

declare(strict_types=1);

test('GET /api/v1/status returns operational status', function (): void {
    $this->getJson('/api/v1/status')
        ->assertOk()
        ->assertJsonStructure([
            'status',
            'version',
            'services' => ['database', 'redis', 'emqx', 'queue'],
        ])
        ->assertJsonMissing(['stats'])
        ->assertJsonPath('services.database', 'ok')
        ->assertJsonPath('services.redis', 'ok');
});

test('GET /api/v1/status is public (no auth required)', function (): void {
    $this->getJson('/api/v1/status')
        ->assertOk();
});
