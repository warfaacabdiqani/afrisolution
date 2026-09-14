<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('tenants', function(Blueprint $t) { $t->unsignedBigInteger('client_sequence')->default(0); $t->unsignedBigInteger('salon_staff_sequence')->default(0); });
        Schema::table('plans', function(Blueprint $t) { $t->unsignedInteger('client_limit')->nullable(); $t->unsignedInteger('service_limit')->nullable(); });
        Schema::create('salon_staff_profiles', function(Blueprint $t) {
            $t->id(); $t->foreignId('tenant_id')->constrained()->cascadeOnDelete(); $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('staff_number',40); $t->string('display_name',150); $t->string('title',100)->nullable(); $t->text('bio')->nullable(); $t->string('status',20)->default('active');
            $t->string('commission_type',20)->nullable(); $t->decimal('commission_value',12,2)->nullable(); $t->timestamps();
            $t->unique(['tenant_id','user_id']); $t->unique(['tenant_id','staff_number']);
        });
        Schema::create('salon_clients', function(Blueprint $t) {
            $t->id(); $t->foreignId('tenant_id')->constrained()->cascadeOnDelete(); $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->string('client_number',40); $t->string('first_name',100); $t->string('middle_name',100)->nullable(); $t->string('last_name',100); $t->string('gender',20)->nullable(); $t->date('date_of_birth')->nullable();
            $t->string('phone',40)->nullable(); $t->string('email')->nullable(); $t->text('address')->nullable(); $t->text('notes')->nullable(); $t->string('status',20)->default('active');
            $t->foreignId('preferred_stylist_id')->nullable()->constrained('salon_staff_profiles')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete(); $t->foreignId('updated_by')->constrained('users')->restrictOnDelete(); $t->timestamps();
            $t->unique(['tenant_id','client_number']); $t->index(['tenant_id','branch_id','status']);
        });
        Schema::create('service_categories', function(Blueprint $t) {
            $t->id(); $t->foreignId('tenant_id')->constrained()->cascadeOnDelete(); $t->string('name',100); $t->text('description')->nullable(); $t->string('status',20)->default('active'); $t->unsignedInteger('sort_order')->default(0); $t->timestamps(); $t->unique(['tenant_id','name']);
        });
        Schema::create('salon_services', function(Blueprint $t) {
            $t->id(); $t->foreignId('tenant_id')->constrained()->cascadeOnDelete(); $t->foreignId('service_category_id')->constrained('service_categories')->restrictOnDelete();
            $t->string('name',150); $t->string('code',40)->nullable(); $t->text('description')->nullable(); $t->unsignedInteger('duration_minutes'); $t->decimal('price',12,2); $t->string('status',20)->default('active');
            $t->boolean('requires_deposit')->default(false); $t->decimal('deposit_amount',12,2)->nullable(); $t->timestamps(); $t->unique(['tenant_id','code']);
        });
        foreach (['salon_staff_branch'=>'salon_staff_profile_id','salon_service_branch'=>'salon_service_id'] as $table=>$key) Schema::create($table,function(Blueprint $t) use($key) {
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete(); $t->foreignId($key)->constrained($key==='salon_staff_profile_id'?'salon_staff_profiles':'salon_services')->cascadeOnDelete(); $t->foreignId('branch_id')->constrained()->cascadeOnDelete(); $t->primary([$key,'branch_id']);
        });
        Schema::create('salon_service_staff',function(Blueprint $t) {
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete(); $t->foreignId('service_id')->constrained('salon_services')->cascadeOnDelete(); $t->foreignId('salon_staff_profile_id')->constrained()->cascadeOnDelete(); $t->primary(['service_id','salon_staff_profile_id']);
        });
    }
    public function down(): void {
        foreach (['salon_service_staff','salon_service_branch','salon_staff_branch','salon_services','service_categories','salon_clients','salon_staff_profiles'] as $table) Schema::dropIfExists($table);
        Schema::table('plans',fn(Blueprint $t)=>$t->dropColumn(['client_limit','service_limit']));
        Schema::table('tenants',fn(Blueprint $t)=>$t->dropColumn(['client_sequence','salon_staff_sequence']));
    }
};
