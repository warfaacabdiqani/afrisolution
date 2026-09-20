<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('whatsapp_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('business_account_id', 100);
            $table->string('phone_number_id', 100)->unique();
            $table->string('display_phone_number', 40)->nullable();
            $table->string('display_name', 150)->nullable();
            $table->text('access_token');
            $table->string('status', 24)->default('configured');
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_error_code', 100)->nullable();
            $table->timestamps();
        });
        Schema::create('whatsapp_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_connection_id')->constrained('whatsapp_connections')->cascadeOnDelete();
            $table->char('event_key', 64)->unique();
            $table->string('event_type', 32);
            $table->string('provider_event_id', 200)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_webhook_events');
        Schema::dropIfExists('whatsapp_connections');
    }
};
