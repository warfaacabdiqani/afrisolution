<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_connection_id')->constrained('whatsapp_connections')->cascadeOnDelete();
            $table->string('meta_template_id', 100)->nullable();
            $table->string('name', 200);
            $table->string('language', 20);
            $table->string('category', 50)->nullable();
            $table->string('status', 50);
            $table->json('components');
            $table->boolean('is_available')->default(true);
            $table->timestamp('last_synced_at');
            $table->timestamps();
            $table->unique(['whatsapp_connection_id', 'name', 'language'], 'wa_templates_connection_name_language_unique');
            $table->index(['tenant_id', 'status', 'is_available']);
        });
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('whatsapp_connection_id')->constrained('whatsapp_connections')->restrictOnDelete();
            $table->foreignId('whatsapp_template_id')->nullable()->constrained('whatsapp_templates')->nullOnDelete();
            $table->string('direction', 16)->default('outbound');
            $table->string('recipient', 20);
            $table->string('template_name', 200);
            $table->string('template_language', 20);
            $table->text('parameters')->nullable();
            $table->string('meta_message_id', 200)->nullable();
            $table->string('status', 16)->default('queued');
            $table->timestamp('requested_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_code', 100)->nullable();
            $table->timestamps();
            $table->unique(['whatsapp_connection_id', 'meta_message_id'], 'wa_messages_connection_meta_unique');
            $table->index(['tenant_id', 'requested_at']);
            $table->index(['tenant_id', 'status', 'requested_at']);
        });
        Schema::table('whatsapp_webhook_events', function (Blueprint $table) {
            $table->string('event_status', 16)->nullable();
            $table->timestamp('provider_timestamp')->nullable();
            $table->string('failure_code', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_webhook_events', fn (Blueprint $table) => $table->dropColumn(['event_status', 'provider_timestamp', 'failure_code']));
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_templates');
    }
};
