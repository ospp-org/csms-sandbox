<?php

test('health endpoint returns JSON with status', function () {
    $response = $this->getJson('/health');

    $response->assertOk()
        ->assertJsonStructure([
            'status',
            'checks' => [
                'database',
                'redis',
                'emqx',
            ],
        ]);
});
