<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class ConformanceSeeder extends Seeder
{
    /**
     * Conformance results are now created dynamically by getReport()
     * when missing from DB. This seeder is kept for backward compatibility
     * but no longer pre-creates records.
     */
    public function run(string $tenantId = ''): void
    {
        // No-op: getReport() fills missing actions from config('conformance.actions')
    }
}
