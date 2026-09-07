<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('status')->default('active')->after('is_platform_admin'));
        Schema::create('platform_roles', function (Blueprint $table) { $table->id(); $table->string('name')->unique(); $table->string('slug')->unique(); $table->text('description')->nullable(); $table->boolean('is_system')->default(false); $table->timestamps(); });
        Schema::create('platform_permissions', function (Blueprint $table) { $table->id(); $table->string('name')->unique(); $table->string('label'); $table->timestamps(); });
        Schema::create('platform_permission_role', function (Blueprint $table) { $table->foreignId('platform_role_id')->constrained()->cascadeOnDelete(); $table->foreignId('platform_permission_id')->constrained()->cascadeOnDelete(); $table->primary(['platform_role_id','platform_permission_id']); });
        Schema::create('platform_role_user', function (Blueprint $table) { $table->foreignId('platform_role_id')->constrained()->restrictOnDelete(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->primary(['platform_role_id','user_id']); });

        $now = now();
        $roleId = DB::table('platform_roles')->insertGetId(['name'=>'Super Administrator','slug'=>'super-administrator','description'=>'Full platform access.','is_system'=>true,'created_at'=>$now,'updated_at'=>$now]);
        foreach (config('platform_permissions') as $name => $label) {
            $permissionId = DB::table('platform_permissions')->insertGetId(['name'=>$name,'label'=>$label,'created_at'=>$now,'updated_at'=>$now]);
            DB::table('platform_permission_role')->insert(['platform_role_id'=>$roleId,'platform_permission_id'=>$permissionId]);
        }
        foreach (DB::table('users')->where('is_platform_admin', true)->pluck('id') as $userId) DB::table('platform_role_user')->insert(['platform_role_id'=>$roleId,'user_id'=>$userId]);
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_role_user'); Schema::dropIfExists('platform_permission_role'); Schema::dropIfExists('platform_permissions'); Schema::dropIfExists('platform_roles');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('status'));
    }
};
