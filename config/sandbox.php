<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Protocol Version
    |--------------------------------------------------------------------------
    */
    'default_protocol_version' => \Ospp\Protocol\ValueObjects\ProtocolVersion::default()->value,
    'supported_protocol_versions' => [\Ospp\Protocol\ValueObjects\ProtocolVersion::default()->value],

    /*
    |--------------------------------------------------------------------------
    | Validation Mode
    |--------------------------------------------------------------------------
    */
    'default_validation_mode' => 'strict',

    /*
    |--------------------------------------------------------------------------
    | Rate Limits
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        'mqtt' => (int) env('RATE_LIMIT_MQTT', 100),  // messages per minute
        'api' => (int) env('RATE_LIMIT_API', 60),      // requests per minute
    ],

    /*
    |--------------------------------------------------------------------------
    | Message Retention
    |--------------------------------------------------------------------------
    */
    'message_retention_days' => (int) env('MESSAGE_RETENTION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    */
    'max_payload_size' => 65536,    // 64 KB
    'max_websocket_connections' => 3,
    'stations_per_tenant' => 1,

    /*
    |--------------------------------------------------------------------------
    | JWT
    |--------------------------------------------------------------------------
    */
    'jwt' => [
        'algorithm' => env('JWT_ALGORITHM', 'ES256'),
        'private_key_path' => storage_path('keys/jwt-private.pem'),
        'public_key_path' => storage_path('keys/jwt-public.pem'),
        'ttl' => (int) env('JWT_TTL', 3600), // 1 hour
    ],

    /*
    |--------------------------------------------------------------------------
    | MQTT Public Host
    |--------------------------------------------------------------------------
    */
    'mqtt_public_host' => env('SANDBOX_MQTT_HOST', 'csms-sandbox.ospp-standard.org'),

    /*
    |--------------------------------------------------------------------------
    | PKI — Station Certificate Authority
    |--------------------------------------------------------------------------
    */
    'pki' => [
        'station_ca_cert' => env('PKI_STATION_CA_CERT', '/opt/pki/station-ca/station-ca.pem'),
        'station_ca_key' => env('PKI_STATION_CA_KEY', '/opt/pki/station-ca/station-ca.key'),
        'ca_chain' => env('PKI_CA_CHAIN', '/opt/pki/station-ca/chain.pem'),
    ],

];
