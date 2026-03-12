# CSMS Sandbox — Database Schema

---

## Overview

PostgreSQL 16 with 5 tables. Station state lives in Redis (see ARCHITECTURE.md), NOT in PostgreSQL.

---

## Migrations

### 001_create_tenants_table

```php
Schema::create('tenants', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('email')->unique();
    $table->string('name');
    $table->string('password')->nullable(); // null if Google OAuth
    $table->string('google_id')->nullable()->unique();
    $table->string('protocol_version', 10)->default('0.1.0');
    $table->enum('validation_mode', ['strict', 'lenient'])->default('strict');
    $table->timestamp('email_verified_at')->nullable();
    $table->timestamps();

    $table->index('email');
    $table->index('google_id');
});
```

### 002_create_tenant_stations_table

```php
Schema::create('tenant_stations', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('tenant_id');
    $table->string('station_id', 50)->unique(); // OSPP format: stn_[a-f0-9]{8,}
    $table->string('mqtt_username', 100)->unique();
    $table->string('mqtt_password_hash'); // bcrypt
    $table->text('mqtt_password_encrypted'); // AES-256, shown once, regeneratable
    $table->string('protocol_version', 10)->default('0.1.0');
    $table->boolean('is_connected')->default(false);
    $table->timestamp('last_connected_at')->nullable();
    $table->timestamp('last_boot_at')->nullable();
    $table->integer('bay_count')->nullable(); // from BootNotification
    $table->string('firmware_version', 50)->nullable();
    $table->string('station_model', 100)->nullable();
    $table->string('station_vendor', 100)->nullable();
    $table->timestamps();

    $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
    $table->index('tenant_id');
    $table->index('mqtt_username');
    $table->index('station_id');
});
```

### 003_create_message_log_table

```php
Schema::create('message_log', function (Blueprint $table) {
    $table->bigIncrements('id'); // BIGINT auto-increment for high volume
    $table->uuid('tenant_id');
    $table->string('station_id', 50);
    $table->enum('direction', ['inbound', 'outbound']);
    $table->string('action', 50); // BootNotification, Heartbeat, etc.
    $table->string('message_id', 100); // OSPP messageId
    $table->string('message_type', 20); // Request, Response, Event
    $table->jsonb('payload'); // Full OSPP message
    $table->boolean('schema_valid')->nullable();
    $table->jsonb('validation_errors')->nullable();
    $table->integer('processing_time_ms')->nullable();
    $table->timestamp('created_at');

    $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

    // Primary query: "show me messages for my tenant, newest first"
    $table->index(['tenant_id', 'created_at']);

    // Filter by action
    $table->index(['tenant_id', 'action']);

    // Correlation: find response for a request by messageId
    $table->index(['station_id', 'message_id']);

    // Cleanup job: delete messages older than 30 days
    $table->index('created_at');
});
```

### 004_create_conformance_results_table

```php
Schema::create('conformance_results', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('tenant_id');
    $table->string('protocol_version', 10);
    $table->string('action', 50); // OSPP message action
    $table->enum('status', ['passed', 'failed', 'partial', 'not_tested'])->default('not_tested');
    $table->timestamp('last_tested_at')->nullable();
    $table->jsonb('last_payload')->nullable();
    $table->jsonb('error_details')->nullable(); // [{path, message, expected, actual}]
    $table->jsonb('behavior_checks')->nullable(); // [{rule, passed, detail}]
    $table->timestamps();

    $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

    // One result per tenant per version per action
    $table->unique(['tenant_id', 'protocol_version', 'action']);
});
```

### 005_create_command_history_table

```php
Schema::create('command_history', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('tenant_id');
    $table->string('station_id', 50);
    $table->string('action', 50); // Command sent
    $table->string('message_id', 100); // For correlation
    $table->jsonb('payload'); // Command payload
    $table->jsonb('response_payload')->nullable();
    $table->timestamp('response_received_at')->nullable();
    $table->enum('status', ['sent', 'responded', 'timeout'])->default('sent');
    $table->timestamps();

    $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
    $table->index(['tenant_id', 'created_at']);
    $table->index(['station_id', 'message_id']);
});
```

---

## Indexes Rationale

| Index | Query Pattern |
|-------|--------------|
| `message_log(tenant_id, created_at)` | Dashboard: newest messages for tenant |
| `message_log(tenant_id, action)` | Filter by action type |
| `message_log(station_id, message_id)` | Correlate request ↔ response |
| `message_log(created_at)` | Cleanup job: DELETE WHERE created_at < 30 days |
| `conformance_results(tenant_id, protocol_version, action)` | Unique constraint + lookup |
| `command_history(tenant_id, created_at)` | Recent commands |
| `command_history(station_id, message_id)` | Match command ↔ response |

---

## Seeders

### DevelopmentSeeder

Creates one dev tenant for local testing:

```php
// Tenant
$tenant = Tenant::create([
    'email' => 'dev@ospp-standard.org',
    'name' => 'Development Tenant',
    'password' => Hash::make('password'),
    'protocol_version' => '0.1.0',
    'validation_mode' => 'strict',
    'email_verified_at' => now(),
]);

// Station with known credentials
TenantStation::create([
    'tenant_id' => $tenant->id,
    'station_id' => 'stn_00000001',
    'mqtt_username' => 'sandbox_dev_001',
    'mqtt_password_hash' => Hash::make('dev-mqtt-password'),
    'mqtt_password_encrypted' => encrypt('dev-mqtt-password'),
    'protocol_version' => '0.1.0',
]);
```

### ConformanceSeeder

Pre-populates conformance_results with all 26 actions as `not_tested`:

```php
$actions = [
    'BootNotification', 'Heartbeat', 'StatusNotification',
    'MeterValues', 'DataTransfer', 'SecurityEvent',
    'SignCertificate',
    'StartServiceResponse', 'StopServiceResponse',
    'ReserveBayResponse', 'CancelReservationResponse',
    'ChangeConfigurationResponse', 'GetConfigurationResponse',
    'ResetResponse', 'UpdateFirmwareResponse',
    'UploadDiagnosticsResponse', 'SetMaintenanceModeResponse',
    'TriggerMessageResponse', 'UpdateServiceCatalogResponse',
    'CertificateInstallResponse', 'TriggerCertificateRenewalResponse',
];

foreach ($actions as $action) {
    ConformanceResult::create([
        'tenant_id' => $tenant->id,
        'protocol_version' => '0.1.0',
        'action' => $action,
        'status' => 'not_tested',
    ]);
}
```

---

## Data Retention

| Table | Retention | Mechanism |
|-------|-----------|-----------|
| tenants | Indefinite | — |
| tenant_stations | Indefinite (1 per tenant) | — |
| message_log | 30 days | `messages:cleanup` scheduled command |
| conformance_results | Indefinite (per tenant) | Reset via API |
| command_history | 30 days | `messages:cleanup` scheduled command |

Cleanup command:

```php
// Runs daily at 3AM
MessageLog::where('created_at', '<', now()->subDays(30))->delete();
CommandHistory::where('created_at', '<', now()->subDays(30))->delete();
```

---

## Notes

- **UUID primary keys** on all tables except message_log (BIGINT for volume + performance)
- **JSONB** for payload storage (supports indexing, querying in PostgreSQL)
- **Cascade delete** on tenant_id FK — deleting tenant removes all data
- **No PostGIS** — sandbox doesn't need geospatial (unlike csms-server)
- **No soft deletes** — hard delete for simplicity and GDPR compliance
