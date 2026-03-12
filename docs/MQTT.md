# CSMS Sandbox — MQTT Configuration

---

## Overview

EMQX 5.8 serves as the MQTT broker. Each tenant gets unique MQTT credentials. EMQX authenticates via HTTP auth backend (Laravel endpoint) and enforces per-tenant topic ACL. Messages are forwarded to Laravel via async webhook.

---

## Topic Structure

Standard OSPP topics — identical to production. Firmware code does not know it's on a sandbox.

```
ospp/v1/stations/{station_id}/to-server     Station → CSMS (inbound)
ospp/v1/stations/{station_id}/to-station    CSMS → Station (outbound)
```

Station subscribes to `to-station` to receive commands.
Station publishes to `to-server` to send messages.

LWT (Last Will and Testament):
```
ospp/v1/stations/{station_id}/to-server
Payload: {"action":"ConnectionLost","messageType":"Event","source":"Broker",...}
```

---

## EMQX Authentication (HTTP Auth Backend)

EMQX authenticates MQTT connections via HTTP request to Laravel.

### EMQX Config (`docker/emqx/emqx.conf`)

```hocon
authentication = [
  {
    mechanism = password_based
    backend = http
    method = post
    url = "http://nginx/internal/mqtt/auth"
    headers {
      "Content-Type" = "application/json"
      "X-Webhook-Secret" = "${EMQX_WEBHOOK_SECRET}"
    }
    body {
      username = "${username}"
      password = "${password}"
      clientid = "${clientid}"
    }
    connect_timeout = "5s"
    request_timeout = "5s"
    pool_size = 4
  }
]
```

### Laravel Auth Endpoint

`POST /internal/mqtt/auth`

Called by EMQX on every MQTT CONNECT.

```php
// MqttAuthController
public function __invoke(Request $request): JsonResponse
{
    $username = $request->input('username', '');
    $password = $request->input('password', '');

    $station = TenantStation::where('mqtt_username', $username)->first();

    if ($station === null) {
        return response()->json(['result' => 'deny'], 200);
    }

    if (!Hash::check($password, $station->mqtt_password_hash)) {
        return response()->json(['result' => 'deny'], 200);
    }

    return response()->json([
        'result' => 'allow',
        'is_superuser' => false,
    ], 200);
}
```

---

## EMQX Authorization (ACL via HTTP)

EMQX checks publish/subscribe permissions via HTTP.

### EMQX Config

```hocon
authorization {
  sources = [
    {
      type = http
      enable = true
      method = post
      url = "http://nginx/internal/mqtt/acl"
      headers {
        "Content-Type" = "application/json"
        "X-Webhook-Secret" = "${EMQX_WEBHOOK_SECRET}"
      }
      body {
        username = "${username}"
        topic = "${topic}"
        action = "${action}"
      }
      connect_timeout = "5s"
      request_timeout = "5s"
      pool_size = 4
    }
  ]
  no_match = deny
  deny_action = disconnect
}
```

### Laravel ACL Endpoint

`POST /internal/mqtt/acl`

Called by EMQX on every PUBLISH and SUBSCRIBE.

```php
// MqttAclController (add to MqttAuthController or separate)
public function acl(Request $request): JsonResponse
{
    $username = $request->input('username', '');
    $topic = $request->input('topic', '');
    $action = $request->input('action', ''); // publish or subscribe

    $station = TenantStation::where('mqtt_username', $username)->first();

    if ($station === null) {
        return response()->json(['result' => 'deny'], 200);
    }

    $stationId = $station->station_id;

    // Station can publish to its own to-server topic
    $allowedPublish = "ospp/v1/stations/{$stationId}/to-server";

    // Station can subscribe to its own to-station topic
    $allowedSubscribe = "ospp/v1/stations/{$stationId}/to-station";

    if ($action === 'publish' && $topic === $allowedPublish) {
        return response()->json(['result' => 'allow'], 200);
    }

    if ($action === 'subscribe' && $topic === $allowedSubscribe) {
        return response()->json(['result' => 'allow'], 200);
    }

    // Deny everything else
    return response()->json(['result' => 'deny'], 200);
}
```

---

## EMQX Webhook (Message Forwarding)

Async webhook forwards all messages from `to-server` topics to Laravel for processing.

### Init Script (`docker/emqx/init-webhook.sh`)

```bash
#!/bin/sh
set -e

EMQX_API="http://emqx:18083/api/v5"
WEBHOOK_URL="http://nginx/internal/mqtt/webhook"
WEBHOOK_SECRET="${EMQX_WEBHOOK_SECRET:-csms-webhook-secret-dev-only}"

echo "[emqx-init] Waiting for EMQX API..."
until curl -sf "${EMQX_API}/status" > /dev/null 2>&1; do
  sleep 2
done
echo "[emqx-init] EMQX API is ready."

# Authenticate
TOKEN=$(curl -sf -X POST "${EMQX_API}/login" \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"public"}' \
  | grep -o '"token":"[^"]*"' | cut -d'"' -f4)

HEADERS="{\"Content-Type\":\"application/json\",\"X-Webhook-Secret\":\"${WEBHOOK_SECRET}\"}"

# Create connector (if not exists)
curl -sf -H "Authorization: Bearer ${TOKEN}" \
  "${EMQX_API}/connectors/http:csms_webhook" > /dev/null 2>&1 || \
curl -sf -X POST -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  "${EMQX_API}/connectors" -d "{
    \"type\": \"http\",
    \"name\": \"csms_webhook\",
    \"connector\": {
      \"url\": \"${WEBHOOK_URL}\",
      \"method\": \"post\",
      \"headers\": ${HEADERS},
      \"connect_timeout\": \"5s\",
      \"pool_type\": \"hash\",
      \"pool_size\": 8
    }
  }"
echo "[emqx-init] Connector ready."

# Create action (if not exists)
curl -sf -H "Authorization: Bearer ${TOKEN}" \
  "${EMQX_API}/actions/http:csms_mqtt_webhook" > /dev/null 2>&1 || \
curl -sf -X POST -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  "${EMQX_API}/actions" -d "{
    \"type\": \"http\",
    \"name\": \"csms_mqtt_webhook\",
    \"connector\": \"csms_webhook\",
    \"parameters\": {
      \"path\": \"\",
      \"method\": \"post\",
      \"body\": \"{\\\"topic\\\": \\\"\${topic}\\\", \\\"payload\\\": \\\"\${payload}\\\", \\\"qos\\\": \${qos}}\",
      \"headers\": ${HEADERS}
    },
    \"resource_opts\": {
      \"query_mode\": \"async\",
      \"inflight_window\": 100,
      \"request_ttl\": \"10s\",
      \"worker_pool_size\": 8
    }
  }"
echo "[emqx-init] Action ready."

# Create rule (if not exists)
curl -sf -H "Authorization: Bearer ${TOKEN}" \
  "${EMQX_API}/rules/csms_mqtt_forward" > /dev/null 2>&1 || \
curl -sf -X POST -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  "${EMQX_API}/rules" -d "{
    \"id\": \"csms_mqtt_forward\",
    \"sql\": \"SELECT * FROM 'ospp/v1/stations/+/to-server'\",
    \"actions\": [\"http:csms_mqtt_webhook\"],
    \"enable\": true,
    \"description\": \"Forward OSPP station messages to CSMS webhook\"
  }"
echo "[emqx-init] Rule ready."
echo "[emqx-init] Done."
```

### Webhook Controller

```php
// MqttWebhookController — thin proxy
public function __invoke(Request $request): JsonResponse
{
    $topic = $request->input('topic', '');
    $payload = $request->input('payload', '');

    if ($topic === '' || $payload === '') {
        return response()->json(['error' => 'missing_fields'], 400);
    }

    $parts = explode('/', $topic);
    $stationId = $parts[3] ?? 'unknown';

    ProcessMqttMessage::dispatch($stationId, $topic, $payload);

    return response()->json(['status' => 'ok']);
}
```

---

## EMQX Publishing (CSMS → Station)

Sandbox publishes responses and commands via EMQX REST API.

### EmqxApiPublisher

```php
final class EmqxApiPublisher
{
    private ?string $token = null;
    private ?int $tokenExpiresAt = null;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $username,
        private readonly string $password,
    ) {}

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
                "Failed to publish to {$topic}: {$response->status()}"
            );
        }
    }

    private function ensureAuthenticated(): void
    {
        if ($this->token !== null && $this->tokenExpiresAt > time()) {
            return;
        }

        $response = Http::post("{$this->baseUrl}/login", [
            'username' => $this->username,
            'password' => $this->password,
        ]);

        $this->token = $response->json('token');
        $this->tokenExpiresAt = time() + 3500; // ~1 hour
    }
}
```

Config (`config/mqtt.php`):

```php
return [
    'emqx_api' => [
        'base_url' => env('EMQX_API_URL', 'http://emqx:18083/api/v5'),
        'username' => env('EMQX_API_USERNAME', 'admin'),
        'password' => env('EMQX_API_PASSWORD', 'public'),
    ],

    'webhook' => [
        'secret' => env('EMQX_WEBHOOK_SECRET', 'csms-webhook-secret-dev-only'),
    ],

    'topics' => [
        'to_server' => 'ospp/v1/stations/{stationId}/to-server',
        'to_station' => 'ospp/v1/stations/{stationId}/to-station',
    ],
];
```

---

## MQTT Credential Generation

When a tenant registers, a station + MQTT credentials are auto-created.

### MqttCredentialService

```php
final class MqttCredentialService
{
    public function generateForTenant(Tenant $tenant): TenantStation
    {
        $stationIndex = TenantStation::count() + 1;
        $stationId = 'stn_' . str_pad(dechex($stationIndex), 8, '0', STR_PAD_LEFT);

        $mqttUsername = 'sandbox_' . bin2hex(random_bytes(8));
        $mqttPassword = bin2hex(random_bytes(16));

        return TenantStation::create([
            'tenant_id' => $tenant->id,
            'station_id' => $stationId,
            'mqtt_username' => $mqttUsername,
            'mqtt_password_hash' => Hash::make($mqttPassword),
            'mqtt_password_encrypted' => encrypt($mqttPassword),
            'protocol_version' => $tenant->protocol_version,
        ]);
    }

    public function regeneratePassword(TenantStation $station): string
    {
        $newPassword = bin2hex(random_bytes(16));

        $station->update([
            'mqtt_password_hash' => Hash::make($newPassword),
            'mqtt_password_encrypted' => encrypt($newPassword),
        ]);

        return $newPassword;
    }

    public function getPlainPassword(TenantStation $station): string
    {
        return decrypt($station->mqtt_password_encrypted);
    }
}
```

---

## Connection Status Tracking

### On MQTT Connect

EMQX HTTP auth returns `allow` → station is connected. But there's no EMQX hook for "client connected" in CE edition.

Alternative: track via first message. When BootNotification arrives:
```php
$station->update([
    'is_connected' => true,
    'last_connected_at' => now(),
]);
Redis::set("sandbox:station:{$stationId}:connected", '1', 'EX', 90);
```

### Heartbeat Refresh

Each Heartbeat refreshes the TTL:
```php
Redis::set("sandbox:station:{$stationId}:connected", '1', 'EX', 90);
```

### Scheduled Connection Check

Every minute, check Redis key:
```php
// StationCheckConnectionCommand (runs every 1 min)
$stations = TenantStation::where('is_connected', true)->get();

foreach ($stations as $station) {
    $connected = Redis::get("sandbox:station:{$station->station_id}:connected");
    if ($connected === null) {
        $station->update(['is_connected' => false]);
        event(new StationDisconnected($station));
    }
}
```

---

## EMQX Docker Config

### emqx.conf (minimal, production-ready)

```hocon
node {
  name = "csms-sandbox@127.0.0.1"
  cookie = "csms-sandbox-secret"
}

dashboard {
  listeners.http {
    bind = 18083
  }
  default_username = "admin"
  default_password = "${EMQX_DASHBOARD_PASSWORD:-public}"
}

listeners.tcp.default {
  bind = "0.0.0.0:1883"
  max_connections = 1000
}

listeners.ssl.default {
  bind = "0.0.0.0:8883"
  ssl_options {
    certfile = "/opt/emqx/etc/certs/server-cert.pem"
    keyfile = "/opt/emqx/etc/certs/server-key.pem"
    cacertfile = "/opt/emqx/etc/certs/root-ca-cert.pem"
  }
  max_connections = 1000
}

# Authentication and authorization configured above
```

---

## Security Considerations

1. **MQTT credentials are per-tenant** — no shared credentials
2. **ACL enforced per publish/subscribe** — tenant cannot access other tenants' topics
3. **Webhook secret** — EMQX webhook includes secret header, Laravel verifies
4. **TLS on port 8883** — encrypted MQTT in production
5. **Password stored as bcrypt hash** — plain text never stored (encrypted copy for "show once" feature)
6. **EMQX dashboard** — password changed from default in production
7. **Internal endpoints** — `/internal/*` restricted to Docker network (Nginx config)
