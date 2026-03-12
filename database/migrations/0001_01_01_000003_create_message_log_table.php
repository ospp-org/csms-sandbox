<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('tenant_id');
            $table->string('station_id', 50);
            $table->enum('direction', ['inbound', 'outbound']);
            $table->string('action', 50);
            $table->string('message_id', 100);
            $table->string('message_type', 20);
            $table->jsonb('payload');
            $table->boolean('schema_valid')->nullable();
            $table->jsonb('validation_errors')->nullable();
            $table->integer('processing_time_ms')->nullable();
            $table->timestamp('created_at');

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'action']);
            $table->index(['station_id', 'message_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_log');
    }
};
