# OSPP CSMS Sandbox

[![CI](https://github.com/ospp-org/csms-sandbox/actions/workflows/ci.yml/badge.svg)](https://github.com/ospp-org/csms-sandbox/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

Multi-tenant OSPP protocol testing environment. Firmware developers connect their station via MQTT, see every message in a real-time dashboard, send commands manually, and get a conformance report scoring their implementation against the OSPP specification.

**Hosted instance:** [csms-sandbox.ospp-standard.org](https://csms-sandbox.ospp-standard.org)

## Features

- 27 OSPP protocol handlers with JSON Schema validation (ospp/protocol SDK v0.3.2)
- mTLS enforcement on port 8883 (ECDSA P-256 client certificates)
- Per-station conformance tracking with station selector UI
- Multi-tenant MQTT isolation (per-tenant credentials + EMQX ACL + CN-based topic enforcement)
- Real-time message inspector via WebSocket (Laravel Reverb)
- 14 outbound command sender with schema-aware form builder
- 14 conformance behavior rules with per-action OSPP spec timeouts
- Simulator control API (force-reject, force-pending, trigger-command)
- Bulk certificate export for simulator testing
- PDF/JSON conformance report export
- REST API with JWT (ES256) authentication
- Connection code snippets: C (ESP-IDF), Python, JavaScript

## Architecture

```
+-----------+     +-----------+     +------------+     +---------+
|   Nginx   |---->|  Laravel  |---->| PostgreSQL |     |  Redis  |
|   :80     |     |  (PHP)    |     |  :5432     |     |  :6379  |
+-----+-----+     +-----+-----+     +------------+     +---------+
      |                 |                                    |
      |            +----+------+                             |
      |            |  Reverb   |<----------------------------+
      |            |  (WS)     |
      |            +-----------+
      |
      v
+-----------+     +--------------+
|   EMQX    |---->| Queue Workers|
|   :1883   |     | (mqtt-messages)
|   :8883   |     +--------------+
+-----------+
```

## Stack

Laravel 12 / PHP 8.4 / PostgreSQL 16 / Redis 7 / EMQX 5.8 / Laravel Reverb / Blade + Alpine.js + Tailwind CSS

## Quick Start

### Hosted (recommended)

1. Register at [csms-sandbox.ospp-standard.org/register](https://csms-sandbox.ospp-standard.org/register)
2. Copy MQTT credentials from the Setup page
3. Connect your station — see [Quick Start Guide](docs/QUICKSTART.md)

### Self-Host

```bash
git clone https://github.com/ospp-org/csms-sandbox.git
cd csms-sandbox
cp .env.example .env
# Edit .env — change all 'change-me' values
docker compose build && docker compose up -d
docker compose exec app php artisan migrate --seed
```

Visit http://localhost / Login: dev@ospp-standard.org / password

## Documentation

| Document | Description |
|----------|-------------|
| [Quick Start](docs/QUICKSTART.md) | Firmware developer onboarding guide |
| [API Reference](docs/API.md) | REST API endpoints, auth, examples |
| [Conformance Engine](docs/CONFORMANCE.md) | 14 behavior rules, scoring, timeouts |
| [Deployment](docs/DEPLOYMENT.md) | Production setup on VPS |
| [Architecture](docs/ARCHITECTURE.md) | System design, Docker services |
| [MQTT](docs/MQTT.md) | Topic structure, ACL, webhook pipeline |
| [Handlers](docs/HANDLERS.md) | 27 OSPP action handlers |

## MQTT Topics

```
ospp/v1/stations/{station_id}/to-server    # Station -> CSMS (publish)
ospp/v1/stations/{station_id}/to-station   # CSMS -> Station (subscribe)
```

## Conformance Engine

14 behavior rules validated against the [OSPP specification](https://github.com/ospp-org/spec):

| Rule | Checks |
|------|--------|
| BootFirst | No action before BootNotification |
| HeartbeatTiming | Interval within configured tolerance |
| EnvelopeFormat | Required fields, timestamp format |
| BayTransition | Valid state machine transitions |
| SessionState | MeterValues requires active session |
| ResponseTiming | Per-action timeouts (5s-300s) |
| Idempotency | Unique messageId per station |
| FirmwareUpdateSequence | Downloading->Downloaded->Installing->Installed |
| DiagnosticsUpload | Collecting->Uploading->Uploaded |
| OfflineTransaction | txCounter monotonicity, no gaps |
| MeterValuesFrequency | Non-negative values, advancing timestamps |
| ReservationExpiry | expirationTime in the future |
| CertificateFormat | PEM-encoded CSR, valid certificateType |
| ConfigurationPersistence | Rejected/NotSupported include error details |

## Testing

```bash
docker compose exec app php vendor/bin/pest --parallel --processes=28
```

308 tests / 921 assertions / PHPStan level 6

## mTLS (Client Certificates)

Port 8883 requires mTLS — stations must present an ECDSA P-256 client certificate signed by the sandbox Station CA.

- Certificates are auto-generated on station registration (when PKI is configured)
- Download from dashboard Setup page or via `GET /api/v1/simulator/certificates` (bulk)
- CN must match station_id (e.g., `stn_00000001`)
- Port 1883 (plain TCP) available for development only — no client cert required

**PKI environment variables:**
```
PKI_STATION_CA_CERT=/opt/pki/station-ca/station-ca.pem
PKI_STATION_CA_KEY=/opt/pki/station-ca/station-ca.key
PKI_CA_CHAIN=/opt/pki/station-ca/chain.pem
```

Generate missing certificates: `php artisan certificates:generate-missing`

## Station Simulator

The [OSPP Station Simulator](https://github.com/ospp-org/station-simulator) uses MQTT 3.1.1 (php-mqtt/client limitation). OSPP spec requires MQTT 5.0. Transport-level features (Will Delay, Session Expiry) are unavailable in simulator testing. All 27 protocol actions are fully testable.

**Simulator control API:**
- `POST /api/v1/stations/{id}/force-reject` — force next boot to Rejected
- `POST /api/v1/stations/{id}/force-pending` — force next boot to Pending
- `POST /api/v1/stations/{id}/trigger-command` — send any command to station
- `GET /api/v1/simulator/certificates` — bulk download all station certs

## Security

- mTLS on port 8883 (ECDSA P-256 client certs, CN-based ACL)
- Multi-tenant MQTT ACL isolation (EMQX HTTP backends + cert CN verification)
- JWT ES256 authentication with 1-hour TTL
- Session cookies: Secure, HttpOnly, SameSite=Lax
- Rate limiting on auth endpoints (5/min per IP)
- SRI hashes on CDN scripts (Alpine.js, Pusher, Echo)
- No hardcoded secrets (empty fallbacks, fail-loud)
- CSRF protection on all web forms
- Nginx IP restriction on internal endpoints
- API routes return 401 JSON for unauthenticated requests (no redirect)

## Known Limitations

- Empty JSON arrays `[]` vs empty objects `{}`: PHP `json_decode(true)` loses type distinction. Raw JSON passthrough mitigates for inbound validation.
- Station Simulator uses MQTT 3.1.1, not 5.0.
- Conformance rules evaluate before handlers — state checks reflect previous message state.

## Related

- [OSPP Protocol Specification](https://github.com/ospp-org/spec)
- [OSPP PHP SDK](https://github.com/ospp-org/ospp-sdk-php)
- [OSPP Station Simulator](https://github.com/ospp-org/station-simulator)

## License

[MIT](LICENSE)
