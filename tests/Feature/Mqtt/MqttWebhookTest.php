<?php

declare(strict_types=1);

use App\Jobs\ProcessMqttMessage;
use Illuminate\Support\Facades\Queue;

test('POST /internal/mqtt/webhook dispatches ProcessMqttMessage job', function (): void {
    Queue::fake([ProcessMqttMessage::class]);

    $payload = json_encode([
        'action' => 'BootNotification',
        'messageId' => 'msg_001',
        'messageType' => 'Request',
        'source' => 'Station',
        'protocolVersion' => '0.1.0',
        'timestamp' => '2026-03-09T10:00:05.000Z',
        'payload' => [
            'stationModel' => 'WashPro 5000',
            'stationVendor' => 'Test',
            'firmwareVersion' => '1.0.0',
            'bayCount' => 4,
        ],
    ]);

    $response = $this->postJson('/internal/mqtt/webhook', [
        'topic' => 'ospp/v1/stations/stn_00000001/to-server',
        'payload' => $payload,
    ], ['X-Webhook-Secret' => config('mqtt.webhook.secret')]);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'ok');

    Queue::assertPushed(ProcessMqttMessage::class, function (ProcessMqttMessage $job): bool {
        return $job->stationId === 'stn_00000001';
    });
});

test('webhook rejects without secret header', function (): void {
    $this->postJson('/internal/mqtt/webhook', [
        'topic' => 'ospp/v1/stations/stn_00000001/to-server',
        'payload' => '{}',
    ])->assertStatus(401);
});

test('webhook rejects empty payload', function (): void {
    $this->postJson('/internal/mqtt/webhook', [
        'topic' => 'ospp/v1/stations/stn_00000001/to-server',
        'payload' => '',
    ], ['X-Webhook-Secret' => config('mqtt.webhook.secret')])
        ->assertStatus(400);
});

test('webhook rejects empty topic', function (): void {
    $this->postJson('/internal/mqtt/webhook', [
        'topic' => '',
        'payload' => '{}',
    ], ['X-Webhook-Secret' => config('mqtt.webhook.secret')])
        ->assertStatus(400);
});
