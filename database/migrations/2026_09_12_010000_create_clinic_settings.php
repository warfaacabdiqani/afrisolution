<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('tenant_settings', function(Blueprint $t) {
            $t->id(); $t->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $t->string('section',40); $t->json('values'); $t->timestamps(); $t->unique(['tenant_id','section']);
        });
        Schema::table('branches', function(Blueprint $t) {
            $t->string('code',30)->nullable(); $t->string('phone',40)->nullable(); $t->string('email')->nullable();
            $t->text('address')->nullable(); $t->string('city',100)->nullable(); $t->string('timezone',100)->nullable();
            $t->string('status',20)->default('active'); $t->unique(['tenant_id','code']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('tenant_settings');
        Schema::table('branches', function(Blueprint $t) { $t->dropUnique(['tenant_id','code']); $t->dropColumn(['code','phone','email','address','city','timezone','status']); });
    }
};
