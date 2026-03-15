<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Behavior Validation Rules
    |--------------------------------------------------------------------------
    */
    'heartbeat_drift_tolerance' => 0.10, // 10%
    'command_response_timeout' => 30,     // seconds

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
        'sessions' => ['MeterValues', 'StartService', 'StopService'],
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
