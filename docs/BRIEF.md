# CSMS Sandbox — Implementation Brief

**Repository:** `ospp-org/csms-sandbox`
**License:** MIT
**Domain:** `csms-sandbox.ospp-standard.org`

---

## What This Is

A hosted, multi-tenant OSPP protocol testing environment. Firmware developers connect their station (physical or simulated) via MQTT, see every message in a real-time dashboard, send commands manually, and get a conformance report scoring their implementation against the OSPP v0.1.0 specification.

**PRD:** See `docs/PRD-CSMS-Sandbox.md` for full product requirements.

---

## Stack

| Component | Technology | Version |
|-----------|-----------|---------|
| Backend | Laravel | 12.x |
| PHP | | 8.4+ |
| Database | PostgreSQL | 16 |
| Cache / Queue | Redis | 7 |
| MQTT Broker | EMQX | 5.8 |
| Web Server | Nginx | 1.25+ |
| Real-time | Laravel Reverb | Latest |
| Frontend | Blade + Alpine.js + Tailwind CSS | CDN, no build |
| Protocol SDK | `ospp/protocol` | ^0.2.1 |
| Containerization | Docker + Docker Compose | v2 |

---

## Implementation Documents

Read in order. Each document is self-contained for its domain.

| # | Document | Purpose | When |
|---|----------|---------|------|
| 1 | `ARCHITECTURE.md` | Folder structure, Docker services, message flow, module boundaries | Before any code |
| 2 | `STYLE.md` | Code conventions, Laravel patterns, naming, error handling | Before any code |
| 3 | `DATABASE.md` | All migrations, indexes, constraints, seed data | Phase 1 |
| 4 | `MQTT.md` | EMQX config, ACL, webhook, topic patterns, multi-tenant isolation | Phase 2 |
| 5 | `API.md` | Every endpoint — request/response format, auth, errors, examples | Phase 2-4 |
| 6 | `HANDLERS.md` | All 26 OSPP handlers — input, processing, output, per-handler conformance | Phase 3 |
| 7 | `CONFORMANCE.md` | Scoring algorithm, behavior rules, schema validation, export | Phase 4 |
| 8 | `FRONTEND.md` | Blade templates, Alpine.js components, Reverb WebSocket, Tailwind | Phase 5 |
| 9 | `TESTING.md` | Test strategy per module, fixtures, what to test, CI expectations | Every phase |
| 10 | `DEPLOYMENT.md` | Docker Compose, entrypoint, EMQX init, TLS, domain, production config | Phase 6 |

---

## Implementation Phases

### Phase 1 — Scaffolding (estimated: 1 session)

**Goal:** Empty Laravel 12 app running in Docker with database ready.

**Tasks:**
- `laravel new csms-sandbox` with PHP 8.4
- Docker Compose: app, nginx, postgres, redis, emqx, queue-worker
- Auto-setup entrypoint (migrations, JWT keys, permissions)
- All database migrations from `DATABASE.md`
- Seeders: admin tenant for development
- Health check endpoint
- PHPStan + Pest configured

**Deliverable:** `docker compose up -d` → all services healthy, migrations run, health endpoint returns 200.

**Reference:** `ARCHITECTURE.md`, `DATABASE.md`, `DEPLOYMENT.md`, `STYLE.md`

---

### Phase 2 — Auth + MQTT Pipeline (estimated: 1-2 sessions)

**Goal:** Tenant registers, gets MQTT credentials, station connects and messages flow through pipeline.

**Tasks:**
- Auth: register (email + Google OAuth), login, JWT (ES256)
- Tenant station auto-provisioning (on register → create station + MQTT credentials)
- EMQX ACL integration (HTTP auth backend → verify MQTT credentials per tenant)
- EMQX webhook → MqttWebhookController → Laravel Queue → ProcessMqttMessage job
- MqttMessageDispatcher: route to handler by action
- BootNotificationHandler + HeartbeatHandler (first 2 handlers)
- Station connection status tracking

**Deliverable:** Register → get MQTT creds → connect station → send BootNotification → receive Accepted response.

**Reference:** `API.md` (auth section), `MQTT.md`, `HANDLERS.md` (Boot + Heartbeat)

---

### Phase 3 — All Protocol Handlers (estimated: 2-3 sessions)

**Goal:** All 26 OSPP message handlers implemented with schema validation and message logging.

**Tasks:**
- Remaining 24 handlers (see `HANDLERS.md` for each)
- Schema validation on every inbound message (via `ospp/protocol` SDK)
- Message logging to `message_log` table
- Validation mode (strict/lenient) per tenant
- Outbound command publishing via EMQX API

**Deliverable:** All 26 messages work end-to-end. Messages logged. Schema validation active.

**Reference:** `HANDLERS.md`, `CONFORMANCE.md` (schema validation section)

---

### Phase 4 — Conformance Engine (estimated: 1-2 sessions)

**Goal:** Automated protocol conformance scoring with behavior validation.

**Tasks:**
- Conformance result tracking per action per tenant
- Schema validation scoring (pass/fail per message)
- Behavior validation (heartbeat timing, boot sequence, state machine, response timing)
- Scoring algorithm (passed/total_tested)
- Category scoring (core, sessions, reservations, device management, security)
- Conformance reset
- PDF export (conformance report)
- JSON export (machine-readable)

**Deliverable:** After testing, tenant sees score 18/22 with per-message details and can export PDF.

**Reference:** `CONFORMANCE.md`

---

### Phase 5 — Dashboard UI (estimated: 2-3 sessions)

**Goal:** Complete browser-based dashboard with live messages, command center, conformance view.

**Tasks:**
- Layout: sidebar nav, responsive
- Setup page: MQTT credentials, code snippets, connection status
- Live Monitor: WebSocket feed via Laravel Reverb, filters, auto-scroll
- Command Center: form per command, auto-generated from schema, send + see response
- Conformance page: checklist, scoring, per-message detail, export buttons
- History page: paginated, filterable, searchable, exportable
- Settings page: validation mode, protocol version

**Deliverable:** Full dashboard functional in browser. Real-time updates work.

**Reference:** `FRONTEND.md`, `API.md`

---

### Phase 6 — Deployment (estimated: 1 session)

**Goal:** Running on `csms-sandbox.ospp-standard.org` with TLS.

**Tasks:**
- Production Docker Compose (no dev tools, optimized images)
- Nginx config with TLS (Let's Encrypt)
- EMQX TLS on port 8883
- DNS: `csms-sandbox.ospp-standard.org` → VPS IP
- Scheduled jobs: message cleanup, connection check
- Monitoring: health endpoint, queue depth, error rate

**Deliverable:** Public URL works. Firmware developer can register and connect.

**Reference:** `DEPLOYMENT.md`

---

## Key Principles

1. **Every handler reuses `ospp/protocol` SDK** — schemas, DTOs, enums. No duplication with csms-server.

2. **No database for station state** — station state (bays, sessions, reservations) lives in Redis. Database is for tenants, message logs, and conformance results only.

3. **Strict mode by default** — conformance testing requires strict validation. Lenient mode is opt-in for development.

4. **Firmware code must be identical to production** — MQTT topics, message format, protocol behavior are the same. The only difference is the MQTT host.

5. **Zero frontend build process** — Blade templates, Alpine.js via CDN, Tailwind via CDN. No npm, no webpack, no vite. Dashboard is server-rendered with sprinkles of reactivity.

6. **One station per tenant** — sandbox is for protocol testing, not load testing. Simplifies everything.

7. **Self-contained handlers** — each handler receives a message, processes it, sends response, logs result, updates conformance. No cross-handler dependencies.

---

## CLI Workflow

Each phase is a separate prompt to Claude CLI. The prompt references the specific docs:

```
Phase 1: "Read docs/ARCHITECTURE.md, docs/DATABASE.md, docs/DEPLOYMENT.md, docs/STYLE.md. Implement Phase 1 from docs/BRIEF.md."

Phase 2: "Read docs/API.md (auth section), docs/MQTT.md, docs/HANDLERS.md (Boot + Heartbeat). Implement Phase 2 from docs/BRIEF.md."

...etc
```

CLI has all docs in the repo. Each prompt is focused on one phase. No guessing.
