<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('platform_whatsapp_connections', function (Blueprint $table) {
            $table->id();
            $table->string('singleton_key', 16)->unique();
            $table->string('business_account_id', 100);
            $table->string('phone_number_id', 100)->unique();
            $table->string('display_phone_number', 40)->nullable();
            $table->text('access_token');
            $table->string('status', 24)->default('configured');
            $table->timestamps();
        });
        Schema::create('platform_whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_whatsapp_connection_id');
            $table->foreign('platform_whatsapp_connection_id', 'pwa_tpl_conn_fk')->references('id')->on('platform_whatsapp_connections')->cascadeOnDelete();
            $table->string('meta_template_id', 100)->nullable();
            $table->string('name', 200);
            $table->string('language', 20);
            $table->string('category', 50)->nullable();
            $table->string('status', 50);
            $table->string('purpose', 30)->default('general');
            $table->json('components');
            $table->boolean('is_available')->default(true);
            $table->timestamp('last_synced_at');
            $table->timestamps();
            $table->unique(['platform_whatsapp_connection_id', 'name', 'language'], 'platform_wa_template_identity');
        });
        Schema::create('platform_whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_whatsapp_connection_id');
            $table->foreign('platform_whatsapp_connection_id', 'pwa_msg_conn_fk')->references('id')->on('platform_whatsapp_connections')->restrictOnDelete();
            $table->foreignId('platform_whatsapp_template_id')->nullable();
            $table->foreign('platform_whatsapp_template_id', 'pwa_msg_tpl_fk')->references('id')->on('platform_whatsapp_templates')->nullOnDelete();
            $table->string('recipient', 20);
            $table->string('template_name', 200);
            $table->string('template_language', 20);
            $table->string('purpose', 30);
            $table->text('parameters')->nullable();
            $table->string('meta_message_id', 200)->nullable()->unique();
            $table->string('status', 16)->default('queued');
            $table->timestamp('requested_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_code', 100)->nullable();
            $table->timestamps();
            $table->index(['status', 'requested_at']);
        });
        Schema::create('platform_whatsapp_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_whatsapp_connection_id');
            $table->foreign('platform_whatsapp_connection_id', 'pwa_evt_conn_fk')->references('id')->on('platform_whatsapp_connections')->cascadeOnDelete();
            $table->string('event_key', 64)->unique();
            $table->string('provider_event_id', 200);
            $table->string('event_status', 16);
            $table->timestamp('provider_timestamp')->nullable();
            $table->string('failure_code', 100)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
        $now = now();
        $superRole = DB::table('platform_roles')->where('slug', 'super-administrator')->value('id');
        foreach (['platform_whatsapp.view' => 'View platform WhatsApp', 'platform_whatsapp.manage' => 'Manage platform WhatsApp templates and connection', 'platform_whatsapp.send' => 'Send platform WhatsApp messages'] as $name => $label) {
            $permission = DB::table('platform_permissions')->insertGetId(['name' => $name, 'label' => $label, 'created_at' => $now, 'updated_at' => $now]);
            if ($superRole) DB::table('platform_permission_role')->insertOrIgnore(['platform_role_id' => $superRole, 'platform_permission_id' => $permission]);
        }
    }

    public function down(): void
    {
        $ids = DB::table('platform_permissions')->whereIn('name', ['platform_whatsapp.view', 'platform_whatsapp.manage', 'platform_whatsapp.send'])->pluck('id');
        DB::table('platform_permission_role')->whereIn('platform_permission_id', $ids)->delete();
        DB::table('platform_permissions')->whereIn('id', $ids)->delete();
        Schema::dropIfExists('platform_whatsapp_webhook_events');
        Schema::dropIfExists('platform_whatsapp_messages');
        Schema::dropIfExists('platform_whatsapp_templates');
        Schema::dropIfExists('platform_whatsapp_connections');
    }
};
