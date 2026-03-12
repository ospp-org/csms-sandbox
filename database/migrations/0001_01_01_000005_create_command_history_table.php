<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('command_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('station_id', 50);
            $table->string('action', 50);
            $table->string('message_id', 100);
            $table->jsonb('payload');
            $table->jsonb('response_payload')->nullable();
            $table->timestamp('response_received_at')->nullable();
            $table->enum('status', ['sent', 'responded', 'timeout'])->default('sent');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'created_at']);
            $table->index(['station_id', 'message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('command_history');
    }
};
