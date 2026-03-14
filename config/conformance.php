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
        'GetDiagnosticsResponse',
        'SetMaintenanceModeResponse',
        'TriggerMessageResponse',
        'UpdateServiceCatalogResponse',
        // Offline (2)
        'AuthorizeOfflinePass',
        'TransactionEvent',
        // Notifications (3)
        'ConnectionLost',
        'DiagnosticsNotification',
        'FirmwareStatusNotification',
        // Security (5)
        'SecurityEvent',
        'SignCertificate',
        'CertificateInstall',
        'CertificateInstallResponse',
        'TriggerCertificateRenewalResponse',
    ],

    /*
    |--------------------------------------------------------------------------
    | Scoring Categories
    |--------------------------------------------------------------------------
    */
    'categories' => [
        'core' => ['BootNotification', 'Heartbeat', 'StatusNotification', 'DataTransfer'],
        'sessions' => ['MeterValues', 'StartService', 'StartServiceResponse', 'StopService', 'StopServiceResponse'],
        'reservations' => ['ReserveBay', 'ReserveBayResponse', 'CancelReservation', 'CancelReservationResponse'],
        'device_management' => [
            'ChangeConfigurationResponse', 'GetConfigurationResponse',
            'ResetResponse', 'UpdateFirmwareResponse',
            'GetDiagnosticsResponse', 'SetMaintenanceModeResponse',
            'TriggerMessageResponse', 'UpdateServiceCatalogResponse',
        ],
        'offline' => ['AuthorizeOfflinePass', 'TransactionEvent'],
        'notifications' => ['ConnectionLost', 'DiagnosticsNotification', 'FirmwareStatusNotification'],
        'security' => [
            'SecurityEvent', 'SignCertificate',
            'CertificateInstall', 'CertificateInstallResponse',
            'TriggerCertificateRenewalResponse',
        ],
    ],

];
