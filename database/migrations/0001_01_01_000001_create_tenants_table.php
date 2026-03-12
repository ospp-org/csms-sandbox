<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->string('name');
            $table->string('password')->nullable();
            $table->string('google_id')->nullable()->unique();
            $table->string('protocol_version', 10)->default('0.1.0');
            $table->enum('validation_mode', ['strict', 'lenient'])->default('strict');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();

            $table->index('email');
            $table->index('google_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
