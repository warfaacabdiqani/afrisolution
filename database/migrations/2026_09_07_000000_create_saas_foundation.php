<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->boolean('is_platform_admin')->default(false));
        Schema::create('plans', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->unsignedInteger('branch_limit');
            $t->unsignedInteger('member_limit');
            $t->unsignedInteger('trial_days')->default(14);
            $t->timestamps();
        });
        Schema::create('tenants', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('status')->default('active');
            $t->string('timezone')->default('Africa/Nairobi');
            $t->timestamps();
        });
        Schema::create('tenant_memberships', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('role')->default('owner');
            $t->string('status')->default('active');
            $t->timestamps();
            $t->unique(['tenant_id', 'user_id']);
            $t->unique(['tenant_id', 'id']);
        });
        Schema::create('branches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->timestamps();
            $t->unique(['tenant_id', 'id']);
            $t->unique(['tenant_id', 'name']);
        });
        Schema::create('subscriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('plan_id')->constrained()->restrictOnDelete();
            $t->string('status')->default('trial');
            $t->timestamp('trial_ends_at')->nullable();
            $t->unsignedInteger('branch_limit');
            $t->unsignedInteger('member_limit');
            $t->timestamps();
        });
        Schema::create('platform_audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('action');
            $t->string('subject_type');
            $t->unsignedBigInteger('subject_id');
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index('created_at');
        });
    }

    public function down(): void
    {
        foreach (['platform_audit_logs', 'subscriptions', 'branches', 'tenant_memberships', 'tenants', 'plans'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_platform_admin'));
    }
};
