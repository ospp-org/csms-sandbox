# OSPP CSMS Sandbox

**Version: 0.1.0** | **Protocol: OSPP v0.1.0**

Hosted, multi-tenant OSPP protocol testing environment.
Firmware developers connect their station via MQTT, see
every message in a real-time dashboard, send commands
manually, and get a conformance report scoring their
implementation against the OSPP specification.

**Hosted:** csms-sandbox.ospp-standard.org
**Self-host:** docker compose up -d

## Features

- Multi-tenant MQTT isolation (per-tenant credentials + EMQX ACL)
- 21 OSPP protocol handlers with full schema validation
- Real-time message inspector (WebSocket via Laravel Reverb)
- Manual command sender (14 outbound OSPP commands)
- Conformance scoring with behavior validation (7 rules)
- PDF/JSON conformance report export
- Code snippets: C (ESP-IDF), Python, JavaScript

## Stack

Laravel 12, PHP 8.4, PostgreSQL 16, Redis 7, EMQX 5.8,
Laravel Reverb, Blade + Alpine.js + Tailwind CSS

## Quick Start (Self-Host)

```bash
git clone https://github.com/ospp-org/csms-sandbox.git
cd csms-sandbox
cp .env.example .env
docker compose build
docker compose up -d
docker compose exec app php artisan db:seed
```

Visit http://localhost
Login: dev@ospp-standard.org / password

## Documentation

See docs/ for complete implementation documentation:
- PRD, Architecture, API spec, MQTT config
- Handler specifications, Conformance engine
- Frontend, Testing, Deployment guides

## Testing

```bash
docker compose exec app php artisan test --parallel
```

170 tests, 452 assertions

## Related

- [OSPP Protocol Specification](https://github.com/ospp-org/spec)
- [OSPP PHP SDK](https://github.com/ospp-org/ospp-sdk-php)
- [OSPP Station Simulator](https://github.com/ospp-org/station-simulator)

## License

[MIT](LICENSE)
