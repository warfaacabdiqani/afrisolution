<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('platform_audit_logs',function(Blueprint $t){
  $t->string('module')->nullable()->after('action'); $t->text('description')->nullable()->after('module');
  $t->string('actor_name')->nullable()->after('actor_id'); $t->string('actor_email')->nullable()->after('actor_name');
  $t->unsignedBigInteger('tenant_id')->nullable()->after('actor_email'); $t->string('tenant_name')->nullable()->after('tenant_id');
  $t->string('subject_name')->nullable()->after('subject_id'); $t->json('old_values')->nullable()->after('subject_name'); $t->json('new_values')->nullable()->after('old_values');
  $t->string('ip_address',45)->nullable(); $t->text('user_agent')->nullable(); $t->string('request_method',10)->nullable(); $t->text('request_url')->nullable(); $t->string('result')->default('success');
  $t->index('action'); $t->index('actor_id'); $t->index('tenant_id'); $t->index(['subject_type','subject_id']);
 }); }
 public function down(): void { Schema::table('platform_audit_logs',function(Blueprint $t){$t->dropIndex(['action']);$t->dropIndex(['actor_id']);$t->dropIndex(['tenant_id']);$t->dropIndex(['subject_type','subject_id']);$t->dropColumn(['module','description','actor_name','actor_email','tenant_id','tenant_name','subject_name','old_values','new_values','ip_address','user_agent','request_method','request_url','result']);}); }
};
