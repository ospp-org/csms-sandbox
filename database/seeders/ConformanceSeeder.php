<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ConformanceResult;
use Illuminate\Database\Seeder;

final class ConformanceSeeder extends Seeder
{
    private const ACTIONS = [
        // Core (4)
        'BootNotification',
        'Heartbeat',
        'StatusNotification',
        'DataTransfer',
        // Sessions (5)
        'MeterValues',
        'StartService',
        'StartServiceResponse',
        'StopService',
        'StopServiceResponse',
        // Reservations (4)
        'ReserveBay',
        'ReserveBayResponse',
        'CancelReservation',
        'CancelReservationResponse',
        // Device Management (8)
        'ChangeConfigurationResponse',
        'GetConfigurationResponse',
        'ResetResponse',
        'UpdateFirmwareResponse',
        'UploadDiagnosticsResponse',
        'SetMaintenanceModeResponse',
        'TriggerMessageResponse',
        'UpdateServiceCatalogResponse',
        // Security (5)
        'SecurityEvent',
        'SignCertificate',
        'CertificateInstall',
        'CertificateInstallResponse',
        'TriggerCertificateRenewalResponse',
    ];

    public function run(string $tenantId = ''): void
    {
        if ($tenantId === '') {
            return;
        }

        foreach (self::ACTIONS as $action) {
            ConformanceResult::firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'protocol_version' => '0.1.0',
                    'action' => $action,
                ],
                [
                    'status' => 'not_tested',
                ],
            );
        }
    }
}
