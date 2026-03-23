<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Behavior Validation Rules
    |--------------------------------------------------------------------------
    */
    'heartbeat_drift_tolerance' => 0.10, // 10%
    'command_response_timeout' => 30,     // fallback (seconds)
    'response_timeouts' => [
        'BootNotification' => 30,
        'Heartbeat' => 30,
        'AuthorizeOfflinePass' => 15,
        'StartService' => 10,
        'StopService' => 10,
        'ReserveBay' => 5,
        'CancelReservation' => 5,
        'TransactionEvent' => 60,
        'ChangeConfiguration' => 60,
        'GetConfiguration' => 30,
        'Reset' => 30,
        'UpdateFirmware' => 300,
        'GetDiagnostics' => 300,
        'SetMaintenanceMode' => 30,
        'TriggerMessage' => 10,
        'UpdateServiceCatalog' => 30,
        'SignCertificate' => 30,
        'CertificateInstall' => 30,
        'TriggerCertificateRenewal' => 10,
        'DataTransfer' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | OSPP Actions
    |--------------------------------------------------------------------------
    */
    'actions' => [
        // Core (4)
        'BootNotification',
        'Heartbeat',
        'StatusNotification',
        'DataTransfer',
        // Sessions (3)
        'MeterValues',
        'StartService',
        'StopService',
        'SessionEnded',
        // Reservations (2)
        'ReserveBay',
        'CancelReservation',
        // Device Management (8)
        'ChangeConfiguration',
        'GetConfiguration',
        'Reset',
        'UpdateFirmware',
        'GetDiagnostics',
        'SetMaintenanceMode',
        'TriggerMessage',
        'UpdateServiceCatalog',
        // Offline (2)
        'AuthorizeOfflinePass',
        'TransactionEvent',
        // Notifications (3)
        'ConnectionLost',
        'DiagnosticsNotification',
        'FirmwareStatusNotification',
        // Security (4)
        'SecurityEvent',
        'SignCertificate',
        'CertificateInstall',
        'TriggerCertificateRenewal',
    ],

    /*
    |--------------------------------------------------------------------------
    | Scoring Categories
    |--------------------------------------------------------------------------
    */
    'categories' => [
        'core' => ['BootNotification', 'Heartbeat', 'StatusNotification', 'DataTransfer'],
        'sessions' => ['MeterValues', 'StartService', 'StopService', 'SessionEnded'],
        'reservations' => ['ReserveBay', 'CancelReservation'],
        'device_management' => [
            'ChangeConfiguration', 'GetConfiguration',
            'Reset', 'UpdateFirmware',
            'GetDiagnostics', 'SetMaintenanceMode',
            'TriggerMessage', 'UpdateServiceCatalog',
        ],
        'offline' => ['AuthorizeOfflinePass', 'TransactionEvent'],
        'notifications' => ['ConnectionLost', 'DiagnosticsNotification', 'FirmwareStatusNotification'],
        'security' => [
            'SecurityEvent', 'SignCertificate',
            'CertificateInstall', 'TriggerCertificateRenewal',
        ],
    ],

];
