<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\CertificateServiceInterface;
use App\Models\TenantStation;
use Illuminate\Support\Carbon;
use RuntimeException;

final class CertificateService implements CertificateServiceInterface
{
    private const VALIDITY_DAYS = 365;

    /**
     * Check if PKI infrastructure is available for cert generation.
     */
    public function isConfigured(): bool
    {
        $caCert = (string) config('sandbox.pki.station_ca_cert');
        $caKey = (string) config('sandbox.pki.station_ca_key');

        return file_exists($caCert) && file_exists($caKey);
    }

    /**
     * Generate an ECDSA P-256 client certificate for a station.
     *
     * SANDBOX DEVIATION: private key is generated server-side.
     * Per OSPP spec §4.3, station private keys MUST be generated on-device
     * and NEVER leave the station. The sandbox generates keys server-side
     * because there is no real hardware. This is acceptable for testing only.
     *
     * @return array{cert: string, key: string, expires_at: Carbon}
     */
    public function generateStationCert(TenantStation $station): array
    {
        $caCertPath = (string) config('sandbox.pki.station_ca_cert');
        $caKeyPath = (string) config('sandbox.pki.station_ca_key');

        if (! file_exists($caCertPath) || ! file_exists($caKeyPath)) {
            throw new RuntimeException('Station CA certificate or key not found. Configure PKI_STATION_CA_CERT and PKI_STATION_CA_KEY.');
        }

        $caCert = file_get_contents($caCertPath);
        $caKey = file_get_contents($caKeyPath);

        if ($caCert === false || $caKey === false) {
            throw new RuntimeException('Failed to read Station CA files.');
        }

        // Generate ECDSA P-256 key pair (SANDBOX DEVIATION: server-side generation)
        $privateKey = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        if ($privateKey === false) {
            throw new RuntimeException('Failed to generate ECDSA P-256 key pair: ' . openssl_error_string());
        }

        // Create CSR with CN = stationId
        $csr = openssl_csr_new(
            [
                'commonName' => $station->station_id,
            ],
            $privateKey,
            [
                'digest_alg' => 'sha256',
                'private_key_type' => OPENSSL_KEYTYPE_EC,
                'curve_name' => 'prime256v1',
            ],
        );

        if ($csr === false) {
            throw new RuntimeException('Failed to create CSR: ' . openssl_error_string());
        }

        // Sign with Station CA
        /** @var \OpenSSLCertificate|false $signedCert */
        $signedCert = openssl_csr_sign(
            $csr,
            $caCert,
            $caKey,
            self::VALIDITY_DAYS,
            [
                'digest_alg' => 'sha256',
                'x509_extensions' => 'v3_req',
            ],
        );

        if ($signedCert === false) {
            throw new RuntimeException('Failed to sign certificate: ' . openssl_error_string());
        }

        // Export PEM strings
        $certPem = '';
        $keyPem = '';
        openssl_x509_export($signedCert, $certPem);
        openssl_pkey_export($privateKey, $keyPem);

        $expiresAt = Carbon::now()->addDays(self::VALIDITY_DAYS);

        // Store on station record
        $station->update([
            'station_cert' => $certPem,
            'station_key' => $keyPem,
            'cert_issued_at' => Carbon::now(),
            'cert_expires_at' => $expiresAt,
        ]);

        return [
            'cert' => $certPem,
            'key' => $keyPem,
            'expires_at' => $expiresAt,
        ];
    }

    public function revokeCert(TenantStation $station): void
    {
        $station->update([
            'station_cert' => null,
            'station_key' => null,
            'cert_issued_at' => null,
            'cert_expires_at' => null,
        ]);

        // CRL publication is a future milestone
    }

    public function getCaChain(): string
    {
        $chainPath = (string) config('sandbox.pki.ca_chain');

        if (! file_exists($chainPath)) {
            throw new RuntimeException('CA chain file not found. Configure PKI_CA_CHAIN.');
        }

        $chain = file_get_contents($chainPath);

        if ($chain === false) {
            throw new RuntimeException('Failed to read CA chain file.');
        }

        return $chain;
    }
}
