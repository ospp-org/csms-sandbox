<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conformance_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('protocol_version', 10);
            $table->string('action', 50);
            $table->enum('status', ['passed', 'failed', 'partial', 'not_tested'])->default('not_tested');
            $table->timestamp('last_tested_at')->nullable();
            $table->jsonb('last_payload')->nullable();
            $table->jsonb('error_details')->nullable();
            $table->jsonb('behavior_checks')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'protocol_version', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conformance_results');
    }
};
